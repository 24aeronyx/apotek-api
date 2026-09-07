<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan {{ $label }}</title>
    <style>
        @page { margin: 28px 30px 34px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 10px; }
        .header { border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { font-size: 18px; font-weight: bold; color: #0f172a; }
        .subtitle { color: #64748b; margin-top: 4px; }
        .meta { float: right; text-align: right; color: #475569; }
        .clearfix { clear: both; }
        .summary { width: 100%; margin-bottom: 16px; }
        .summary td { width: 25%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .label { color: #64748b; font-size: 8px; text-transform: uppercase; }
        .value { font-size: 15px; font-weight: bold; margin-top: 5px; }
        table.data { width: 100%; border-collapse: collapse; }
        .data th { background: #0f766e; color: white; text-align: left; padding: 8px; font-size: 8px; text-transform: uppercase; }
        .data td { border-bottom: 1px solid #e2e8f0; padding: 8px; vertical-align: top; }
        .right { text-align: right !important; }
        .footer { position: fixed; bottom: -18px; left: 0; right: 0; text-align: center; color: #94a3b8; font-size: 8px; }
    </style>
</head>
<body>
    @php($brand = config('branding'))
    <div class="header">
        <div class="meta">Laporan {{ $label }}<br>Periode: {{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }}</div>
        <div class="brand">{{ $brand['name'] }}</div>
        <div class="subtitle">{{ $brand['report_subtitle'] }}</div>
        <div class="clearfix"></div>
    </div>

    <table class="summary">
        <tr>
            <td><div class="label">Total Dokumen</div><div class="value">{{ $summary['document_count'] ?? 0 }}</div></td>
            @if ($type === 'sales')
                <td><div class="label">Lunas</div><div class="value">{{ $summary['paid_count'] ?? 0 }}</div></td>
                <td><div class="label">Unpaid</div><div class="value">{{ $summary['unpaid_count'] ?? 0 }}</div></td>
                <td><div class="label">Total Lunas</div><div class="value">Rp {{ number_format($summary['total_amount'] ?? 0, 0, ',', '.') }}</div></td>
            @elseif ($type === 'purchases')
                <td><div class="label">Total Nilai Pembelian</div><div class="value">Rp {{ number_format($summary['total_amount'] ?? 0, 0, ',', '.') }}</div></td>
                <td><div class="label">Jenis</div><div class="value">Pembelian</div></td>
                <td><div class="label">Keterangan</div><div class="value">Barang Masuk</div></td>
            @else
                <td><div class="label">Selesai</div><div class="value">{{ $summary['completed_count'] ?? 0 }}</div></td>
                <td><div class="label">Menunggu</div><div class="value">{{ $summary['waiting_count'] ?? 0 }}</div></td>
                <td><div class="label">Jenis</div><div class="value">Kunjungan</div></td>
            @endif
        </tr>
    </table>

    <table class="data">
        <thead>
        @if ($type === 'sales')
            <tr><th>Invoice</th><th>Tanggal</th><th>Pasien</th><th>Metode</th><th>Status</th><th class="right">Nilai</th></tr>
        @elseif ($type === 'purchases')
            <tr><th>Faktur</th><th>Tanggal</th><th>Supplier</th><th>Input Oleh</th><th class="right">Nilai</th></tr>
        @else
            <tr><th>No. Kunjungan</th><th>Tanggal</th><th>Pasien</th><th>Dokter</th><th>Status</th><th>Diagnosa</th></tr>
        @endif
        </thead>
        <tbody>
        @forelse ($rows as $row)
            @if ($type === 'sales')
                <tr><td>{{ $row->invoice_number }}</td><td>{{ optional($row->created_at)->format('d/m/Y H:i') }}</td><td>{{ $row->patient->name ?? 'Umum' }}</td><td>{{ $row->payment_method }}</td><td>{{ $row->status }}</td><td class="right">Rp {{ number_format($row->final_amount, 0, ',', '.') }}</td></tr>
            @elseif ($type === 'purchases')
                <tr><td>{{ $row->invoice_number }}</td><td>{{ $row->invoice_date }}</td><td>{{ $row->supplier->name ?? '-' }}</td><td>{{ $row->creator->name ?? '-' }}</td><td class="right">Rp {{ number_format($row->grand_total, 0, ',', '.') }}</td></tr>
            @else
                <tr><td>{{ $row->visit_number }}</td><td>{{ optional($row->created_at)->format('d/m/Y H:i') }}</td><td>{{ $row->patient->name ?? '-' }}</td><td>{{ $row->doctor->name ?? ($row->rme->doctor->name ?? '-') }}</td><td>{{ $row->status }}</td><td>{{ $row->rme->assessment ?? '-' }}</td></tr>
            @endif
        @empty
            <tr><td colspan="6">Tidak ada data pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="footer">Dicetak {{ now()->format('d/m/Y H:i') }} · {{ $brand['name'] }} · {{ $brand['address'] }}</div>
</body>
</html>
