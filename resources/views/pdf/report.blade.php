<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 30px 52px; }
        * { box-sizing: border-box; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .header { display: table; width: 100%; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid #172033; }
        .header-logo, .header-copy, .header-spacer { display: table-cell; vertical-align: middle; }
        .header-logo, .header-spacer { width: 82px; }
        .logo { display: block; width: 74px; height: 74px; margin: 0 auto; object-fit: contain; }
        .header-copy { text-align: center; }
        .header h1 { margin: 0 0 5px; font-size: 12px; font-weight: bold; }
        .header h2 { margin: 0 0 4px; font-size: 15px; }
        .header p { margin: 2px 0; color: #45566d; font-size: 8px; }
        .header .system { color: #78889b; }
        .report-title { margin: 20px 0 16px; text-align: center; }
        .report-title h2 { margin: 0 0 5px; font-size: 12px; }
        .report-title p { margin: 0; color: #64748b; font-size: 9px; }
        .section { margin: 14px 0 7px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        table { border-collapse: collapse; width: 100%; }
        .table th, .table td { border: 1px solid #c8d4e2; padding: 6px 7px; vertical-align: middle; }
        .table th { background: #f3f6fa; color: #14213a; font-weight: bold; text-align: left; }
        .summary-table td:first-child { width: 6%; color: #23549b; }
        .summary-table td:nth-child(2) { width: 34%; font-weight: bold; }
        .summary-table td:last-child { color: #17613d; font-weight: bold; text-align: right; white-space: nowrap; }
        .detail-table td { overflow-wrap: anywhere; }
        .detail-table td.money { text-align: right; white-space: nowrap; font-family: DejaVu Sans Mono, monospace; }
        .empty { padding: 14px 0; color: #64748b; text-align: center; }
        .signatures { display: table; width: 100%; margin-top: 30px; page-break-inside: avoid; text-align: center; }
        .signature { display: table-cell; width: 50%; color: #64748b; }
        .signature .role { margin-top: 3px; color: #172033; font-weight: bold; }
        .signature .name { margin-top: 48px; color: #172033; font-weight: bold; text-decoration: underline; }
        .signature .caption { margin-top: 3px; color: #64748b; font-size: 8px; }
        .footer { position: fixed; right: 0; bottom: -31px; left: 0; padding-top: 8px; border-top: 1px solid #d5deea; color: #8492a6; font-size: 7px; line-height: 1.5; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-logo">
            @if($logoDataUri)
                <img class="logo" src="{{ $logoDataUri }}" alt="Logo kelompok">
            @endif
        </div>
        <div class="header-copy">
            <h1>PEMBERDAYAAN KESEJAHTERAAN KELUARGA (PKK)</h1>
            <h2>{{ $organization['name'] }}</h2>
            @if($organization['address'])
                <p>{{ $organization['address'] }}</p>
            @endif
            <p class="system">Sistem Informasi Keuangan Dasawisma (SIKADASA) Berbasis Web</p>
        </div>
        <div class="header-spacer"></div>
    </header>

    <div class="report-title">
        <h2>{{ mb_strtoupper($report['title']) }}</h2>
        <p>Periode: {{ \Carbon\CarbonImmutable::parse($from)->locale('id')->translatedFormat('d M Y') }} – {{ \Carbon\CarbonImmutable::parse($to)->locale('id')->translatedFormat('d M Y') }}</p>
    </div>

    @if(count($report['summary']) > 0)
        <h3 class="section">I. Ringkasan {{ $report['title'] }}</h3>
        <table class="table summary-table">
            <thead>
                <tr><th>No</th><th>Komponen</th><th>Nilai</th></tr>
            </thead>
            <tbody>
                @foreach($report['summary'] as $index => $item)
                    @php
                        $summaryIsMoney = preg_match('/^\d+\.\d{2}$/', (string) $item['amount']) === 1;
                        $summaryValue = $summaryIsMoney
                            ? 'Rp '.number_format((float) $item['amount'], 0, ',', '.')
                            : $item['amount'];
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item['label'] }}</td>
                        <td>{{ $summaryValue }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3 class="section">{{ count($report['summary']) > 0 ? 'II' : 'I' }}. Rincian {{ $report['title'] }}</h3>
    @if(count($report['rows']) > 0)
        <table class="table detail-table">
            <thead>
                <tr>
                    @foreach($report['columns'] as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($report['rows'] as $row)
                    <tr>
                        @foreach($row as $cell)
                            @php
                                $cellText = (string) $cell;
                                $isMoney = preg_match('/^\d+\.\d{2}$/', $cellText) === 1;
                                $isDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $cellText) === 1;
                                $isDateTime = preg_match('/^\d{4}-\d{2}-\d{2}T/', $cellText) === 1;
                                $displayCell = match ($cellText) {
                                    'income' => 'Pemasukan',
                                    'expense' => 'Pengeluaran',
                                    'deposit' => 'Setoran',
                                    'withdrawal' => 'Penarikan',
                                    'iuran' => 'Iuran',
                                    'penyaluran' => 'Penyaluran',
                                    'pending' => 'Menunggu',
                                    'approved' => 'Disetujui',
                                    'rejected' => 'Ditolak',
                                    'active' => 'Berjalan',
                                    'paid_off' => 'Lunas',
                                    'cancelled' => 'Dibatalkan',
                                    default => $isMoney
                                        ? 'Rp '.number_format((float) $cellText, 0, ',', '.')
                                        : ($isDate
                                            ? \Carbon\CarbonImmutable::parse($cellText)->locale('id')->translatedFormat('d M Y')
                                            : ($isDateTime
                                                ? \Carbon\CarbonImmutable::parse($cellText)->locale('id')->translatedFormat('d M Y H:i')
                                                : $cell)),
                                };
                            @endphp
                            <td class="{{ $isMoney ? 'money' : '' }}">{{ $displayCell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">Tidak ada transaksi pada rentang tanggal ini.</p>
    @endif

    <div class="signatures">
        <div class="signature">
            <div>Mengetahui &amp; Menyetujui,</div>
            <div class="role">Ketua {{ $organization['name'] }}</div>
            <div class="name">{{ $organization['chairman_name'] ?: '________________________' }}</div>
            <div class="caption">Pengurus kelompok</div>
        </div>
        <div class="signature">
            <div>{{ \Carbon\CarbonImmutable::parse($to)->locale('id')->translatedFormat('d F Y') }}</div>
            <div class="role">Bendahara</div>
            <div class="name">{{ $organization['treasurer_name'] ?: '________________________' }}</div>
            <div class="caption">Pengelola keuangan {{ $organization['name'] }}</div>
        </div>
    </div>

    <footer class="footer">
        {{ $organization['footer'] ?: 'Dokumen laporan sah diterbitkan melalui Sistem Informasi Keuangan Dasawisma (SIKADASA).' }}
        {{ $organization['name'] }}@if($organization['address']) · {{ $organization['address'] }}@endif
        · Dicetak {{ now()->locale('id')->translatedFormat('d F Y H:i') }}
    </footer>
</body>
</html>
