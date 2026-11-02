<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arisan_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('arisan_cycles')->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->boolean('has_won')->default(false);
            $table->timestamp('won_at')->nullable();
            $table->timestamps();
            $table->unique(['cycle_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arisan_participants');
    }
};