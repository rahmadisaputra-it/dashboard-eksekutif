<?php
declare(strict_types=1);

/**
 * REST API Endpoint: Sinkronisasi Keterisian Tempat Tidur (BOR & Sensus Kamar Rawat Inap)
 * URL: POST /dashboard-eksekutif/api/sync-tempat-tidur.php
 * Header Wajib: X-API-KEY: DASHBOARD-EKSEKUTIF-2026-KEY
 */

date_default_timezone_set('Asia/Jakarta');

require_once dirname(__DIR__) . '/app/Core/Helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix  = 'App\\';
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
use App\Modules\Operasional\Models\TempatTidurModel;

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

// 1. Verifikasi API Key (X-API-KEY atau Authorization Bearer)
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

$model = new TempatTidurModel();

// 2. Method GET: Cek status endpoint & ringkasan BOR tempat tidur terakhir
if ($method === 'GET') {
    $dash = $model->getDashboardData([]);
    http_response_code(200);
    echo json_encode([
        'status'  => 'success',
        'code'    => 200,
        'message' => 'Endpoint API Keterisian Tempat Tidur Dashboard Eksekutif aktif.',
        'summary' => $dash['kpi'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Method POST: Terima kiriman data tempat tidur dari SIMRS Khanza
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status'  => 'error',
        'code'    => 405,
        'message' => 'Metode HTTP tidak diizinkan. Gunakan POST untuk mengirim data.',
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
        'message' => 'Snapshot keterisian tempat tidur berhasil disimpan ke Dashboard Eksekutif.',
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
        'message' => 'Terjadi kesalahan internal saat menyimpan snapshot tempat tidur.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
