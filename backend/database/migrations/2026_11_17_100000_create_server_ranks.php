<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ранги игрового сервера (группы LuckPerms), выданные игрокам: админом
 * или автоматически по роли на сайте. Сами права живут в LuckPerms,
 * здесь — кто, что и до какого числа получил.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_rank_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rank', 32);
            // admin — выдал администратор, role — следует за ролью на сайте
            $table->string('source', 16)->default('admin');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_rank_grants');
    }
};
