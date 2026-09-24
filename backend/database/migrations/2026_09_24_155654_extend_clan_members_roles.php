<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clan_members', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('role');
            $table->string('title', 32)->nullable()->after('permissions');
            $table->timestamp('promoted_at')->nullable()->after('title');
            $table->foreignId('promoted_by')->nullable()->after('promoted_at')
                ->constrained('users')->nullOnDelete();
        });    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
