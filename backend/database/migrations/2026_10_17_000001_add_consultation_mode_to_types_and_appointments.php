<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the consultation mode to the offering (consultation_types) and
     * snapshot it on appointments, mirroring the price_toman snapshot.
     *
     * Existing rows keep their historical semantics by backfilling as
     * "in_person"; appointments are backfilled from their booked type so
     * every appointment row carries a concrete mode. The column stays
     * nullable for strict backward compatibility with any reader that was
     * created before this migration.
     */
    public function up(): void
    {
        Schema::table('consultation_types', function (Blueprint $table): void {
            $table->enum('mode', ['online', 'in_person'])
                ->default('in_person')
                ->after('duration_minutes');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->enum('mode', ['online', 'in_person'])
                ->nullable()
                ->after('consultation_type_id');
        });

        // Portable backfill (works on MariaDB and SQLite): every appointment
        // inherits the mode of its booked consultation type.
        DB::table('appointments')
            ->whereNull('mode')
            ->update([
                'mode' => DB::raw(
                    '(select mode from consultation_types where consultation_types.id = appointments.consultation_type_id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn('mode');
        });

        Schema::table('consultation_types', function (Blueprint $table): void {
            $table->dropColumn('mode');
        });
    }
};
