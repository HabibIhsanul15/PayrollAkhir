# Alur Master Karyawan dan Payroll

Dokumen ini menjelaskan alasan desain, hubungan tabel, dan urutan proses. Bacalah dari atas ke bawah sebelum membaca controller satu per satu.

## 1. Gambaran Besar

```text
Master Jabatan
      |
      +---- Gaji pokok default
      |
      +---- Tarif Tunjangan Jabatan ---- Jenis Tunjangan
      |                                |
      v                                v
Data Karyawan ---> Profil Gaji ---> Rekap Bulanan ---> Mesin Payroll
      |                 |                                     |
      v                 v                                     v
Riwayat Jabatan   Data berdasarkan tanggal              Payroll terenkripsi
```

Prinsip utamanya:

1. Jabatan tidak diketik bebas. Karyawan menunjuk satu data Master Jabatan.
2. Master Jabatan menentukan struktur jabatan, sedangkan nominal gaji harian jabatan diisi oleh Finance.
3. Nama jabatan harus unik. Kode jabatan dibuat otomatis dari nama jabatan dan tetap disimpan sebagai kode sistem internal.
4. Untuk batasan TA, form Master Jabatan dibuat sederhana: identitas jabatan, level, status, dan nominal gaji harian default.
5. Nominal individual disimpan dalam profil gaji berdasarkan tanggal efektif.
6. Tarif master dipilih berdasarkan jabatan, jenis tunjangan, dan tanggal.
7. Data karyawan hanya menampilkan kondisi personal yang memang dibutuhkan di flow awal payroll, misalnya `join_date`, `is_trainer`, dan `num_toddlers`.
8. Hasil payroll disimpan sebagai ciphertext, bukan nominal plaintext.
9. Setiap hasil payroll menyimpan breakdown agar perhitungannya dapat ditelusuri.
10. Status karyawan otomatis `active` saat data awal dibuat, lalu baru diubah bila ada kebutuhan nonaktif.

## 2. Tabel dan Tanggung Jawabnya

| Tabel | Isi | Kunci hubungan |
|---|---|---|
| `employees` | Identitas dan referensi jabatan aktif | `grade_id` |
| `grades` | Master Jabatan dan level hierarki | `id` |
| `allowance_types` | Cara menghitung suatu tunjangan | `id` |
| `grade_allowance_rates` | Tarif tunjangan per jabatan dan periode | `grade_id`, `allowance_type_id` |
| `salary_profiles` | Snapshot jabatan dan nominal individual | `employee_id`, `effective_from` |
| `job_histories` | Riwayat perubahan jabatan | `employee_id`, `start_date` |
| `monthly_recaps` | Aktivitas bulanan untuk dasar hitung | `employee_id`, `salary_profile_id` |
| `payrolls` | Total hasil payroll terenkripsi | `employee_id`, `periode` |
| `payroll_allowances` | Rincian tunjangan hasil perhitungan | `payroll_id`, `allowance_type_id` |

`employees.position` masih dipertahankan sebagai snapshot nama jabatan untuk tampilan cepat. Sumber keputusan tetap `grade_id` dan `salary_profiles.grade_id`.

Untuk TA ini, penentuan gaji pokok dipusatkan ke data Jabatan dan dibuat sederhana:

```text
Grade / Jabatan
   |
   +---- base_salary_basis = daily
   |
   +---- default_base_salary_amount = nominal harian
```

Artinya halaman karyawan tidak lagi menentukan basis gaji pokok. Semua gaji pokok jabatan dihitung sebagai nominal harian. Struktur jabatan dibuat oleh HCGA, sedangkan nominal hariannya diisi Finance. Pengaturan komponen yang bersifat harian, bulanan, trip, atau formula ditempatkan pada `Jenis Tunjangan` dan `Tarif Tunjangan Jabatan`.

Isi bisnis Master Jabatan:

1. Nama jabatan.
2. Level hierarki.
3. Kode sistem internal.
4. Status aktif.
5. Nominal gaji pokok harian default.

Pembagian pengelolaannya:

1. HCGA mengelola nama jabatan, level, kode sistem, keterangan, dan status aktif.
2. Finance mengelola nominal gaji pokok harian pada jabatan yang sudah dibuat HCGA.

Batasan yang sengaja dipilih:

1. Master Jabatan tidak menampung semua komponen upah sebagai field.
2. Detail tunjangan tetap dikelola lewat `Jenis Tunjangan` dan `Tarif Tunjangan Jabatan`.
3. Kode jabatan dipakai untuk kebutuhan sistem, otomatis mengikuti nama jabatan, dan bukan menjadi fokus utama operator.
4. Basis gaji pokok jabatan dikunci harian agar scope TA tidak melebar.

## 3. Cara Membaca Jenis Tunjangan

Indikator rekap bulanan dibuat baku agar data kehadiran mudah divalidasi. Contoh indikator yang dipakai sistem:

1. `total_mandays` untuk total hari dibayar.
2. `wfo_days` untuk hari WFO.
3. `wfh_days` untuk hari WFH.
4. `out_of_town_days` untuk hari luar kota.
5. `training_days` untuk hari training.
6. `business_trips` untuk jumlah perjalanan dinas.
7. `overtime_hours` untuk jumlah jam lembur.

Finance tidak membuat indikator baru dari halaman `Jenis Tunjangan`. Finance hanya memilih indikator rekap mana yang menjadi dasar perhitungan tunjangan.

Jika tunjangan dibayar untuk semua bentuk hari kerja yang diakui payroll, gunakan `total_mandays`. Nilai ini merupakan gabungan hari WFO, WFH, luar kota, dan training. Jika tunjangan hanya untuk aktivitas tertentu, gunakan indikator khusus seperti `wfo_days`, `out_of_town_days`, `training_days`, atau `overtime_hours`.

Contoh Tunjangan Makan:

```text
calculation_type = per_mandays
input_source     = total_mandays
condition        = tidak ada
```

Rumusnya:

```text
jumlah tunjangan = tarif jabatan x total_mandays
```

Contoh Tunjangan Bulanan Tetap:

```text
calculation_type = flat
input_source     = kosong
```

Rumusnya:

```text
jumlah tunjangan = tarif jabatan
```

Tunjangan bulanan tetap diberikan jika jabatan karyawan memiliki tarif aktif untuk jenis tunjangan tersebut. Jika dalam satu bulan ada promosi/demosi, sistem membagi nominal mengikuti proporsi segmen rekap yang dibuat HCGA.

Contoh Tunjangan Lembur:

```text
calculation_type = per_mandays
input_source     = overtime_hours
```

Rumusnya:

```text
jumlah tunjangan = tarif lembur per jam x overtime_hours
```

Contoh Tunjangan Training:

```text
calculation_type   = formula
input_source       = training_days
condition_field    = is_trainer
condition_operator = =
condition_value    = 1
rate_multiplier    = 1.5
```

Rumusnya:

```text
jumlah tunjangan = basis gaji pokok x 1.5 x training_days
```

Nilai `requires_condition` dan `rate_formula` pada tarif hanya merupakan keterangan manusia. Keputusan program memakai field kondisi yang terstruktur pada `allowance_types`.

Penting: hak menerima tunjangan sekarang ditentukan oleh kombinasi:

1. Jabatan punya tarif aktif untuk tunjangan tersebut.
2. Kondisi karyawan terpenuhi, misalnya `is_trainer = 1`.
3. Rekap bulanan menyediakan angka aktivitas jika tunjangan itu berbasis harian/trip.

Field lama seperti `Berlaku Untuk: Project Partner/Fix Rate/HO` tidak dipakai sebagai penentu payroll karena master karyawan sudah disederhanakan. Secara konsep, jenis tunjangan berlaku umum, lalu yang benar-benar dihitung adalah tunjangan yang memiliki tarif aktif pada jabatan karyawan.

## 4. Pemilihan Tarif Berdasarkan Tanggal

Satu kombinasi Jabatan dan Jenis Tunjangan boleh mempunyai beberapa versi tarif, tetapi periodenya tidak boleh bertumpang tindih.

```text
Staff + Tunjangan Makan

01-01-2026 sampai 30-06-2026 = Rp20.000
01-07-2026 dan seterusnya    = Rp25.000
```

Saat menghitung payroll Juli 2026, `AllowanceRateResolver` memilih Rp25.000 karena:

```text
effective_from <= tanggal hitung
dan
effective_to kosong atau effective_to >= tanggal hitung
dan
is_active = true
```

File utama: `backend/app/Services/AllowanceRateResolver.php`.

## 5. Flow Membuat Karyawan

```text
HCGA memilih Jabatan
        |
        v
Backend menentukan:
- status = active
- join_date sebagai acuan awal histori
- jabatan aktif sebagai acuan profil gaji
- nominal default dibaca backend dari master Finance
        |
        v
Simpan employees dalam transaction
        |
        +---- buat salary_profiles awal
        |
        +---- buat job_histories awal
        |
        +---- opsional membuat akun user
```

Transaction berarti semua langkah berhasil bersama-sama. Jika salah satu gagal, tidak ada user atau data karyawan setengah jadi yang tertinggal.

Data NIK, NPWP, telepon, alamat, nomor rekening, dan nominal profil disimpan pada kolom `*_enc`. Kolom plaintext dikosongkan setelah ciphertext tersedia.

Catatan desain form:

1. `department` disembunyikan dari form master karyawan karena bukan penentu inti payroll.
2. `status` tidak ditampilkan sebagai keputusan awal, karena karyawan baru logisnya langsung aktif.
3. `join_date` ditampilkan karena penting untuk menjelaskan kapan histori jabatan dan profil gaji mulai berlaku.
4. Basis gaji pokok tidak diedit dari form karyawan. Untuk batasan TA, gaji pokok jabatan dibuat harian dan nominalnya dikelola Finance di menu Master Jabatan.
5. Kondisi khusus payroll seperti `is_trainer` dan `num_toddlers` tetap bisa diisi di form karyawan, meskipun suatu jabatan belum memakai aturan tersebut saat ini.
6. Jabatan tidak diedit bebas dari form edit, tetapi mengikuti flow promosi atau demosi agar histori tetap konsisten.
7. `employment_type` dan `work_basis` tidak lagi menjadi penentu utama payroll. Keduanya hanya tersisa sebagai data legacy.
8. Flag probation promosi tidak dimasukkan ke form awal karyawan agar scope master data tetap sederhana. Jika nanti dipakai, lebih tepat masuk ke flow promosi atau demosi.
9. `NIK`, `NPWP`, nomor telepon, dan nomor rekening dibatasi angka saja agar validasi form dan data terenkripsi tetap konsisten.
10. Saat membuat akun login dari form karyawan, email dan password dimulai kosong. Operator bisa isi manual, melihat password, atau menggunakan generator password.

File utama: `backend/app/Http/Controllers/Api/EmployeeController.php`.

## 5A. Status Rekap Bulanan

Rekap bulanan dibuat oleh HCGA dengan dua status sederhana:

1. `Draft`: masih bisa diedit atau dihapus oleh HCGA.
2. `Terkirim ke Finance`: sudah dikunci dan menjadi dasar proses payroll.

Kolom `is_finalized` di database dipakai sebagai penanda bahwa rekap sudah dikirim ke Finance. Nama teknis ini tetap dipertahankan agar tidak perlu mengubah banyak relasi lama, tetapi istilah di tampilan dibuat lebih mudah dipahami.

Indikator yang dicatat pada rekap:

1. Hari WFO.
2. Hari WFH.
3. Hari luar kota.
4. Hari training.
5. Jumlah perjalanan dinas.
6. Jam lembur.
7. Jumlah terlambat (`late_count`).

Tabel utama tidak menampilkan semua indikator sebagai kolom terpisah agar tidak terlalu lebar. Detail lengkap tetap ada di modal input/edit, sedangkan tabel menampilkan ringkasan kehadiran dan indikator tambahan.

## 6. Flow Promosi atau Demosi

Contoh: Staff dipromosikan menjadi Manager pada 1 Agustus 2026.

Aturan level:

- `level 1` adalah jabatan tertinggi.
- `Promosi` hanya boleh memilih jabatan dengan angka level lebih kecil dari level saat ini.
- `Demosi` hanya boleh memilih jabatan dengan angka level lebih besar dari level saat ini.

Pada 12 Juli 2026:

```text
employees.grade_id             = Staff
salary_profiles 01-01-2026     = Staff
salary_profiles 01-08-2026     = Manager
```

Jabatan aktif tidak langsung berubah ketika perubahan masa depan disimpan. Pada tanggal 1 Agustus, command berikut menyinkronkan referensi aktif:

```bash
php artisan employees:sync-effective-jobs
```

Scheduler menjalankannya setiap hari pukul 00:05. Perhitungan payroll tetap membaca profil berdasarkan tanggal sehingga histori lama tidak berubah.

File utama:

- `backend/app/Http/Controllers/Api/MutationController.php`
- `backend/app/Console/Commands/SyncEffectiveEmployeeJobs.php`

## 7. Contoh Hitung Sederhana

Data Staff untuk Juli:

```text
Tarif harian         = Rp100.000
Total mandays        = 10 hari
Tunjangan jabatan    = Rp1.200.000
Tunjangan makan      = Rp25.000 per hari
```

Perhitungan:

```text
Gaji pokok           = 100.000 x 10 = 1.000.000
Tunjangan jabatan    = 1.200.000
Tunjangan makan      = 25.000 x 10 = 250.000
Total tunjangan      = 1.450.000
Total diterima       = 1.000.000 + 1.450.000 = 2.450.000
```

File utama: `backend/app/Services/PayrollCalculationService.php`.

Aturan dasar gaji pokok:

```text
Jika base_salary_basis = daily
    gaji pokok = nominal default x total_mandays

Jika base_salary_basis = monthly
    gaji pokok = nominal default bulanan
```

Jika dalam satu bulan ada promosi/demosi sehingga salary profile berubah, nominal bulanan dibagi prorata berdasarkan segmen rekap yang tersedia.

## 8. Enkripsi Payroll

`PayrollCipherService` menerima nilai yang masih berada di memory, lalu menghasilkan ciphertext sesuai konfigurasi:

```text
AES     -> setiap field dienkripsi AES-128-GCM
RSA     -> setiap field dienkripsi RSA-2048
HYBRID  -> field dienkripsi AES-128-GCM dengan DEK,
           kemudian DEK dibungkus RSA-2048
```

Kolom `gaji_pokok`, `tunjangan`, `potongan`, dan `total` disimpan `NULL`. Nilainya dibaca dari `*_enc` hanya setelah pengguna lolos pemeriksaan hak akses.

File utama:

- `backend/app/Services/PayrollCipherService.php`
- `backend/app/Services/CryptoService.php`

## 9. Pembagian Hak Akses

| Peran | Data karyawan | Master jabatan | Nominal dan tunjangan |
|---|---|---|---|
| HCGA | Kelola | Kelola struktur jabatan tanpa nominal | Tidak melihat/mengubah nominal payroll |
| FAT | Baca untuk kebutuhan payroll | Isi gaji harian jabatan | Kelola jenis tunjangan, tarif tunjangan, dan payroll |
| Director | Lihat | Tidak mengubah | Lihat dan menyetujui |
| Staff | Data sendiri | Tidak | Payroll sendiri setelah disetujui |

Backend tetap menjadi pengaman utama. Pembatasan menu frontend hanya membantu tampilan dan tidak boleh dianggap sebagai mekanisme keamanan.

## 10. Bukti Pengujian

Tes utama berada di:

```text
backend/tests/Feature/MasterPayrollFlowTest.php
```

Skenario yang diuji:

1. Tarif dipilih sesuai tanggal.
2. Versi tarif baru menutup periode tarif lama.
3. Tunjangan membaca jumlah aktivitas dari rekap bulanan.
4. Karyawan baru otomatis aktif dan memakai basis gaji dari master jabatan.
5. Gaji pokok bulanan dibayar sebagai nominal tetap per periode.
6. Promosi masa depan tidak langsung mengubah jabatan hari ini.
7. Auto payroll memakai master dan menyimpan nominal sebagai cipher-only.

Jalankan:

```bash
cd backend
php artisan test --filter=MasterPayrollFlowTest
```

## 11. Urutan Belajar untuk Sidang

1. Pahami hubungan tabel pada bagian 2.
2. Coba hitung manual contoh pada bagian 7.
3. Baca `AllowanceRateResolver` untuk memahami tarif efektif.
4. Baca `AllowanceCalculationService` untuk memahami jenis tunjangan.
5. Baca `PayrollCalculationService::runEngine()` untuk melihat penggabungan hasil.
6. Baca `PayrollCipherService` untuk memahami perubahan plaintext di memory menjadi ciphertext di database.
7. Jalankan tes satu per satu dan jelaskan alasan setiap assertion.

Kalimat ringkas yang dapat dipakai saat menjelaskan desain:

> Master menentukan aturan standar, salary profile menyimpan kondisi karyawan pada suatu periode, rekap bulanan menyediakan jumlah aktivitas, lalu payroll engine menghitung dan mengenkripsi hasilnya berdasarkan hak akses pengguna.
