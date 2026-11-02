<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'sikadasa:backup';

    protected $description = 'Membuat backup database dan file publik SIKADASA.';

    public function handle(BackupService $backups): int
    {
        $archive = $backups->create();
        $deleted = $backups->prune();

        $this->info('Backup berhasil dibuat: '.basename($archive));
        if ($deleted > 0) {
            $this->info("{$deleted} backup lama dihapus sesuai retensi.");
        }

        return self::SUCCESS;
    }
}