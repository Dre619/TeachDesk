<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'declined', 'cancelled'])->default('pending');
            $table->text('message')->nullable();
            $table->string('token', 64)->unique()->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // Only one active transfer per class at a time
            $table->unique(['class_id', 'status'], 'one_pending_per_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_transfers');
    }
};
