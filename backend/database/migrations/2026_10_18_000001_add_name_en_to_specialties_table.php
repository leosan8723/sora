<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specialties', function (Blueprint $table): void {
            // English display name for localized API responses; Persian
            // remains the canonical `name` column.
            $table->string('name_en')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('specialties', function (Blueprint $table): void {
            $table->dropColumn('name_en');
        });
    }
};
