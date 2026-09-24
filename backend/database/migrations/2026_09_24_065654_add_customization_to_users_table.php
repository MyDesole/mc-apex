<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('avatar');
            $table->string('banner_color', 16)->default('#7c3aed')->after('cover_path');
            $table->json('socials')->nullable()->after('banner_color');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'banner_color', 'socials']);
        });
    }
};
