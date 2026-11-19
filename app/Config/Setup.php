<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use Throwable;

class Setup
{
    public static function ensureAppSchema(PDO $pdo): void
    {
        // 1. Tabel users mandiri
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `nama` VARCHAR(150) NOT NULL,
                `nik_pegawai` VARCHAR(20) DEFAULT NULL,
                `jabatan` VARCHAR(100) NOT NULL DEFAULT 'Petugas',
                `departemen` VARCHAR(100) NOT NULL DEFAULT '-',
                `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
                `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
                `allowed_modules` TEXT NOT NULL,
                `last_login` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                KEY `idx_username` (`username`),
                KEY `idx_role` (`role`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // 2. Tabel app_settings (Pengaturan Profil Rumah Sakit, Alamat, & Logo)
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `app_settings` (
                `id` INT PRIMARY KEY DEFAULT 1,
                `nama_aplikasi` VARCHAR(100) NOT NULL DEFAULT 'Dashboard Eksekutif',
                `nama_instansi` VARCHAR(150) NOT NULL DEFAULT 'RS Mitra SIMRS Khanza',
                `alamat_instansi` VARCHAR(255) NOT NULL DEFAULT 'Jl. Kesehatan No. 1',
                `kabupaten` VARCHAR(100) NOT NULL DEFAULT 'Indonesia',
                `propinsi` VARCHAR(100) NOT NULL DEFAULT 'Indonesia',
                `kontak` VARCHAR(100) NOT NULL DEFAULT '(021) 000000',
                `email` VARCHAR(120) NOT NULL DEFAULT 'info@rumahsakit.id',
                `kode_ppk` VARCHAR(20) NOT NULL DEFAULT '0000R000',
                `nama_direktur` VARCHAR(150) NOT NULL DEFAULT '( ................................................. )',
                `logo_instansi` VARCHAR(255) NOT NULL DEFAULT 'assets/images/logo-default.svg',
                `logo_akreditasi` VARCHAR(255) DEFAULT '',
                `updated_at` DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // 3. Tabel api_log (audit terpusat pengiriman API dari service Khanza)
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `api_log` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `waktu_sync` DATETIME NOT NULL,
                `jenis_data` VARCHAR(50) NOT NULL,
                `total_record` INT NOT NULL DEFAULT 0,
                `pengirim` VARCHAR(100) NOT NULL DEFAULT 'khanza_service',
                `ip_pengirim` VARCHAR(45) DEFAULT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'SUKSES',
                `keterangan` TEXT DEFAULT NULL,
                KEY `idx_waktu` (`waktu_sync`),
                KEY `idx_jenis` (`jenis_data`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // 4. Tabel tarif_inacbg (referensi tarif paket INA-CBG mandiri di dashboard_eksekutif)
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `tarif_inacbg` (
                `kd_penyakit` VARCHAR(15) NOT NULL,
                `nm_penyakit` VARCHAR(200) DEFAULT NULL,
                `biaya` DOUBLE NOT NULL DEFAULT 0,
                `updated_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`kd_penyakit`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // 5. Tabel kendali_biaya (snapshot pasien ranap aktif & komponen biaya dari Khanza)
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `kendali_biaya` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `no_rawat` VARCHAR(20) NOT NULL,
                `no_rkm_medis` VARCHAR(20) NOT NULL,
                `nm_pasien` VARCHAR(150) NOT NULL,
                `kd_kamar` VARCHAR(20) NOT NULL DEFAULT '-',
                `nm_bangsal` VARCHAR(100) NOT NULL DEFAULT '-',
                `lokasi` VARCHAR(150) NOT NULL DEFAULT '-',
                `perujuk` VARCHAR(150) NOT NULL DEFAULT '-',
                `diagnosa_awal` VARCHAR(100) NOT NULL DEFAULT '-',
                `actual_obat` DOUBLE NOT NULL DEFAULT 0,
                `actual_lab` DOUBLE NOT NULL DEFAULT 0,
                `actual_radiologi` DOUBLE NOT NULL DEFAULT 0,
                `actual_kamar` DOUBLE NOT NULL DEFAULT 0,
                `actual_darah` DOUBLE NOT NULL DEFAULT 0,
                `actual_oksigen` DOUBLE NOT NULL DEFAULT 0,
                `actual_diet` DOUBLE NOT NULL DEFAULT 0,
                `actual_operasi` DOUBLE NOT NULL DEFAULT 0,
                `manual_tarif` DOUBLE NOT NULL DEFAULT 0,
                `modal_jasa_medis` DOUBLE NOT NULL DEFAULT 0,
                `modal_obat` DOUBLE NOT NULL DEFAULT 0,
                `modal_lab` DOUBLE NOT NULL DEFAULT 0,
                `modal_radiologi` DOUBLE NOT NULL DEFAULT 0,
                `modal_darah` DOUBLE NOT NULL DEFAULT 0,
                `modal_oksigen` DOUBLE NOT NULL DEFAULT 0,
                `modal_diet` DOUBLE NOT NULL DEFAULT 0,
                `modal_kamar` DOUBLE NOT NULL DEFAULT 0,
                `modal_operasi` DOUBLE NOT NULL DEFAULT 0,
                `modal_biaya_bayi` DOUBLE NOT NULL DEFAULT 0,
                `modal_adm` DOUBLE NOT NULL DEFAULT 0,
                `tarif_rs` DOUBLE NOT NULL DEFAULT 0,
                `tarif_inacbg` DOUBLE NOT NULL DEFAULT 0,
                `selisih` DOUBLE NOT NULL DEFAULT 0,
                `rasio` DOUBLE DEFAULT NULL,
                `keterangan` VARCHAR(40) NOT NULL DEFAULT 'N/A',
                `synced_at` DATETIME NOT NULL,
                KEY `idx_no_rawat` (`no_rawat`),
                KEY `idx_bangsal` (`nm_bangsal`),
                KEY `idx_keterangan` (`keterangan`),
                KEY `idx_diagnosa` (`diagnosa_awal`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // 6. Tabel tempat_tidur (snapshot kapasitas tempat tidur dari Khanza)
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `tempat_tidur` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `kd_kamar` VARCHAR(20) NOT NULL UNIQUE,
                `kd_bangsal` VARCHAR(15) NOT NULL,
                `nm_bangsal` VARCHAR(100) NOT NULL,
                `kelas` VARCHAR(30) NOT NULL,
                `trf_kamar` DOUBLE NOT NULL DEFAULT 0,
                `status` VARCHAR(15) NOT NULL DEFAULT 'KOSONG',
                `no_rawat` VARCHAR(20) DEFAULT NULL,
                `nm_pasien` VARCHAR(150) DEFAULT NULL,
                `tgl_masuk` DATE DEFAULT NULL,
                `synced_at` DATETIME NOT NULL,
                KEY `idx_bangsal` (`kd_bangsal`),
                KEY `idx_kelas` (`kelas`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // 7. Tabel jadwal_hfis & sync_log
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `jadwal_hfis` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `kd_poli_rs` VARCHAR(15) NOT NULL,
                `nm_poli_rs` VARCHAR(100) NOT NULL,
                `kd_poli_bpjs` VARCHAR(15) DEFAULT NULL,
                `kd_dokter_rs` VARCHAR(20) NOT NULL,
                `nm_dokter_rs` VARCHAR(150) NOT NULL,
                `kd_dokter_bpjs` VARCHAR(20) DEFAULT NULL,
                `hari_kerja` VARCHAR(15) NOT NULL,
                `jam_mulai_rs` VARCHAR(8) NOT NULL,
                `jam_selesai_rs` VARCHAR(8) NOT NULL,
                `kuota_rs` INT NOT NULL DEFAULT 0,
                `jam_mulai_hfis` VARCHAR(8) DEFAULT '-',
                `jam_selesai_hfis` VARCHAR(8) DEFAULT '-',
                `kuota_hfis` INT NOT NULL DEFAULT 0,
                `status_buka_hfis` VARCHAR(20) DEFAULT 'BUKA',
                `status_sinkron` VARCHAR(30) NOT NULL DEFAULT 'SINKRON',
                `catatan_selisih` TEXT DEFAULT NULL,
                `synced_at` DATETIME NOT NULL,
                KEY `idx_poli` (`kd_poli_rs`),
                KEY `idx_dokter` (`kd_dokter_rs`),
                KEY `idx_hari` (`hari_kerja`),
                KEY `idx_status` (`status_sinkron`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `sync_log` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `waktu_sync` DATETIME NOT NULL,
                `kode_ppk` VARCHAR(15) DEFAULT NULL,
                `pengirim` VARCHAR(50) NOT NULL,
                `total_jadwal` INT NOT NULL DEFAULT 0,
                `total_sinkron` INT NOT NULL DEFAULT 0,
                `total_konflik` INT NOT NULL DEFAULT 0,
                `persentase_kepatuhan` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `ip_pengirim` VARCHAR(45) DEFAULT NULL,
                KEY `idx_waktu_sync` (`waktu_sync`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // 8. Tabel jam_poliklinik
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `jam_poliklinik` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `tgl_pelayanan` DATE NOT NULL,
                `hari_kerja` VARCHAR(15) NOT NULL,
                `kd_poli` VARCHAR(15) NOT NULL,
                `nm_poli` VARCHAR(100) NOT NULL,
                `kd_dokter` VARCHAR(20) NOT NULL,
                `nm_dokter` VARCHAR(150) NOT NULL,
                `jam_mulai_jadwal` VARCHAR(8) NOT NULL,
                `jam_selesai_jadwal` VARCHAR(8) NOT NULL,
                `jam_periksa_pertama` VARCHAR(8) DEFAULT '-',
                `jam_periksa_terakhir` VARCHAR(8) DEFAULT '-',
                `no_rawat_pertama` VARCHAR(20) DEFAULT NULL,
                `nm_pasien_pertama` VARCHAR(100) DEFAULT NULL,
                `total_pasien_reg` INT NOT NULL DEFAULT 0,
                `total_pasien_periksa` INT NOT NULL DEFAULT 0,
                `selisih_menit` INT DEFAULT NULL,
                `status_kepatuhan` VARCHAR(25) NOT NULL DEFAULT 'BELUM_MULAI',
                `catatan_kepatuhan` VARCHAR(255) DEFAULT NULL,
                `synced_at` DATETIME NOT NULL,
                KEY `idx_tgl` (`tgl_pelayanan`),
                KEY `idx_poli` (`kd_poli`),
                KEY `idx_dokter` (`kd_dokter`),
                KEY `idx_status` (`status_kepatuhan`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Pastikan baris app_settings default (id=1) tersedia
        try {
            $cntSet = (int) $pdo->query("SELECT COUNT(*) FROM `app_settings` WHERE `id` = 1")->fetchColumn();
            if ($cntSet === 0) {
                $now = date('Y-m-d H:i:s');
                $stmtSet = $pdo->prepare(
                    "INSERT INTO `app_settings`
                     (`id`, `nama_aplikasi`, `nama_instansi`, `alamat_instansi`, `kabupaten`, `propinsi`, `kontak`, `email`, `kode_ppk`, `nama_direktur`, `logo_instansi`, `logo_akreditasi`, `updated_at`)
                     VALUES
                     (1, 'Dashboard Eksekutif', 'RS Mitra SIMRS Khanza', 'Jl. Kesehatan No. 1', 'Indonesia', 'Indonesia', '(021) 000000', 'info@rumahsakit.id', '0000R000', '( ................................................. )', 'assets/images/logo-default.svg', '', :u)"
                );
                $stmtSet->execute([':u' => $now]);
            }
        } catch (Throwable $e) {
            // Abaikan
        }

        // Pastikan akun Super Admin default tersedia
        try {
            $cntUser = (int) $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
            if ($cntUser === 0) {
                $defaultPass = 'admin123';
                $passHash    = password_hash($defaultPass, PASSWORD_BCRYPT);
                $now         = date('Y-m-d H:i:s');
                $modules     = json_encode(['keuangan', 'operasional']);

                $insertAdmin = $pdo->prepare(
                    "INSERT INTO `users`
                     (`username`, `password_hash`, `nama`, `jabatan`, `departemen`, `role`, `status`, `allowed_modules`, `created_at`, `updated_at`)
                     VALUES
                     ('admin', :pass, 'Administrator Dashboard Eksekutif', 'Super Admin Eksekutif', 'IT & Manajemen RS', 'admin', 'aktif', :modules, :created, :updated)"
                );
                $insertAdmin->execute([
                    ':pass'    => $passHash,
                    ':modules' => $modules,
                    ':created' => $now,
                    ':updated' => $now,
                ]);
            }
        } catch (Throwable $e) {
            // Abaikan
        }
    }

    public static function run(
        string $host = Database::DB_HOST,
        string $user = Database::DB_USER,
        string $pass = Database::DB_PASS,
        string $dbName = Database::DB_NAME,
        string $port = Database::DB_PORT
    ): array {
        $log = [];
        $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName) ?: Database::DB_NAME;

        try {
            $pdoRoot = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $log[] = "Database `{$safeDb}` siap.";

            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$safeDb};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            self::ensureAppSchema($pdo);
            $log[] = "Seluruh tabel mandiri siap.";

            return ['success' => true, 'log' => $log];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'log' => $log];
        }
    }
}
