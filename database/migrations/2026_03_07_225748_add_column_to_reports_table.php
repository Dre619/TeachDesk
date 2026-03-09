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
        Schema::table('reports', function (Blueprint $table) {
            //
             $table->string('conduct_grade')->nullable()->after('teacher_comment');          // A–F
            $table->text('head_teacher_comment')->nullable()->after('conduct_grade');
            $table->text('form_teacher_comment')->nullable()->after('head_teacher_comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            //
        });
    }
};
