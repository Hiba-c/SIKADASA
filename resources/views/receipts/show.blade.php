<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt['title'] }} - {{ $receipt['number'] }}</title>
    <style>
        @page { margin: 28px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17211b; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        .toolbar { margin: 0 auto 16px; max-width: 760px; text-align: right; }
        .button { display: inline-block; margin-left: 6px; padding: 9px 13px; border-radius: 6px; background: #167447; color: #fff; font-size: 12px; text-decoration: none; }
        .button.secondary { background: #e8f4ec; color: #17613d; }
        .receipt { position: relative; max-width: 760px; margin: 0 auto; padding: 30px 34px; border: 1px solid #d5e1d8; border-radius: 12px; }
        .brand { display: table; width: 100%; padding-bottom: 18px; border-bottom: 2px solid #26764c; }
        .brand-logo, .brand-copy { display: table-cell; vertical-align: middle; }
        .brand-logo { width: 78px; }
        .brand-logo img { width: 64px; height: 64px; object-fit: contain; }
        .brand-copy h1 { margin: 0 0 5px; color: #17613d; font-size: 17px; }
        .brand-copy p { margin: 0; color: #64746a; line-height: 1.5; }
        .heading { margin: 22px 0 18px; text-align: center; }
        .heading h2 { margin: 0; color: #17613d; font-size: 17px; letter-spacing: .5px; }
        .heading p { margin: 7px 0 0; color: #617067; }
        .status { position: absolute; top: 120px; right: 34px; border: 1px solid #16804b; border-radius: 4px; padding: 5px 8px; color: #16804b; font-size: 9px; font-weight: bold; letter-spacing: 1px; transform: rotate(8deg); }
        .status.valid { display: none; }
        .status.invalid { border-color: #b42318; color: #b42318; }
        .details { width: 100%; border-collapse: collapse; }
        .details td { padding: 9px 7px; border-bottom: 1px solid #e7ede9; vertical-align: top; }
        .details td:first-child { width: 34%; color: #65756a; }
        .details td:nth-child(2) { width: 15px; color: #8a9a8f; }
        .details td:last-child { font-weight: 600; }
        .amount { margin-top: 18px; padding: 15px 18px; border: 1px solid #cce3d3; border-radius: 8px; background: #f1f8f3; }
        .amount span { display: block; color: #617067; font-size: 10px; }
        .amount strong { display: block; margin-top: 5px; color: #17613d; font-size: 20px; }
        .description { margin-top: 14px; padding: 11px 13px; border-left: 3px solid #74ae88; background: #f7faf8; line-height: 1.6; }
        .signatures { display: table; width: 100%; margin-top: 34px; text-align: center; }
        .signature { display: table-cell; width: 50%; color: #65756a; }
        .signature strong { display: block; margin-top: 52px; color: #17211b; }
        .footer { margin-top: 22px; color: #728078; font-size: 9px; line-height: 1.5; text-align: center; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .toolbar { display: none; }
            .receipt { max-width: none; border: 1px solid #d5e1d8; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a class="button secondary" href="{{ route('receipts.show', ['type' => request()->route('type'), 'id' => request()->route('id')]) }}?format=pdf">Unduh PDF</a>
        <a class="button" href="#" onclick="window.print(); return false;">Cetak kuitansi</a>
    </div>
    <main class="receipt">
        <div class="status {{ $receipt['is_valid'] ? 'valid' : 'invalid' }}">{{ $receipt['status'] }}</div>
        <header class="brand">
            @if($logoDataUri)
                <div class="brand-logo"><img src="{{ $logoDataUri }}" alt="Logo SIKADASA"></div>
            @endif
            <div class="brand-copy">
                <h1>{{ $organization['name'] }}</h1>
                @if($organization['address'])
                    <p>{{ $organization['address'] }}</p>
                @endif
                <p>Bukti transaksi resmi · SIKADASA</p>
            </div>
        </header>
        <div class="heading">
            <h2>{{ $receipt['title'] }}</h2>
            <p>Nomor kuitansi: <strong>{{ $receipt['number'] }}</strong></p>
        </div>
        <table class="details">
            @foreach([
                'Tanggal transaksi' => $receipt['date'],
                'Nama anggota / pihak' => $receipt['member'],
                'Unit / rumah' => $receipt['unit'],
                'Kode anggota' => $receipt['member_code'] ?: '—',
                'Jenis transaksi' => $receipt['transaction'],
                'Periode' => $receipt['period'],
                'Kategori' => $receipt['category'],
                'Aliran dana' => $receipt['fund_flow'],
                'Dicatat oleh' => $receipt['recorder'],
            ] as $label => $value)
                <tr><td>{{ $label }}</td><td>:</td><td>{{ $value }}</td></tr>
            @endforeach
        </table>
        <div class="amount">
            <span>Jumlah transaksi</span>
            <strong>{{ $receipt['amount'] }}</strong>
        </div>
        <div class="description"><strong>Keterangan:</strong> {{ $receipt['description'] }}</div>
        <div class="signatures">
            <div class="signature">Penerima / pihak terkait<strong>{{ $receipt['member'] }}</strong></div>
            <div class="signature">Petugas pencatat<strong>{{ $receipt['recorder'] }}</strong></div>
        </div>
        <footer class="footer">
            {{ $organization['footer'] ?: 'Simpan kuitansi ini sebagai bukti transaksi.' }}<br>
            Dicetak {{ $printedAt }} · Dokumen dibuat oleh SIKADASA
        </footer>
    </main>
</body>
</html>
