<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['1001', 'Kas Kelompok', 'asset'],
            ['1101', 'Piutang Pinjaman', 'asset'],
            ['2001', 'Kewajiban Tabungan Anggota', 'liability'],
            ['2101', 'Kewajiban Arisan Anggota', 'liability'],
            ['3001', 'Ekuitas Kelompok', 'equity'],
            ['4001', 'Pendapatan Jasa Pinjaman', 'income'],
            ['4002', 'Pendapatan Iuran dan Lainnya', 'income'],
            ['5001', 'Beban Operasional', 'expense'],
        ] as [$code, $name, $type]) {
            Account::firstOrCreate(
                ['account_code' => $code],
                ['account_name' => $name, 'account_type' => $type, 'is_active' => true],
            );
        }
    }
}