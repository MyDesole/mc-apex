<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->integer('power')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->unsignedInteger('power')->default(0)->change();
        });
    }
};
