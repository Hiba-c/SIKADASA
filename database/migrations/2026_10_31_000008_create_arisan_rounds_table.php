<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arisan_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('arisan_cycles')->restrictOnDelete();
            $table->unsignedSmallInteger('round_number');
            $table->date('round_date');
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('winner_participant_id')->nullable()->constrained('arisan_participants')->nullOnDelete();
            $table->decimal('total_collected', 15, 2)->default(0);
            $table->timestamp('drawn_at')->nullable();
            $table->foreignId('drawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['cycle_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arisan_rounds');
    }
};