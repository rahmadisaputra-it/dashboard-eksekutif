<?php
declare(strict_types=1);

namespace App\Modules\Keuangan\Models;

use App\Config\Database;
use PDO;
use Throwable;

class PerkiraanBiayaModel
{
    public const MARKUP_OBAT                 = 0.41;
    public const BIAYA_ADM                   = 150000.0;
    public const THRESHOLD_TIDAK_TERKENDALI  = 1.00;
    public const THRESHOLD_KURANG_TERKENDALI = 0.86;
    public const THRESHOLD_CUKUP_TERKENDALI  = 0.71;

    private ?PDO $appPdo;
    private ?PDO $khanzaPdo;
    private string $cacheDir;
    private string $snapshotFile;

    public function __construct()
    {
        $db              = Database::getInstance();
        $this->appPdo    = $db->getAppConnection();
        $this->khanzaPdo = $db->getConnection();
        $this->cacheDir  = dirname(__DIR__, 4) . '/storage/cache';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
        $this->snapshotFile = $this->cacheDir . '/kendali_biaya_snapshot.json';

        $this->seedTarifInacbgIfEmpty();
    }

    /**
     * Migrasikan referensi inacbg_dummy dari Khanza ke dashboard_eksekutif.tarif_inacbg bila tabel mandiri masih kosong
     */
    private function seedTarifInacbgIfEmpty(): void
    {
        if ($this->appPdo === null || $this->khanzaPdo === null) {
            return;
        }
        try {
            $cnt = (int) $this->appPdo->query('SELECT COUNT(*) FROM `tarif_inacbg`')->fetchColumn();
            if ($cnt > 0) {
                return;
            }

            $stmt = $this->khanzaPdo->query(
                'SELECT UPPER(TRIM(i.kd_penyakit)) AS kd_penyakit, p.nm_penyakit, i.biaya
                 FROM inacbg_dummy i
                 LEFT JOIN penyakit p ON i.kd_penyakit = p.kd_penyakit'
            );
            $rows = $stmt ? $stmt->fetchAll() : [];
            if (!is_array($rows) || empty($rows)) {
                return;
            }

            $now = date('Y-m-d H:i:s');
            $ins = $this->appPdo->prepare(
                'INSERT INTO `tarif_inacbg` (`kd_penyakit`, `nm_penyakit`, `biaya`, `updated_at`)
                 VALUES (:kd, :nm, :biaya, :u)
                 ON DUPLICATE KEY UPDATE `nm_penyakit` = VALUES(`nm_penyakit`), `biaya` = VALUES(`biaya`)'
            );
            foreach ($rows as $r) {
                $kd = trim((string) ($r['kd_penyakit'] ?? ''));
                if ($kd === '') {
                    continue;
                }
                $ins->execute([
                    ':kd'    => substr($kd, 0, 15),
                    ':nm'    => !empty($r['nm_penyakit']) ? substr(trim((string) $r['nm_penyakit']), 0, 200) : null,
                    ':biaya' => (float) ($r['biaya'] ?? 0),
                    ':u'     => $now,
                ]);
            }
        } catch (Throwable $e) {
            // Abaikan bila tabel inacbg_dummy tidak ada di Khanza
        }
    }

    /**
     * Ambil seluruh data Dashboard Kendali Biaya Rawat Inap dari database mandiri dashboard_eksekutif
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getDashboardData(array $filters, bool $forceRefresh = false): array
    {
        $search       = trim((string) ($filters['search'] ?? ''));
        $bangsal      = trim((string) ($filters['bangsal'] ?? ''));
        $statusFilter = trim((string) ($filters['status'] ?? ''));

        // Jika forceRefresh diminta dan koneksi lokal Khanza ada, tarik & simpan snapshot terbaru
        if ($forceRefresh) {
            if ($this->khanzaPdo !== null) {
                try {
                    $payload = $this->extractPayloadFromKhanza();
                    if (!empty($payload['data'])) {
                        $this->saveSnapshot($payload, '127.0.0.1');
                    }
                } catch (Throwable $e) {
                    // Lanjut gunakan snapshot mandiri
                }
            } else {
                $this->recalculateStoredSnapshot();
            }
        }

        $allRows   = $this->loadStoredRows();
        $cachedAt  = $allRows['cached_at'];
        $pasienAll = $allRows['data'];

        // Filter berdasarkan bangsal, pencarian teks, dan status kendali biaya
        $patients = array_values(array_filter($pasienAll, static function (array $r) use ($bangsal, $search, $statusFilter): bool {
            if ($bangsal !== '' && strcasecmp((string) ($r['nm_bangsal'] ?? ''), $bangsal) !== 0) {
                return false;
            }
            if ($statusFilter !== '' && ($r['keterangan'] ?? '') !== $statusFilter) {
                return false;
            }
            if ($search !== '') {
                $hay = strtolower(
                    ($r['no_rawat'] ?? '') . ' ' .
                    ($r['no_rkm_medis'] ?? '') . ' ' .
                    ($r['nm_pasien'] ?? '') . ' ' .
                    ($r['lokasi'] ?? '') . ' ' .
                    ($r['diagnosa_awal'] ?? '')
                );
                if (!str_contains($hay, strtolower($search))) {
                    return false;
                }
            }
            return true;
        }));

        return [
            'patients'    => $patients,
            'totalsData'  => $this->computeTotals($patients),
            'stats'       => $this->getSummary($patients),
            'bangsalList' => $this->getBangsalList($pasienAll),
            'cached_at'   => $cachedAt,
        ];
    }

    /**
     * Baca baris kendali biaya dari tabel dashboard_eksekutif.kendali_biaya (dengan auto-seed bila kosong)
     *
     * @return array{data: array<int, array<string, mixed>>, cached_at: string}
     */
    private function loadStoredRows(): array
    {
        if ($this->appPdo !== null) {
            try {
                $stmt = $this->appPdo->query(
                    'SELECT * FROM `kendali_biaya` ORDER BY `nm_bangsal` ASC, `kd_kamar` ASC, `no_rawat` ASC'
                );
                $rows = $stmt ? $stmt->fetchAll() : [];
                if (is_array($rows) && !empty($rows)) {
                    $syncedAt = (string) ($rows[0]['synced_at'] ?? date('Y-m-d H:i:s'));
                    $timeOnly = date('H:i:s', strtotime($syncedAt) ?: time());
                    foreach ($rows as &$r) {
                        $r['rasio'] = $r['rasio'] !== null ? (float) $r['rasio'] : null;
                    }
                    unset($r);
                    return ['data' => $rows, 'cached_at' => $timeOnly];
                }
            } catch (Throwable $e) {
                // Lanjut ke fallback
            }
        }

        // Bila tabel mandiri masih kosong dan koneksi Khanza tersedia, tarik otomatis pertama kali
        if ($this->khanzaPdo !== null) {
            try {
                $payload = $this->extractPayloadFromKhanza();
                if (!empty($payload['data'])) {
                    $this->saveSnapshot($payload, '127.0.0.1');
                    return $this->loadStoredRowsFromSnapshotFile();
                }
            } catch (Throwable $e) {
                // Lanjut ke file cache
            }
        }

        return $this->loadStoredRowsFromSnapshotFile();
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, cached_at: string}
     */
    private function loadStoredRowsFromSnapshotFile(): array
    {
        if (is_file($this->snapshotFile)) {
            $decoded = json_decode((string) @file_get_contents($this->snapshotFile), true);
            if (is_array($decoded) && isset($decoded['data']) && is_array($decoded['data'])) {
                return [
                    'data'      => $decoded['data'],
                    'cached_at' => (string) ($decoded['cached_at'] ?? date('H:i:s')),
                ];
            }
        }

        // Fallback ke file cache lama bila ada
        $legacyFiles = glob($this->cacheDir . '/kendali_biaya_*.json');
        if (is_array($legacyFiles)) {
            foreach ($legacyFiles as $f) {
                $decoded = json_decode((string) @file_get_contents($f), true);
                if (is_array($decoded) && !empty($decoded['data']) && is_array($decoded['data'])) {
                    return [
                        'data'      => $decoded['data'],
                        'cached_at' => (string) ($decoded['cached_at'] ?? date('H:i:s')),
                    ];
                }
            }
        }

        return ['data' => [], 'cached_at' => date('H:i:s')];
    }

    /**
     * Simpan snapshot data kendali biaya rawat inap yang dikirim via POST API dari service Khanza
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function saveSnapshot(array $payload, string $clientIp = '127.0.0.1'): array
    {
        $rawItems = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        if (empty($rawItems)) {
            throw new \InvalidArgumentException('Payload array "data" kendali biaya kosong atau tidak valid.');
        }

        $waktuSync = !empty($payload['waktu_sync']) && is_string($payload['waktu_sync'])
            ? date('Y-m-d H:i:s', strtotime($payload['waktu_sync']) ?: time())
            : date('Y-m-d H:i:s');

        $pengirim = substr(trim((string) ($payload['pengirim'] ?? 'khanza_push_worker')), 0, 100);

        // Bila payload menyertakan master tarif inacbg dari Khanza, sinkronkan ke tarif_inacbg
        if (!empty($payload['tarif_inacbg_master']) && is_array($payload['tarif_inacbg_master']) && $this->appPdo !== null) {
            try {
                $insTarif = $this->appPdo->prepare(
                    'INSERT INTO `tarif_inacbg` (`kd_penyakit`, `nm_penyakit`, `biaya`, `updated_at`)
                     VALUES (:kd, :nm, :biaya, :u)
                     ON DUPLICATE KEY UPDATE
                        `nm_penyakit` = COALESCE(VALUES(`nm_penyakit`), `nm_penyakit`),
                        `biaya` = VALUES(`biaya`),
                        `updated_at` = VALUES(`updated_at`)'
                );
                foreach ($payload['tarif_inacbg_master'] as $tm) {
                    if (!is_array($tm) || empty($tm['kd_penyakit'])) {
                        continue;
                    }
                    $insTarif->execute([
                        ':kd'    => strtoupper(substr(trim((string) $tm['kd_penyakit']), 0, 15)),
                        ':nm'    => !empty($tm['nm_penyakit']) ? substr(trim((string) $tm['nm_penyakit']), 0, 200) : null,
                        ':biaya' => (float) ($tm['biaya'] ?? 0),
                        ':u'     => $waktuSync,
                    ]);
                }
            } catch (Throwable $e) {
                // Abaikan
            }
        }

        $tarifMap       = $this->loadAppTarifInacbgMap();
        $normalizedRows = [];

        foreach ($rawItems as $item) {
            if (!is_array($item) || empty($item['no_rawat'])) {
                continue;
            }
            $normalizedRows[] = $this->normalizeAndCalculateRow($item, $tarifMap, $waktuSync);
        }

        if (empty($normalizedRows)) {
            throw new \InvalidArgumentException('Tidak ada baris pasien rawat inap yang valid di dalam payload.');
        }

        $savedToDb = false;
        if ($this->appPdo !== null) {
            try {
                $this->appPdo->beginTransaction();
                $this->appPdo->exec('DELETE FROM `kendali_biaya`');

                $stmt = $this->appPdo->prepare(
                    'INSERT INTO `kendali_biaya` (
                        `no_rawat`, `no_rkm_medis`, `nm_pasien`, `kd_kamar`, `nm_bangsal`, `lokasi`,
                        `perujuk`, `diagnosa_awal`,
                        `actual_obat`, `actual_lab`, `actual_radiologi`, `actual_kamar`,
                        `actual_darah`, `actual_oksigen`, `actual_diet`, `actual_operasi`, `manual_tarif`,
                        `modal_jasa_medis`, `modal_obat`, `modal_lab`, `modal_radiologi`,
                        `modal_darah`, `modal_oksigen`, `modal_diet`, `modal_kamar`,
                        `modal_operasi`, `modal_biaya_bayi`, `modal_adm`,
                        `tarif_rs`, `tarif_inacbg`, `selisih`, `rasio`, `keterangan`, `synced_at`
                    ) VALUES (
                        :no_rawat, :no_rkm_medis, :nm_pasien, :kd_kamar, :nm_bangsal, :lokasi,
                        :perujuk, :diagnosa_awal,
                        :actual_obat, :actual_lab, :actual_radiologi, :actual_kamar,
                        :actual_darah, :actual_oksigen, :actual_diet, :actual_operasi, :manual_tarif,
                        :modal_jasa_medis, :modal_obat, :modal_lab, :modal_radiologi,
                        :modal_darah, :modal_oksigen, :modal_diet, :modal_kamar,
                        :modal_operasi, :modal_biaya_bayi, :modal_adm,
                        :tarif_rs, :tarif_inacbg, :selisih, :rasio, :keterangan, :synced_at
                    )'
                );

                foreach ($normalizedRows as $r) {
                    $stmt->execute([
                        ':no_rawat'         => $r['no_rawat'],
                        ':no_rkm_medis'     => $r['no_rkm_medis'],
                        ':nm_pasien'        => $r['nm_pasien'],
                        ':kd_kamar'         => $r['kd_kamar'],
                        ':nm_bangsal'       => $r['nm_bangsal'],
                        ':lokasi'           => $r['lokasi'],
                        ':perujuk'          => $r['perujuk'],
                        ':diagnosa_awal'    => $r['diagnosa_awal'],
                        ':actual_obat'      => $r['actual_obat'],
                        ':actual_lab'       => $r['actual_lab'],
                        ':actual_radiologi' => $r['actual_radiologi'],
                        ':actual_kamar'     => $r['actual_kamar'],
                        ':actual_darah'     => $r['actual_darah'],
                        ':actual_oksigen'   => $r['actual_oksigen'],
                        ':actual_diet'      => $r['actual_diet'],
                        ':actual_operasi'   => $r['actual_operasi'],
                        ':manual_tarif'     => $r['manual_tarif'],
                        ':modal_jasa_medis' => $r['modal_jasa_medis'],
                        ':modal_obat'       => $r['modal_obat'],
                        ':modal_lab'        => $r['modal_lab'],
                        ':modal_radiologi'  => $r['modal_radiologi'],
                        ':modal_darah'      => $r['modal_darah'],
                        ':modal_oksigen'    => $r['modal_oksigen'],
                        ':modal_diet'       => $r['modal_diet'],
                        ':modal_kamar'      => $r['modal_kamar'],
                        ':modal_operasi'    => $r['modal_operasi'],
                        ':modal_biaya_bayi' => $r['modal_biaya_bayi'],
                        ':modal_adm'        => $r['modal_adm'],
                        ':tarif_rs'         => $r['tarif_rs'],
                        ':tarif_inacbg'     => $r['tarif_inacbg'],
                        ':selisih'          => $r['selisih'],
                        ':rasio'            => $r['rasio'],
                        ':keterangan'       => $r['keterangan'],
                        ':synced_at'        => $r['synced_at'],
                    ]);
                }

                $this->appPdo->commit();
                $savedToDb = true;
            } catch (Throwable $e) {
                if ($this->appPdo->inTransaction()) {
                    $this->appPdo->rollBack();
                }
            }
        }

        @file_put_contents(
            $this->snapshotFile,
            json_encode([
                'cached_at'  => date('H:i:s', strtotime($waktuSync) ?: time()),
                'waktu_sync' => $waktuSync,
                'pengirim'   => $pengirim,
                'data'       => $normalizedRows,
            ], JSON_UNESCAPED_UNICODE)
        );

        $stats = $this->getSummary($normalizedRows);
        Database::logApiPush(
            'kendali_biaya',
            count($normalizedRows),
            $pengirim,
            $clientIp,
            'SUKSES',
            sprintf(
                'Pasien: %d, Tidak Terkendali: %d, Total Modal RS: Rp %s',
                $stats['total_pasien'],
                $stats['tidak_terkendali'],
                number_format((float) $stats['total_tarif_rs'], 0, ',', '.')
            )
        );

        return [
            'waktu_sync'         => $waktuSync,
            'pengirim'           => $pengirim,
            'total_pasien'       => $stats['total_pasien'],
            'tidak_terkendali'   => $stats['tidak_terkendali'],
            'kurang_terkendali'  => $stats['kurang_terkendali'],
            'cukup_terkendali'   => $stats['cukup_terkendali'],
            'total_tarif_rs'     => round((float) $stats['total_tarif_rs'], 2),
            'total_tarif_inacbg' => round((float) $stats['total_tarif_inacbg'], 2),
            'ip_pengirim'        => substr($clientIp, 0, 45),
            'storage_mode'       => $savedToDb ? 'mysql_and_cache' : 'cache_snapshot',
        ];
    }

    /**
     * Normalisasi dan kalkulasi 21 kolom Kendali Biaya per pasien
     *
     * @param array<string, mixed> $item
     * @param array<string, float> $tarifMap
     * @return array<string, mixed>
     */
    private function normalizeAndCalculateRow(array $item, array $tarifMap, string $waktuSync): array
    {
        $noRawat      = substr(trim((string) ($item['no_rawat'] ?? '')), 0, 20);
        $noRkmMedis   = substr(trim((string) ($item['no_rkm_medis'] ?? '')), 0, 20);
        $nmPasien     = substr(trim((string) ($item['nm_pasien'] ?? '')), 0, 150);
        $kdKamar      = substr(trim((string) ($item['kd_kamar'] ?? '-')), 0, 20);
        $nmBangsal    = substr(trim((string) ($item['nm_bangsal'] ?? '-')), 0, 100);
        $lokasi       = !empty($item['lokasi'])
            ? substr(trim((string) $item['lokasi']), 0, 150)
            : trim($kdKamar . ' ' . $nmBangsal);
        $perujuk      = substr(trim((string) ($item['perujuk'] ?? '-')), 0, 150);
        $diagnosaAwal = substr(trim((string) ($item['diagnosa_awal'] ?? '-')), 0, 100);

        // Ambil nilai actual_* (atau rekonstruksi dari modal_* bila sumber berasal dari cache lama)
        $actualObat      = isset($item['actual_obat'])
            ? (float) $item['actual_obat']
            : ((float) ($item['modal_obat'] ?? 0.0)) * (1.0 + self::MARKUP_OBAT);
        $actualLab       = isset($item['actual_lab'])
            ? (float) $item['actual_lab']
            : ((float) ($item['modal_lab'] ?? 0.0)) * 2.0;
        $actualRadiologi = isset($item['actual_radiologi'])
            ? (float) $item['actual_radiologi']
            : ((float) ($item['modal_radiologi'] ?? 0.0)) * 2.0;
        $actualKamar     = isset($item['actual_kamar'])
            ? (float) $item['actual_kamar']
            : ((float) ($item['modal_kamar'] ?? 0.0)) * 2.0;
        $actualDarah     = isset($item['actual_darah'])
            ? (float) $item['actual_darah']
            : (((float) ($item['modal_darah'] ?? 0.0)) / 0.65);
        $actualOksigen   = isset($item['actual_oksigen'])
            ? (float) $item['actual_oksigen']
            : (((float) ($item['modal_oksigen'] ?? 0.0)) / 0.40);
        $actualDiet      = isset($item['actual_diet'])
            ? (float) $item['actual_diet']
            : ((float) ($item['modal_diet'] ?? 0.0)) * 2.0;
        $actualOperasi   = isset($item['actual_operasi'])
            ? (float) $item['actual_operasi']
            : ((float) ($item['modal_operasi'] ?? 0.0)) * 2.0;
        $manualTarif     = (float) ($item['manual_tarif'] ?? 0.0);

        // Hitung tarif INA-CBG berdasarkan diagnosa_awal terhadap tabel mandiri tarif_inacbg
        $tarifInacbg = 0.0;
        if ($diagnosaAwal !== '' && $diagnosaAwal !== '-') {
            $codes = array_filter(array_map(
                static fn(string $c): string => strtoupper(trim($c)),
                explode(',', $diagnosaAwal)
            ));
            foreach ($codes as $kd) {
                if (isset($tarifMap[$kd])) {
                    $tarifInacbg += $tarifMap[$kd];
                }
            }
        }
        if ($tarifInacbg <= 0.0) {
            $tarifInacbg = $manualTarif > 0.0 ? $manualTarif : (float) ($item['tarif_inacbg'] ?? 0.0);
        }

        $isBayi = (bool) preg_match('/\b(by|bayi)\b/i', $nmPasien);
        if ($isBayi) {
            $modalJasaMedis = 0.0;
            $modalObat      = 0.0;
            $modalLab       = 0.0;
            $modalRadiologi = 0.0;
            $modalDarah     = 0.0;
            $modalOksigen   = 0.0;
            $modalDiet      = 0.0;
            $modalKamar     = 0.0;
            $modalOperasi   = 0.0;
            $modalBiayaBayi = 250000.0;
            $modalAdm       = 0.0;
        } else {
            $modalJasaMedis = $tarifInacbg * 0.35;
            $modalObat      = $actualObat > 0 ? $actualObat / (1.0 + self::MARKUP_OBAT) : 0.0;
            $modalLab       = $actualLab * 0.50;
            $modalRadiologi = $actualRadiologi * 0.50;
            $modalDarah     = $actualDarah * 0.65;
            $modalOksigen   = $actualOksigen * 0.40;
            $modalDiet      = $actualDiet * 0.50;
            $modalKamar     = $actualKamar * 0.50;
            $modalOperasi   = $actualOperasi * 0.50;
            $modalBiayaBayi = 0.0;
            $modalAdm       = self::BIAYA_ADM;
        }

        $tarifRs = $modalJasaMedis + $modalObat + $modalLab + $modalRadiologi
                 + $modalDarah + $modalOksigen + $modalDiet + $modalKamar
                 + $modalOperasi + $modalBiayaBayi + $modalAdm;

        $selisih    = $tarifInacbg - $tarifRs;
        $rasio      = $tarifInacbg > 0 ? ($tarifRs / $tarifInacbg) : null;
        $keterangan = $this->hitungKeterangan($rasio);

        return [
            'no_rawat'         => $noRawat,
            'no_rkm_medis'     => $noRkmMedis,
            'nm_pasien'        => $nmPasien,
            'kd_kamar'         => $kdKamar,
            'nm_bangsal'       => $nmBangsal,
            'lokasi'           => $lokasi,
            'perujuk'          => $perujuk !== '' ? $perujuk : '-',
            'diagnosa_awal'    => $diagnosaAwal !== '' ? $diagnosaAwal : '-',
            'actual_obat'      => $actualObat,
            'actual_lab'       => $actualLab,
            'actual_radiologi' => $actualRadiologi,
            'actual_kamar'     => $actualKamar,
            'actual_darah'     => $actualDarah,
            'actual_oksigen'   => $actualOksigen,
            'actual_diet'      => $actualDiet,
            'actual_operasi'   => $actualOperasi,
            'manual_tarif'     => $manualTarif,
            'modal_jasa_medis' => $modalJasaMedis,
            'modal_obat'       => $modalObat,
            'modal_lab'        => $modalLab,
            'modal_radiologi'  => $modalRadiologi,
            'modal_darah'      => $modalDarah,
            'modal_oksigen'    => $modalOksigen,
            'modal_diet'       => $modalDiet,
            'modal_kamar'      => $modalKamar,
            'modal_operasi'    => $modalOperasi,
            'modal_biaya_bayi' => $modalBiayaBayi,
            'modal_adm'        => $modalAdm,
            'tarif_rs'         => $tarifRs,
            'tarif_inacbg'     => $tarifInacbg,
            'selisih'          => $selisih,
            'rasio'            => $rasio,
            'keterangan'       => $keterangan,
            'synced_at'        => $waktuSync,
        ];
    }

    /**
     * Hitung ulang seluruh baris di kendali_biaya saat admin mengubah referensi tarif INA-CBG
     */
    public function recalculateStoredSnapshot(): void
    {
        if ($this->appPdo === null) {
            return;
        }
        try {
            $stmt = $this->appPdo->query('SELECT * FROM `kendali_biaya`');
            $rows = $stmt ? $stmt->fetchAll() : [];
            if (!is_array($rows) || empty($rows)) {
                return;
            }
            $this->saveSnapshot([
                'waktu_sync' => (string) ($rows[0]['synced_at'] ?? date('Y-m-d H:i:s')),
                'pengirim'   => 'recalc_tarif_inacbg',
                'data'       => $rows,
            ], '127.0.0.1');
        } catch (Throwable $e) {
            // Abaikan
        }
    }

    /**
     * @return array<string, float>
     */
    private function loadAppTarifInacbgMap(): array
    {
        $map = [];
        if ($this->appPdo === null) {
            return $map;
        }
        try {
            $stmt = $this->appPdo->query('SELECT UPPER(TRIM(kd_penyakit)) AS kd, biaya FROM `tarif_inacbg`');
            $rows = $stmt ? $stmt->fetchAll() : [];
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $map[(string) $r['kd']] = (float) $r['biaya'];
                }
            }
        } catch (Throwable $e) {
            // Abaikan
        }
        return $map;
    }

    /**
     * Bersihkan cache & sinkronkan ulang kalkulasi
     */
    public function clearCache(string $search = '', string $bangsal = ''): void
    {
        $files = glob($this->cacheDir . '/kendali_biaya_*.json');
        if (is_array($files)) {
            foreach ($files as $f) {
                if (basename($f) !== 'kendali_biaya_snapshot.json') {
                    @unlink($f);
                }
            }
        }
        $this->recalculateStoredSnapshot();
    }

    /**
     * Ekstraksi payload mentah pasien rawat inap aktif dari database lokal SIMRS Khanza.
     * Dipanggil oleh worker pengirim (api/khanza-push-worker.php) di sisi server Khanza.
     *
     * @return array<string, mixed>
     */
    public function extractPayloadFromKhanza(): array
    {
        if ($this->khanzaPdo === null) {
            return ['data' => [], 'tarif_inacbg_master' => []];
        }

        $sql = "SELECT
                    kamar_inap.no_rawat,
                    reg_periksa.no_rkm_medis,
                    pasien.nm_pasien,
                    bangsal.nm_bangsal,
                    kamar.kd_kamar,
                    kamar_inap.diagnosa_awal
                FROM kamar_inap
                INNER JOIN reg_periksa ON kamar_inap.no_rawat = reg_periksa.no_rawat
                INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
                INNER JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
                INNER JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
                WHERE kamar_inap.stts_pulang = '-'
                ORDER BY bangsal.nm_bangsal ASC, kamar.kd_kamar ASC";

        try {
            $stmt = $this->khanzaPdo->query($sql);
            $rows = $stmt ? $stmt->fetchAll() : [];
        } catch (Throwable $e) {
            $rows = [];
        }

        if (empty($rows)) {
            return ['data' => [], 'tarif_inacbg_master' => []];
        }

        $noRawatList  = array_values(array_unique(array_column($rows, 'no_rawat')));
        $obatMap      = $this->batchObat($noRawatList);
        $labMap       = $this->batchLab($noRawatList);
        $radiologiMap = $this->batchRadiologi($noRawatList);
        $kamarMap     = $this->batchKamar($noRawatList);
        $tindakanMap  = $this->batchTindakan($noRawatList);
        $perujukMap   = $this->batchPerujuk($noRawatList);
        $manualMap    = $this->batchManualTarif($noRawatList);

        $data = [];
        foreach ($rows as $r) {
            $nr       = (string) $r['no_rawat'];
            $tindakan = $tindakanMap[$nr] ?? ['darah' => 0.0, 'oksigen' => 0.0, 'diet' => 0.0, 'operasi' => 0.0];

            $data[] = [
                'no_rawat'         => $nr,
                'no_rkm_medis'     => (string) ($r['no_rkm_medis'] ?? ''),
                'nm_pasien'        => (string) ($r['nm_pasien'] ?? ''),
                'kd_kamar'         => (string) ($r['kd_kamar'] ?? '-'),
                'nm_bangsal'       => (string) ($r['nm_bangsal'] ?? '-'),
                'lokasi'           => trim(($r['kd_kamar'] ?? '') . ' ' . ($r['nm_bangsal'] ?? '')),
                'perujuk'          => (string) ($perujukMap[$nr] ?? '-'),
                'diagnosa_awal'    => (string) ($r['diagnosa_awal'] ?? '-'),
                'actual_obat'      => (float) ($obatMap[$nr] ?? 0.0),
                'actual_lab'       => (float) ($labMap[$nr] ?? 0.0),
                'actual_radiologi' => (float) ($radiologiMap[$nr] ?? 0.0),
                'actual_kamar'     => (float) ($kamarMap[$nr] ?? 0.0),
                'actual_darah'     => (float) ($tindakan['darah'] ?? 0.0),
                'actual_oksigen'   => (float) ($tindakan['oksigen'] ?? 0.0),
                'actual_diet'      => (float) ($tindakan['diet'] ?? 0.0),
                'actual_operasi'   => (float) ($tindakan['operasi'] ?? 0.0),
                'manual_tarif'     => (float) ($manualMap[$nr] ?? 0.0),
            ];
        }

        // Sertakan juga kamus tarif inacbg_dummy dari Khanza bila ada
        $tarifMaster = [];
        try {
            $stmtT = $this->khanzaPdo->query(
                'SELECT UPPER(TRIM(i.kd_penyakit)) AS kd_penyakit, p.nm_penyakit, i.biaya
                 FROM inacbg_dummy i
                 LEFT JOIN penyakit p ON i.kd_penyakit = p.kd_penyakit'
            );
            $tarifMaster = $stmtT ? $stmtT->fetchAll() : [];
        } catch (Throwable $e) {
            $tarifMaster = [];
        }

        return [
            'waktu_sync'          => date('Y-m-d H:i:s'),
            'pengirim'            => 'khanza_push_worker',
            'data'                => $data,
            'tarif_inacbg_master' => is_array($tarifMaster) ? $tarifMaster : [],
        ];
    }

    private function makePlaceholders(array $list): string
    {
        return implode(',', array_fill(0, count($list), '?'));
    }

    private function batchObat(array $noRawatList): array
    {
        $map = [];
        if ($this->khanzaPdo === null || empty($noRawatList)) {
            return $map;
        }
        $in = $this->makePlaceholders($noRawatList);

        $queries = [
            "SELECT no_rawat, SUM(total) AS total FROM detail_pemberian_obat WHERE no_rawat IN ({$in}) GROUP BY no_rawat",
            "SELECT no_rawat, SUM(besar_tagihan) AS total FROM tagihan_obat_langsung WHERE no_rawat IN ({$in}) GROUP BY no_rawat",
            "SELECT no_rawat, SUM(hargasatuan * jumlah) AS total FROM beri_obat_operasi WHERE no_rawat IN ({$in}) GROUP BY no_rawat",
        ];

        foreach ($queries as $sql) {
            try {
                $stmt = $this->khanzaPdo->prepare($sql);
                $stmt->execute($noRawatList);
                foreach ($stmt->fetchAll() as $r) {
                    $nr = (string) $r['no_rawat'];
                    $map[$nr] = ($map[$nr] ?? 0.0) + (float) $r['total'];
                }
            } catch (Throwable $e) {}
        }

        return $map;
    }

    private function batchLab(array $noRawatList): array
    {
        $map = [];
        if ($this->khanzaPdo === null || empty($noRawatList)) {
            return $map;
        }
        $in = $this->makePlaceholders($noRawatList);

        foreach (['periksa_lab' => 'biaya', 'detail_periksa_lab' => 'biaya_item'] as $tbl => $col) {
            try {
                $stmt = $this->khanzaPdo->prepare(
                    "SELECT no_rawat, SUM({$col}) AS total FROM {$tbl} WHERE no_rawat IN ({$in}) GROUP BY no_rawat"
                );
                $stmt->execute($noRawatList);
                foreach ($stmt->fetchAll() as $r) {
                    $nr = (string) $r['no_rawat'];
                    $map[$nr] = ($map[$nr] ?? 0.0) + (float) $r['total'];
                }
            } catch (Throwable $e) {}
        }

        return $map;
    }

    private function batchRadiologi(array $noRawatList): array
    {
        $map = [];
        if ($this->khanzaPdo === null || empty($noRawatList)) {
            return $map;
        }
        $in = $this->makePlaceholders($noRawatList);

        try {
            $stmt = $this->khanzaPdo->prepare(
                "SELECT no_rawat, SUM(biaya) AS total FROM periksa_radiologi WHERE no_rawat IN ({$in}) GROUP BY no_rawat"
            );
            $stmt->execute($noRawatList);
            foreach ($stmt->fetchAll() as $r) {
                $map[(string) $r['no_rawat']] = (float) $r['total'];
            }
        } catch (Throwable $e) {}

        return $map;
    }

    private function batchKamar(array $noRawatList): array
    {
        $map = [];
        if ($this->khanzaPdo === null || empty($noRawatList)) {
            return $map;
        }
        $in = $this->makePlaceholders($noRawatList);

        $queries = [
            "SELECT no_rawat, SUM(ttl_biaya) AS total FROM kamar_inap WHERE no_rawat IN ({$in}) GROUP BY no_rawat",
            "SELECT ki.no_rawat, SUM(bs.besar_biaya) AS total FROM biaya_sekali bs INNER JOIN kamar_inap ki ON ki.kd_kamar = bs.kd_kamar WHERE ki.no_rawat IN ({$in}) GROUP BY ki.no_rawat",
            "SELECT ki.no_rawat, SUM(bh.jml * bh.besar_biaya * ki.lama) AS total FROM kamar_inap ki INNER JOIN biaya_harian bh ON ki.kd_kamar = bh.kd_kamar WHERE ki.no_rawat IN ({$in}) GROUP BY ki.no_rawat",
        ];

        foreach ($queries as $sql) {
            try {
                $stmt = $this->khanzaPdo->prepare($sql);
                $stmt->execute($noRawatList);
                foreach ($stmt->fetchAll() as $r) {
                    $nr = (string) $r['no_rawat'];
                    $map[$nr] = ($map[$nr] ?? 0.0) + (float) $r['total'];
                }
            } catch (Throwable $e) {}
        }

        return $map;
    }

    private function batchTindakan(array $noRawatList): array
    {
        $map = [];
        if ($this->khanzaPdo === null || empty($noRawatList)) {
            return $map;
        }
        $in = $this->makePlaceholders($noRawatList);

        $tables = [
            'rawat_inap_dr', 'rawat_inap_drpr', 'rawat_inap_pr',
            'rawat_jl_dr',   'rawat_jl_drpr',   'rawat_jl_pr',
        ];

        foreach ($tables as $tbl) {
            try {
                $sql = "SELECT r.no_rawat,
                            SUM(CASE WHEN COALESCE(j1.nm_perawatan, j2.nm_perawatan) LIKE '%transfusi%' THEN r.biaya_rawat ELSE 0 END) AS darah,
                            SUM(CASE WHEN COALESCE(j1.nm_perawatan, j2.nm_perawatan) LIKE '%oksigen%'   THEN r.biaya_rawat ELSE 0 END) AS oksigen,
                            SUM(CASE WHEN COALESCE(j1.nm_perawatan, j2.nm_perawatan) LIKE '%gizi%'      THEN r.biaya_rawat ELSE 0 END) AS diet,
                            SUM(CASE WHEN
                                COALESCE(j1.nm_perawatan, j2.nm_perawatan) LIKE '%sewa alat%' OR
                                COALESCE(j1.nm_perawatan, j2.nm_perawatan) LIKE '%sewa ok%' OR
                                COALESCE(j1.nm_perawatan, j2.nm_perawatan) LIKE '%sewa vk%'
                            THEN r.biaya_rawat ELSE 0 END) AS operasi
                        FROM {$tbl} r
                        LEFT JOIN jns_perawatan      j1 ON r.kd_jenis_prw = j1.kd_jenis_prw
                        LEFT JOIN jns_perawatan_inap j2 ON r.kd_jenis_prw = j2.kd_jenis_prw
                        WHERE r.no_rawat IN ({$in})
                        GROUP BY r.no_rawat";

                $stmt = $this->khanzaPdo->prepare($sql);
                $stmt->execute($noRawatList);

                foreach ($stmt->fetchAll() as $r) {
                    $nr = (string) $r['no_rawat'];
                    $existing = $map[$nr] ?? ['darah' => 0.0, 'oksigen' => 0.0, 'diet' => 0.0, 'operasi' => 0.0];
                    $map[$nr] = [
                        'darah'   => $existing['darah']   + (float) $r['darah'],
                        'oksigen' => $existing['oksigen'] + (float) $r['oksigen'],
                        'diet'    => $existing['diet']    + (float) $r['diet'],
                        'operasi' => $existing['operasi'] + (float) $r['operasi'],
                    ];
                }
            } catch (Throwable $e) {}
        }

        return $map;
    }

    private function batchPerujuk(array $noRawatList): array
    {
        $map = [];
        if ($this->khanzaPdo === null || empty($noRawatList)) {
            return $map;
        }
        $in = $this->makePlaceholders($noRawatList);

        try {
            $stmt = $this->khanzaPdo->prepare(
                "SELECT no_rawat, perujuk FROM rujuk_masuk WHERE no_rawat IN ({$in})"
            );
            $stmt->execute($noRawatList);
            foreach ($stmt->fetchAll() as $r) {
                $map[(string) $r['no_rawat']] = (string) $r['perujuk'];
            }
        } catch (Throwable $e) {}

        return $map;
    }

    private function batchManualTarif(array $noRawatList): array
    {
        $map = [];
        if ($this->khanzaPdo === null || empty($noRawatList)) {
            return $map;
        }
        $in = $this->makePlaceholders($noRawatList);

        try {
            $stmt = $this->khanzaPdo->prepare(
                "SELECT no_rawat, tarif FROM perkiraan_biaya_ranap WHERE no_rawat IN ({$in})"
            );
            $stmt->execute($noRawatList);
            foreach ($stmt->fetchAll() as $r) {
                $map[(string) $r['no_rawat']] = (float) $r['tarif'];
            }
        } catch (Throwable $e) {}

        return $map;
    }

    private function computeTotals(array $patients): array
    {
        $totals = [
            'jasa_medis'   => 0.0,
            'obat'         => 0.0,
            'lab'          => 0.0,
            'radiologi'    => 0.0,
            'darah'        => 0.0,
            'oksigen'      => 0.0,
            'diet'         => 0.0,
            'kamar'        => 0.0,
            'operasi'      => 0.0,
            'biaya_bayi'   => 0.0,
            'adm'          => 0.0,
            'tarif_rs'     => 0.0,
            'tarif_inacbg' => 0.0,
            'selisih'      => 0.0,
        ];

        foreach ($patients as $item) {
            $totals['jasa_medis']   += (float) $item['modal_jasa_medis'];
            $totals['obat']         += (float) $item['modal_obat'];
            $totals['lab']          += (float) $item['modal_lab'];
            $totals['radiologi']    += (float) $item['modal_radiologi'];
            $totals['darah']        += (float) $item['modal_darah'];
            $totals['oksigen']      += (float) $item['modal_oksigen'];
            $totals['diet']         += (float) $item['modal_diet'];
            $totals['kamar']        += (float) $item['modal_kamar'];
            $totals['operasi']      += (float) $item['modal_operasi'];
            $totals['biaya_bayi']   += (float) $item['modal_biaya_bayi'];
            $totals['adm']          += (float) $item['modal_adm'];
            $totals['tarif_rs']     += (float) $item['tarif_rs'];
            $totals['tarif_inacbg'] += (float) $item['tarif_inacbg'];
            $totals['selisih']      += (float) $item['selisih'];
        }

        return $totals;
    }

    private function hitungKeterangan(?float $rasio): string
    {
        if ($rasio === null) {
            return 'N/A';
        }
        if ($rasio > self::THRESHOLD_TIDAK_TERKENDALI) {
            return 'Tidak Terkendali';
        }
        if ($rasio >= self::THRESHOLD_KURANG_TERKENDALI) {
            return 'Kurang Terkendali';
        }
        if ($rasio >= self::THRESHOLD_CUKUP_TERKENDALI) {
            return 'Cukup Terkendali';
        }

        return 'Sangat Terkendali';
    }

    /**
     * Daftar bangsal diambil dari snapshot pasien aktif di dashboard_eksekutif
     *
     * @param array<int, array<string, mixed>> $pasienAll
     * @return string[]
     */
    public function getBangsalList(array $pasienAll = []): array
    {
        if (!empty($pasienAll)) {
            $names = array_values(array_unique(array_filter(array_column($pasienAll, 'nm_bangsal'))));
            sort($names);
            return $names;
        }

        if ($this->appPdo !== null) {
            try {
                $stmt = $this->appPdo->query('SELECT DISTINCT `nm_bangsal` FROM `kendali_biaya` ORDER BY `nm_bangsal` ASC');
                $list = $stmt ? array_column($stmt->fetchAll(), 'nm_bangsal') : [];
                if (!empty($list)) {
                    return $list;
                }
            } catch (Throwable $e) {}
        }

        return [];
    }

    public function getSummary(array $data): array
    {
        $totalPasien      = count($data);
        $totalTarifRs     = (float) array_sum(array_column($data, 'tarif_rs'));
        $totalTarifInacbg = (float) array_sum(array_column($data, 'tarif_inacbg'));
        $tidakTerkendali  = count(array_filter($data, static fn(array $r): bool => $r['keterangan'] === 'Tidak Terkendali'));
        $kurangTerkendali = count(array_filter($data, static fn(array $r): bool => $r['keterangan'] === 'Kurang Terkendali'));
        $cukupTerkendali  = count(array_filter($data, static fn(array $r): bool => $r['keterangan'] === 'Cukup Terkendali'));

        return [
            'total_pasien'       => $totalPasien,
            'tidak_terkendali'   => $tidakTerkendali,
            'kurang_terkendali'  => $kurangTerkendali,
            'cukup_terkendali'   => $cukupTerkendali,
            'total_tarif_rs'     => $totalTarifRs,
            'total_tarif_inacbg' => $totalTarifInacbg,
        ];
    }

    /**
     * Daftar Tarif INA-CBG dari tabel mandiri dashboard_eksekutif.tarif_inacbg
     */
    public function getInacbgDummy(string $search = ''): array
    {
        if ($this->appPdo === null) {
            return [];
        }

        $sql    = 'SELECT `kd_penyakit`, `nm_penyakit`, `biaya` FROM `tarif_inacbg`';
        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE `kd_penyakit` LIKE :s1 OR `nm_penyakit` LIKE :s2';
            $like = "%{$search}%";
            $params[':s1'] = $like;
            $params[':s2'] = $like;
        }

        $sql .= ' ORDER BY `kd_penyakit` ASC';

        try {
            $stmt = $this->appPdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Simpan / Perbarui Tarif INA-CBG di database mandiri dashboard_eksekutif
     */
    public function upsertInacbg(string $kdPenyakit, float $biaya, ?string $nmPenyakit = null): bool
    {
        if ($this->appPdo === null || $kdPenyakit === '') {
            return false;
        }

        $kd = strtoupper(trim($kdPenyakit));
        if ($nmPenyakit === null && $this->khanzaPdo !== null) {
            try {
                $st = $this->khanzaPdo->prepare('SELECT nm_penyakit FROM penyakit WHERE kd_penyakit = :kd LIMIT 1');
                $st->execute([':kd' => $kd]);
                $val = $st->fetchColumn();
                if (is_string($val) && $val !== '') {
                    $nmPenyakit = $val;
                }
            } catch (Throwable $e) {}
        }

        try {
            $stmt = $this->appPdo->prepare(
                'INSERT INTO `tarif_inacbg` (`kd_penyakit`, `nm_penyakit`, `biaya`, `updated_at`)
                 VALUES (:kd, :nm, :biaya, :u)
                 ON DUPLICATE KEY UPDATE
                    `nm_penyakit` = COALESCE(VALUES(`nm_penyakit`), `nm_penyakit`),
                    `biaya` = VALUES(`biaya`),
                    `updated_at` = VALUES(`updated_at`)'
            );
            $ok = $stmt->execute([
                ':kd'    => $kd,
                ':nm'    => $nmPenyakit,
                ':biaya' => $biaya,
                ':u'     => date('Y-m-d H:i:s'),
            ]);
            $this->recalculateStoredSnapshot();
            return $ok;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Hapus Tarif INA-CBG dari database mandiri dashboard_eksekutif
     */
    public function deleteInacbg(string $kdPenyakit): bool
    {
        if ($this->appPdo === null || $kdPenyakit === '') {
            return false;
        }
        try {
            $stmt = $this->appPdo->prepare('DELETE FROM `tarif_inacbg` WHERE `kd_penyakit` = :kd');
            $ok   = $stmt->execute([':kd' => strtoupper(trim($kdPenyakit))]);
            $this->recalculateStoredSnapshot();
            return $ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}
