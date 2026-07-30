<?php

use Illuminate\Support\Facades\Schedule;

// Jika tanggal efektif tiba, jabatan dan riwayat aktif diperbarui otomatis
// segera setelah hari baru dimulai.
Schedule::command('employees:sync-effective-jobs')->dailyAt('00:01')->withoutOverlapping();

// Pengajuan yang dijadwalkan akan dikirim ke Direktur pada tanggal rencananya.
// everyMinute memastikan pengajuan tetap diproses ketika scheduler baru menyala
// setelah pukul 00:01.
Schedule::command('mutation-requests:submit-scheduled')->everyMinute()->withoutOverlapping();
