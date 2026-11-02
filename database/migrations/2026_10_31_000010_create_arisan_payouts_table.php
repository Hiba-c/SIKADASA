<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arisan_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('arisan_rounds')->restrictOnDelete();
            $table->foreignId('winner_participant_id')->constrained('arisan_participants')->restrictOnDelete();
            $table->string('payout_number')->unique();
            $table->decimal('amount', 15, 2);
            $table->date('payout_date');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arisan_payouts');
    }
};