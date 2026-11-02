<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->string('organization_name');
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('chairman_name')->nullable();
            $table->string('treasurer_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->decimal('arisan_contribution_amount', 15, 2)->default(20000);
            $table->decimal('loan_service_rate', 5, 2)->default(0.5);
            $table->text('receipt_footer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};