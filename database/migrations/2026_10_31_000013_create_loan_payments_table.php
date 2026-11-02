<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->date('payment_date')->index();
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('service_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->string('status', 20)->default('posted')->index();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
    }
};