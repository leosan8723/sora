<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained('consultant_reviews')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            // Free-form description of the problem the reporter saw.
            $table->text('reason');
            // open | resolved | dismissed
            $table->string('status')->default('open');
            $table->timestamps();

            // One report per reporter per review: duplicates are rejected with
            // a 422, so the index is also the storage-side invariant.
            $table->unique(['review_id', 'reporter_id']);

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
    }
};
