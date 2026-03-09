<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_card_settings', function (Blueprint $table) {
            $table->string('school_logo')->nullable()->after('school_motto');
        });
    }

    public function down(): void
    {
        Schema::table('report_card_settings', function (Blueprint $table) {
            $table->dropColumn('school_logo');
        });
    }
};
