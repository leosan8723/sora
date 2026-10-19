<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consultant reviews. Every review is anchored to one completed appointment:
 * the unique appointment_id makes rating "verified-only" a data guarantee
 * instead of a UI promise. Moderation is handled with a status column so an
 * approved review can be withdrawn without deleting the audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultant_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consultant_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->text('body');
            $table->enum('status', ['pending', 'approved', 'rejected', 'hidden'])->default('pending');
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            $table->unique('appointment_id');
            $table->index('status');
            $table->index(['consultant_profile_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultant_reviews');
    }
};
