<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tier_tests', function (Blueprint $table) {
            // Разовый буст: заявка идёт вне очереди, приоритет сгорает вместе с заявкой
            $table->boolean('is_priority')->default(false);
            $table->timestamp('priority_purchased_at')->nullable();
            $table->unsignedInteger('priority_price_paid')->nullable();

            // Сдвиг на случай нескольких приоритетных заявок одновременно
            $table->unsignedInteger('priority_weight')->default(0);

            $table->index(['status', 'is_priority']);
        });
    }

    public function down(): void
    {
        Schema::table('tier_tests', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_priority']);
            $table->dropColumn([
                'is_priority', 'priority_purchased_at', 'priority_price_paid', 'priority_weight',
            ]);
        });
    }
};
