# Dashboard Eksekutif — Portal Eksekutif & Operasional SIMRS Khanza

**Author / Pengembang:** Rahmadi Saputra ([@rahmadisaputra-it](https://github.com/rahmadisaputra-it) | `rahmadisaputra.it@gmail.com`)  
**Runtime Web:** PHP 8.0.8+ Native Modular MVC (PDO Prepared Statements)  
**Database Mandiri:** MySQL / MariaDB (`dashboard_eksekutif`)  
**Integrasi Khanza:** Arsitektur **Push-Only (Zero Inbound)** via `tambahan/KhanzaServicePushPermata.jar` (Desktop Java) & `api/khanza-push-worker.php`  
**Kustomisasi Instansi:** Fleksibel untuk rumah sakit / fasyankes mana saja melalui menu **Pengaturan Rumah Sakit** (Nama RS, Alamat, Kontak, Direktur, Kode PPK, & Upload Logo)

---

## Daftar Isi

1. [Gambaran Umum & Arsitektur](#1-gambaran-umum--arsitektur)
2. [Fitur Utama & Pengaturan Instansi Fleksibel](#2-fitur-utama--pengaturan-instansi-fleksibel)
3. [Struktur Direktori & Modul MVC](#3-struktur-direktori--modul-mvc)
4. [Daftar Modul, Menu & Rute Halaman](#4-daftar-modul-menu--rute-halaman)
5. [Autentikasi, RBAC & Keamanan](#5-autentikasi-rbac--keamanan)
6. [Skema Database Mandiri (`dashboard_eksekutif`)](#6-skema-database-mandiri-dashboard_eksekutif)
7. [Kontrak REST API Push-Only (`api/`)](#7-kontrak-rest-api-push-only-api)
8. [Cara Pemasangan & Pemakaian Service Push (`tambahan/`)](#8-cara-pemasangan--pemakaian-service-push-tambahan)
9. [Rumus & Aturan Bisnis per Modul](#9-rumus--aturan-bisnis-per-modul)
10. [Instalasi & Operasional](#10-instalasi--operasional)
11. [Lisensi & Hak Cipta](#11-lisensi--hak-cipta)

---

## 1. Gambaran Umum & Arsitektur

**Dashboard Eksekutif** adalah portal web eksekutif dan pemantauan operasional rumah sakit yang dirancang terpisah dari server produksi **SIMRS Khanza (`sik_fix` / `sik`)** menggunakan pola **Zero Inbound (Push-Only)**:

```text
+--------------------------------------------+       HTTPS / HTTP POST (JSON)        +------------------------------------------+
| SISI SERVER / WORKSTATION SIMRS KHANZA     |       Header: X-API-KEY               | SISI PORTAL WEB DASHBOARD EKSEKUTIF      |
|                                            |                                       |                                          |
|  [MySQL Khanza: sik_fix / sik]             |   1. /api/sync-kendali-biaya.php      |  [REST API Receivers: api/sync-*.php]    |
|     ├── kamar_inap, detail_pemberian_obat  |   2. /api/sync-tempat-tidur.php       |        │                                 |
|     ├── periksa_lab, periksa_radiologi     |   3. /api/sync-jadwal-hfis.php        |        ▼                                 |
|     ├── kamar, bangsal, jadwal             |   4. /api/sync-jam-poliklinik.php     |  [MySQL Mandiri: dashboard_eksekutif]    |
|     └── reg_periksa, pemeriksaan_ralan     | ────────────────────────────────────► |     + Mirror Fallback: storage/cache/    |
|        │                                   |                                       |        │                                 |
|        ▼                                   |                                       |        ▼                                 |
|  [KhanzaServicePushPermata.jar] (Java)     |                                       |  [Portal Web PHP 8.0.8 Modular MVC]      |
|  (atau api/khanza-push-worker.php)         |                                       |     ├── Auth (BCRYPT + CSRF)             |
|        │                                   |                                       |     ├── Keuangan (Kendali Biaya & Tarif) |
|        └── GET WS Antrean BPJS (HFIS)      |                                       |     ├── Operasional (HFIS, Poli, BOR)    |
|            /jadwaldokter/kodepoli/...      |                                       |     └── Administrasi (Akun & Profil RS)  |
+--------------------------------------------+                                       +------------------------------------------+
```

### Keunggulan Arsitektur Ini:
- **Zero Inbound ke Server Khanza:** Portal web tidak membutuhkan port MySQL Khanza dibuka ke luar. Server/workstation internal Khanza yang mendorong snapshot data secara berkala.
- **Fleksibel untuk Semua Rumah Sakit:** Dilengkapi menu **Pengaturan Rumah Sakit** agar setiap instansi bisa mengganti nama RS, alamat kop surat cetak, kota tanda tangan, kontak, email, kode PPK, nama direktur, serta mengunggah logo rumah sakit dan logo akreditasi tanpa menyentuh kode sumber.
- **Database Mandiri + Mirror Cache:** Seluruh tampilan portal web membaca dari database `dashboard_eksekutif` (otomatis dibuat oleh `App\Config\Setup`). Jika MySQL lokal sempat terganggu, model memiliki fallback pembacaan snapshot JSON di `storage/cache/`.
- **Otorisasi Granular Berbasis Modul:** Akun pengguna portal berdiri sendiri di tabel `dashboard_eksekutif.users` dengan kontrol akses per modul (`allowed_modules`).

---

## 2. Fitur Utama & Pengaturan Instansi Fleksibel

Melalui menu **Administrasi Sistem → Pengaturan Rumah Sakit** (`index.php?module=pengguna&page=pengaturan-rs`), Administrator dapat mengatur:
- **Judul Aplikasi & Nama Rumah Sakit:** Otomatis tampil di halaman Login, Sidebar, judul browser, footer, dan kop cetak laporan (contoh: `Dashboard Eksekutif — RS Umum Harapan Bunda`).
- **Alamat Lengkap, Kabupaten/Kota, Provinsi, Kontak, Email & Kode PPK BPJS:** Otomatis tercetak pada Kop Surat Laporan dan baris tanggal/kota penandatangan laporan.
- **Nama Direktur / Pimpinan RS:** Otomatis mengisi blok penandatangan di bagian bawah laporan cetak.
- **Upload Logo Rumah Sakit & Logo Akreditasi:** Mendukung format PNG, JPG, JPEG, SVG, dan WEBP (maks. 2 MB), lengkap dengan pratinjau langsung (*Live Preview*) Kop Surat Cetak.
- **Tarik Otomatis dari Tabel `setting` SIMRS Khanza:** Tombol cepat 1-klik untuk mengisi profil RS otomatis jika database Khanza terhubung di server yang sama.

---

## 3. Struktur Direktori & Modul MVC

```text
dashboard-eksekutif/
├── index.php                                   # Front Controller, Auth Guard, Revalidate & Module Dispatcher
├── README.md                                   # Dokumentasi utama ini
├── database_dashboard_eksekutif.sql            # Skema DDL & data awal database mandiri dashboard_eksekutif
├── tambahan/
│   ├── KhanzaServicePushPermata.jar            # Service desktop Java (masukkan ke folder SIMRS Khanza Anda)
│   └── KhanzaServicePushPermata.bat            # Launcher batch untuk menjalankan service di folder SIMRS Khanza
├── api/
│   ├── sync-kendali-biaya.php                  # Endpoint GET/POST Kendali Biaya Ranap + Master INA-CBG
│   ├── sync-tempat-tidur.php                   # Endpoint GET/POST Keterisian Tempat Tidur (BOR)
│   ├── sync-jadwal-hfis.php                    # Endpoint GET/POST Jadwal Dokter HFIS vs SIMRS
│   ├── sync-jam-poliklinik.php                 # Endpoint GET/POST Evaluasi Mundur Jam Mulai Poliklinik
│   └── khanza-push-worker.php                  # Alternatif worker CLI/HTTP berbasis PHP
├── app/
│   ├── Config/
│   │   ├── Database.php                        # Koneksi PDO (dashboard_eksekutif + opsional sik_fix) & API Key
│   │   └── Setup.php                           # Auto-DDL 9 tabel + seed Super Admin & Pengaturan RS awal
│   ├── Core/
│   │   ├── Auth.php                            # Session, RBAC (Auth::MODULES), revalidate(), CSRF Token
│   │   ├── Controller.php                      # Base Controller (renderModule, profil RS dinamis, redirect, json)
│   │   └── Helpers.php                         # e(), angka(), rupiah(), rupiah_singkat(), tanggal_indo()
│   ├── Views/layouts/
│   │   ├── app.php                             # Master layout (Sidebar + Main + kontrol $printable)
│   │   ├── sidebar.php                         # Navigasi kiri sesuai RBAC + dropdown akun
│   │   └── print_header.php                    # Kop surat cetak dinamis sesuai Pengaturan Rumah Sakit
│   └── Modules/
│       ├── Auth/                               # Login, Logout, Ganti Password Mandiri
│       ├── Keuangan/                           # Kendali Biaya Ranap & Tarif Paket INA-CBG
│       ├── Operasional/                        # Monitoring HFIS, Evaluasi Jam Poli, Tempat Tidur (BOR)
│       ├── Hfis/Models/JadwalHfisModel.php     # Model komparasi jadwal HFIS vs SIMRS
│       └── Pengguna/                           # Manajemen Akun, RBAC, Pengaturan RS, Status Sync & Log API
├── assets/
│   ├── css/
│   │   ├── permata-bevel.css                   # Stylesheet Clean Enterprise & @media print A4 Landscape
│   │   └── tailwind.cdn.js                     # Tailwind CSS lokal (fallback offline)
│   └── images/
│       ├── logo-default.svg                    # Logo vector default Dashboard Eksekutif
│       └── uploads/                            # Direktori simpan logo unggahan rumah sakit
└── storage/cache/                              # Mirror JSON snapshot (hfis, jam_poliklinik, kendali_biaya, tempat_tidur)
```

---

## 4. Daftar Modul, Menu & Rute Halaman

| Kelompok Sidebar | Menu / Halaman | Rute (`index.php?...`) | Controller & Action | Hak Akses | Cetak Laporan |
|---|---|---|---|---|---|
| *(Publik)* | Login Portal | `module=auth&page=login` | `AuthController::showLogin()` / `processLogin()` | Publik | Tidak |
| *(Akun)* | Ganti Password | `module=auth&page=ganti-password` | `AuthController::showChangePassword()` | Semua User Login | Tidak |
| *(Akun)* | Logout | `module=auth&page=logout` | `AuthController::logout()` | Semua User Login | Tidak |
| **Keuangan** | Kendali Biaya Ranap | `module=keuangan&page=kendali-biaya` | `KendaliBiayaController::index()` | Modul `keuangan` / `admin` | Ya |
| **Keuangan** | Tarif Paket INA-CBG | `module=keuangan&page=tarif-inacbg` | `KendaliBiayaController::tarifInacbg()` | Modul `keuangan` / `admin` | Tidak |
| **Operasional & Pelayanan** | Jadwal Dokter HFIS | `module=operasional&page=jadwal-hfis` | `OperasionalController::jadwalHfis()` | Modul `operasional` / `admin` | Ya |
| **Operasional & Pelayanan** | Jam Mulai Poliklinik | `module=operasional&page=jam-poliklinik` | `OperasionalController::jamPoliklinik()` | Modul `operasional` / `admin` | Ya |
| **Operasional & Pelayanan** | Keterisian Tempat Tidur | `module=operasional&page=tempat-tidur` | `OperasionalController::tempatTidur()` | Modul `operasional` / `admin` | Ya |
| **Administrasi Sistem** | Manajemen Akun & Akses | `module=pengguna&page=manajemen-akun` | `PenggunaController::index()` | Khusus `admin` | Tidak |
| **Administrasi Sistem** | Pengaturan Rumah Sakit | `module=pengguna&page=pengaturan-rs` | `PenggunaController::pengaturanRs()` | Khusus `admin` | Tidak |

---

## 5. Autentikasi, RBAC & Keamanan

1. **Hash Password Kuat (`PASSWORD_BCRYPT`):** Password pengguna disimpan dalam kolom `users.password_hash`. Proses login memakai `password_verify()` dengan `$dummyHash` untuk mencegah *timing attack / user enumeration*.
2. **Proteksi CSRF Menyeluruh:** Seluruh form `POST` dilindungi token CSRF (`Auth::csrfToken()` / `Auth::verifyCsrf()`).
3. **Revalidasi Sesi Real-Time (`Auth::revalidate()`):** Dipanggil di setiap request terproteksi (*fail-closed*). Perubahan role, hak akses modul, atau penonaktifan akun langsung berlaku seketika.
4. **Default-Deny Module Authorization:** Modul yang tidak terdaftar di `Auth::MODULES` otomatis ditolak.
5. **Prepared Statements Native:** Seluruh query PDO memakai `ATTR_EMULATE_PREPARES => false` untuk mencegah SQL Injection.

---

## 6. Skema Database Mandiri (`dashboard_eksekutif`)

Seluruh tabel dibuat otomatis saat aplikasi pertama kali diakses oleh `App\Config\Setup::ensureAppSchema()` (atau impor manual `database_dashboard_eksekutif.sql`):

| Nama Tabel | Fungsi Utama |
|---|---|
| `users` | Akun pengguna mandiri portal (`role`, `status`, `allowed_modules` JSON) |
| `app_settings` | Profil rumah sakit: nama RS, alamat, kabupaten/kota, provinsi, kontak, email, kode PPK, direktur, & path logo |
| `api_log` | Log audit terpusat setiap push API masuk dari service Khanza |
| `tarif_inacbg` | Referensi master tarif paket INA-CBG per kode penyakit ICD-10 |
| `kendali_biaya` | Snapshot pasien rawat inap aktif (8 komponen aktual, modal RS, tarif INA-CBG, rasio, status) |
| `tempat_tidur` | Snapshot kapasitas & status keterisian setiap kamar aktif (`ISI`/`KOSONG`) untuk BOR |
| `jadwal_hfis` | Snapshot komparasi jadwal dokter SIMRS vs HFIS BPJS beserta status sinkron |
| `sync_log` | Riwayat agregat persentase kepatuhan sinkronisasi HFIS |
| `jam_poliklinik` | Evaluasi historis waktu mulai poliklinik per tanggal pelayanan, selisih menit, dan `status_kepatuhan` |

---

## 7. Kontrak REST API Push-Only (`api/`)

Semua endpoint menerima metode `GET` (cek status & ringkasan terakhir) dan `POST` (simpan snapshot), dengan autentikasi header wajib:
- `X-API-KEY: <API_KEY>` *(default: `DASHBOARD-EKSEKUTIF-2026-KEY`, dapat diubah via environment variable `DASHBOARD_API_KEY`)*

| Endpoint | Payload Utama |
|---|---|
| `POST /api/sync-kendali-biaya.php` | Daftar pasien rawat inap aktif, 8 komponen biaya aktual, `manual_tarif`, dan master `tarif_inacbg` |
| `POST /api/sync-tempat-tidur.php` | Daftar kamar aktif (`kd_kamar`, `kd_bangsal`, `nm_bangsal`, `kelas`, `trf_kamar`, `status`) |
| `POST /api/sync-jadwal-hfis.php` | Daftar jadwal dokter SIMRS vs hasil komparasi WS Antrean BPJS (HFIS) |
| `POST /api/sync-jam-poliklinik.php` | Evaluasi jam mulai poliklinik (rentang tanggal mundur `H-7 s.d. H-1`) |

---

## 8. Cara Pemasangan & Pemakaian Service Push (`tambahan/`)

Di dalam repositori ini sudah disediakan hasil jadi service pengirim data di folder **`tambahan/`**:
- `tambahan/KhanzaServicePushPermata.jar`
- `tambahan/KhanzaServicePushPermata.bat`

### Langkah Pemasangan ke Folder SIMRS Khanza Anda:
1. **Salin (Copy) Kedua File Service:**
   Masukkan file **`KhanzaServicePushPermata.jar`** dan **`KhanzaServicePushPermata.bat`** dari folder `tambahan/` ke dalam **folder utama instalasi SIMRS Khanza yang Anda miliki** (folder tempat file `khanza.jar`, folder `lib/`, dan folder `setting/` berada — contoh: `D:\KhanzaHMSWindows_2026\`).
2. **Tambahkan Konfigurasi di `setting/database.xml` (di dalam folder SIMRS Khanza Anda):**
   Buka file `setting/database.xml` di folder SIMRS Khanza Anda, lalu tambahkan 3 baris berikut tepat sebelum tag penutup `</properties>`:
   ```xml
   <entry key="URL_DASHBOARD_EKSEKUTIF">http://localhost/dashboard-eksekutif</entry>
   <entry key="KEY_DASHBOARD_EKSEKUTIF">DASHBOARD-EKSEKUTIF-2026-KEY</entry>
   <entry key="INTERVAL_MENIT">15</entry>
   ```
   - Koneksi database Khanza (`HOST`, `PORT`, `DATABASE`, `USER`, `PAS`) dan API WS Antrean BPJS (`URLAPIMOBILEJKN`, `CONSIDAPIMOBILEJKN`, `SECRETKEYAPIMOBILEJKN`, `USERKEYAPIMOBILEJKN`) otomatis membaca dari konfigurasi standar yang sudah ada di `setting/database.xml` Anda.
   - Penambahan 3 baris tersebut **100% aman** dan tidak mengganggu jalannya aplikasi utama SIMRS Khanza.
3. **Jalankan Service Push:**
   - Klik ganda **`KhanzaServicePushPermata.bat`** dari dalam folder SIMRS Khanza Anda.
   - Pastikan **Target URL** sudah mengarah ke URL portal Dashboard Eksekutif Anda (misal `http://localhost/dashboard-eksekutif` atau alamat IP/domain server web).
   - Klik **"Push Semua Sekarang"** untuk sinkronisasi penuh 4 modul sekaligus, atau centang **Auto (15 Menit)** untuk pengiriman otomatis terjadwal.

### Alternatif: Worker CLI PHP (`api/khanza-push-worker.php`)
Apabila portal web berada di server yang sama dengan database SIMRS Khanza, Anda juga dapat menjadwalkan worker PHP lewat Cron / Windows Task Scheduler:
```bash
php api/khanza-push-worker.php
```

---

## 9. Rumus & Aturan Bisnis per Modul

### 9.1. Kendali Biaya Rawat Inap (`PerkiraanBiayaModel`)
- **Tarif INA-CBG:** Penjumlahan tarif dari `tarif_inacbg` berdasarkan kode ICD-10 (dipisah koma) pada `diagnosa_awal`; jika `0`, fallback ke `manual_tarif` (`perkiraan_biaya_ranap`).
- **Komponen Modal RS (Pasien Umum / Non-Bayi):** Jasa Medis `35% × INA-CBG`, Obat `actual / 1.41`, Lab `50%`, Radiologi `50%`, Darah `65%`, Oksigen `40%`, Diet `50%`, Kamar `50%`, Operasi `50%`, Administrasi `Rp 150.000`.
- **Pasien Bayi:** Seluruh komponen modal `0` kecuali `modal_biaya_bayi = Rp 250.000`.
- **Klasifikasi Rasio (`tarif_rs / tarif_inacbg`):** `> 100%` Tidak Terkendali · `86%–100%` Kurang Terkendali · `71%–85%` Cukup Terkendali · `< 71%` Sangat Terkendali.

### 9.2. Jadwal Dokter HFIS (`JadwalHfisModel`)
- Mengevaluasi kecocokan jam buka/tutup, kuota, dan status libur antara SIMRS Khanza dan WS Antrean BPJS (`SINKRON`, `BEDA_JAM`, `BEDA_KUOTA`, `BEDA_JAM_DAN_KUOTA`, `TIDAK_ADA_DI_HFIS`, `BELUM_MAPPING`).

### 9.3. Evaluasi Jam Mulai Poliklinik (`JamPoliklinikModel`)
- Membandingkan jam SOAP pemeriksaan pertama dokter (`pemeriksaan_ralan`) terhadap `jam_mulai_jadwal` dengan toleransi `15 menit` (`TEPAT_WAKTU`, `TERLAMBAT`, `BELUM_MULAI`).

### 9.4. Keterisian Tempat Tidur (`TempatTidurModel`)
- Menghitung total bed aktif (`kamar.statusdata = '1'`), terisi (`ISI`), kosong (`KOSONG`), dan persentase **BOR** keseluruhan serta per bangsal.

---

## 10. Instalasi & Operasional

1. **Prasyarat Server Web:** PHP `8.0.8+` (`pdo_mysql`, `json`, `curl`), MySQL/MariaDB, Apache/Nginx (Laragon / XAMPP / Ubuntu Nginx).
2. **Clone Repositori ke Direktori Web Server:**
   ```bash
   git clone https://github.com/rahmadisaputra-it/dashboard-eksekutif.git
   ```
3. **Inisialisasi Database:**
   - Buka aplikasi di browser (misal `http://localhost/dashboard-eksekutif`). Database `dashboard_eksekutif`, 9 tabel, dan akun Super Admin awal (`admin` / `admin123`) otomatis dibuat.
   - Atau impor manual file `database_dashboard_eksekutif.sql` ke MySQL Anda.
4. **Konfigurasi Awal Setelah Login:**
   - Login dengan akun default `admin` / `admin123`, lalu segera ubah password lewat menu profil di pojok kiri bawah.
   - Buka menu **Administrasi Sistem → Pengaturan Rumah Sakit** untuk mengisi Nama Rumah Sakit, Alamat Kop Cetak, Kontak, Nama Direktur, dan mengunggah Logo Rumah Sakit Anda.
   - Pasang dan jalankan `tambahan/KhanzaServicePushPermata.jar` di folder SIMRS Khanza Anda sesuai panduan di [Bab 8](#8-cara-pemasangan--pemakaian-service-push-tambahan).

---

## 11. Lisensi & Hak Cipta

Hak Cipta &copy; 2026 **Rahmadi Saputra**  
- GitHub: [@rahmadisaputra-it](https://github.com/rahmadisaputra-it)  
- Email: `rahmadisaputra.it@gmail.com`

Proyek ini dikembangkan secara independen oleh **Rahmadi Saputra** sebagai portal eksekutif dan operasional pendamping SIMRS Khanza yang fleksibel dan dapat digunakan oleh berbagai rumah sakit maupun fasilitas pelayanan kesehatan.
