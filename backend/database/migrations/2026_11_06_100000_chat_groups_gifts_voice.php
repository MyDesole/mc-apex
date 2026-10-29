<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мессенджер: групповые беседы, служебные сообщения (подарок, системные
 * отметки) и голосовые вложения.
 *
 * Тип диалога был enum — новое значение group в него не влезает, поэтому
 * колонка становится строкой.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('type', 20)->default('direct')->change();
            $table->string('title', 64)->nullable()->after('type');
            $table->foreignId('owner_id')->nullable()->after('author_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('messages', function (Blueprint $table) {
            // text — обычное, gift — подарок монет, system — отметка беседы
            $table->string('kind', 16)->default('text')->after('user_id');
            $table->json('meta')->nullable()->after('body');
        });

        Schema::table('message_attachments', function (Blueprint $table) {
            $table->boolean('is_voice')->default(false)->after('is_image');
            $table->unsignedSmallInteger('duration')->nullable()->after('is_voice');
        });
    }

    public function down(): void
    {
        Schema::table('message_attachments', function (Blueprint $table) {
            $table->dropColumn(['is_voice', 'duration']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['kind', 'meta']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn('title');
        });
    }
};
