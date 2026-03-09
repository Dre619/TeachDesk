<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('content');
            $table->text('objectives')->nullable()->after('duration_minutes');
            $table->text('resources')->nullable()->after('objectives');
            $table->text('assessment')->nullable()->after('resources');
            $table->text('homework')->nullable()->after('assessment');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->dropColumn(['duration_minutes', 'objectives', 'resources', 'assessment', 'homework']);
        });
    }
};
