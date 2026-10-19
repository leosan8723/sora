<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultant_profiles', function (Blueprint $table) {
            // Additive English content columns; the Persian originals are
            // untouched and always act as the fallback for missing English.
            $table->string('name_en', 160)->nullable()->after('user_id');
            $table->string('headline_en', 160)->nullable()->after('headline');
            $table->text('bio_en')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('consultant_profiles', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'headline_en', 'bio_en']);
        });
    }
};
