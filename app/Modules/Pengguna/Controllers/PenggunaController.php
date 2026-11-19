<?php
declare(strict_types=1);

namespace App\Modules\Pengguna\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Modules\Pengguna\Models\PenggunaModel;

class PenggunaController extends Controller
{
    private PenggunaModel $model;

    public function __construct()
    {
        $this->model = new PenggunaModel();
    }

    public function index(): void
    {
        // Proteksi Keras: Hanya Administrator yang berhak mengelola akun & hak akses
        if (!Auth::check() || !Auth::revalidate() || !Auth::isAdmin()) {
            $this->redirect(Auth::defaultRoute());
        }

        $flashSuccess = !empty($_GET['msg']) && is_string($_GET['msg']) ? trim($_GET['msg']) : null;
        $flashError   = !empty($_GET['err']) && is_string($_GET['err']) ? trim($_GET['err']) : null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
            return;
        }

        $filters = [
            'q'      => isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '',
            'role'   => isset($_GET['role']) && is_string($_GET['role']) ? trim($_GET['role']) : '',
            'status' => isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : '',
        ];

        $users           = $this->model->getAllUsers($filters['q'], $filters['role'], $filters['status']);
        $kpi             = $this->model->getSummaryKpi();
        $apiLogs         = $this->model->getRecentApiLogs(8);
        $moduleSyncStats = $this->model->getModuleSyncStats();

        $this->renderModule('Pengguna', 'index', [
            'currentModule'    => 'pengguna',
            'currentPage'      => 'manajemen-akun',
            'pageTitle'        => 'Manajemen Akun & Hak Akses Modul',
            'pageSubtitle'     => 'Administrasi Sistem — Pembuatan akun staf, pengaturan peran (role), dan otorisasi modul Dashboard Eksekutif',
            'printable'        => false,
            'users'            => $users,
            'kpi'              => $kpi,
            'apiLogs'          => $apiLogs,
            'moduleSyncStats'  => $moduleSyncStats,
            'filters'          => $filters,
            'availableModules' => Auth::MODULES,
            'csrfToken'        => Auth::csrfToken(),
            'flashSuccess'     => $flashSuccess,
            'flashError'       => $flashError,
        ]);
    }

    /**
     * Halaman Pengaturan Profil Rumah Sakit, Alamat Kop Cetak, & Upload Logo
     */
    public function pengaturanRs(): void
    {
        if (!Auth::check() || !Auth::revalidate() || !Auth::isAdmin()) {
            $this->redirect(Auth::defaultRoute());
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
                $this->redirect('index.php?module=pengguna&page=pengaturan-rs&err=' . urlencode('Sesi formulir kedaluwarsa. Silakan ulangi kembali.'));
            }

            $action = (string) ($_POST['action'] ?? 'save_settings');
            if ($action === 'reset_logo') {
                $result = $this->model->resetHospitalLogo();
            } else {
                $result = $this->model->updateHospitalSettings($_POST, $_FILES);
            }

            $param = $result['ok'] ? 'msg' : 'err';
            $this->redirect('index.php?module=pengguna&page=pengaturan-rs&' . $param . '=' . urlencode($result['message']));
            return;
        }

        $flashSuccess = !empty($_GET['msg']) && is_string($_GET['msg']) ? trim($_GET['msg']) : null;
        $flashError   = !empty($_GET['err']) && is_string($_GET['err']) ? trim($_GET['err']) : null;
        $settings     = $this->getHospitalProfile();

        $this->renderModule('Pengguna', 'pengaturan_rs', [
            'currentModule' => 'pengguna',
            'currentPage'   => 'pengaturan-rs',
            'pageTitle'     => 'Pengaturan Profil Rumah Sakit & Kop Cetak',
            'pageSubtitle'  => 'Administrasi Sistem — Konfigurasi nama rumah sakit, alamat resmi, kontak, direktur, dan logo instansi untuk tampilan serta cetak laporan',
            'printable'     => false,
            'settings'      => $settings,
            'hospital'      => $settings,
            'csrfToken'     => Auth::csrfToken(),
            'flashSuccess'  => $flashSuccess,
            'flashError'    => $flashError,
        ]);
    }

    private function handlePost(): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $this->redirect('index.php?module=pengguna&page=manajemen-akun&err=' . urlencode('Sesi formulir kedaluwarsa. Silakan ulangi kembali.'));
        }

        $action         = (string) ($_POST['action'] ?? '');
        $currentAdminId = (int) (Auth::user()['id'] ?? 0);
        $result         = ['ok' => false, 'message' => 'Aksi tidak dikenali.'];

        switch ($action) {
            case 'create':
                $result = $this->model->createUser([
                    'username'    => $_POST['username'] ?? '',
                    'nama'        => $_POST['nama'] ?? '',
                    'nik_pegawai' => $_POST['nik_pegawai'] ?? '',
                    'jabatan'     => $_POST['jabatan'] ?? 'Petugas',
                    'departemen'  => $_POST['departemen'] ?? '-',
                    'password'    => $_POST['password'] ?? '',
                    'role'        => $_POST['role'] ?? 'user',
                    'status'      => $_POST['status'] ?? 'aktif',
                    'modules'     => isset($_POST['modules']) && is_array($_POST['modules']) ? $_POST['modules'] : [],
                ]);
                break;

            case 'update':
                $targetId = (int) ($_POST['user_id'] ?? 0);
                $result   = $this->model->updateUser($targetId, [
                    'nama'        => $_POST['nama'] ?? '',
                    'nik_pegawai' => $_POST['nik_pegawai'] ?? '',
                    'jabatan'     => $_POST['jabatan'] ?? 'Petugas',
                    'departemen'  => $_POST['departemen'] ?? '-',
                    'role'        => $_POST['role'] ?? 'user',
                    'status'      => $_POST['status'] ?? 'aktif',
                    'modules'     => isset($_POST['modules']) && is_array($_POST['modules']) ? $_POST['modules'] : [],
                ], $currentAdminId);
                break;

            case 'reset_password':
                $targetId    = (int) ($_POST['user_id'] ?? 0);
                $newPassword = (string) ($_POST['new_password'] ?? '');
                $result      = $this->model->resetPasswordByAdmin($targetId, $newPassword);
                break;

            case 'toggle_status':
                $targetId = (int) ($_POST['user_id'] ?? 0);
                $result   = $this->model->toggleStatus($targetId, $currentAdminId);
                break;
        }

        $param = $result['ok'] ? 'msg' : 'err';
        $this->redirect('index.php?module=pengguna&page=manajemen-akun&' . $param . '=' . urlencode($result['message']));
    }
}
