<?php

use Illuminate\Support\Facades\Schedule;

// Jika tanggal efektif tiba, jabatan dan riwayat aktif diperbarui otomatis
// segera setelah hari baru dimulai.
Schedule::command('employees:sync-effective-jobs')->dailyAt('00:01')->withoutOverlapping();
