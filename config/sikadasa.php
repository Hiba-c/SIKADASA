<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Identity
    |--------------------------------------------------------------------------
    */

    'name' => 'SIKADASA',
    'full_name' => 'Sistem Informasi Keuangan dan Administrasi Dasawisma',
    'organization' => 'Kelompok Dasawisma Cempaka 46',
    'institution' => 'PKK Kalurahan Banjararum',

    'seed_accounts' => [
        'ketua_username' => env('SIKADASA_KETUA_USERNAME', 'ketua'),
        'ketua_email' => env('SIKADASA_KETUA_EMAIL', 'ketua@example.test'),
        'ketua_password' => env('SIKADASA_KETUA_PASSWORD'),
        'bendahara_username' => env('SIKADASA_BENDAHARA_USERNAME', 'bendahara'),
        'bendahara_email' => env('SIKADASA_BENDAHARA_EMAIL', 'bendahara@example.test'),
        'bendahara_password' => env('SIKADASA_BENDAHARA_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Financial Settings
    |--------------------------------------------------------------------------
    | These are fallback values. Active values are read from
    | organization_settings table at runtime.
    */

    'arisan' => [
        'default_contribution' => 20000,
    ],

    'loan' => [
        'default_service_rate' => 0.50, // percentage
        'max_installments' => 12,
        'min_amount' => 100000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Number Formats
    |--------------------------------------------------------------------------
    */

    'formats' => [
        'cash_income' => 'BKM',
        'cash_expense' => 'BKK',
        'savings' => 'TBG',
        'loan' => 'PJM',
        'loan_payment' => 'AGS',
        'arisan_payment' => 'ARS',
        'arisan_payout' => 'ARP',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'per_page' => 15,

    /*
    |--------------------------------------------------------------------------
    | Letterhead
    |--------------------------------------------------------------------------
    */

    'letterhead' => [
        'line1' => 'PEMERINTAH KABUPATEN KULON PROGO',
        'line2' => 'TP PKK KALURAHAN BANJARARUM',
        'line3' => 'KELOMPOK DASA WISMA CEMPAKA 46',
        'address' => 'Kagongan RT 46 RW 23, Banjararum, Kalibawang, Kulon Progo, D.I. Yogyakarta',
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    */

    'backup' => [
        'path' => storage_path('app/private/backups'),
        'retention_days' => 30,
        'mysqldump_binary' => env('SIKADASA_MYSQLDUMP_BINARY', 'mysqldump'),
        'mysql_binary' => env('SIKADASA_MYSQL_BINARY', 'mysql'),
    ],

];
