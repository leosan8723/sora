<?php

namespace Tests\Feature\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\ConsultantProfile;
use App\Models\ConsultationType;
use App\Models\User;
use App\Services\Booking\SlotGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;
use Throwable;

/**
 * A genuine, cross-process parallel-booking test against the real MariaDB
 * engine (the same engine used in development) rather than the in-memory
 * SQLite database the rest of the suite uses.
 *
 * Two separate PHP processes are released at the same instant and both POST the
 * same availability slot. The test asserts that exactly one booking wins (201)
 * and the loser receives the expected 409 `booking_conflict` response, and that
 * only one active appointment row exists afterwards.
 *
 * Why no RefreshDatabase: the two worker processes must observe committed
 * fixtures and each other's committed writes. RefreshDatabase wraps the test in
 * a transaction on a single connection, which would hide that data from other
 * processes, so the test manages its own schema and cleanup instead.
 *
 * The test is opt-in and self-guarding. It skips unless:
 *   - RUN_MARIADB_CONCURRENCY=1 is set in the environment, and
 *   - the dedicated `consultation_platform_test` database is reachable.
 * It can therefore never touch the development database
 * (`consultation_platform`) or fail a plain `php artisan test` run. See
 * ARCHITECTURE.md §14.1.
 */
class RealDatabaseBookingConcurrencyTest extends TestCase
{
    private const TZ = 'Asia/Tehran';

    private const TEST_DATABASE = 'consultation_platform_test';

    private const DEVELOPMENT_DATABASE = 'consultation_platform';

    private string $database = self::TEST_DATABASE;

    private ?ConsultantProfile $profile = null;

    private ?ConsultationType $type = null;

    private ?AvailabilityRule $rule = null;

    /**
     * @var list<int>
     */
    private array $userIds = [];

    protected function setUp(): void
    {
        if (getenv('RUN_MARIADB_CONCURRENCY') !== '1') {
            $this->markTestSkipped(
                'Set RUN_MARIADB_CONCURRENCY=1 to run the real MariaDB concurrency test.'
            );
        }

        parent::setUp();

        $this->database = (string) (getenv('TEST_DB_DATABASE') ?: self::TEST_DATABASE);

        // Point the application at the dedicated test database. Credentials
        // come from .env; only the database name is overridden here. No secret
        // is ever referenced in this file.
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->database,
        ]);
        DB::purge('mysql');

        try {
            $connection = DB::connection('mysql');
            $connection->getPdo();
        } catch (Throwable $exception) {
            $this->markTestSkipped(sprintf(
                'Dedicated MariaDB test database "%s" is not available: %s',
                $this->database,
                $exception->getMessage(),
            ));
        }

        $connectedDatabase = DB::connection()->getDatabaseName();
        $driver = DB::connection()->getDriverName();

        // Hard safety rails: refuse to run against anything but the dedicated
        // test database on the MariaDB/MySQL engine.
        $this->assertSame($this->database, $connectedDatabase);
        $this->assertNotSame(self::DEVELOPMENT_DATABASE, $connectedDatabase);
        $this->assertContains($driver, ['mysql', 'mariadb']);
    }

    protected function tearDown(): void
    {
        $this->removeTestData();

        parent::tearDown();
    }

    public function test_two_concurrent_requests_cannot_book_the_same_slot(): void
    {
        [$type, $slot, $clientA, $clientB] = $this->bookableFixture();

        $worker = base_path('tests/Support/booking_concurrency_worker.php');
        $childEnv = $this->childEnvironment();

        // Give both child processes time to boot Laravel, then release them on
        // the same wall-clock instant so their transactions overlap.
        $barrier = microtime(true) + 2.0;

        $spawn = fn (User $client): InvokedProcess => Process::timeout(30)
            ->env($childEnv)
            ->start([
                PHP_BINARY,
                $worker,
                (string) $type->id,
                $slot->toIso8601ZuluString(),
                $client->createToken('concurrency-'.$client->id)->plainTextToken,
                (string) $barrier,
                $this->database,
            ]);

        $processA = $spawn($clientA);
        $processB = $spawn($clientB);

        $resultA = $processA->wait();
        $resultB = $processB->wait();

        $this->assertSame(0, $resultA->exitCode(), 'Worker A failed: '.$resultA->errorOutput().$resultA->output());
        $this->assertSame(0, $resultB->exitCode(), 'Worker B failed: '.$resultB->errorOutput().$resultB->output());

        $outA = json_decode(trim($resultA->output()), true);
        $outB = json_decode(trim($resultB->output()), true);

        $this->assertIsArray($outA);
        $this->assertIsArray($outB);

        $statuses = [$outA['status'], $outB['status']];
        sort($statuses);

        $this->assertSame(
            [201, 409],
            $statuses,
            'Exactly one request must win and one must conflict (got '.json_encode($statuses).').'
        );

        $loser = $outA['status'] === 409 ? $outA : $outB;
        $winner = $outA['status'] === 201 ? $outA : $outB;

        $this->assertSame('booking_conflict', $loser['body']['errors'][0]['code'] ?? null);
        $this->assertSame(AppointmentStatus::Pending->value, $winner['body']['data']['status'] ?? null);

        // Exactly one active appointment holds the slot in the database.
        $active = Appointment::query()
            ->where('active_slot_key', $this->slotKey($slot))
            ->count();

        $this->assertSame(1, $active, 'Exactly one active appointment must hold the slot.');
    }

    public function test_the_unique_index_rejects_a_second_active_appointment_on_one_slot(): void
    {
        [$type, $slot, $clientA, $clientB] = $this->bookableFixture();

        $this->makeAppointment($clientA, $type, $slot, AppointmentStatus::Pending);

        $this->expectException(QueryException::class);

        $this->makeAppointment($clientB, $type, $slot, AppointmentStatus::Confirmed);
    }

    public function test_a_cancelled_appointment_does_not_hold_the_slot(): void
    {
        [$type, $slot, $clientA, $clientB] = $this->bookableFixture();

        $this->makeAppointment($clientA, $type, $slot, AppointmentStatus::Cancelled);

        $second = $this->makeAppointment($clientB, $type, $slot, AppointmentStatus::Pending);

        $this->assertSame(AppointmentStatus::Pending, $second->status);
        $this->assertSame(
            1,
            Appointment::query()->where('active_slot_key', $this->slotKey($slot))->count(),
        );
    }

    /**
     * Build a committed consultant/consultation-type/availability fixture, two
     * client accounts, and derive the first bookable slot.
     *
     * @return array{0: ConsultationType, 1: CarbonImmutable, 2: User, 3: User}
     */
    private function bookableFixture(): array
    {
        $this->assertSame(0, $this->artisan('migrate:fresh', ['--force' => true])->run());

        $consultant = User::factory()->role(UserRole::Consultant)->create(['is_active' => true]);
        $this->profile = ConsultantProfile::factory()->create([
            'user_id' => $consultant->id,
            'is_active' => true,
            'is_verified' => true,
        ]);

        $date = CarbonImmutable::now(self::TZ)->addDays(2)->startOfDay();

        $this->rule = AvailabilityRule::factory()->create([
            'consultant_profile_id' => $this->profile->id,
            'weekday' => $date->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $this->type = ConsultationType::factory()->create([
            'consultant_profile_id' => $this->profile->id,
            'duration_minutes' => 30,
            'price_toman' => 500000,
            'is_active' => true,
        ]);

        $clientA = User::factory()->role(UserRole::Client)->create(['is_active' => true]);
        $clientB = User::factory()->role(UserRole::Client)->create(['is_active' => true]);

        $this->userIds = [$consultant->id, $clientA->id, $clientB->id];

        $slot = app(SlotGenerator::class)
            ->candidateStarts($this->profile, $this->type, $date)
            ->first();

        $this->assertNotNull($slot, 'Fixture did not produce a bookable slot.');

        return [$this->type, $slot, $clientA, $clientB];
    }

    private function makeAppointment(
        User $client,
        ConsultationType $type,
        CarbonImmutable $slot,
        AppointmentStatus $status,
    ): Appointment {
        return Appointment::factory()->create([
            'client_id' => $client->id,
            'consultant_profile_id' => $this->profile->id,
            'consultation_type_id' => $type->id,
            'starts_at' => $slot,
            'ends_at' => $slot->addMinutes((int) $type->duration_minutes),
            'status' => $status,
            'price_toman' => (int) $type->price_toman,
        ]);
    }

    private function slotKey(CarbonImmutable $slot): string
    {
        return sprintf('%d|%s', $this->profile->id, $slot->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * @return array<string, string>
     */
    private function childEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => $this->database,
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];
    }

    private function removeTestData(): void
    {
        if ($this->profile === null) {
            return;
        }

        try {
            if (DB::connection()->getDriverName() === 'sqlite') {
                return;
            }

            if (DB::connection()->getDatabaseName() !== $this->database) {
                return;
            }

            Appointment::query()->where('consultant_profile_id', $this->profile->id)->delete();
            AvailabilityRule::query()->where('consultant_profile_id', $this->profile->id)->delete();
            ConsultationType::query()->where('consultant_profile_id', $this->profile->id)->delete();

            if ($this->userIds !== []) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $this->userIds)
                    ->delete();

                User::query()->whereIn('id', $this->userIds)->delete();
            }

            ConsultantProfile::query()->whereKey($this->profile->id)->delete();
        } catch (Throwable $exception) {
            // Cleanup is best-effort; the dedicated test database is the
            // isolation boundary, not the deleted rows.
        }
    }
}
