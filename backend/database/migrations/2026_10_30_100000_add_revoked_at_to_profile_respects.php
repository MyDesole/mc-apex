<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Респект можно забрать: запись остаётся (чтобы в тот же день нельзя было
 * дать снова), но в счёт не идёт.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_respects', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('profile_respects', function (Blueprint $table) {
            $table->dropColumn('revoked_at');
        });
    }
};
