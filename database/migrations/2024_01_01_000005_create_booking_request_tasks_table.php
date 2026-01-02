<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_request_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Prevent duplicate task in same booking request
            $table->unique(['booking_request_id', 'task_id']);

            // Index for efficient task lookup across booking requests
            $table->index(['task_id', 'booking_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_request_tasks');
    }
};
