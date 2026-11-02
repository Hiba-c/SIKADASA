<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\CashCategory;
use Illuminate\Database\Seeder;

class CashCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Iuran bulanan', TransactionType::Income],
            ['Jimpitan', TransactionType::Income],
            ['Sumbangan', TransactionType::Income],
            ['Pendapatan jasa pinjaman', TransactionType::Income],
            ['Kelebihan pembayaran pinjaman', TransactionType::Income],
            ['Pendapatan kegiatan', TransactionType::Income],
            ['Pemasukan lainnya', TransactionType::Income],
            ['Jamuan', TransactionType::Expense],
            ['Santunan', TransactionType::Expense],
            ['ATK', TransactionType::Expense],
            ['Administrasi', TransactionType::Expense],
            ['Operasional', TransactionType::Expense],
            ['Kegiatan kelompok', TransactionType::Expense],
            ['Pengeluaran lainnya', TransactionType::Expense],
        ] as [$name, $type]) {
            CashCategory::firstOrCreate(
                ['name' => $name, 'type' => $type],
                ['is_active' => true],
            );
        }
    }
}