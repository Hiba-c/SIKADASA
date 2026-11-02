<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('registration_code')->unique();
            $table->string('full_name');
            $table->string('nik', 16)->unique();
            $table->text('address');
            $table->string('house_number', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->date('joined_at');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('inactive_at')->nullable();
            $table->text('inactive_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};