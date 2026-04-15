<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_transfers', function (Blueprint $table) {
            // MySQL uses the unique index to support the class_id FK.
            // Add a plain index first so the FK still has backing support,
            // then we can safely drop the overly-broad unique constraint.
            $table->index('class_id', 'class_transfers_class_id_index');
            $table->dropUnique('one_pending_per_class');
        });
    }

    public function down(): void
    {
        Schema::table('class_transfers', function (Blueprint $table) {
            $table->unique(['class_id', 'status'], 'one_pending_per_class');
            $table->dropIndex('class_transfers_class_id_index');
        });
    }
};
