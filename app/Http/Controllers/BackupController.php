<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backups,
        private readonly AuditService $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('backups/index', [
            'backups' => $this->backups->list(),
        ]);
    }

    public function create(): RedirectResponse
    {
        try {
            $archive = $this->backups->create();
            $deleted = $this->backups->prune();
        } catch (RuntimeException $exception) {
            report($exception);

            return redirect()->route('backups.index')
                ->with('error', 'Backup gagal dibuat. Pastikan koneksi database aktif dan folder penyimpanan dapat ditulis.');
        }

        $name = basename($archive);
        $this->audit->record('backups', 'created', null, null, [
            'backup_name' => $name,
            'type' => 'manual',
        ]);
        $message = 'Backup berhasil dibuat: '.$name.'.';
        if ($deleted > 0) {
            $message .= " {$deleted} backup lama dihapus sesuai retensi.";
        }

        return redirect()->route('backups.index')->with('success', $message);
    }

    public function download(string $backup): BinaryFileResponse
    {
        return response()->download(
            $this->backups->resolveArchive($backup),
            $backup,
            ['Content-Type' => 'application/zip'],
        );
    }

    public function restore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'backup' => [
                'required',
                'string',
                'regex:/^sikadasa-(daily|pre-restore)-\d{8}-\d{6}\.zip$/',
            ],
            'confirmation' => ['required', 'in:PULIHKAN'],
        ], [
            'backup.regex' => 'File backup yang dipilih tidak valid.',
            'confirmation.in' => 'Ketik PULIHKAN untuk mengonfirmasi pemulihan.',
        ]);
        $actorId = (int) $request->user()->id;
        try {
            $safetyBackup = $this->backups->restore($data['backup']);
        } catch (RuntimeException $exception) {
            report($exception);

            return redirect()->route('backups.index')
                ->with('error', 'Pemulihan gagal. Data tidak dapat dipastikan pulih; periksa log server sebelum mencoba kembali.');
        }

        Auth::logout();
        $this->audit->record('backups', 'restored', null, null, [
            'backup_name' => $data['backup'],
            'safety_backup' => $safetyBackup,
            'initiated_by_user_id' => $actorId,
        ]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Pemulihan selesai. Silakan masuk kembali.')
            ->with('info', 'Backup kondisi sebelum pemulihan disimpan sebagai '.$safetyBackup.'.');
    }
}
