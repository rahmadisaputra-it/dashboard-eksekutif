<?php
declare(strict_types=1);

namespace App\Modules\Pengguna\Models;

use App\Config\Database;
use App\Core\Auth;
use PDO;
use Throwable;

class PenggunaModel
{
    private ?PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getAppConnection();
    }

    /**
     * Ambil daftar seluruh akun pengguna (tanpa password_hash)
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllUsers(string $search = '', string $role = '', string $status = ''): array
    {
        if ($this->pdo === null) {
            return [];
        }

        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[]           = '(username LIKE :q1 OR nama LIKE :q2 OR jabatan LIKE :q3 OR departemen LIKE :q4)';
            $like              = '%' . $search . '%';
            $params[':q1']     = $like;
            $params[':q2']     = $like;
            $params[':q3']     = $like;
            $params[':q4']     = $like;
        }

        if (in_array($role, ['admin', 'user'], true)) {
            $where[]         = 'role = :role';
            $params[':role'] = $role;
        }

        if (in_array($status, ['aktif', 'nonaktif'], true)) {
            $where[]           = 'status = :status';
            $params[':status'] = $status;
        }

        $sql = 'SELECT id, username, nama, nik_pegawai, jabatan, departemen, role, status, allowed_modules, last_login, created_at, updated_at
                FROM users';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY FIELD(role, "admin", "user"), status ASC, nama ASC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            if (!is_array($rows)) {
                return [];
            }

            foreach ($rows as &$r) {
                $r['modules_list'] = Auth::normalizeModules($r['allowed_modules'] ?? '[]');
            }
            unset($r);

            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Ringkasan KPI untuk kartu bagian atas halaman Manajemen Pengguna
     *
     * @return array{total: int, aktif: int, nonaktif: int, admin: int}
     */
    public function getSummaryKpi(): array
    {
        $kpi = ['total' => 0, 'aktif' => 0, 'nonaktif' => 0, 'admin' => 0];
        if ($this->pdo === null) {
            return $kpi;
        }

        try {
            $stmt = $this->pdo->query(
                'SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = "aktif" THEN 1 ELSE 0 END) AS aktif,
                    SUM(CASE WHEN status = "nonaktif" THEN 1 ELSE 0 END) AS nonaktif,
                    SUM(CASE WHEN role = "admin" THEN 1 ELSE 0 END) AS admin
                 FROM users'
            );
            $row = $stmt ? $stmt->fetch() : false;
            if (is_array($row)) {
                $kpi['total']    = (int) ($row['total'] ?? 0);
                $kpi['aktif']    = (int) ($row['aktif'] ?? 0);
                $kpi['nonaktif'] = (int) ($row['nonaktif'] ?? 0);
                $kpi['admin']    = (int) ($row['admin'] ?? 0);
            }
        } catch (Throwable $e) {
            // Gunakan default 0
        }

        return $kpi;
    }

    /**
     * Tambah akun baru oleh Administrator
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, message: string}
     */
    public function createUser(array $data): array
    {
        if ($this->pdo === null) {
            return ['ok' => false, 'message' => 'Koneksi database aplikasi tidak tersedia.'];
        }

        $username   = strtolower(trim((string) ($data['username'] ?? '')));
        $nama       = trim((string) ($data['nama'] ?? ''));
        $nikPegawai = trim((string) ($data['nik_pegawai'] ?? ''));
        $jabatan    = trim((string) ($data['jabatan'] ?? 'Petugas'));
        $departemen = trim((string) ($data['departemen'] ?? '-'));
        $password   = (string) ($data['password'] ?? '');
        $role       = ($data['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $status     = ($data['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
        $modules    = Auth::normalizeModules($data['modules'] ?? []);

        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
            return [
                'ok'      => false,
                'message' => 'Username wajib 3–50 karakter (huruf kecil, angka, titik, garis bawah, atau strip tanpa spasi).',
            ];
        }

        if ($nama === '' || strlen($nama) > 150) {
            return ['ok' => false, 'message' => 'Nama lengkap pengguna wajib diisi (maks 150 karakter).'];
        }

        if (strlen($password) < 6) {
            return ['ok' => false, 'message' => 'Password awal minimal 6 karakter.'];
        }

        if ($role === 'admin') {
            $modules = array_keys(Auth::MODULES);
        } elseif (empty($modules)) {
            return ['ok' => false, 'message' => 'Pilih minimal 1 hak akses modul untuk pengguna ini.'];
        }

        // Cek duplikasi username
        try {
            $chk = $this->pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
            $chk->execute([':u' => $username]);
            if ($chk->fetch()) {
                return ['ok' => false, 'message' => "Username '{$username}' sudah terdaftar. Gunakan username lain."];
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal memeriksa ketersediaan username.'];
        }

        $now  = date('Y-m-d H:i:s');
        $hash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users
                 (username, password_hash, nama, nik_pegawai, jabatan, departemen, role, status, allowed_modules, created_at, updated_at)
                 VALUES
                 (:u, :p, :nama, :nik, :jbtn, :dept, :role, :st, :mods, :c, :u_at)'
            );
            $stmt->execute([
                ':u'    => $username,
                ':p'    => $hash,
                ':nama' => $nama,
                ':nik'  => $nikPegawai !== '' ? $nikPegawai : null,
                ':jbtn' => $jabatan !== '' ? $jabatan : 'Petugas',
                ':dept' => $departemen !== '' ? $departemen : '-',
                ':role' => $role,
                ':st'   => $status,
                ':mods' => json_encode($modules, JSON_UNESCAPED_UNICODE),
                ':c'    => $now,
                ':u_at' => $now,
            ]);

            return ['ok' => true, 'message' => "Akun pengguna '{$username}' ({$nama}) berhasil dibuat."];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal menyimpan pengguna baru ke database.'];
        }
    }

    /**
     * Perbarui profil, role, status, dan hak akses modul pengguna oleh Administrator
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, message: string}
     */
    public function updateUser(int $id, array $data, int $currentAdminId): array
    {
        if ($this->pdo === null || $id <= 0) {
            return ['ok' => false, 'message' => 'ID pengguna tidak valid.'];
        }

        $nama       = trim((string) ($data['nama'] ?? ''));
        $nikPegawai = trim((string) ($data['nik_pegawai'] ?? ''));
        $jabatan    = trim((string) ($data['jabatan'] ?? 'Petugas'));
        $departemen = trim((string) ($data['departemen'] ?? '-'));
        $role       = ($data['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $status     = ($data['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
        $modules    = Auth::normalizeModules($data['modules'] ?? []);

        if ($nama === '' || strlen($nama) > 150) {
            return ['ok' => false, 'message' => 'Nama lengkap pengguna wajib diisi.'];
        }

        // Proteksi: Admin yang sedang login tidak boleh menurunkan role atau menonaktifkan dirinya sendiri
        if ($id === $currentAdminId) {
            if ($role !== 'admin') {
                return ['ok' => false, 'message' => 'Anda tidak dapat mengubah role akun Anda sendiri menjadi non-admin.'];
            }
            if ($status !== 'aktif') {
                return ['ok' => false, 'message' => 'Anda tidak dapat menonaktifkan akun yang sedang Anda gunakan.'];
            }
        }

        if ($role === 'admin') {
            $modules = array_keys(Auth::MODULES);
        } elseif (empty($modules)) {
            return ['ok' => false, 'message' => 'Pilih minimal 1 hak akses modul untuk pengguna ini.'];
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE users
                 SET nama = :nama,
                     nik_pegawai = :nik,
                     jabatan = :jbtn,
                     departemen = :dept,
                     role = :role,
                     status = :st,
                     allowed_modules = :mods,
                     updated_at = :u_at
                 WHERE id = :id'
            );
            $stmt->execute([
                ':nama' => $nama,
                ':nik'  => $nikPegawai !== '' ? $nikPegawai : null,
                ':jbtn' => $jabatan !== '' ? $jabatan : 'Petugas',
                ':dept' => $departemen !== '' ? $departemen : '-',
                ':role' => $role,
                ':st'   => $status,
                ':mods' => json_encode($modules, JSON_UNESCAPED_UNICODE),
                ':u_at' => date('Y-m-d H:i:s'),
                ':id'   => $id,
            ]);

            return ['ok' => true, 'message' => "Data & hak akses '{$nama}' berhasil diperbarui."];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal memperbarui data pengguna.'];
        }
    }

    /**
     * Reset password pengguna oleh Administrator
     *
     * @return array{ok: bool, message: string}
     */
    public function resetPasswordByAdmin(int $id, string $newPassword): array
    {
        if ($this->pdo === null || $id <= 0) {
            return ['ok' => false, 'message' => 'ID pengguna tidak valid.'];
        }

        if (strlen($newPassword) < 6) {
            return ['ok' => false, 'message' => 'Password baru minimal 6 karakter.'];
        }

        try {
            $chk = $this->pdo->prepare('SELECT username, nama FROM users WHERE id = :id LIMIT 1');
            $chk->execute([':id' => $id]);
            $user = $chk->fetch();
            if (!is_array($user)) {
                return ['ok' => false, 'message' => 'Akun pengguna tidak ditemukan.'];
            }

            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :h, updated_at = :t WHERE id = :id');
            $stmt->execute([
                ':h'  => $hash,
                ':t'  => date('Y-m-d H:i:s'),
                ':id' => $id,
            ]);

            return [
                'ok'      => true,
                'message' => "Password untuk akun '{$user['username']}' ({$user['nama']}) berhasil di-reset.",
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal mereset password pengguna.'];
        }
    }

    /**
     * Aktifkan / Nonaktifkan akun pengguna dengan cepat
     *
     * @return array{ok: bool, message: string}
     */
    public function toggleStatus(int $id, int $currentAdminId): array
    {
        if ($this->pdo === null || $id <= 0) {
            return ['ok' => false, 'message' => 'ID pengguna tidak valid.'];
        }

        if ($id === $currentAdminId) {
            return ['ok' => false, 'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.'];
        }

        try {
            $chk = $this->pdo->prepare('SELECT username, nama, status FROM users WHERE id = :id LIMIT 1');
            $chk->execute([':id' => $id]);
            $user = $chk->fetch();
            if (!is_array($user)) {
                return ['ok' => false, 'message' => 'Akun pengguna tidak ditemukan.'];
            }

            $newStatus = ($user['status'] ?? '') === 'aktif' ? 'nonaktif' : 'aktif';
            $stmt      = $this->pdo->prepare('UPDATE users SET status = :st, updated_at = :t WHERE id = :id');
            $stmt->execute([
                ':st' => $newStatus,
                ':t'  => date('Y-m-d H:i:s'),
                ':id' => $id,
            ]);

            $label = $newStatus === 'aktif' ? 'diaktifkan' : 'dinonaktifkan';
            return ['ok' => true, 'message' => "Akun '{$user['username']}' berhasil {$label}."];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal mengubah status akun.'];
        }
    }

    /**
     * Ambil riwayat log pengiriman data API terakhir dari service SIMRS Khanza
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentApiLogs(int $limit = 8): array
    {
        if ($this->pdo === null) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, waktu_sync, jenis_data, total_record, pengirim, ip_pengirim, status, keterangan
                 FROM api_log
                 ORDER BY id DESC
                 LIMIT :lim'
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Ambil status ringkas snapshot keempat modul di database mandiri dashboard_eksekutif
     *
     * @return array<string, array{count: int, last_sync: string}>
     */
    public function getModuleSyncStats(): array
    {
        $stats = [
            'kendali_biaya'  => ['count' => 0, 'last_sync' => '-'],
            'tempat_tidur'   => ['count' => 0, 'last_sync' => '-'],
            'jadwal_hfis'    => ['count' => 0, 'last_sync' => '-'],
            'jam_poliklinik' => ['count' => 0, 'last_sync' => '-'],
        ];

        if ($this->pdo === null) {
            return $stats;
        }

        $tables = [
            'kendali_biaya'  => 'kendali_biaya',
            'tempat_tidur'   => 'tempat_tidur',
            'jadwal_hfis'    => 'jadwal_hfis',
            'jam_poliklinik' => 'jam_poliklinik',
        ];

        foreach ($tables as $key => $tbl) {
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) AS cnt, MAX(synced_at) AS last_sync FROM `{$tbl}`");
                $row  = $stmt ? $stmt->fetch() : false;
                if (is_array($row)) {
                    $stats[$key]['count']     = (int) ($row['cnt'] ?? 0);
                    $stats[$key]['last_sync'] = !empty($row['last_sync']) ? (string) $row['last_sync'] : '-';
                }
            } catch (Throwable $e) {
                // Abaikan bila tabel belum ada
            }
        }

        return $stats;
    }

    /**
     * Ambil pengaturan profil rumah sakit / instansi dari tabel app_settings
     *
     * @return array<string, mixed>
     */
    public function getHospitalSettings(): array
    {
        $default = [
            'nama_aplikasi'   => 'Dashboard Eksekutif',
            'nama_instansi'   => 'RS Mitra SIMRS Khanza',
            'alamat_instansi' => 'Jl. Kesehatan No. 1',
            'kabupaten'       => 'Indonesia',
            'propinsi'        => 'Indonesia',
            'kontak'          => '(021) 000000',
            'email'           => 'info@rumahsakit.id',
            'kode_ppk'        => '0000R000',
            'nama_direktur'   => '( ................................................. )',
            'logo_instansi'   => 'assets/images/logo-default.svg',
            'logo_akreditasi' => '',
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        if ($this->pdo === null) {
            return $default;
        }

        try {
            $stmt = $this->pdo->query('SELECT * FROM `app_settings` WHERE `id` = 1 LIMIT 1');
            $row  = $stmt ? $stmt->fetch() : false;
            if (is_array($row)) {
                return array_merge($default, $row);
            }
        } catch (Throwable $e) {
            // Gunakan default
        }

        return $default;
    }

    /**
     * Simpan perubahan pengaturan profil rumah sakit, alamat kop cetak, dan upload logo
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $files
     * @return array{ok: bool, message: string}
     */
    public function updateHospitalSettings(array $input, array $files = []): array
    {
        if ($this->pdo === null) {
            return ['ok' => false, 'message' => 'Koneksi database tidak tersedia.'];
        }

        $current = $this->getHospitalSettings();

        $namaAplikasi   = trim((string) ($input['nama_aplikasi'] ?? 'Dashboard Eksekutif'));
        $namaInstansi   = trim((string) ($input['nama_instansi'] ?? ''));
        $alamatInstansi = trim((string) ($input['alamat_instansi'] ?? ''));
        $kabupaten      = trim((string) ($input['kabupaten'] ?? ''));
        $propinsi       = trim((string) ($input['propinsi'] ?? ''));
        $kontak         = trim((string) ($input['kontak'] ?? ''));
        $email          = trim((string) ($input['email'] ?? ''));
        $kodePpk        = trim((string) ($input['kode_ppk'] ?? ''));
        $namaDirektur   = trim((string) ($input['nama_direktur'] ?? ''));

        if ($namaAplikasi === '') {
            $namaAplikasi = 'Dashboard Eksekutif';
        }
        if ($namaInstansi === '') {
            return ['ok' => false, 'message' => 'Nama Rumah Sakit / Instansi wajib diisi.'];
        }
        if ($alamatInstansi === '') {
            return ['ok' => false, 'message' => 'Alamat lengkap rumah sakit wajib diisi untuk keperluan kop cetak laporan.'];
        }

        $logoInstansi   = (string) ($current['logo_instansi'] ?? 'assets/images/logo-default.svg');
        $logoAkreditasi = (string) ($current['logo_akreditasi'] ?? '');

        $uploadDir = dirname(__DIR__, 4) . '/assets/images/uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        // 1. Proses Upload Logo Utama Rumah Sakit jika ada
        if (!empty($files['logo_instansi']['tmp_name']) && is_uploaded_file($files['logo_instansi']['tmp_name'])) {
            $resLogo = $this->processLogoUpload($files['logo_instansi'], $uploadDir, 'logo-rs');
            if (!$resLogo['ok']) {
                return $resLogo;
            }
            $logoInstansi = $resLogo['path'];
        }

        // 2. Proses Upload Logo Akreditasi / Kanan Kop Cetak jika ada
        if (!empty($input['hapus_logo_akreditasi'])) {
            $logoAkreditasi = '';
        } elseif (!empty($files['logo_akreditasi']['tmp_name']) && is_uploaded_file($files['logo_akreditasi']['tmp_name'])) {
            $resAkr = $this->processLogoUpload($files['logo_akreditasi'], $uploadDir, 'logo-akreditasi');
            if (!$resAkr['ok']) {
                return $resAkr;
            }
            $logoAkreditasi = $resAkr['path'];
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `app_settings`
                 (`id`, `nama_aplikasi`, `nama_instansi`, `alamat_instansi`, `kabupaten`, `propinsi`, `kontak`, `email`, `kode_ppk`, `nama_direktur`, `logo_instansi`, `logo_akreditasi`, `updated_at`)
                 VALUES (1, :app, :rs, :al, :kab, :prop, :kon, :em, :ppk, :dir, :logo1, :logo2, :u)
                 ON DUPLICATE KEY UPDATE
                    `nama_aplikasi`   = VALUES(`nama_aplikasi`),
                    `nama_instansi`   = VALUES(`nama_instansi`),
                    `alamat_instansi` = VALUES(`alamat_instansi`),
                    `kabupaten`       = VALUES(`kabupaten`),
                    `propinsi`        = VALUES(`propinsi`),
                    `kontak`          = VALUES(`kontak`),
                    `email`           = VALUES(`email`),
                    `kode_ppk`        = VALUES(`kode_ppk`),
                    `nama_direktur`   = VALUES(`nama_direktur`),
                    `logo_instansi`   = VALUES(`logo_instansi`),
                    `logo_akreditasi` = VALUES(`logo_akreditasi`),
                    `updated_at`      = VALUES(`updated_at`)'
            );
            $stmt->execute([
                ':app'   => substr($namaAplikasi, 0, 100),
                ':rs'    => substr($namaInstansi, 0, 150),
                ':al'    => substr($alamatInstansi, 0, 255),
                ':kab'   => substr($kabupaten !== '' ? $kabupaten : '-', 0, 100),
                ':prop'  => substr($propinsi !== '' ? $propinsi : '-', 0, 100),
                ':kon'   => substr($kontak !== '' ? $kontak : '-', 0, 100),
                ':em'    => substr($email !== '' ? $email : '-', 0, 120),
                ':ppk'   => substr($kodePpk !== '' ? $kodePpk : '-', 0, 20),
                ':dir'   => substr($namaDirektur !== '' ? $namaDirektur : '( ................................................. )', 0, 150),
                ':logo1' => $logoInstansi,
                ':logo2' => $logoAkreditasi,
                ':u'     => date('Y-m-d H:i:s'),
            ]);

            return ['ok' => true, 'message' => "Pengaturan profil '{$namaInstansi}' berhasil disimpan."];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal menyimpan pengaturan rumah sakit: ' . $e->getMessage()];
        }
    }

    /**
     * Tarik profil instansi otomatis dari tabel `setting` SIMRS Khanza bila koneksi tersedia
     *
     * @return array{ok: bool, message: string}
     */
    public function syncHospitalFromKhanza(): array
    {
        if ($this->pdo === null) {
            return ['ok' => false, 'message' => 'Koneksi database mandiri tidak tersedia.'];
        }

        $khanzaPdo = Database::getInstance()->getConnection();
        if ($khanzaPdo === null) {
            return ['ok' => false, 'message' => 'Database SIMRS Khanza tidak terhubung langsung di server ini. Silakan isi profil rumah sakit secara manual pada formulir di bawah.'];
        }

        try {
            $stmt = $khanzaPdo->query(
                'SELECT nama_instansi, alamat_instansi, kabupaten, propinsi, kontak, email, kode_ppk
                 FROM setting LIMIT 1'
            );
            $row = $stmt ? $stmt->fetch() : false;
            if (!is_array($row) || empty($row['nama_instansi'])) {
                return ['ok' => false, 'message' => 'Tabel setting di database SIMRS Khanza kosong.'];
            }

            $current = $this->getHospitalSettings();
            return $this->updateHospitalSettings([
                'nama_aplikasi'   => $current['nama_aplikasi'] ?? 'Dashboard Eksekutif',
                'nama_instansi'   => $row['nama_instansi'] ?? '',
                'alamat_instansi' => $row['alamat_instansi'] ?? '-',
                'kabupaten'       => $row['kabupaten'] ?? '-',
                'propinsi'        => $row['propinsi'] ?? '-',
                'kontak'          => $row['kontak'] ?? '-',
                'email'           => $row['email'] ?? '-',
                'kode_ppk'        => $row['kode_ppk'] ?? '-',
                'nama_direktur'   => $current['nama_direktur'] ?? '( ................................................. )',
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal menarik data dari tabel setting SIMRS Khanza.'];
        }
    }

    /**
     * Reset logo kembali ke ikon vector default
     */
    public function resetHospitalLogo(): array
    {
        if ($this->pdo === null) {
            return ['ok' => false, 'message' => 'Koneksi database tidak tersedia.'];
        }
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE `app_settings` SET `logo_instansi` = 'assets/images/logo-default.svg', `updated_at` = :u WHERE `id` = 1"
            );
            $stmt->execute([':u' => date('Y-m-d H:i:s')]);
            return ['ok' => true, 'message' => 'Logo instansi berhasil dikembalikan ke logo default Dashboard Eksekutif.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal mereset logo instansi.'];
        }
    }

    /**
     * @param array<string, mixed> $file
     * @return array{ok: bool, message: string, path?: string}
     */
    private function processLogoUpload(array $file, string $uploadDir, string $prefix): array
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Gagal mengunggah file logo (kode error: ' . $err . ').'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'Ukuran file logo maksimal 2 MB.'];
        }

        $origName = (string) ($file['name'] ?? '');
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed  = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            return ['ok' => false, 'message' => 'Format file logo harus berupa PNG, JPG, JPEG, SVG, atau WEBP.'];
        }

        $fileName = $prefix . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
        $destPath = $uploadDir . '/' . $fileName;

        if (!@move_uploaded_file((string) $file['tmp_name'], $destPath)) {
            return ['ok' => false, 'message' => 'Gagal menyimpan file logo ke direktori assets/images/uploads/.'];
        }

        return [
            'ok'      => true,
            'message' => 'Logo berhasil diunggah.',
            'path'    => 'assets/images/uploads/' . $fileName,
        ];
    }
}
