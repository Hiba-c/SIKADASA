<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Ketua Dasawisma',
                'username' => config('sikadasa.seed_accounts.ketua_username'),
                'email' => config('sikadasa.seed_accounts.ketua_email'),
                'password' => config('sikadasa.seed_accounts.ketua_password'),
                'role' => UserRole::Ketua,
            ],
            [
                'name' => 'Bendahara Dasawisma',
                'username' => config('sikadasa.seed_accounts.bendahara_username'),
                'email' => config('sikadasa.seed_accounts.bendahara_email'),
                'password' => config('sikadasa.seed_accounts.bendahara_password'),
                'role' => UserRole::Bendahara,
            ],
        ];

        foreach ($accounts as $account) {
            if (! is_string($account['password']) || strlen($account['password']) < 12) {
                throw new RuntimeException(
                    'Set SIKADASA_KETUA_PASSWORD and SIKADASA_BENDAHARA_PASSWORD to unique values of at least 12 characters before seeding.'
                );
            }
        }

        if ($accounts[0]['password'] === $accounts[1]['password']) {
            throw new RuntimeException('Use different passwords for the Ketua and Bendahara accounts.');
        }

        foreach ($accounts as $account) {
            User::firstOrCreate(
                ['username' => $account['username']],
                [
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'password' => $account['password'],
                    'role' => $account['role'],
                    'is_active' => true,
                ],
            );
        }
    }
}