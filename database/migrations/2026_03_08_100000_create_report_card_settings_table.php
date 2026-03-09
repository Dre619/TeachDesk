<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_card_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('class_id')->nullable()->index();
            $table->string('school_name')->default('Student Report Card');
            $table->string('school_motto')->nullable();
            $table->string('accent_color', 7)->default('#4f46e5');
            $table->boolean('show_attendance')->default(true);
            $table->boolean('show_conduct')->default(true);
            $table->boolean('show_grading_scale')->default(true);
            $table->boolean('show_signatures')->default(true);
            $table->string('footer_note')->nullable();
            $table->timestamps();

            // One setting record per teacher per class (null class_id = global default)
            $table->unique(['user_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_settings');
    }
};
