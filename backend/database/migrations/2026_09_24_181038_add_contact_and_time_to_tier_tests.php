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
        Schema::table('tier_tests', function (Blueprint $table) {
            $table->string('contact_type', 16)->nullable()->after('user_id');   // discord | telegram
            $table->string('contact_value', 128)->nullable()->after('contact_type');
            $table->string('preferred_time', 128)->nullable()->after('scheduled_at'); // текстом: "сегодня 20:00-22:00"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tier_tests', function (Blueprint $table) {
            $table->dropColumn(['contact_type', 'contact_value', 'preferred_time']);
        });
    }
};
