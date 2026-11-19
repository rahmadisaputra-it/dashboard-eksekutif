<?php
declare(strict_types=1);

namespace App\Modules\Hfis\Models;

use App\Config\Database;
use PDO;
use Throwable;

class JadwalHfisModel
{
    private ?PDO $pdo;
    private string $cacheFile;
    private string $logCacheFile;

    private const VALID_STATUSES = [
        'SINKRON',
        'BEDA_JAM',
        'BEDA_KUOTA',
        'BEDA_JAM_DAN_KUOTA',
        'BELUM_MAPPING',
        'TIDAK_ADA_DI_HFIS',
    ];

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getAppConnection();
        $cacheDir = dirname(__DIR__, 4) . '/storage/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $this->cacheFile    = $cacheDir . '/hfis_jadwal_snapshot.json';
        $this->logCacheFile = $cacheDir . '/hfis_sync_log.json';
    }

    /**
     * Simpan snapshot jadwal dokter HFIS vs SIMRS yang dikirim via POST API dari sistem Khanza
     */
    public function saveSnapshot(array $payload, string $clientIp = '127.0.0.1'): array
    {
        $rawItems = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        if (empty($rawItems)) {
            throw new \InvalidArgumentException('Payload array "data" kosong atau tidak valid.');
        }

        $waktuSync = !empty($payload['waktu_sync']) && is_string($payload['waktu_sync'])
            ? date('Y-m-d H:i:s', strtotime($payload['waktu_sync']) ?: time())
            : date('Y-m-d H:i:s');

        $kodePpk  = substr(trim((string) ($payload['kode_ppk'] ?? '0000R000')), 0, 15);
        $pengirim = substr(trim((string) ($payload['pengirim'] ?? 'khanza_sync')), 0, 50);

        $normalizedRows = [];
        $totalSinkron   = 0;
        $totalKonflik   = 0;

        foreach ($rawItems as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $row = $this->normalizeRow($item, $waktuSync, $index + 1);
            if ($row['status_sinkron'] === 'SINKRON') {
                $totalSinkron++;
            } else {
                $totalKonflik++;
            }
            $normalizedRows[] = $row;
        }

        $totalJadwal = count($normalizedRows);
        if ($totalJadwal === 0) {
            throw new \InvalidArgumentException('Tidak ada baris data jadwal yang valid untuk disimpan.');
        }

        $persentaseKepatuhan = round(($totalSinkron / $totalJadwal) * 100, 2);

        $logEntry = [
            'waktu_sync'           => $waktuSync,
            'kode_ppk'             => $kodePpk,
            'pengirim'             => $pengirim,
            'total_jadwal'         => $totalJadwal,
            'total_sinkron'        => $totalSinkron,
            'total_konflik'        => $totalKonflik,
            'persentase_kepatuhan' => $persentaseKepatuhan,
            'ip_pengirim'          => substr($clientIp, 0, 45),
        ];

        $savedToDb = false;

        // 1. Simpan ke Tabel MySQL (jadwal_hfis & sync_log) jika koneksi tersedia
        if ($this->pdo !== null) {
            try {
                $this->pdo->beginTransaction();

                $this->pdo->exec("DELETE FROM `jadwal_hfis`");

                $stmtInsert = $this->pdo->prepare(
                    "INSERT INTO `jadwal_hfis` (
                        `kd_poli_rs`, `nm_poli_rs`, `kd_poli_bpjs`,
                        `kd_dokter_rs`, `nm_dokter_rs`, `kd_dokter_bpjs`,
                        `hari_kerja`, `jam_mulai_rs`, `jam_selesai_rs`, `kuota_rs`,
                        `jam_mulai_hfis`, `jam_selesai_hfis`, `kuota_hfis`,
                        `status_buka_hfis`, `status_sinkron`, `catatan_selisih`, `synced_at`
                    ) VALUES (
                        :kd_poli_rs, :nm_poli_rs, :kd_poli_bpjs,
                        :kd_dokter_rs, :nm_dokter_rs, :kd_dokter_bpjs,
                        :hari_kerja, :jam_mulai_rs, :jam_selesai_rs, :kuota_rs,
                        :jam_mulai_hfis, :jam_selesai_hfis, :kuota_hfis,
                        :status_buka_hfis, :status_sinkron, :catatan_selisih, :synced_at
                    )"
                );

                foreach ($normalizedRows as $row) {
                    $stmtInsert->execute([
                        ':kd_poli_rs'       => $row['kd_poli_rs'],
                        ':nm_poli_rs'       => $row['nm_poli_rs'],
                        ':kd_poli_bpjs'     => $row['kd_poli_bpjs'],
                        ':kd_dokter_rs'     => $row['kd_dokter_rs'],
                        ':nm_dokter_rs'     => $row['nm_dokter_rs'],
                        ':kd_dokter_bpjs'   => $row['kd_dokter_bpjs'],
                        ':hari_kerja'       => $row['hari_kerja'],
                        ':jam_mulai_rs'     => $row['jam_mulai_rs'],
                        ':jam_selesai_rs'   => $row['jam_selesai_rs'],
                        ':kuota_rs'         => $row['kuota_rs'],
                        ':jam_mulai_hfis'   => $row['jam_mulai_hfis'],
                        ':jam_selesai_hfis' => $row['jam_selesai_hfis'],
                        ':kuota_hfis'       => $row['kuota_hfis'],
                        ':status_buka_hfis' => $row['status_buka_hfis'],
                        ':status_sinkron'   => $row['status_sinkron'],
                        ':catatan_selisih'  => $row['catatan_selisih'],
                        ':synced_at'        => $row['synced_at'],
                    ]);
                }

                $stmtLog = $this->pdo->prepare(
                    "INSERT INTO `sync_log` (
                        `waktu_sync`, `kode_ppk`, `pengirim`,
                        `total_jadwal`, `total_sinkron`, `total_konflik`,
                        `persentase_kepatuhan`, `ip_pengirim`
                    ) VALUES (
                        :waktu_sync, :kode_ppk, :pengirim,
                        :total_jadwal, :total_sinkron, :total_konflik,
                        :persentase_kepatuhan, :ip_pengirim
                    )"
                );

                $stmtLog->execute([
                    ':waktu_sync'           => $logEntry['waktu_sync'],
                    ':kode_ppk'             => $logEntry['kode_ppk'],
                    ':pengirim'             => $logEntry['pengirim'],
                    ':total_jadwal'         => $logEntry['total_jadwal'],
                    ':total_sinkron'        => $logEntry['total_sinkron'],
                    ':total_konflik'        => $logEntry['total_konflik'],
                    ':persentase_kepatuhan' => $logEntry['persentase_kepatuhan'],
                    ':ip_pengirim'          => $logEntry['ip_pengirim'],
                ]);

                $this->pdo->commit();
                $savedToDb = true;
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
            }
        }

        // 2. Mirror Snapshot ke File Storage lokal agar tetap instan & tahan gangguan DB
        @file_put_contents(
            $this->cacheFile,
            json_encode([
                'meta' => $logEntry,
                'data' => $normalizedRows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $existingLogs = [];
        if (is_file($this->logCacheFile)) {
            $decodedLogs = json_decode((string) @file_get_contents($this->logCacheFile), true);
            if (is_array($decodedLogs)) {
                $existingLogs = $decodedLogs;
            }
        }
        array_unshift($existingLogs, $logEntry);
        $existingLogs = array_slice($existingLogs, 0, 30);
        @file_put_contents(
            $this->logCacheFile,
            json_encode($existingLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        Database::logApiPush(
            'jadwal_hfis',
            $totalJadwal,
            $pengirim,
            $clientIp,
            'SUKSES',
            "Total: {$totalJadwal}, Sinkron: {$totalSinkron}, Konflik: {$totalKonflik} ({$persentaseKepatuhan}%)"
        );

        return array_merge($logEntry, [
            'storage_mode' => $savedToDb ? 'mysql_and_cache' : 'cache_snapshot',
        ]);
    }

    /**
     * Normalisasi dan kalkulasi otomatis status sinkronisasi per baris jadwal
     */
    private function normalizeRow(array $item, string $waktuSync, int $fallbackId): array
    {
        $kdPoliRs   = strtoupper(substr(trim((string) ($item['kd_poli_rs'] ?? '-')), 0, 15));
        $nmPoliRs   = substr(trim((string) ($item['nm_poli_rs'] ?? 'Poliklinik')), 0, 100);
        $kdPoliBpjs = strtoupper(substr(trim((string) ($item['kd_poli_bpjs'] ?? '')), 0, 15));

        $kdDokterRs   = substr(trim((string) ($item['kd_dokter_rs'] ?? '-')), 0, 20);
        $nmDokterRs   = substr(trim((string) ($item['nm_dokter_rs'] ?? 'Dokter Spesialis')), 0, 150);
        $kdDokterBpjs = substr(trim((string) ($item['kd_dokter_bpjs'] ?? '')), 0, 20);

        $hariKerja = strtoupper(substr(trim((string) ($item['hari_kerja'] ?? 'SENIN')), 0, 15));

        $jamMulaiRs   = $this->normalizeTime((string) ($item['jam_mulai_rs'] ?? '00:00'));
        $jamSelesaiRs = $this->normalizeTime((string) ($item['jam_selesai_rs'] ?? '00:00'));
        $kuotaRs      = max(0, (int) ($item['kuota_rs'] ?? 0));

        $jamMulaiHfis   = $this->normalizeTime((string) ($item['jam_mulai_hfis'] ?? '-'), true);
        $jamSelesaiHfis = $this->normalizeTime((string) ($item['jam_selesai_hfis'] ?? '-'), true);
        $kuotaHfis      = max(0, (int) ($item['kuota_hfis'] ?? 0));
        $statusBukaHfis = strtoupper(substr(trim((string) ($item['status_buka_hfis'] ?? 'BUKA')), 0, 20));

        $providedStatus = strtoupper(trim((string) ($item['status_sinkron'] ?? '')));
        $providedNotes  = trim((string) ($item['catatan_selisih'] ?? ''));

        // Kalkulasi otomatis apabila pengirim tidak mengisi status_sinkron secara eksplisit
        if (!in_array($providedStatus, self::VALID_STATUSES, true)) {
            [$computedStatus, $computedNotes] = $this->evaluateSyncStatus(
                $kdPoliBpjs,
                $kdDokterBpjs,
                $jamMulaiRs,
                $jamSelesaiRs,
                $kuotaRs,
                $jamMulaiHfis,
                $jamSelesaiHfis,
                $kuotaHfis
            );
            $statusSinkron  = $computedStatus;
            $catatanSelisih = $providedNotes !== '' ? $providedNotes : $computedNotes;
        } else {
            $statusSinkron  = $providedStatus;
            $catatanSelisih = $providedNotes !== '' ? $providedNotes : ($statusSinkron === 'SINKRON' ? 'Sesuai' : '-');
        }

        return [
            'id'               => (int) ($item['id'] ?? $fallbackId),
            'kd_poli_rs'       => $kdPoliRs,
            'nm_poli_rs'       => $nmPoliRs,
            'kd_poli_bpjs'     => $kdPoliBpjs !== '' ? $kdPoliBpjs : null,
            'kd_dokter_rs'     => $kdDokterRs,
            'nm_dokter_rs'     => $nmDokterRs,
            'kd_dokter_bpjs'   => $kdDokterBpjs !== '' ? $kdDokterBpjs : null,
            'hari_kerja'       => $hariKerja,
            'jam_mulai_rs'     => $jamMulaiRs,
            'jam_selesai_rs'   => $jamSelesaiRs,
            'kuota_rs'         => $kuotaRs,
            'jam_mulai_hfis'   => $jamMulaiHfis,
            'jam_selesai_hfis' => $jamSelesaiHfis,
            'kuota_hfis'       => $kuotaHfis,
            'status_buka_hfis' => $statusBukaHfis,
            'status_sinkron'   => $statusSinkron,
            'catatan_selisih'  => $catatanSelisih,
            'synced_at'        => $waktuSync,
        ];
    }

    /**
     * Evaluasi komparasi jadwal RS vs HFIS
     *
     * @return array{0: string, 1: string}
     */
    private function evaluateSyncStatus(
        string $kdPoliBpjs,
        string $kdDokterBpjs,
        string $jamMulaiRs,
        string $jamSelesaiRs,
        int $kuotaRs,
        string $jamMulaiHfis,
        string $jamSelesaiHfis,
        int $kuotaHfis
    ): array {
        if ($kdPoliBpjs === '' || $kdDokterBpjs === '' || $kdPoliBpjs === '-' || $kdDokterBpjs === '-') {
            return ['BELUM_MAPPING', 'Kode Poli atau Dokter belum dimapping ke referensi BPJS'];
        }

        if ($jamMulaiHfis === '-' || $jamSelesaiHfis === '-' || $jamMulaiHfis === '') {
            return ['TIDAK_ADA_DI_HFIS', 'Jadwal praktek terdaftar di SIMRS tetapi belum ditemukan di HFIS BPJS'];
        }

        $sameJam   = ($jamMulaiRs === $jamMulaiHfis && $jamSelesaiRs === $jamSelesaiHfis);
        $sameKuota = ($kuotaRs === $kuotaHfis);

        if ($sameJam && $sameKuota) {
            return ['SINKRON', 'Sesuai'];
        }

        if (!$sameJam && !$sameKuota) {
            return [
                'BEDA_JAM_DAN_KUOTA',
                "Jam RS {$jamMulaiRs}-{$jamSelesaiRs} vs HFIS {$jamMulaiHfis}-{$jamSelesaiHfis}; Kuota RS {$kuotaRs} vs HFIS {$kuotaHfis}",
            ];
        }

        if (!$sameJam) {
            return [
                'BEDA_JAM',
                "Jam praktek RS ({$jamMulaiRs}-{$jamSelesaiRs}) berbeda dengan HFIS ({$jamMulaiHfis}-{$jamSelesaiHfis})",
            ];
        }

        return [
            'BEDA_KUOTA',
            "Selisih kuota: RS={$kuotaRs}, HFIS={$kuotaHfis}",
        ];
    }

    private function normalizeTime(string $raw, bool $allowDash = false): string
    {
        $trimmed = trim($raw);
        if ($allowDash && ($trimmed === '' || $trimmed === '-')) {
            return '-';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', $trimmed, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
        }
        return $allowDash ? '-' : '00:00';
    }

    /**
     * Ambil daftar jadwal dokter HFIS vs RS dengan dukungan filter pencarian
     */
    public function getJadwalList(array $filters = []): array
    {
        $rows = $this->fetchAllRawRows();

        $hariFilter   = strtoupper(trim((string) ($filters['hari'] ?? '')));
        $poliFilter   = strtoupper(trim((string) ($filters['kd_poli'] ?? '')));
        $statusFilter = strtoupper(trim((string) ($filters['status'] ?? '')));
        $keyword      = strtolower(trim((string) ($filters['q'] ?? '')));

        return array_values(array_filter($rows, static function (array $row) use ($hariFilter, $poliFilter, $statusFilter, $keyword): bool {
            if ($hariFilter !== '' && strtoupper((string) ($row['hari_kerja'] ?? '')) !== $hariFilter) {
                return false;
            }
            if ($poliFilter !== '' && strtoupper((string) ($row['kd_poli_rs'] ?? '')) !== $poliFilter) {
                return false;
            }
            if ($statusFilter !== '') {
                $rowStatus = strtoupper((string) ($row['status_sinkron'] ?? ''));
                if ($statusFilter === 'KONFLIK') {
                    if ($rowStatus === 'SINKRON') {
                        return false;
                    }
                } elseif ($rowStatus !== $statusFilter) {
                    return false;
                }
            }
            if ($keyword !== '') {
                $haystack = strtolower(implode(' ', [
                    $row['nm_dokter_rs'] ?? '',
                    $row['kd_dokter_rs'] ?? '',
                    $row['kd_dokter_bpjs'] ?? '',
                    $row['nm_poli_rs'] ?? '',
                    $row['kd_poli_rs'] ?? '',
                    $row['kd_poli_bpjs'] ?? '',
                ]));
                if (strpos($haystack, $keyword) === false) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Ambil ringkasan KPI untuk kartu eksekutif manajemen
     */
    public function getSummaryKpi(): array
    {
        $rows = $this->fetchAllRawRows();

        $totalJadwal      = count($rows);
        $totalSinkron     = 0;
        $totalBedaJam     = 0;
        $totalBedaKuota   = 0;
        $totalBelumMap    = 0;
        $totalTidakDiHfis = 0;

        foreach ($rows as $row) {
            $st = strtoupper((string) ($row['status_sinkron'] ?? ''));
            if ($st === 'SINKRON') {
                $totalSinkron++;
            } elseif ($st === 'BEDA_JAM' || $st === 'BEDA_JAM_DAN_KUOTA') {
                $totalBedaJam++;
            } elseif ($st === 'BEDA_KUOTA') {
                $totalBedaKuota++;
            } elseif ($st === 'BELUM_MAPPING') {
                $totalBelumMap++;
            } elseif ($st === 'TIDAK_ADA_DI_HFIS') {
                $totalTidakDiHfis++;
            }
        }

        $totalKonflik = $totalJadwal - $totalSinkron;
        $persentase   = $totalJadwal > 0 ? round(($totalSinkron / $totalJadwal) * 100, 1) : 0.0;

        return [
            'total_jadwal'         => $totalJadwal,
            'total_sinkron'        => $totalSinkron,
            'total_konflik'        => $totalKonflik,
            'total_beda_jam'       => $totalBedaJam,
            'total_beda_kuota'     => $totalBedaKuota,
            'total_belum_mapping'  => $totalBelumMap,
            'total_tidak_ada_hfis' => $totalTidakDiHfis,
            'persentase_kepatuhan' => $persentase,
            'last_sync'            => $this->getLatestSyncMeta(),
        ];
    }

    /**
     * Ambil metadata sinkronisasi terakhir
     */
    public function getLatestSyncMeta(): ?array
    {
        if ($this->pdo !== null) {
            try {
                $stmt = $this->pdo->query(
                    "SELECT `waktu_sync`, `kode_ppk`, `pengirim`, `total_jadwal`, `total_sinkron`, `total_konflik`, `persentase_kepatuhan`, `ip_pengirim`
                     FROM `sync_log`
                     ORDER BY `id` DESC
                     LIMIT 1"
                );
                $row = $stmt ? $stmt->fetch() : false;
                if (is_array($row) && !empty($row)) {
                    return $row;
                }
            } catch (Throwable $e) {
                // Lanjut ke cache file
            }
        }

        if (is_file($this->cacheFile)) {
            $decoded = json_decode((string) @file_get_contents($this->cacheFile), true);
            if (is_array($decoded) && isset($decoded['meta']) && is_array($decoded['meta'])) {
                return $decoded['meta'];
            }
        }

        return null;
    }

    /**
     * Ambil seluruh baris jadwal dari DB (atau fallback ke file cache snapshot)
     */
    private function fetchAllRawRows(): array
    {
        if ($this->pdo !== null) {
            try {
                $stmt = $this->pdo->query(
                    "SELECT *
                     FROM `jadwal_hfis`
                     ORDER BY
                       FIELD(`hari_kerja`, 'SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU','MINGGU'),
                       `nm_poli_rs` ASC,
                       `jam_mulai_rs` ASC,
                       `nm_dokter_rs` ASC"
                );
                $rows = $stmt ? $stmt->fetchAll() : [];
                if (is_array($rows) && !empty($rows)) {
                    return $rows;
                }
            } catch (Throwable $e) {
                // Fallback ke file cache
            }
        }

        if (is_file($this->cacheFile)) {
            $decoded = json_decode((string) @file_get_contents($this->cacheFile), true);
            if (is_array($decoded) && isset($decoded['data']) && is_array($decoded['data'])) {
                return $decoded['data'];
            }
        }

        return [];
    }
}
