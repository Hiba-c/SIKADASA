<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Services\AuditService;
use App\Models\OrganizationSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    private const TYPES = ['cash', 'savings', 'loans', 'arisan', 'financial'];

    public function __construct(
        private readonly ReportService $reports,
        private readonly AuditService $audit,
    ) {}

    public function index(): Response
    {
        Gate::authorize('view-reports');

        return Inertia::render('reports/index');
    }

    public function show(Request $request, string $type): Response
    {
        Gate::authorize('view-reports');
        abort_unless(in_array($type, self::TYPES, true), 404);
        [$from, $to] = $this->dates($request);
        $report = $this->reports->build($type, $from, $to, true);
        $organization = OrganizationSetting::query()->first();

        return Inertia::render('reports/'.$type, [
            'report' => $report,
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'organization' => [
                'name' => $organization?->organization_name ?? 'Kelompok Dasawisma Cempaka 46',
                'address' => $organization?->address,
                'chairman_name' => $organization?->chairman_name,
                'treasurer_name' => $organization?->treasurer_name,
                'footer' => $organization?->receipt_footer,
                'logo' => $this->organizationLogo($organization?->logo_path),
            ],
        ]);
    }

    public function export(Request $request, string $type): HttpResponse
    {
        Gate::authorize('view-reports');
        Gate::authorize('export-reports');
        abort_unless(in_array($type, self::TYPES, true), 404);
        [$from, $to] = $this->dates($request);
        $report = $this->reports->build($type, $from, $to);
        $filename = sprintf('sikadasa-%s-%s-%s.pdf', $type, $from->format('Ymd'), $to->format('Ymd'));
        $organization = OrganizationSetting::query()->first();

        $viewData = [
            'report' => $report,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'logoPath' => public_path('images/logo-sikadasa.png'),
            'logoDataUri' => $this->organizationLogo($organization?->logo_path),
            'organization' => [
                'name' => $organization?->organization_name ?? 'Kelompok Dasawisma Cempaka 46',
                'address' => $organization?->address,
                'chairman_name' => $organization?->chairman_name,
                'treasurer_name' => $organization?->treasurer_name,
                'footer' => $organization?->receipt_footer,
            ],
        ];
        $view = $type === 'financial' ? 'pdf.consolidated-financial' : 'pdf.report';
        $orientation = $type === 'financial' ? 'portrait' : 'landscape';
        $content = Pdf::loadView($view, $viewData)->setPaper('a4', $orientation)->output();

        $this->audit->record('reports', 'exported', null, null, [
            'report' => $type,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'format' => 'pdf',
        ]);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function validate(): RedirectResponse
    {
        Gate::authorize('validate-reports');
        $result = $this->reports->reconcile();
        $this->audit->record('reports', 'validated', null, null, [
            'unbalanced_journals' => $result['unbalanced_journals'],
            'savings_discrepancies' => $result['savings_discrepancies'],
        ]);
        $message = sprintf(
            'Pemeriksaan selesai: %d jurnal tidak seimbang, %d saldo tabungan berbeda.',
            $result['unbalanced_journals'],
            $result['savings_discrepancies'],
        );

        return back()->with($result['unbalanced_journals'] + $result['savings_discrepancies'] === 0 ? 'success' : 'warning', $message);
    }

    private function dates(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $today = CarbonImmutable::today();
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from']) : $today->startOfMonth();
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to']) : $today;

        if ($from->greaterThan($to)) {
            abort(422, 'Tanggal awal laporan tidak boleh melewati tanggal akhir.');
        }

        return [$from, $to];
    }

    private function organizationLogo(?string $logoPath): ?string
    {
        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            $mimeType = Storage::disk('public')->mimeType($logoPath) ?: 'image/png';

            return 'data:'.$mimeType.';base64,'.base64_encode(Storage::disk('public')->get($logoPath));
        }

        $defaultPath = public_path('images/logo-sikadasa.png');
        if (! is_file($defaultPath)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(file_get_contents($defaultPath));
    }

}