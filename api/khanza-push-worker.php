<?php
declare(strict_types=1);

/**
 * Service Worker Pengirim Data Satu Arah (Outbound Push) dari SIMRS Khanza ke API Dashboard Eksekutif
 * Arsitektur: Zero Inbound ke Server Khanza — Service ini berjalan di sisi Khanza,
 * membaca database lokal Khanza (`sik_fix`), lalu mengirim JSON ke Dashboard Eksekutif (`dashboard_eksekutif`).
 *
 * Cara Eksekusi CLI (Task Scheduler / Cron):
 *   C:\laragon\bin\php\php-8.0.8\php.exe C:\laragon\www\dashboard-eksekutif\api\khanza-push-worker.php
 *
 * Parameter Opsional (GET atau CLI):
 *   ?target=all|kendali-biaya|tempat-tidur|jadwal-hfis|jam-poliklinik
 *   ?tanggal=YYYY-MM-DD
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
use App\Modules\Hfis\Models\JadwalHfisModel;
use App\Modules\Keuangan\Models\PerkiraanBiayaModel;
use App\Modules\Operasional\Models\JamPoliklinikModel;
use App\Modules\Operasional\Models\TempatTidurModel;

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

$db        = Database::getInstance();
$khanzaPdo = $db->getConnection();

if ($khanzaPdo === null) {
    http_response_code(503);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Koneksi ke database lokal SIMRS Khanza tidak tersedia.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$target = isset($_GET['target']) && is_string($_GET['target']) && trim($_GET['target']) !== ''
    ? strtolower(trim($_GET['target']))
    : 'all';

$tgl = isset($_GET['tanggal']) && is_string($_GET['tanggal']) && trim($_GET['tanggal']) !== ''
    ? trim($_GET['tanggal'])
    : date('Y-m-d');

$waktuSync = date('Y-m-d H:i:s');
$apiBase   = getenv('PERMATA_API_BASE_URL');
$apiUrl    = is_string($apiBase) && trim($apiBase) !== '' ? rtrim(trim($apiBase), '/') : '';
$apiKey    = Database::getApiKey();

/**
 * Helper: Kirim payload via HTTP POST cURL ke server Dashboard Eksekutif bila PERMATA_API_BASE_URL diset
 *
 * @param array<string, mixed> $payload
 * @return array<string, mixed>|null
 */
function pushViaHttp(string $endpointUrl, string $apiKey, array $payload): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($endpointUrl);
    if ($ch === false) {
        return null;
    }
    $jsonBody = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json; charset=UTF-8',
            'X-API-KEY: ' . $apiKey,
        ],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!is_string($resp) || $resp === '') {
        return null;
    }
    $decoded = json_decode($resp, true);
    return is_array($decoded) ? $decoded : null;
}

$results = [];

// =========================================================================
// 1. PUSH DATA KENDALI BIAYA RAWAT INAP AKTIF -> /api/sync-kendali-biaya.php
// =========================================================================
if ($target === 'all' || $target === 'kendali-biaya') {
    try {
        $biayaModel = new PerkiraanBiayaModel();
        $payload    = $biayaModel->extractPayloadFromKhanza();
        $payload['waktu_sync'] = $waktuSync;
        $payload['pengirim']   = 'khanza_push_worker';

        if (!empty($payload['data'])) {
            if ($apiUrl !== '') {
                $httpRes = pushViaHttp($apiUrl . '/api/sync-kendali-biaya.php', $apiKey, $payload);
                $results['kendali_biaya'] = $httpRes['summary'] ?? $biayaModel->saveSnapshot($payload, '127.0.0.1');
            } else {
                $results['kendali_biaya'] = $biayaModel->saveSnapshot($payload, '127.0.0.1');
            }
        } else {
            $results['kendali_biaya'] = ['status' => 'empty', 'message' => 'Tidak ada pasien rawat inap aktif.'];
        }
    } catch (\Throwable $e) {
        $results['kendali_biaya'] = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// =========================================================================
// 2. PUSH DATA KETERISIAN TEMPAT TIDUR (BOR) -> /api/sync-tempat-tidur.php
// =========================================================================
if ($target === 'all' || $target === 'tempat-tidur') {
    try {
        $sqlKamar = "SELECT
                        k.kd_kamar,
                        b.kd_bangsal,
                        b.nm_bangsal,
                        k.kelas,
                        k.trf_kamar,
                        IF(k.status = 'ISI', 'ISI', 'KOSONG') AS status,
                        (
                            SELECT ki.no_rawat
                            FROM kamar_inap ki
                            WHERE ki.kd_kamar = k.kd_kamar AND ki.stts_pulang = '-'
                            ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC
                            LIMIT 1
                        ) AS no_rawat,
                        (
                            SELECT ps.nm_pasien
                            FROM kamar_inap ki2
                            INNER JOIN reg_periksa rp ON rp.no_rawat = ki2.no_rawat
                            INNER JOIN pasien ps ON ps.no_rkm_medis = rp.no_rkm_medis
                            WHERE ki2.kd_kamar = k.kd_kamar AND ki2.stts_pulang = '-'
                            ORDER BY ki2.tgl_masuk DESC, ki2.jam_masuk DESC
                            LIMIT 1
                        ) AS nm_pasien,
                        (
                            SELECT ki3.tgl_masuk
                            FROM kamar_inap ki3
                            WHERE ki3.kd_kamar = k.kd_kamar AND ki3.stts_pulang = '-'
                            ORDER BY ki3.tgl_masuk DESC, ki3.jam_masuk DESC
                            LIMIT 1
                        ) AS tgl_masuk
                    FROM kamar k
                    INNER JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
                    WHERE k.statusdata = '1'
                    ORDER BY b.nm_bangsal ASC, k.kd_kamar ASC";

        $stmtKamar = $khanzaPdo->query($sqlKamar);
        $rowsKamar = $stmtKamar ? $stmtKamar->fetchAll() : [];

        if (is_array($rowsKamar) && !empty($rowsKamar)) {
            $payloadBed = [
                'waktu_sync' => $waktuSync,
                'pengirim'   => 'khanza_push_worker',
                'data'       => $rowsKamar,
            ];
            $bedModel = new TempatTidurModel();
            if ($apiUrl !== '') {
                $httpRes = pushViaHttp($apiUrl . '/api/sync-tempat-tidur.php', $apiKey, $payloadBed);
                $results['tempat_tidur'] = $httpRes['summary'] ?? $bedModel->saveSnapshot($payloadBed, '127.0.0.1');
            } else {
                $results['tempat_tidur'] = $bedModel->saveSnapshot($payloadBed, '127.0.0.1');
            }
        } else {
            $results['tempat_tidur'] = ['status' => 'empty', 'message' => 'Tidak ada data kamar aktif.'];
        }
    } catch (\Throwable $e) {
        $results['tempat_tidur'] = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// =========================================================================
// 3. PUSH DATA JADWAL DOKTER HFIS vs SIMRS -> /api/sync-jadwal-hfis.php
// =========================================================================
if ($target === 'all' || $target === 'jadwal-hfis') {
    try {
        $sqlHfis = "SELECT
                        j.kd_poli AS kd_poli_rs,
                        p.nm_poli AS nm_poli_rs,
                        mp.kd_poli_bpjs,
                        j.kd_dokter AS kd_dokter_rs,
                        d.nm_dokter AS nm_dokter_rs,
                        md.kd_dokter_bpjs,
                        j.hari_kerja,
                        LEFT(j.jam_mulai, 5) AS jam_mulai_rs,
                        LEFT(j.jam_selesai, 5) AS jam_selesai_rs,
                        j.kuota AS kuota_rs
                    FROM jadwal j
                    INNER JOIN poliklinik p ON p.kd_poli = j.kd_poli
                    INNER JOIN dokter d ON d.kd_dokter = j.kd_dokter
                    LEFT JOIN maping_poli_bpjs mp ON mp.kd_poli_rs = j.kd_poli
                    LEFT JOIN maping_dokter_dpjpvclaim md ON md.kd_dokter = j.kd_dokter
                    ORDER BY FIELD(j.hari_kerja, 'SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU','MINGGU','AKHAD'), p.nm_poli ASC, j.jam_mulai ASC";

        $stmtHfis = $khanzaPdo->query($sqlHfis);
        $rowsHfis = $stmtHfis ? $stmtHfis->fetchAll() : [];

        if (is_array($rowsHfis) && !empty($rowsHfis)) {
            $dataHfis = [];
            foreach ($rowsHfis as $rh) {
                $hasMapping = !empty($rh['kd_poli_bpjs']) && !empty($rh['kd_dokter_bpjs']);
                $dataHfis[] = [
                    'kd_poli_rs'       => (string) ($rh['kd_poli_rs'] ?? '-'),
                    'nm_poli_rs'       => (string) ($rh['nm_poli_rs'] ?? '-'),
                    'kd_poli_bpjs'     => (string) ($rh['kd_poli_bpjs'] ?? ''),
                    'kd_dokter_rs'     => (string) ($rh['kd_dokter_rs'] ?? '-'),
                    'nm_dokter_rs'     => (string) ($rh['nm_dokter_rs'] ?? '-'),
                    'kd_dokter_bpjs'   => (string) ($rh['kd_dokter_bpjs'] ?? ''),
                    'hari_kerja'       => (string) ($rh['hari_kerja'] ?? 'SENIN'),
                    'jam_mulai_rs'     => (string) ($rh['jam_mulai_rs'] ?? '08:00'),
                    'jam_selesai_rs'   => (string) ($rh['jam_selesai_rs'] ?? '12:00'),
                    'kuota_rs'         => (int) ($rh['kuota_rs'] ?? 0),
                    'jam_mulai_hfis'   => $hasMapping ? (string) ($rh['jam_mulai_rs'] ?? '08:00') : '-',
                    'jam_selesai_hfis' => $hasMapping ? (string) ($rh['jam_selesai_rs'] ?? '12:00') : '-',
                    'kuota_hfis'       => $hasMapping ? (int) ($rh['kuota_rs'] ?? 0) : 0,
                    'status_buka_hfis' => $hasMapping ? 'BUKA' : 'TUTUP',
                ];
            }

            $payloadHfis = [
                'waktu_sync' => $waktuSync,
                'kode_ppk'   => '0000R000',
                'pengirim'   => 'khanza_push_worker',
                'data'       => $dataHfis,
            ];
            $hfisModel = new JadwalHfisModel();
            if ($apiUrl !== '') {
                $httpRes = pushViaHttp($apiUrl . '/api/sync-jadwal-hfis.php', $apiKey, $payloadHfis);
                $results['jadwal_hfis'] = $httpRes['summary'] ?? $hfisModel->saveSnapshot($payloadHfis, '127.0.0.1');
            } else {
                $results['jadwal_hfis'] = $hfisModel->saveSnapshot($payloadHfis, '127.0.0.1');
            }
        } else {
            $results['jadwal_hfis'] = ['status' => 'empty', 'message' => 'Tidak ada jadwal dokter di tabel jadwal Khanza.'];
        }
    } catch (\Throwable $e) {
        $results['jadwal_hfis'] = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// =========================================================================
// 4. PUSH DATA EVALUASI MUNDUR JAM MULAI POLIKLINIK -> /api/sync-jam-poliklinik.php
// =========================================================================
if ($target === 'all' || $target === 'jam-poliklinik') {
    try {
        $hariMap = [
            1 => 'SENIN',
            2 => 'SELASA',
            3 => 'RABU',
            4 => 'KAMIS',
            5 => 'JUMAT',
            6 => 'SABTU',
            7 => 'AKHAD',
        ];

        // Tentukan daftar tanggal evaluasi mundur (H-1 ke belakang yang memiliki pelayanan poliklinik)
        $targetDates = [];
        if (!empty($_GET['tanggal']) && is_string($_GET['tanggal'])) {
            $targetDates = [trim($_GET['tanggal'])];
        } else {
            $stmtDates = $khanzaPdo->query(
                "SELECT DISTINCT rp.tgl_registrasi
                 FROM reg_periksa rp
                 INNER JOIN pemeriksaan_ralan pr ON pr.no_rawat = rp.no_rawat
                 INNER JOIN jadwal j ON j.kd_poli = rp.kd_poli AND j.kd_dokter = rp.kd_dokter
                 WHERE rp.tgl_registrasi < CURDATE()
                   AND rp.stts <> 'Batal'
                 ORDER BY rp.tgl_registrasi DESC
                 LIMIT 14"
            );
            $targetDates = $stmtDates ? $stmtDates->fetchAll(\PDO::FETCH_COLUMN) : [];
            if (empty($targetDates)) {
                $targetDates = [date('Y-m-d', strtotime('-1 day'))];
            }
        }

        $sqlPoli = "SELECT
                        j.hari_kerja,
                        j.kd_poli,
                        p.nm_poli,
                        j.kd_dokter,
                        d.nm_dokter,
                        MIN(LEFT(j.jam_mulai, 5)) AS jam_mulai_jadwal,
                        MAX(LEFT(j.jam_selesai, 5)) AS jam_selesai_jadwal,
                        COALESCE(
                            MIN(CASE WHEN pr.nip = j.kd_dokter THEN pr.jam_rawat END),
                            MIN(pr.jam_rawat)
                        ) AS jam_periksa_pertama,
                        MAX(pr.jam_rawat) AS jam_periksa_terakhir,
                        COUNT(DISTINCT rp.no_rawat) AS total_pasien_reg,
                        COUNT(DISTINCT pr.no_rawat) AS total_pasien_periksa,
                        (
                            SELECT pr2.no_rawat
                            FROM reg_periksa rp2
                            INNER JOIN pemeriksaan_ralan pr2 ON pr2.no_rawat = rp2.no_rawat
                            WHERE rp2.kd_poli = j.kd_poli
                              AND rp2.kd_dokter = j.kd_dokter
                              AND rp2.tgl_registrasi = :tgl1
                            ORDER BY CASE WHEN pr2.nip = j.kd_dokter THEN 0 ELSE 1 END, pr2.jam_rawat ASC
                            LIMIT 1
                        ) AS no_rawat_pertama,
                        (
                            SELECT ps2.nm_pasien
                            FROM reg_periksa rp3
                            INNER JOIN pemeriksaan_ralan pr3 ON pr3.no_rawat = rp3.no_rawat
                            INNER JOIN pasien ps2 ON ps2.no_rkm_medis = rp3.no_rkm_medis
                            WHERE rp3.kd_poli = j.kd_poli
                              AND rp3.kd_dokter = j.kd_dokter
                              AND rp3.tgl_registrasi = :tgl2
                            ORDER BY CASE WHEN pr3.nip = j.kd_dokter THEN 0 ELSE 1 END, pr3.jam_rawat ASC
                            LIMIT 1
                        ) AS nm_pasien_pertama
                    FROM jadwal j
                    INNER JOIN poliklinik p ON p.kd_poli = j.kd_poli
                    INNER JOIN dokter d ON d.kd_dokter = j.kd_dokter
                    LEFT JOIN reg_periksa rp
                        ON rp.kd_poli = j.kd_poli
                        AND rp.kd_dokter = j.kd_dokter
                        AND rp.tgl_registrasi = :tgl3
                        AND rp.stts <> 'Batal'
                    LEFT JOIN pemeriksaan_ralan pr
                        ON pr.no_rawat = rp.no_rawat
                    WHERE j.hari_kerja = :hari
                    GROUP BY j.kd_poli, j.kd_dokter
                    ORDER BY jam_mulai_jadwal ASC, p.nm_poli ASC";

        $stmtPoli    = $khanzaPdo->prepare($sqlPoli);
        $dataJamPoli = [];

        foreach ($targetDates as $tglItem) {
            $tglStr    = (string) $tglItem;
            $dayNum    = (int) date('N', strtotime($tglStr));
            $hariKerja = $hariMap[$dayNum] ?? 'SENIN';

            $stmtPoli->execute([
                ':tgl1' => $tglStr,
                ':tgl2' => $tglStr,
                ':tgl3' => $tglStr,
                ':hari' => $hariKerja,
            ]);

            $rowsPoli = $stmtPoli->fetchAll();
            if (is_array($rowsPoli)) {
                foreach ($rowsPoli as $r) {
                    $dataJamPoli[] = [
                        'tgl_pelayanan'        => $tglStr,
                        'hari_kerja'           => (string) ($r['hari_kerja'] ?? $hariKerja),
                        'kd_poli'              => (string) ($r['kd_poli'] ?? '-'),
                        'nm_poli'              => (string) ($r['nm_poli'] ?? '-'),
                        'kd_dokter'            => (string) ($r['kd_dokter'] ?? '-'),
                        'nm_dokter'            => (string) ($r['nm_dokter'] ?? '-'),
                        'jam_mulai_jadwal'     => (string) ($r['jam_mulai_jadwal'] ?? '08:00'),
                        'jam_selesai_jadwal'   => (string) ($r['jam_selesai_jadwal'] ?? '12:00'),
                        'jam_periksa_pertama'  => !empty($r['jam_periksa_pertama']) ? (string) $r['jam_periksa_pertama'] : '-',
                        'jam_periksa_terakhir' => !empty($r['jam_periksa_terakhir']) ? (string) $r['jam_periksa_terakhir'] : '-',
                        'no_rawat_pertama'     => $r['no_rawat_pertama'] ?? null,
                        'nm_pasien_pertama'    => $r['nm_pasien_pertama'] ?? null,
                        'total_pasien_reg'     => (int) ($r['total_pasien_reg'] ?? 0),
                        'total_pasien_periksa' => (int) ($r['total_pasien_periksa'] ?? 0),
                    ];
                }
            }
        }

        if (!empty($dataJamPoli)) {
            $payloadPoli = [
                'tgl_pelayanan'   => (string) ($targetDates[0] ?? $tgl),
                'waktu_sync'      => $waktuSync,
                'pengirim'        => 'khanza_push_worker',
                'toleransi_menit' => 15,
                'data'            => $dataJamPoli,
            ];
            $jamModel = new JamPoliklinikModel();
            if ($apiUrl !== '') {
                $httpRes = pushViaHttp($apiUrl . '/api/sync-jam-poliklinik.php', $apiKey, $payloadPoli);
                $results['jam_poliklinik'] = $httpRes['summary'] ?? $jamModel->saveSnapshot($payloadPoli, '127.0.0.1');
            } else {
                $results['jam_poliklinik'] = $jamModel->saveSnapshot($payloadPoli, '127.0.0.1');
            }
        } else {
            $results['jam_poliklinik'] = [
                'status'  => 'empty',
                'message' => 'Tidak ditemukan jadwal poliklinik aktif untuk dievaluasi.',
            ];
        }
    } catch (\Throwable $e) {
        $results['jam_poliklinik'] = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

echo json_encode([
    'status'     => 'success',
    'waktu_sync' => $waktuSync,
    'target'     => $target,
    'message'    => 'Sinkronisasi satu arah (push) dari SIMRS Khanza ke database mandiri Dashboard Eksekutif selesai.',
    'results'    => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
