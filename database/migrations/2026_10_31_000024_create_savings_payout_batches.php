<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('savings_payout_batches')) {
            Schema::create('savings_payout_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('hijri_year')->unique();
                $table->date('payout_date');
                $table->string('status', 30)->index();
                $table->decimal('total_due', 15, 2);
                $table->decimal('total_paid', 15, 2)->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('last_processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('last_processed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('savings_payout_items')) {
            Schema::create('savings_payout_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('savings_payout_batch_id')->constrained()->restrictOnDelete();
                $table->foreignId('savings_account_id')->constrained()->restrictOnDelete();
                $table->decimal('amount_due', 15, 2);
                $table->decimal('amount_paid', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('savings_payout_items', 'savings_payout_item_batch_account_unique', 'unique')) {
            Schema::table('savings_payout_items', function (Blueprint $table) {
                $table->unique(
                    ['savings_payout_batch_id', 'savings_account_id'],
                    'savings_payout_item_batch_account_unique',
                );
            });
        }

        if (! Schema::hasColumn('savings_transactions', 'savings_payout_item_id')) {
            Schema::table('savings_transactions', function (Blueprint $table) {
                $table->foreignId('savings_payout_item_id')
                    ->nullable()
                    ->constrained('savings_payout_items')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('savings_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('savings_payout_item_id');
        });
        Schema::dropIfExists('savings_payout_items');
        Schema::dropIfExists('savings_payout_batches');
    }
};
