<?php
declare(strict_types=1);

/**
 * Dashboard Eksekutif — Portal Eksekutif & Operasional Penghubung SIMRS Khanza
 * Author: Rahmadi Saputra (@rahmadisaputra-it | rahmadisaputra.it@gmail.com)
 * Runtime: PHP 8.0.8 Native Modular MVC
 */

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/app/Core/Helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

use App\Core\Auth;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Keuangan\Controllers\KendaliBiayaController;
use App\Modules\Operasional\Controllers\OperasionalController;
use App\Modules\Pengguna\Controllers\PenggunaController;

Auth::start();

$module = isset($_GET['module']) && is_string($_GET['module']) ? strtolower(trim($_GET['module'])) : '';
$page   = isset($_GET['page']) && is_string($_GET['page'])     ? strtolower(trim($_GET['page']))   : '';

// 1. Modul Autentikasi (Login / Logout / Ganti Password Mandiri)
if ($module === 'auth') {
    $authController = new AuthController();
    if ($page === 'logout') {
        $authController->logout();
        exit;
    }
    if ($page === 'ganti-password') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->processChangePassword();
        } else {
            $authController->showChangePassword();
        }
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $authController->processLogin();
        exit;
    }
    $authController->showLogin();
    exit;
}

// 2. Proteksi Login Wajib (akun mandiri di database dashboard_eksekutif)
if (!Auth::check()) {
    if (!headers_sent()) {
        header('Location: index.php?module=auth&page=login');
        exit;
    }
    $authController = new AuthController();
    $authController->showLogin();
    exit;
}

// 2b. Validasi ulang akun ke database: akun dinonaktifkan / hak akses diubah admin berlaku seketika
if (!Auth::revalidate()) {
    header('Location: index.php?module=auth&page=login');
    exit;
}

// 3. Akun aktif tetapi belum diberi modul oleh admin -> tolak (hindari redirect loop ke login)
if (Auth::defaultModule() === '') {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Akses Belum Diberikan</title></head>'
        . '<body style="font-family:system-ui,sans-serif;max-width:420px;margin:15vh auto;color:#334155">'
        . '<h1 style="font-size:18px">Akses belum diberikan</h1>'
        . '<p style="font-size:14px">Akun Anda aktif tetapi belum memiliki hak akses modul. Hubungi administrator.</p>'
        . '<p><a href="index.php?module=auth&amp;page=logout" style="color:#1d4ed8;font-size:14px">Keluar</a></p>'
        . '</body></html>';
    exit;
}

// 4. Modul Administrasi Sistem: Manajemen Akun & Pengaturan Rumah Sakit (Khusus Admin)
if ($module === 'pengguna') {
    if (!Auth::isAdmin()) {
        header('Location: ' . Auth::defaultRoute());
        exit;
    }
    $controller = new PenggunaController();
    if ($page === 'pengaturan-rs') {
        $controller->pengaturanRs();
    } else {
        $controller->index();
    }
    exit;
}

// 5. Tentukan modul default sesuai hak akses user jika parameter module kosong
if ($module === '') {
    $module = Auth::defaultModule();
}

// 6. Cek Otorisasi Hak Akses per Modul (default deny; modul tidak dikenal / tidak berhak -> modul miliknya)
if (!Auth::canAccessModule($module)) {
    header('Location: ' . Auth::defaultRoute());
    exit;
}

// 7. Routing per Folder Modul (Modular MVC).
switch ($module) {
    case 'operasional':
        $controller = new OperasionalController();
        if ($page === 'jam-poliklinik') {
            $controller->jamPoliklinik();
        } elseif ($page === 'tempat-tidur') {
            $controller->tempatTidur();
        } else {
            $controller->jadwalHfis();
        }
        break;

    case 'keuangan':
        $controller = new KendaliBiayaController();
        if ($page === 'tarif-inacbg') {
            $controller->tarifInacbg();
        } else {
            $controller->index();
        }
        break;

    default:
        header('Location: ' . Auth::defaultRoute());
        exit;
}
