<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('class_room_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // subject teacher
            $table->foreignId('invited_by')->constrained('users');            // form teacher
            $table->string('subject');                                         // "Mathematics"
            $table->enum('role', ['form_teacher', 'subject_teacher'])->default('subject_teacher');
            $table->string('invite_token')->unique()->nullable();              // for email link
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['class_id', 'user_id', 'subject']); // no duplicate subject per teacher
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_room_members');
    }
};
