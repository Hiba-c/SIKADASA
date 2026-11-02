<?php

namespace Database\Seeders;

use App\Models\OrganizationSetting;
use Illuminate\Database\Seeder;

class OrganizationSettingSeeder extends Seeder
{
    public function run(): void
    {
        OrganizationSetting::firstOrCreate([], [
            'organization_name' => 'Kelompok Dasawisma Cempaka 46',
            'address' => 'Kagongan RT 46 RW 23, Banjararum, Kalibawang, Kulon Progo, D.I. Yogyakarta',
            'arisan_contribution_amount' => config('sikadasa.arisan.default_contribution'),
            'loan_service_rate' => config('sikadasa.loan.default_service_rate'),
        ]);
    }
}