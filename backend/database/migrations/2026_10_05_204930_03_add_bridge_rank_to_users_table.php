<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Звание бриджера у пользователя: выдаёт бридж-тестер.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('bridge_rank_id')
                ->nullable()
                ->after('profile_mode')
                ->constrained('bridge_ranks')
                ->nullOnDelete();

            $table->foreignId('bridge_rank_by')
                ->nullable()
                ->after('bridge_rank_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('bridge_rank_at')->nullable()->after('bridge_rank_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bridge_rank_id');
            $table->dropConstrainedForeignId('bridge_rank_by');
            $table->dropColumn('bridge_rank_at');
        });
    }
};
