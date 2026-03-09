<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates all core application tables that were originally created outside
 * of the migrations system. This migration enables tests to run against
 * a fresh in-memory SQLite database.
 *
 * Each block is guarded by Schema::hasTable() so this migration is safe
 * to run against a live database that already has these tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_plans')) {
            Schema::create('subscription_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->decimal('price_zmw', 8, 2);
                $table->string('billing_cycle')->default('monthly');
                $table->json('features');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
                $table->enum('status', ['trial', 'active', 'expired', 'cancelled'])->default('trial');
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
                $table->decimal('amount_zmw', 8, 2);
                $table->enum('method', ['mtn', 'airtel', 'card', 'paypal', 'manual'])->default('manual');
                $table->string('gateway_ref')->nullable();
                $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
                $table->timestamp('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('classes')) {
            Schema::create('classes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('subject')->nullable();
                $table->year('academic_year');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('students')) {
            Schema::create('students', function (Blueprint $table) {
                $table->id();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('first_name');
                $table->string('last_name');
                $table->enum('gender', ['male', 'female', 'other'])->nullable();
                $table->date('date_of_birth')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assessments')) {
            Schema::create('assessments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('subject');
                $table->tinyInteger('term');
                $table->year('academic_year');
                $table->enum('type', ['test', 'exam', 'assignment', 'ca', 'other'])->default('test');
                $table->decimal('score', 5, 2);
                $table->decimal('max_score', 5, 2)->default(100);
                $table->string('grade', 2)->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('attendance')) {
            Schema::create('attendance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->date('date');
                $table->enum('status', ['present', 'absent', 'late']);
                $table->string('notes', 500)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('behaviour_logs')) {
            Schema::create('behaviour_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->enum('type', ['positive', 'negative']);
                $table->string('category')->nullable();
                $table->text('description');
                $table->text('action_taken')->nullable();
                $table->date('date');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('lesson_plans')) {
            Schema::create('lesson_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
                $table->string('title');
                $table->string('subject');
                $table->string('topic')->nullable();
                $table->tinyInteger('term');
                $table->tinyInteger('week_number')->nullable();
                $table->year('academic_year');
                $table->longText('content')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->tinyInteger('term');
                $table->year('academic_year');
                $table->string('pdf_path')->nullable();
                $table->enum('status', ['draft', 'generated'])->default('draft');
                $table->timestamp('generated_at')->nullable();
                $table->text('teacher_comment')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('lesson_plans');
        Schema::dropIfExists('behaviour_logs');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('students');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
