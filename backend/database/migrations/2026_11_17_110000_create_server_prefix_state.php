<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Какой префикс (донат + тир) мы последний раз отправили игроку на сервер. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_prefix_state', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('nick', 16);
            $table->string('prefix', 255)->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_prefix_state');
    }
};
