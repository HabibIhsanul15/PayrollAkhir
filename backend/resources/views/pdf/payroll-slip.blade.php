<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Slip Gaji</title>
  <style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #000; margin: 0; padding: 0; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 4px; vertical-align: top; }
    .header-table td { padding: 2px; }
    .title { font-size: 14px; font-weight: bold; }
    .company-name { font-size: 14px; font-weight: bold; margin-bottom: 5px; color: #1a4f8b; }
    .company-address { font-size: 9px; line-height: 1.2; }
    .line-thin { border-top: 1px solid #000; margin: 5px 0; }
    .line-thick { border-top: 2px solid #000; margin: 5px 0; }
    
    .data-table { border-collapse: collapse; width: 100%; font-size: 9px; margin-top: 10px; }
    .data-table th { 
      background-color: #b4c6e7; 
      border-bottom: 1px solid #000; 
      border-top: 1px solid #000; 
      font-weight: bold; 
      text-align: center;
      padding: 4px;
    }
    .data-table td { padding: 4px; }
    
    .right { text-align: right; }
    .center { text-align: center; }
    .bold { font-weight: bold; }

    .bg-blue-light { background-color: #b4c6e7; }
    .bg-blue-dark { background-color: #1f4e79; color: #fff; }

    .watermark {
      position: absolute;
      top: 30%;
      left: 20%;
      font-size: 80px;
      color: rgba(200, 200, 200, 0.2);
      transform: rotate(-30deg);
      z-index: -1;
      font-weight: bold;
    }
    
    .bottom-section { margin-top: 20px; font-size: 9px; }
  </style>
</head>
<body>

  @php
    $rupiah = fn($n) => number_format((float)($n ?? 0), 0, ',', '.');
    $unit = function ($n) {
        if ($n === null || $n === '' || $n === '-') return '-';
        $num = (float) $n;
        if (floor($num) == $num) return number_format($num, 0, ',', '.');
        return rtrim(rtrim(number_format($num, 2, ',', '.'), '0'), ',');
    };
    
    $periodStr = optional($payroll->periode)->format('F Y');
    if ($payroll->period_from && $payroll->period_to) {
        $from = \Carbon\Carbon::parse($payroll->period_from);
        $to = \Carbon\Carbon::parse($payroll->period_to);
        if ($from->month == $to->month) {
            $periodStr = $from->format('d') . ' sd ' . $to->format('d F Y');
        } else {
            $periodStr = $from->format('d F') . ' sd ' . $to->format('d F Y');
        }
    }

    $emp = $payroll->employee;
    
    $incomes = [];
    $incomes[] = [
        'name' => 'FIXED RATE',
        'mandays' => '-',
        'rate' => '-',
        'amount' => $payroll->gaji_pokok,
        'is_subrow' => false
    ];
    
    // Fetch actual allowances from payroll
    $actualAllowances = isset($payroll->allowances) ? collect($payroll->allowances) : collect();
    
    foreach ($actualAllowances as $al) {
        $type = $al->allowanceType;
        $calcDetail = $al->calculation_detail;
        if (is_string($calcDetail)) $calcDetail = json_decode($calcDetail, true);
        
        $hasSegments = isset($calcDetail['segments']) && count($calcDetail['segments']) > 0;
        
        // Generate details label string (Harian, Bulanan, dll)
        $defaultTypeLabel = ' (Bulanan)';
        $nameLower = strtolower($type ? $type->name : $al->allowance_type);
        if (str_contains($nameLower, 'trip') || str_contains($nameLower, 'perjalanan dinas')) {
            $defaultTypeLabel = ' (Per Perjalanan Dinas)';
        } else if (str_contains($nameLower, 'harian') || str_contains($nameLower, 'makan')) {
            $defaultTypeLabel = ' (Harian)';
        }

        $typeLabel = $defaultTypeLabel;
        $isTrip = isset($calcDetail['num_trips']);
        $hasDaily = ($al->mandays > 0 || isset($calcDetail['total_mandays']) || isset($calcDetail['mandays_ho_wfo']) || isset($calcDetail['wfo_days']) || isset($calcDetail['mandays_ho_wfh']) || isset($calcDetail['wfh_days']) || isset($calcDetail['mandays_outside_city']) || isset($calcDetail['out_of_town_days']));
        
        if ($isTrip) {
            $typeLabel = ' (Per Perjalanan Dinas)';
        } else if ($hasDaily) {
            $typeLabel = ' (Harian)';
        }

        $typeName = strtoupper($type ? $type->name : $al->allowance_type);

        $incomes[] = [
            'name' => $typeName . $typeLabel,
            'mandays' => (!$hasSegments && $al->mandays > 0) ? $unit($al->mandays) : '-',
            'rate' => (!$hasSegments && $al->rate_amount > 0) ? $rupiah($al->rate_amount) : '-',
            'amount' => $al->amount,
            'is_subrow' => false
        ];
        
        if ($hasSegments) {
            foreach ($calcDetail['segments'] as $seg) {
                $incomes[] = [
                    'name' => '   - ' . ($seg['grade'] ?? 'Jabatan'),
                    'mandays' => (isset($seg['mandays']) && $seg['mandays'] > 0) ? $unit($seg['mandays']) : '-',
                    'rate' => (isset($seg['rate']) && $seg['rate'] > 0) ? $rupiah($seg['rate']) : '-',
                    'amount' => $seg['amount'],
                    'is_subrow' => true
                ];
            }
        }
    }
    
    $actualDeductions = isset($payroll->deductions) ? collect($payroll->deductions)->filter(function($d) { return $d->amount > 0; }) : collect();
    
    $deductions = [];
    foreach ($actualDeductions as $dd) {
        $deductions[] = [
            'name' => strtoupper($dd->deduction_label),
            'amount' => $dd->amount,
            'is_subrow' => false
        ];
    }
    
    $rowCount = max(count($incomes), count($deductions));
    if ($rowCount == 0) $rowCount = 1;

    $paidAt = $payroll->paid_at ? \Carbon\Carbon::parse($payroll->paid_at)->format('d F Y') : null;
  @endphp

  @if(strtolower($payroll->status) === 'paid')
    <div class="watermark">PAID</div>
  @endif

  <table class="header-table" style="margin-bottom: 20px;">
    <tr>
      <td style="width: 60%;">
        @php
            $logoPath = public_path('logo.png');
            if (file_exists($logoPath)) {
                $logoData = base64_encode(file_get_contents($logoPath));
                $logoSrc = 'data:image/png;base64,' . $logoData;
            } else {
                $logoSrc = '';
            }
        @endphp
        @if($logoSrc)
            <img src="{{ $logoSrc }}" style="max-height: 28px; margin-bottom: 2px;" alt="Logo">
        @else
            <div class="company-name">PT. HUMAN PLUS INSTITUTE</div>
        @endif
        <div class="company-address">
          Jl. H.R. Rasuna Said X-5, Cyber 2 Tower, 18th floor, RT.7/RW.2, Kuningan<br>
          Timur, RT.7/RW.2, Kuningan, East Kuningan, Setiabudi, Jakarta, 12950
        </div>
      </td>
      <td style="width: 40%; text-align: right; vertical-align: bottom;">
        <div class="title">SLIP GAJI</div>
        <div style="font-size: 9px; font-weight: bold;">Periode {{ $periodStr }}</div>
      </td>
    </tr>
  </table>

  <div class="line-thin"></div>

  <table style="font-size: 9px;">
    <tr>
      <td style="width: 10%;">NIK</td>
      <td style="width: 2%;">:</td>
      <td style="width: 38%;" class="bold">{{ $emp?->employee_code ?? '-' }}</td>
      <td style="width: 15%;">Grade</td>
      <td style="width: 2%;">:</td>
      <td style="width: 33%;">{{ $emp?->grade?->name ?? '-' }}</td>
    </tr>
    <tr>
      <td>Nama</td>
      <td>:</td>
      <td class="bold">{{ strtoupper($emp?->name ?? '-') }}</td>
      <td>Golongan</td>
      <td>:</td>
      <td>{{ $emp?->grade?->code ?? '0' }}</td>
    </tr>
    <tr>
      <td>Jabatan</td>
      <td>:</td>
      <td>{{ $emp?->position ?? '-' }}</td>
      <td>Kategori</td>
      <td>:</td>
      <td>{{ strtoupper($emp?->workBasis?->name ?? '-') }}</td>
    </tr>
  </table>

  <div class="line-thick"></div>

  <table class="data-table">
    <thead>
      <tr>
        <th style="text-align: left; width: 28%;">Penerimaan :</th>
        <th style="width: 10%;">Mandays</th>
        <th style="width: 12%;">Rate</th>
        <th style="width: 15%; text-align: right;">Jumlah</th>
        <th style="text-align: left; width: 20%; padding-left: 10px;">Potongan</th>
        <th style="width: 15%; text-align: right;">Jumlah</th>
      </tr>
    </thead>
    <tbody>
      @for($i = 0; $i < $rowCount; $i++)
        @php
          $inc = $i < count($incomes) ? $incomes[$i] : null;
          $ded = $i < count($deductions) ? $deductions[$i] : null;
        @endphp
        <tr>
          <td style="{{ $inc && $inc['is_subrow'] ? 'padding-left: 15px; font-style: italic; color: #555;' : '' }}">{{ $inc ? (!$inc['is_subrow'] ? '- ' : '') . $inc['name'] : '' }}</td>
          <td class="center" style="{{ $inc && $inc['is_subrow'] ? 'font-style: italic; color: #555;' : '' }}">{{ $inc ? $inc['mandays'] : '' }}</td>
          <td class="right" style="{{ $inc && $inc['is_subrow'] ? 'font-style: italic; color: #555;' : '' }}">{{ $inc && $inc['rate'] !== '-' ? 'Rp ' . $inc['rate'] : ($inc ? '-' : '') }}</td>
          <td class="right" style="{{ $inc && $inc['is_subrow'] ? 'font-style: italic; color: #555;' : '' }}">{{ $inc ? ($inc['amount'] > 0 ? $rupiah($inc['amount']) : '-') : '' }}</td>
          
          <td style="padding-left: 10px;">{{ $ded ? '- '.$ded['name'] : '' }}</td>
          <td class="right">{{ $ded ? ($ded['amount'] > 0 ? $rupiah($ded['amount']) : '-') : '' }}</td>
        </tr>
      @endfor
      
      <!-- Footer Totals -->
      <tr class="bg-blue-light bold" style="border-top: 1px solid #000;">
        <td colspan="3">Total Gaji Bruto</td>
        <td class="right">{{ $rupiah($payroll->gaji_pokok + $payroll->total_allowances) }}</td>
        <td style="padding-left: 10px;">Total Potongan</td>
        <td class="right">{{ $rupiah($payroll->total_deductions) }}</td>
      </tr>
      <tr class="bg-blue-dark bold">
        <td colspan="3">Take Home Pay</td>
        <td class="right">{{ $rupiah($payroll->total) }}</td>
        <td colspan="2" style="background-color: #fff; color: #000; font-size: 8px;">*)</td>
      </tr>
    </tbody>
  </table>

  <table class="bottom-section" style="width: 100%;">
    <tr>
      <td style="width: 60%; vertical-align: top;">
        <div style="font-style: italic; font-weight: bold;">Pembayaran gaji telah ditransfer Ke :</div>
        <table style="width: 100%; margin-top: 5px;">
          <tr>
            <td style="width: 15%;" class="bold">No. Rek. {{ ($canSeeBank ?? false) ? ($emp?->bank_name ?: '-') : '-' }}</td>
            <td style="width: 25%;" class="bold">{{ ($canSeeBank ?? false) ? ($emp?->bank_account_number_decrypted ?: '-') : '-' }}</td>
            <td style="width: 10%;">a.n</td>
            <td style="width: 50%;">{{ ($canSeeBank ?? false) ? strtoupper($emp?->bank_account_name ?: '-') : '-' }}</td>
          </tr>
          <tr>
            <td class="bold">No. Ref.</td>
            <td class="bold">{{ $payroll->paid_ref ?: '-' }}</td>
            <td>Tgl</td>
            <td>{{ $paidAt ?: '-' }}</td>
          </tr>
        </table>
      </td>
      <td style="width: 40%; vertical-align: top; text-align: right;">
        <table style="width: 100%;">
          <tr>
            <td style="width: 100%; text-align: right;">Diterima Oleh :</td>
          </tr>
          <tr>
            <td style="text-align: right;" class="bold">{{ $paidAt ?: optional($payroll->periode)->format('d F Y') }}</td>
          </tr>
          <tr>
            <td style="height: 40px;"></td>
          </tr>
          <tr>
            <td style="text-align: right;" class="bold">{{ strtoupper($emp?->name ?? '-') }}</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

</body>
</html>
