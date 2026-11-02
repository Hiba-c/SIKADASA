<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 34px 46px; }
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
        .section { margin: 15px 0 7px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        table { border-collapse: collapse; width: 100%; }
        .table th, .table td { border: 1px solid #c8d4e2; padding: 6px 7px; vertical-align: middle; }
        .table th { background: #f3f6fa; color: #14213a; font-weight: bold; text-align: left; }
        .table td { background: #fff; }
        .summary-table td:nth-child(1) { width: 6%; color: #23549b; }
        .summary-table td:nth-child(2) { width: 30%; font-weight: bold; }
        .summary-table td:nth-child(3) { width: 17%; font-weight: bold; text-align: right; white-space: nowrap; }
        .summary-table td:nth-child(4) { color: #64748b; }
        .money { text-align: right; white-space: nowrap; font-family: DejaVu Sans Mono, monospace; }
        .positive { color: #00804b; }
        .negative { color: #dc2626; }
        .balance { color: #00804b; font-weight: bold; }
        .members-table th, .members-table td { padding: 5px 6px; }
        .members-table td:first-child { width: 5%; color: #23549b; }
        .members-table td:nth-child(2) { width: 23%; font-weight: bold; }
        .members-table td:nth-child(3) { width: 15%; color: #64748b; }
        .members-table td:nth-child(4), .members-table td:nth-child(5), .members-table td:nth-child(6) { width: 19%; }
        .empty { padding: 12px; color: #64748b; text-align: center; }
        .signatures { display: table; width: 100%; margin-top: 30px; page-break-inside: avoid; text-align: center; }
        .signature { display: table-cell; width: 50%; color: #64748b; }
        .signature .role { margin-top: 3px; color: #172033; font-weight: bold; }
        .signature .name { margin-top: 57px; color: #172033; font-weight: bold; text-decoration: underline; }
        .signature .caption { margin-top: 3px; color: #64748b; font-size: 8px; }
        .footer { position: fixed; right: 0; bottom: -28px; left: 0; padding-top: 8px; border-top: 1px solid #d5deea; color: #8492a6; font-size: 7px; line-height: 1.5; text-align: center; }
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
        <h2>LAPORAN PERTANGGUNGJAWABAN KEUANGAN KONSOLIDASI</h2>
        <p>Periode: {{ $report['consolidated']['period_label'] }} · Tahun Buku {{ $report['consolidated']['fiscal_year'] }}</p>
    </div>

    <h3 class="section">I. Ringkasan Posisi Keuangan Kelompok</h3>
    <table class="table summary-table">
        <thead>
            <tr><th>No</th><th>Posisi Rekening / Komponen</th><th>Saldo Saat Ini</th><th>Keterangan</th></tr>
        </thead>
        <tbody>
            @foreach($report['consolidated']['summary'] as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item['label'] }}</td>
                    <td class="money {{ $index === 2 ? 'negative' : ($index === 3 ? 'balance' : '') }}">Rp {{ number_format((float) $item['amount'], 0, ',', '.') }}</td>
                    <td>{{ $item['note'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3 class="section">II. Rincian Saldo Tabungan Sukarela Anggota</h3>
    @if(count($report['consolidated']['savings_rows']) > 0)
        <table class="table members-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Anggota</th>
                    <th>Unit Rumah</th>
                    <th class="money">Total Setoran (+)</th>
                    <th class="money">Total Penarikan (−)</th>
                    <th class="money">Saldo Tabungan Sukarela</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['consolidated']['savings_rows'] as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $row['member'] }}</td>
                        <td>{{ $row['unit'] }}</td>
                        <td class="money positive">+Rp {{ number_format((float) $row['deposits'], 0, ',', '.') }}</td>
                        <td class="money negative">−Rp {{ number_format((float) $row['withdrawals'], 0, ',', '.') }}</td>
                        <td class="money balance">Rp {{ number_format((float) $row['balance'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">Belum ada data rekening tabungan anggota.</p>
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
    </footer>
</body>
</html>
