<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->decimal('loan_service_rate', 5, 2)->default(0.50)->change();
        });

        DB::table('organization_settings')->update(['loan_service_rate' => '0.50']);
    }

    public function down(): void
    {
        DB::table('organization_settings')
            ->where('loan_service_rate', '0.50')
            ->update(['loan_service_rate' => '1.00']);

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->decimal('loan_service_rate', 5, 2)->default(1.00)->change();
        });
    }
};
