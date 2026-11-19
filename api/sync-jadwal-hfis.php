<?php
declare(strict_types=1);

/**
 * REST API Endpoint: Sinkronisasi Jadwal Dokter HFIS BPJS vs SIMRS Khanza
 * URL: POST /dashboard-eksekutif/api/sync-jadwal-hfis.php
 * Header Wajib: X-API-KEY: DASHBOARD-EKSEKUTIF-2026-KEY
 */

date_default_timezone_set('Asia/Jakarta');

require_once dirname(__DIR__) . '/app/Core/Helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';

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

use App\Config\Database;
use App\Modules\Hfis\Models\JadwalHfisModel;

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// 1. Ambil API Key dari Header X-API-KEY atau Authorization Bearer
$providedKey = '';
if (!empty($_SERVER['HTTP_X_API_KEY'])) {
    $providedKey = trim((string) $_SERVER['HTTP_X_API_KEY']);
} elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s+(.+)$/i', (string) $_SERVER['HTTP_AUTHORIZATION'], $m)) {
        $providedKey = trim($m[1]);
    }
}

$expectedKey = Database::getApiKey();

if (!Database::isValidApiKey($providedKey)) {
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'code'    => 401,
        'message' => 'Akses ditolak: X-API-KEY tidak valid atau tidak disertakan.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$model = new JadwalHfisModel();

// 2. Method GET: Cek status endpoint & ringkasan sync terakhir
if ($method === 'GET') {
    $kpi = $model->getSummaryKpi();
    http_response_code(200);
    echo json_encode([
        'status'  => 'success',
        'code'    => 200,
        'message' => 'Endpoint API Sinkronisasi Jadwal HFIS Dashboard Eksekutif aktif.',
        'summary' => $kpi,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Method POST: Terima kiriman data jadwal dari SIMRS Khanza
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status'  => 'error',
        'code'    => 405,
        'message' => 'Metode HTTP tidak diizinkan. Gunakan POST untuk mengirim data jadwal.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$rawBody = isset($GLOBALS['MOCK_RAW_BODY']) ? (string) $GLOBALS['MOCK_RAW_BODY'] : (string) file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'code'    => 422,
        'message' => 'Format payload JSON tidak valid.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $summary  = $model->saveSnapshot($payload, $clientIp);

    http_response_code(200);
    echo json_encode([
        'status'  => 'success',
        'code'    => 200,
        'message' => 'Snapshot jadwal dokter HFIS vs SIMRS berhasil disimpan.',
        'summary' => $summary,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'code'    => 422,
        'message' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'code'    => 500,
        'message' => 'Terjadi kesalahan internal saat menyimpan snapshot jadwal.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
