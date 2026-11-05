<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Короткий префикс ника над головой (до 16 символов на 1.8) рядом с полным. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_prefix_state', function (Blueprint $table) {
            $table->string('tag', 32)->nullable()->after('prefix');
        });
    }

    public function down(): void
    {
        Schema::table('server_prefix_state', function (Blueprint $table) {
            $table->dropColumn('tag');
        });
    }
};
