<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arisan_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('cycle_number')->unique();
            $table->string('name');
            $table->decimal('contribution_amount', 15, 2);
            $table->string('status', 20)->default('active')->index();
            $table->date('started_at');
            $table->date('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arisan_cycles');
    }
};