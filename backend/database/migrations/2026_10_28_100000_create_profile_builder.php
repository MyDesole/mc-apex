<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Конструктор профиля: раскладка блоков (порядок, ширина, оформление,
 * настройки) и гостевая книга — блок, куда гости оставляют записи.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('profile_blocks')->nullable();
        });

        Schema::create('profile_guestbook_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('body', 200);
            $table->timestamps();

            $table->index(['owner_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_guestbook_entries');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_blocks');
        });
    }
};
