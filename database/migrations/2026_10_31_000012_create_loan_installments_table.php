<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date')->index();
            $table->decimal('principal_due', 15, 2);
            $table->decimal('service_due', 15, 2);
            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('service_paid', 15, 2)->default(0);
            $table->string('status', 20)->default('unpaid')->index();
            $table->timestamps();
            $table->unique(['loan_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};