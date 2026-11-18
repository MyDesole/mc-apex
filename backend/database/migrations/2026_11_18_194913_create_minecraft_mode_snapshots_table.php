<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minecraft_mode_snapshots', function (Blueprint $table) {
            $table->string('mode', 24)->primary();
            $table->json('data');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minecraft_mode_snapshots');
    }
};
