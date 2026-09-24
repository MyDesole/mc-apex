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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['user', 'tester', 'moderator', 'admin'])
                ->default('user')
                ->after('email');

            $table->boolean('is_banned')->default(false)->after('role');
            $table->string('ban_reason')->nullable()->after('is_banned');
            $table->timestamp('banned_until')->nullable()->after('ban_reason');
            $table->foreignId('banned_by')->nullable()->after('banned_until')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['banned_by']);
            $table->dropColumn([
                'role', 'is_banned', 'ban_reason', 'banned_until', 'banned_by',
            ]);
        });
    }
};
