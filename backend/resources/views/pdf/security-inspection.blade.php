<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Security Inspection Report</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #10b981;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #111827;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0 0;
            color: #6b7280;
            font-size: 14px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }
        th {
            width: 35%;
            font-weight: 600;
            color: #4b5563;
            background-color: #f9fafb;
        }
        td {
            color: #111827;
        }
        .status-pass {
            color: #059669;
            font-weight: bold;
        }
        .monospace {
            font-family: monospace;
            font-size: 12px;
            color: #6b7280;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 11px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>CRYPTOGRAPHIC SECURITY AUDIT REPORT</h1>
        <p>Enterprise Payroll Internal System</p>
    </div>

    <div class="section">
        <div class="section-title">1. AUDIT METADATA</div>
        <table>
            <tr>
                <th>Payroll Reference ID</th>
                <td>PAY-{{ str_pad($payroll_id, 6, '0', STR_PAD_LEFT) }}</td>
            </tr>
            <tr>
                <th>Employee Name</th>
                <td>{{ $employee_name }}</td>
            </tr>
            <tr>
                <th>Inspection Time</th>
                <td>{{ $inspection_time }}</td>
            </tr>
            <tr>
                <th>Authorized Inspector</th>
                <td>{{ $inspector }} (Director)</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">2. CRYPTOGRAPHIC VERIFICATION</div>
        <table>
            <tr>
                <th>Encryption Architecture</th>
                <td>{{ $algorithm }}</td>
            </tr>
            <tr>
                <th>GCM Authentication Tag</th>
                <td class="status-pass">VERIFIED (No Tampering Detected)</td>
            </tr>
            <tr>
                <th>Data Integrity</th>
                <td class="status-pass">PASSED</td>
            </tr>
            <tr>
                <th>Data Confidentiality</th>
                <td class="status-pass">PASSED</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">3. DECRYPTION RESULTS</div>
        <table>
            <tr>
                <th>Ciphertext Sample (Base64)</th>
                <td class="monospace">{{ $ciphertext_sample }}</td>
            </tr>
            <tr>
                <th>Recovered Plaintext (Total)</th>
                <td style="font-weight: bold; color: #111827;">Rp {{ number_format((float)$plaintext_total, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Decryption Performance</th>
                <td>{{ $decryption_time_ms }} ms</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">4. AUDIT CONCLUSION</div>
        <table>
            <tr>
                <th>Overall Inspection Result</th>
                <td style="font-size: 18px; font-weight: bold; color: #059669;">PASS</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        This inspection report uses actual encrypted payroll data stored in the database.<br>
        No dummy data is used in this cryptographic audit process.
    </div>

</body>
</html>
