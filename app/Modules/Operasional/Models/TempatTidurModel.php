<?php
declare(strict_types=1);

namespace App\Modules\Operasional\Models;

use App\Config\Database;
use PDO;
use Throwable;

class TempatTidurModel
{
    private ?PDO $pdo;
    private string $cacheFile;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getAppConnection();
        $cacheDir  = dirname(__DIR__, 4) . '/storage/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $this->cacheFile = $cacheDir . '/tempat_tidur_snapshot.json';
    }

    /**
     * Simpan snapshot keterisian tempat tidur yang di-push dari service SIMRS Khanza
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function saveSnapshot(array $payload, string $clientIp = '127.0.0.1'): array
    {
        $rawItems = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        if (empty($rawItems)) {
            throw new \InvalidArgumentException('Payload array "data" tempat tidur kosong atau tidak valid.');
        }

        $waktuSync = !empty($payload['waktu_sync']) && is_string($payload['waktu_sync'])
            ? date('Y-m-d H:i:s', strtotime($payload['waktu_sync']) ?: time())
            : date('Y-m-d H:i:s');

        $pengirim = substr(trim((string) ($payload['pengirim'] ?? 'khanza_push_worker')), 0, 100);

        $normalizedRows = [];
        $totalTerisi    = 0;
        $totalKosong    = 0;

        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $kdKamar = trim((string) ($item['kd_kamar'] ?? ''));
            if ($kdKamar === '') {
                continue;
            }

            $kdBangsal = trim((string) ($item['kd_bangsal'] ?? '-'));
            $nmBangsal = trim((string) ($item['nm_bangsal'] ?? 'Bangsal Rawat Inap'));
            if ($kdKamar === 'KL1-01A') {
                $nmBangsal = 'Ruang Rawat Inap Dewasa (Al-Afiyah)';
            }

            $kelas    = trim((string) ($item['kelas'] ?? 'Kelas 3'));
            $trfKamar = (float) ($item['trf_kamar'] ?? 0);
            $status   = strtoupper(trim((string) ($item['status'] ?? 'KOSONG'))) === 'ISI' ? 'ISI' : 'KOSONG';

            $noRawat  = !empty($item['no_rawat']) ? substr(trim((string) $item['no_rawat']), 0, 20) : null;
            $nmPasien = !empty($item['nm_pasien']) ? substr(trim((string) $item['nm_pasien']), 0, 150) : null;
            $tglMasuk = !empty($item['tgl_masuk']) && is_string($item['tgl_masuk'])
                ? date('Y-m-d', strtotime($item['tgl_masuk']) ?: time())
                : null;

            if ($status === 'ISI') {
                $totalTerisi++;
            } else {
                $totalKosong++;
                $noRawat  = null;
                $nmPasien = null;
                $tglMasuk = null;
            }

            $normalizedRows[] = [
                'kd_kamar'   => substr($kdKamar, 0, 20),
                'kd_bangsal' => substr($kdBangsal, 0, 15),
                'nm_bangsal' => substr($nmBangsal, 0, 100),
                'kelas'      => substr($kelas, 0, 30),
                'trf_kamar'  => $trfKamar,
                'status'     => $status,
                'no_rawat'   => $noRawat,
                'nm_pasien'  => $nmPasien,
                'tgl_masuk'  => $tglMasuk,
                'synced_at'  => $waktuSync,
            ];
        }

        $totalBed = count($normalizedRows);
        if ($totalBed === 0) {
            throw new \InvalidArgumentException('Tidak ada baris data kamar yang valid untuk disimpan.');
        }

        $bor = round(($totalTerisi / $totalBed) * 100, 1);

        $savedToDb = false;
        if ($this->pdo !== null) {
            try {
                $this->pdo->beginTransaction();
                $this->pdo->exec('DELETE FROM `tempat_tidur`');

                $stmt = $this->pdo->prepare(
                    'INSERT INTO `tempat_tidur`
                     (`kd_kamar`, `kd_bangsal`, `nm_bangsal`, `kelas`, `trf_kamar`, `status`, `no_rawat`, `nm_pasien`, `tgl_masuk`, `synced_at`)
                     VALUES
                     (:kd_kamar, :kd_bangsal, :nm_bangsal, :kelas, :trf_kamar, :status, :no_rawat, :nm_pasien, :tgl_masuk, :synced_at)'
                );

                foreach ($normalizedRows as $r) {
                    $stmt->execute([
                        ':kd_kamar'   => $r['kd_kamar'],
                        ':kd_bangsal' => $r['kd_bangsal'],
                        ':nm_bangsal' => $r['nm_bangsal'],
                        ':kelas'      => $r['kelas'],
                        ':trf_kamar'  => $r['trf_kamar'],
                        ':status'     => $r['status'],
                        ':no_rawat'   => $r['no_rawat'],
                        ':nm_pasien'  => $r['nm_pasien'],
                        ':tgl_masuk'  => $r['tgl_masuk'],
                        ':synced_at'  => $r['synced_at'],
                    ]);
                }

                $this->pdo->commit();
                $savedToDb = true;
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
            }
        }

        // Mirror ke file snapshot JSON
        @file_put_contents(
            $this->cacheFile,
            json_encode($normalizedRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        Database::logApiPush(
            'tempat_tidur',
            $totalBed,
            $pengirim,
            $clientIp,
            'SUKSES',
            "Total: {$totalBed}, Terisi: {$totalTerisi}, Kosong: {$totalKosong}, BOR: {$bor}%"
        );

        return [
            'waktu_sync'     => $waktuSync,
            'pengirim'       => $pengirim,
            'total_bed'      => $totalBed,
            'total_terisi'   => $totalTerisi,
            'total_kosong'   => $totalKosong,
            'persentase_bor' => $bor,
            'ip_pengirim'    => substr($clientIp, 0, 45),
            'storage_mode'   => $savedToDb ? 'mysql_and_cache' : 'cache_snapshot',
        ];
    }

    /**
     * Ambil seluruh data kamar dari database mandiri dashboard_eksekutif (atau fallback JSON cache)
     *
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function getDashboardData(array $filters = []): array
    {
        $rawKamar  = [];
        $waktuSync = date('Y-m-d H:i:s');

        if ($this->pdo !== null) {
            try {
                $stmt = $this->pdo->query(
                    'SELECT kd_kamar, kd_bangsal, nm_bangsal, kelas, trf_kamar, status, no_rawat, nm_pasien, tgl_masuk, synced_at
                     FROM tempat_tidur
                     ORDER BY nm_bangsal ASC, kd_kamar ASC'
                );
                $rows = $stmt ? $stmt->fetchAll() : [];
                if (is_array($rows) && !empty($rows)) {
                    $rawKamar  = $rows;
                    $waktuSync = (string) ($rows[0]['synced_at'] ?? $waktuSync);
                }
            } catch (Throwable $e) {
                $rawKamar = [];
            }
        }

        // Fallback ke file cache jika tabel dashboard_eksekutif.tempat_tidur belum terisi
        if (empty($rawKamar) && is_file($this->cacheFile)) {
            $decoded = json_decode((string) @file_get_contents($this->cacheFile), true);
            if (is_array($decoded) && !empty($decoded)) {
                $rawKamar = $decoded;
                // Jika koneksi appPdo ada tapi tabel masih kosong, migrasikan isi cache ke DB otomatis
                if ($this->pdo !== null) {
                    try {
                        $this->saveSnapshot([
                            'waktu_sync' => date('Y-m-d H:i:s', (int) @filemtime($this->cacheFile) ?: time()),
                            'pengirim'   => 'auto_seed_cache',
                            'data'       => $rawKamar,
                        ], '127.0.0.1');
                    } catch (Throwable $e) {
                        // Abaikan
                    }
                }
            }
        }

        $totalBed       = count($rawKamar);
        $totalTerisi    = 0;
        $totalKosong    = 0;
        $rekapMap       = [];
        $bangsalOptions = [];
        $kelasOptions   = [];

        foreach ($rawKamar as &$k) {
            if (($k['kd_kamar'] ?? '') === 'KL1-01A') {
                $k['nm_bangsal'] = 'Ruang Rawat Inap Dewasa (Al-Afiyah)';
            }
            $kdB = (string) ($k['kd_bangsal'] ?? '-');
            $nmB = (string) ($k['nm_bangsal'] ?? '-');
            $kls = (string) ($k['kelas'] ?? '-');
            $st  = ($k['status'] ?? '') === 'ISI' ? 'ISI' : 'KOSONG';

            $bangsalOptions[$kdB] = $nmB;
            $kelasOptions[$kls]   = $kls;

            if ($st === 'ISI') {
                $totalTerisi++;
            } else {
                $totalKosong++;
            }

            if (!isset($rekapMap[$kdB])) {
                $rekapMap[$kdB] = [
                    'kd_bangsal' => $kdB,
                    'nm_bangsal' => $nmB,
                    'total_bed'  => 0,
                    'terisi'     => 0,
                    'kosong'     => 0,
                ];
            }
            $rekapMap[$kdB]['total_bed']++;
            if ($st === 'ISI') {
                $rekapMap[$kdB]['terisi']++;
            } else {
                $rekapMap[$kdB]['kosong']++;
            }
        }
        unset($k);

        $rekapBangsal = [];
        foreach ($rekapMap as $item) {
            $item['persentase_bor'] = $item['total_bed'] > 0
                ? round(($item['terisi'] / $item['total_bed']) * 100, 1)
                : 0.0;
            $rekapBangsal[] = $item;
        }

        $fBangsal = strtoupper(trim((string) ($filters['kd_bangsal'] ?? '')));
        $fKelas   = strtoupper(trim((string) ($filters['kelas'] ?? '')));
        $fStatus  = strtoupper(trim((string) ($filters['status'] ?? '')));
        $fQ       = strtolower(trim((string) ($filters['q'] ?? '')));

        $listKamar = array_values(array_filter($rawKamar, static function (array $row) use ($fBangsal, $fKelas, $fStatus, $fQ): bool {
            if ($fBangsal !== '' && strtoupper((string) ($row['kd_bangsal'] ?? '')) !== $fBangsal) {
                return false;
            }
            if ($fKelas !== '' && strtoupper((string) ($row['kelas'] ?? '')) !== $fKelas) {
                return false;
            }
            if ($fStatus !== '' && strtoupper((string) ($row['status'] ?? '')) !== $fStatus) {
                return false;
            }
            if ($fQ !== '') {
                $hay = strtolower(($row['kd_kamar'] ?? '') . ' ' . ($row['nm_bangsal'] ?? '') . ' ' . ($row['kelas'] ?? '') . ' ' . ($row['nm_pasien'] ?? ''));
                if (!str_contains($hay, $fQ)) {
                    return false;
                }
            }
            return true;
        }));

        $kpi = [
            'total_bed'      => $totalBed,
            'total_terisi'   => $totalTerisi,
            'total_kosong'   => $totalKosong,
            'persentase_bor' => $totalBed > 0 ? round(($totalTerisi / $totalBed) * 100, 1) : 0.0,
            'waktu_update'   => $waktuSync,
        ];

        return [
            'kpi'            => $kpi,
            'rekapBangsal'   => $rekapBangsal,
            'listKamar'      => $listKamar,
            'bangsalOptions' => $bangsalOptions,
            'kelasOptions'   => $kelasOptions,
        ];
    }
}
