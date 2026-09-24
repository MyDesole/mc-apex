<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_event_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('clan_event_comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['clan_event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_event_comments');
    }
};
