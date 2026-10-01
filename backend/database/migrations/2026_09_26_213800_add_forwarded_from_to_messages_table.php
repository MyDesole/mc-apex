<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Пересылка сообщений: колонка forwarded_from_user_id.
 *
 * Файл был удалён как «дубль» миграции 213153, но дубль относился только
 * к живой базе разработчика: там колонку добавлял другой файл. На чистой базе
 * единственным создателем колонки был именно этот файл, поэтому без него
 * ломалось создание сообщений и падали тесты.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'forwarded_from_user_id')) {
                $table->foreignId('forwarded_from_user_id')
                    ->nullable()
                    ->after('reply_to_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'forwarded_from_user_id')) {
                $table->dropForeign(['forwarded_from_user_id']);
                $table->dropColumn('forwarded_from_user_id');
            }
        });
    }
};
