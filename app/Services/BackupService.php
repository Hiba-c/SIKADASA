<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupService
{
    public function create(string $type = 'daily'): string
    {
        if (! in_array($type, ['daily', 'pre-restore'], true)) {
            throw new \InvalidArgumentException('Jenis backup tidak dikenal.');
        }

        $directory = $this->directory();
        File::ensureDirectoryExists($directory);

        $workspace = $directory.DIRECTORY_SEPARATOR.'.tmp-'.Str::uuid();
        File::ensureDirectoryExists($workspace);
        $archivePath = $directory.DIRECTORY_SEPARATOR.sprintf(
            'sikadasa-%s-%s.zip',
            $type,
            now()->format('Ymd-His'),
        );
        $archiveCreated = false;

        try {
            $databasePath = $workspace.DIRECTORY_SEPARATOR.'database.sql';
            $this->dumpDatabase($databasePath);

            $zip = new ZipArchive;
            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Arsip backup tidak dapat dibuat.');
            }

            $closed = false;
            try {
                if (! $zip->addFile($databasePath, 'database.sql')) {
                    throw new RuntimeException('Data database tidak dapat ditambahkan ke backup.');
                }

                $files = $this->applicationFiles();
                foreach ($files as $relativePath => $absolutePath) {
                    if (! $zip->addFile($absolutePath, $relativePath)) {
                        throw new RuntimeException('File aplikasi tidak dapat ditambahkan ke backup.');
                    }
                }

                $databaseHash = hash_file('sha256', $databasePath);
                if ($databaseHash === false) {
                    throw new RuntimeException('Checksum database backup tidak dapat dibuat.');
                }
                $fileHashes = [];
                foreach ($files as $relativePath => $absolutePath) {
                    $fileHash = hash_file('sha256', $absolutePath);
                    if ($fileHash === false) {
                        throw new RuntimeException('Checksum file aplikasi backup tidak dapat dibuat.');
                    }
                    $fileHashes[$relativePath] = $fileHash;
                }
                $manifest = [
                    'format_version' => 2,
                    'backup_type' => $type,
                    'created_at' => now()->toIso8601String(),
                    'database_format' => 'sql-jsonl-v1',
                    'database_sha256' => $databaseHash,
                    'files' => $fileHashes,
                ];
                if (! $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR))) {
                    throw new RuntimeException('Manifest backup tidak dapat dibuat.');
                }

                if (! $zip->close()) {
                    throw new RuntimeException('Arsip backup tidak dapat diselesaikan.');
                }
                $closed = true;
                $archiveCreated = true;
            } finally {
                if (! $closed) {
                    $zip->close();
                }
            }

            return $archivePath;
        } finally {
            File::deleteDirectory($workspace);
            if (! $archiveCreated && File::exists($archivePath)) {
                File::delete($archivePath);
            }
        }
    }

    public function list(): array
    {
        $directory = $this->directory();
        if (! File::isDirectory($directory)) {
            return [];
        }

        $backups = [];
        foreach (new \DirectoryIterator($directory) as $file) {
            if (
                $file->isDot()
                || ! $file->isFile()
                || ! preg_match('/^sikadasa-(daily|pre-restore)-\d{8}-\d{6}\.zip$/', $file->getFilename())
            ) {
                continue;
            }

            $backups[] = [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => date(DATE_ATOM, $file->getMTime()),
                'type' => str_contains($file->getFilename(), '-pre-restore-') ? 'Sebelum pemulihan' : 'Harian',
            ];
        }

        usort($backups, fn (array $left, array $right): int => strcmp($right['name'], $left['name']));

        return $backups;
    }

    public function resolveArchive(string $name): string
    {
        abort_unless(
            basename($name) === $name
                && preg_match('/^sikadasa-(daily|pre-restore)-\d{8}-\d{6}\.zip$/', $name),
            404,
        );

        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        abort_unless(File::isFile($path), 404);

        return $path;
    }

    public function prune(): int
    {
        $retentionDays = max(1, (int) config('sikadasa.backup.retention_days', 30));
        $cutoff = now()->subDays($retentionDays)->getTimestamp();
        $deleted = 0;

        foreach ($this->list() as $backup) {
            $path = $this->resolveArchive($backup['name']);
            if (File::lastModified($path) < $cutoff) {
                File::delete($path);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function restore(string $name): string
    {
        $archivePath = $this->resolveArchive($name);
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Arsip backup tidak dapat dibuka.');
        }

        try {
            $manifest = $this->verifyArchive($zip);
            $safetyBackup = $this->create('pre-restore');

            if (($manifest['database_format'] ?? null) === 'sql-jsonl-v1') {
                $this->restoreDatabase($zip);
            } else {
                $this->restoreLegacyDatabase($zip);
            }
            $this->restoreApplicationFiles($zip, $manifest['files']);

            return basename($safetyBackup);
        } finally {
            $zip->close();
        }
    }

    private function verifyArchive(ZipArchive $zip): array
    {
        $manifestJson = $zip->getFromName('manifest.json');
        $databaseStream = $zip->getStream('database.sql');
        if ($manifestJson === false || $databaseStream === false) {
            throw new RuntimeException('Arsip bukan backup SIKADASA yang lengkap.');
        }

        try {
            $manifest = json_decode($manifestJson, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            fclose($databaseStream);
            throw new RuntimeException('Manifest backup tidak valid.', 0, $exception);
        }
        if (
            ! is_array($manifest)
            || ! in_array($manifest['format_version'] ?? null, [1, 2], true)
            || ! is_array($manifest['files'] ?? null)
            || ! is_string($manifest['database_sha256'] ?? null)
            || (($manifest['format_version'] ?? null) === 2 && ($manifest['database_format'] ?? null) !== 'sql-jsonl-v1')
        ) {
            fclose($databaseStream);
            throw new RuntimeException('Manifest backup tidak valid.');
        }

        $databaseHash = hash_init('sha256');
        hash_update_stream($databaseHash, $databaseStream);
        fclose($databaseStream);
        if (! hash_equals($manifest['database_sha256'], hash_final($databaseHash))) {
            throw new RuntimeException('Pemeriksaan integritas database backup gagal.');
        }

        $expectedEntries = ['database.sql', 'manifest.json'];
        foreach ($manifest['files'] as $relativePath => $expectedHash) {
            if (! is_string($relativePath) || ! is_string($expectedHash)) {
                throw new RuntimeException('Daftar file dalam manifest backup tidak valid.');
            }
            $this->applicationFilePath($relativePath);
            $stream = $zip->getStream($relativePath);
            if ($stream === false) {
                throw new RuntimeException('File aplikasi tidak ditemukan di dalam backup.');
            }
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
            fclose($stream);
            if (! hash_equals($expectedHash, hash_final($hash))) {
                throw new RuntimeException('Pemeriksaan integritas file aplikasi gagal.');
            }
            $expectedEntries[] = $relativePath;
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);
            if (! is_string($entry) || ! in_array($entry, $expectedEntries, true)) {
                throw new RuntimeException('Arsip berisi file yang tidak dikenali.');
            }
        }

        return $manifest;
    }

    private function dumpDatabase(string $databasePath): void
    {
        $this->assertMysqlConnection();
        $output = fopen($databasePath, 'wb');
        if ($output === false) {
            throw new RuntimeException('File dump database tidak dapat dibuat.');
        }

        try {
            $pdo = DB::connection()->getPdo();
            $tables = $this->databaseTables($pdo);
            $this->writeDatabaseRecord($output, 'SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $table) {
                $this->writeDatabaseRecord($output, 'DROP TABLE IF EXISTS '.$this->quoteIdentifier($table));
            }
            foreach ($tables as $table) {
                $createTable = $pdo->query('SHOW CREATE TABLE '.$this->quoteIdentifier($table))->fetch(PDO::FETCH_ASSOC);
                $createSql = $createTable['Create Table'] ?? null;
                if (! is_string($createSql)) {
                    throw new RuntimeException('Struktur tabel database tidak dapat dibaca.');
                }
                $this->writeDatabaseRecord($output, $createSql);
            }
            foreach ($tables as $table) {
                $this->dumpTableRows($pdo, $output, $table);
            }
            $this->writeDatabaseRecord($output, 'SET FOREIGN_KEY_CHECKS=1');
        } finally {
            fclose($output);
        }

        if (File::size($databasePath) === 0) {
            File::delete($databasePath);
            throw new RuntimeException('Backup database tidak menghasilkan data.');
        }
    }

    private function databaseTables(PDO $pdo): array
    {
        $tables = [];
        foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $row) {
            $tables[] = (string) $row[0];
        }
        sort($tables);

        if ($tables === []) {
            throw new RuntimeException('Database tidak memiliki tabel untuk dicadangkan.');
        }

        return $tables;
    }

    private function dumpTableRows(PDO $pdo, $output, string $table): void
    {
        $quotedTable = $this->quoteIdentifier($table);
        $columns = $pdo->query('SHOW FULL COLUMNS FROM '.$quotedTable)->fetchAll(PDO::FETCH_ASSOC);
        $insertableColumns = array_values(array_filter(
            $columns,
            fn (array $column): bool => ! str_contains(strtolower((string) ($column['Extra'] ?? '')), 'generated'),
        ));
        if ($insertableColumns === []) {
            return;
        }

        $columnNames = array_map(fn (array $column): string => (string) $column['Field'], $insertableColumns);
        $quotedColumns = implode(', ', array_map($this->quoteIdentifier(...), $columnNames));
        $cursor = $pdo->query('SELECT * FROM '.$quotedTable);
        while (($row = $cursor->fetch(PDO::FETCH_ASSOC)) !== false) {
            $values = [];
            foreach ($insertableColumns as $column) {
                $name = (string) $column['Field'];
                $value = $row[$name];
                if ($value === null) {
                    $values[] = 'NULL';
                } elseif (preg_match('/^(binary|varbinary|tinyblob|blob|mediumblob|longblob)/i', (string) $column['Type']) === 1) {
                    $values[] = '0x'.bin2hex((string) $value);
                } else {
                    $quoted = $pdo->quote((string) $value);
                    if ($quoted === false) {
                        throw new RuntimeException('Nilai data tabel tidak dapat dikodekan untuk backup.');
                    }
                    $values[] = $quoted;
                }
            }
            $this->writeDatabaseRecord(
                $output,
                'INSERT INTO '.$quotedTable.' ('.$quotedColumns.') VALUES ('.implode(', ', $values).')',
            );
        }
        $cursor->closeCursor();
    }

    private function restoreDatabase(ZipArchive $zip): void
    {
        $stream = $zip->getStream('database.sql');
        if ($stream === false) {
            throw new RuntimeException('Data database tidak ditemukan di dalam backup.');
        }

        try {
            $pdo = DB::connection()->getPdo();
            $lineNumber = 0;
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            try {
                while (($line = fgets($stream)) !== false) {
                    $lineNumber++;
                    try {
                        $statement = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                    } catch (\JsonException $exception) {
                        throw new RuntimeException('Format data database backup tidak valid pada baris '.$lineNumber.'.', 0, $exception);
                    }
                    if (! is_string($statement) || trim($statement) === '') {
                        throw new RuntimeException('Format data database backup tidak valid pada baris '.$lineNumber.'.');
                    }
                    $pdo->exec($statement);
                }
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
        } finally {
            fclose($stream);
        }
    }

    private function restoreLegacyDatabase(ZipArchive $zip): void
    {
        $this->assertMysqlConnection();
        $credentialsPath = $this->temporaryCredentialsPath();
        try {
            $databaseStream = $zip->getStream('database.sql');
            if ($databaseStream === false) {
                throw new RuntimeException('Data database tidak ditemukan di dalam backup.');
            }

            try {
                $process = new Process([
                    (string) config('sikadasa.backup.mysql_binary', 'mysql'),
                    '--defaults-extra-file='.$credentialsPath,
                    '--default-character-set=utf8mb4',
                    '--binary-mode=1',
                    '--database='.(string) config('database.connections.mysql.database'),
                ]);
                $process->setInput($databaseStream)->setTimeout(600)->run();
                if (! $process->isSuccessful()) {
                    throw new RuntimeException('Pemulihan database backup lama gagal: '.trim($process->getErrorOutput()));
                }
            } finally {
                fclose($databaseStream);
            }
        } finally {
            File::delete($credentialsPath);
        }
    }

    private function temporaryCredentialsPath(): string
    {
        $directory = $this->directory();
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.'.mysql-'.Str::uuid().'.cnf';
        $this->writeCredentials($path);

        return $path;
    }

    private function writeCredentials(string $path): void
    {
        $this->assertMysqlConnection();
        $connection = config('database.connections.mysql');
        $contents = "[client]\n";
        foreach (['host', 'username', 'password', 'port', 'socket'] as $key) {
            $value = $connection[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $option = $key === 'username' ? 'user' : $key;
            $contents .= $option.'='.$this->quoteOptionValue((string) $value)."\n";
        }

        if (File::put($path, $contents) === false) {
            throw new RuntimeException('File konfigurasi sementara backup tidak dapat dibuat.');
        }
        @chmod($path, 0600);
    }

    private function writeDatabaseRecord($output, string $statement): void
    {
        $this->writeAll($output, json_encode($statement, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function applicationFiles(): array
    {
        $files = [];
        foreach (['storage/app/public', 'public/images'] as $relativeDirectory) {
            $directory = base_path($relativeDirectory);
            if (! File::isDirectory($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }
                $relativePath = str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    substr($file->getPathname(), strlen(base_path()) + 1),
                );
                $files[$relativePath] = $file->getPathname();
            }
        }
        ksort($files);

        return $files;
    }

    private function restoreApplicationFiles(ZipArchive $zip, array $files): void
    {
        foreach ($files as $relativePath => $_hash) {
            $target = $this->applicationFilePath($relativePath);
            if (is_link($target)) {
                throw new RuntimeException('Pemulihan tidak dapat menimpa tautan file aplikasi.');
            }
            File::ensureDirectoryExists(dirname($target));
            $stream = $zip->getStream($relativePath);
            if ($stream === false) {
                throw new RuntimeException('File aplikasi tidak dapat dibaca dari backup.');
            }
            $output = fopen($target, 'wb');
            if ($output === false) {
                fclose($stream);
                throw new RuntimeException('File aplikasi tidak dapat dipulihkan.');
            }
            try {
                if (stream_copy_to_stream($stream, $output) === false) {
                    throw new RuntimeException('Isi file aplikasi tidak dapat dipulihkan.');
                }
            } finally {
                fclose($stream);
                fclose($output);
            }
        }
    }

    private function applicationFilePath(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        if (
            str_contains($normalized, "\0")
            || str_starts_with($normalized, '/')
            || preg_match('/(^|\/)\.\.?($|\/)/', $normalized)
            || (! str_starts_with($normalized, 'storage/app/public/') && ! str_starts_with($normalized, 'public/images/'))
        ) {
            throw new RuntimeException('Jalur file dalam backup tidak diizinkan.');
        }

        return base_path(str_replace('/', DIRECTORY_SEPARATOR, $normalized));
    }

    private function assertMysqlConnection(): void
    {
        if (config('database.default') !== 'mysql' || ! config('database.connections.mysql.database')) {
            throw new RuntimeException('Backup dan pemulihan hanya dikonfigurasi untuk database MySQL.');
        }
    }

    private function quoteOptionValue(string $value): string
    {
        return '"'.strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
        ]).'"';
    }

    private function writeAll($stream, string $contents): void
    {
        $offset = 0;
        $length = strlen($contents);
        while ($offset < $length) {
            $written = fwrite($stream, substr($contents, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Data dump database tidak dapat ditulis.');
            }
            $offset += $written;
        }
    }

    private function directory(): string
    {
        return (string) config('sikadasa.backup.path');
    }
}
