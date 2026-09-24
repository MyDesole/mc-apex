<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('banner_color');  // картинка-подложка
            $table->boolean('is_highlighted')->default(false)->after('is_open'); // выделение в листинге
            $table->json('socials')->nullable()->after('is_highlighted');     // { discord, telegram, youtube, vk, website }
        });
    }

    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'is_highlighted', 'socials']);
        });
    }
};
