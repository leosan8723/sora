<?php

/**
 * Booking concurrency worker.
 *
 * Boots the real application in a separate OS process and dispatches a single
 * authenticated booking request through the HTTP kernel. It is spawned by
 * Tests\Feature\Booking\RealDatabaseBookingConcurrencyTest so that two genuine,
 * cross-process requests can race for the same availability slot against the
 * MariaDB engine used in production/development.
 *
 * This is a plain script, not a PHPUnit test, so PHPUnit never collects it.
 *
 * Arguments:
 *   1. consultation_type_id  (int)
 *   2. starts_at             (ISO-8601 UTC string)
 *   3. sanctum token         (plain-text token)
 *   4. barrier epoch         (float; wait until microtime(true) >= this)
 *   5. expected database     (safety guard; must match the connected database)
 *
 * The caller only ever passes the dedicated test database name, and this
 * worker refuses to run against anything else, so the development database can
 * never be touched by accident.
 *
 * Output (stdout, one JSON object on a single line):
 *   {"status": 201, "body": {...}}
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';

$typeId = (int) ($argv[1] ?? 0);
$startsAt = (string) ($argv[2] ?? '');
$token = (string) ($argv[3] ?? '');
$barrier = (float) ($argv[4] ?? 0.0);
$expectedDatabase = (string) ($argv[5] ?? '');

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$database = DB::connection()->getDatabaseName();
$driver = DB::connection()->getDriverName();

if ($expectedDatabase === '' || $database !== $expectedDatabase) {
    fwrite(STDERR, sprintf(
        'refusing to run: connected to database "%s", expected "%s"%s',
        $database,
        $expectedDatabase,
        PHP_EOL,
    ));

    exit(3);
}

if (! in_array($driver, ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, sprintf(
        'refusing to run: driver "%s" is not MariaDB/MySQL%s',
        $driver,
        PHP_EOL,
    ));

    exit(3);
}

// Release both workers at the same moment so their transactions overlap.
while (microtime(true) < $barrier) {
    usleep(200);
}

$request = Request::create('/api/v1/appointments', 'POST', [
    'consultation_type_id' => $typeId,
    'starts_at' => $startsAt,
]);
$request->headers->set('Accept', 'application/json');
$request->headers->set('Authorization', 'Bearer '.$token);
// These workers exercise the primary (Persian) booking flow; without an
// explicit header the request defaults may resolve to `en`, which requires a
// configured USD fee and unrelated to the concurrency being tested.
$request->headers->set('Accept-Language', 'fa');

$response = $kernel->handle($request);

fwrite(STDOUT, json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode((string) $response->getContent(), true),
], JSON_UNESCAPED_UNICODE).PHP_EOL);

$kernel->terminate($request, $response);

exit(0);
