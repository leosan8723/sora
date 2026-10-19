<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // USD prices are stored as integer cents ("money as integers"), entered
        // manually by the administrator. Null is valid: the Persian (toman)
        // price remains mandatory and untouched; existing historical rows keep
        // working exactly as before.
        Schema::table('consultation_types', function (Blueprint $table) {
            $table->unsignedInteger('price_usd_cents')->nullable()->after('price_toman');
        });

        // Historical snapshots are never rewritten; new bookings may capture
        // the USD cents alongside the toman snapshot.
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedInteger('price_usd_cents')->nullable()->after('price_toman');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('price_usd_cents');
        });

        Schema::table('consultation_types', function (Blueprint $table) {
            $table->dropColumn('price_usd_cents');
        });
    }
};
