@php
    $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $periodStart = ! empty($filters['start']) ? \Carbon\Carbon::parse($filters['start'])->format('d/m/Y') : '-';
    $periodEnd = ! empty($filters['end']) ? \Carbon\Carbon::parse($filters['end'])->format('d/m/Y') : '-';
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22px 26px; }
        * { box-sizing: border-box; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.4; }
        .header { border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { color: #0f766e; font-size: 9px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        h1 { font-size: 18px; line-height: 1.2; margin: 4px 0 3px; }
        .meta { color: #64748b; font-size: 8px; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 16px; }
        .summary td { width: 25%; border: 1px solid #dbe5e3; background: #f8fafc; padding: 9px 10px; vertical-align: top; }
        .summary .label { color: #64748b; font-size: 7px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; }
        .summary .value { color: #0f172a; font-size: 12px; font-weight: 700; margin-top: 3px; }
        .summary .net { color: #0f766e; }
        .section-title { color: #334155; font-size: 9px; font-weight: 700; margin: 4px 0 7px; }
        table.report { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report thead { display: table-header-group; }
        .report th { background: #0f766e; color: #fff; font-size: 7px; font-weight: 700; letter-spacing: .35px; padding: 7px 5px; text-align: left; text-transform: uppercase; }
        .report td { border-bottom: 1px solid #dbe5e3; padding: 7px 5px; vertical-align: top; }
        .report tbody tr:nth-child(even) { background: #f8fafc; }
        .report .num { text-align: right; white-space: nowrap; }
        .report .muted { color: #64748b; }
        .report .total { color: #0f172a; font-weight: 700; }
        .empty { border: 1px solid #dbe5e3; color: #64748b; padding: 16px; text-align: center; }
        .footer { border-top: 1px solid #dbe5e3; color: #64748b; font-size: 7px; margin-top: 14px; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $companyName }}</div>
        <h1>Laporan Pembayaran Payroll</h1>
        <div class="meta">Periode pembayaran {{ $periodStart }} s.d. {{ $periodEnd }} &nbsp;|&nbsp; Status: Sudah Dibayarkan &nbsp;|&nbsp; Dibuat {{ $generatedAt->format('d/m/Y H:i') }}</div>
    </div>

    <table class="summary">
        <tr>
            <td><div class="label">Jumlah Payroll</div><div class="value">{{ (int) ($summary['count'] ?? 0) }} pegawai</div></td>
            <td><div class="label">Total Gaji Pokok</div><div class="value">{{ $rupiah($summary['sum_gaji_pokok'] ?? 0) }}</div></td>
            <td><div class="label">Total Tunjangan</div><div class="value">{{ $rupiah($summary['sum_tunjangan'] ?? 0) }}</div></td>
            <td><div class="label">Total Dibayarkan</div><div class="value net">{{ $rupiah($summary['sum_total'] ?? 0) }}</div></td>
        </tr>
    </table>

    <div class="section-title">Rincian Pembayaran Pegawai</div>
    @if(count($rows) > 0)
        <table class="report">
            <thead>
                <tr>
                    <th style="width: 4%;">No</th>
                    <th style="width: 13%;">ID Pegawai</th>
                    <th style="width: 20%;">Nama Pegawai</th>
                    <th style="width: 16%;">Jabatan</th>
                    <th class="num" style="width: 12%;">Gaji Pokok</th>
                    <th class="num" style="width: 12%;">Tunjangan</th>
                    <th class="num" style="width: 11%;">Potongan</th>
                    <th class="num" style="width: 12%;">Total Dibayarkan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="muted">{{ $row['employee_code'] ?? '-' }}</td>
                        <td>{{ $row['employee_name'] ?? '-' }}</td>
                        <td class="muted">{{ $row['position_name'] ?? 'Belum ditentukan' }}</td>
                        <td class="num">{{ $rupiah($row['gaji_pokok'] ?? 0) }}</td>
                        <td class="num">{{ $rupiah($row['tunjangan'] ?? 0) }}</td>
                        <td class="num">{{ $rupiah($row['potongan'] ?? 0) }}</td>
                        <td class="num total">{{ $rupiah($row['total'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">Tidak ada payroll berstatus sudah dibayarkan pada periode ini.</div>
    @endif

    <div class="footer">Dokumen ini dibuat oleh sistem payroll. Hanya memuat data penting untuk pelaporan pembayaran.</div>
</body>
</html>
