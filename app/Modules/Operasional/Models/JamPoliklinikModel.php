<?php
declare(strict_types=1);

namespace App\Modules\Operasional\Models;

use App\Config\Database;
use PDO;
use Throwable;

class JamPoliklinikModel
{
    private ?PDO $pdo;
    private string $cacheFile;

    private const HARI_INDO = [
        1 => 'SENIN',
        2 => 'SELASA',
        3 => 'RABU',
        4 => 'KAMIS',
        5 => 'JUMAT',
        6 => 'SABTU',
        7 => 'AKHAD',
    ];

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getAppConnection();
        $cacheDir = dirname(__DIR__, 4) . '/storage/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $this->cacheFile = $cacheDir . '/jam_poliklinik_snapshot.json';
    }

    /**
     * Simpan snapshot evaluasi jam mulai poliklinik (mendukung pengiriman 1 tanggal maupun banyak tanggal mundur sekaligus)
     */
    public function saveSnapshot(array $payload, string $clientIp = '127.0.0.1'): array
    {
        $rawItems = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        if (empty($rawItems)) {
            throw new \InvalidArgumentException('Payload array "data" kosong atau tidak valid.');
        }

        $defaultTgl = !empty($payload['tgl_pelayanan']) && is_string($payload['tgl_pelayanan'])
            ? date('Y-m-d', strtotime($payload['tgl_pelayanan']) ?: strtotime('-1 day'))
            : date('Y-m-d', strtotime('-1 day'));

        $waktuSync = !empty($payload['waktu_sync']) && is_string($payload['waktu_sync'])
            ? date('Y-m-d H:i:s', strtotime($payload['waktu_sync']) ?: time())
            : date('Y-m-d H:i:s');

        $pengirim       = substr(trim((string) ($payload['pengirim'] ?? 'khanza_sync')), 0, 100);
        $toleransiMenit = isset($payload['toleransi_menit']) ? max(0, (int) $payload['toleransi_menit']) : 15;

        $normalizedRows  = [];
        $groupedByDate   = [];
        $totalTepatWaktu = 0;
        $totalTerlambat  = 0;
        $totalBelumMulai = 0;

        foreach ($rawItems as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $row = $this->normalizeRow($item, $defaultTgl, $waktuSync, $toleransiMenit, $index + 1);
            if ($row['status_kepatuhan'] === 'TEPAT_WAKTU') {
                $totalTepatWaktu++;
            } elseif ($row['status_kepatuhan'] === 'TERLAMBAT') {
                $totalTerlambat++;
            } else {
                $totalBelumMulai++;
            }

            $normalizedRows[] = $row;
            $groupedByDate[$row['tgl_pelayanan']][] = $row;
        }

        $totalJadwal = count($normalizedRows);
        if ($totalJadwal === 0) {
            throw new \InvalidArgumentException('Tidak ada baris data jam poliklinik yang valid untuk disimpan.');
        }

        $sudahMulai = $totalTepatWaktu + $totalTerlambat;
        $persentase = $sudahMulai > 0 ? round(($totalTepatWaktu / $sudahMulai) * 100, 1) : 0.0;

        $datesList = array_keys($groupedByDate);
        sort($datesList);
        $minDate = reset($datesList) ?: $defaultTgl;
        $maxDate = end($datesList) ?: $defaultTgl;

        $meta = [
            'tgl_pelayanan'          => $minDate === $maxDate ? $maxDate : "{$minDate} s/d {$maxDate}",
            'jumlah_tanggal'         => count($datesList),
            'waktu_sync'             => $waktuSync,
            'pengirim'               => $pengirim,
            'toleransi_menit'        => $toleransiMenit,
            'total_jadwal'           => $totalJadwal,
            'total_tepat_waktu'      => $totalTepatWaktu,
            'total_terlambat'        => $totalTerlambat,
            'total_belum_mulai'      => $totalBelumMulai,
            'persentase_tepat_waktu' => $persentase,
            'ip_pengirim'            => substr($clientIp, 0, 45),
        ];

        $savedToDb = false;

        // 1. Simpan ke Tabel MySQL jam_poliklinik per tanggal
        if ($this->pdo !== null) {
            try {
                $this->pdo->beginTransaction();

                $stmtDelete = $this->pdo->prepare("DELETE FROM `jam_poliklinik` WHERE `tgl_pelayanan` = :tgl");
                $stmtInsert = $this->pdo->prepare(
                    "INSERT INTO `jam_poliklinik` (
                        `tgl_pelayanan`, `hari_kerja`, `kd_poli`, `nm_poli`,
                        `kd_dokter`, `nm_dokter`, `jam_mulai_jadwal`, `jam_selesai_jadwal`,
                        `jam_periksa_pertama`, `jam_periksa_terakhir`,
                        `no_rawat_pertama`, `nm_pasien_pertama`,
                        `total_pasien_reg`, `total_pasien_periksa`,
                        `selisih_menit`, `status_kepatuhan`, `catatan_kepatuhan`, `synced_at`
                    ) VALUES (
                        :tgl_pelayanan, :hari_kerja, :kd_poli, :nm_poli,
                        :kd_dokter, :nm_dokter, :jam_mulai_jadwal, :jam_selesai_jadwal,
                        :jam_periksa_pertama, :jam_periksa_terakhir,
                        :no_rawat_pertama, :nm_pasien_pertama,
                        :total_pasien_reg, :total_pasien_periksa,
                        :selisih_menit, :status_kepatuhan, :catatan_kepatuhan, :synced_at
                    )"
                );

                foreach ($groupedByDate as $tglKey => $rowsForDate) {
                    $stmtDelete->execute([':tgl' => $tglKey]);
                    foreach ($rowsForDate as $row) {
                        $stmtInsert->execute([
                            ':tgl_pelayanan'        => $row['tgl_pelayanan'],
                            ':hari_kerja'           => $row['hari_kerja'],
                            ':kd_poli'              => $row['kd_poli'],
                            ':nm_poli'              => $row['nm_poli'],
                            ':kd_dokter'            => $row['kd_dokter'],
                            ':nm_dokter'            => $row['nm_dokter'],
                            ':jam_mulai_jadwal'     => $row['jam_mulai_jadwal'],
                            ':jam_selesai_jadwal'   => $row['jam_selesai_jadwal'],
                            ':jam_periksa_pertama'  => $row['jam_periksa_pertama'],
                            ':jam_periksa_terakhir' => $row['jam_periksa_terakhir'],
                            ':no_rawat_pertama'     => $row['no_rawat_pertama'],
                            ':nm_pasien_pertama'    => $row['nm_pasien_pertama'],
                            ':total_pasien_reg'     => $row['total_pasien_reg'],
                            ':total_pasien_periksa' => $row['total_pasien_periksa'],
                            ':selisih_menit'        => $row['selisih_menit'],
                            ':status_kepatuhan'     => $row['status_kepatuhan'],
                            ':catatan_kepatuhan'    => $row['catatan_kepatuhan'],
                            ':synced_at'            => $row['synced_at'],
                        ]);
                    }
                }

                $this->pdo->commit();
                $savedToDb = true;
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
            }
        }

        // 2. Mirror ke file snapshot JSON (menyimpan per tanggal pelayanan, maksimal 30 hari terakhir)
        $allSnapshots = $this->readAllSnapshotsFromFile();
        foreach ($groupedByDate as $tglKey => $rowsForDate) {
            $allSnapshots[$tglKey] = [
                'meta' => [
                    'tgl_pelayanan' => $tglKey,
                    'waktu_sync'    => $waktuSync,
                    'pengirim'      => $pengirim,
                ],
                'data' => $rowsForDate,
            ];
        }
        krsort($allSnapshots);
        $allSnapshots = array_slice($allSnapshots, 0, 30, true);

        @file_put_contents(
            $this->cacheFile,
            json_encode($allSnapshots, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        Database::logApiPush(
            'jam_poliklinik',
            $totalJadwal,
            $pengirim,
            $clientIp,
            'SUKSES',
            "Tgl: {$meta['tgl_pelayanan']} (" . count($datesList) . " hari), Tepat: {$totalTepatWaktu}, Terlambat: {$totalTerlambat}"
        );

        return array_merge($meta, [
            'storage_mode' => $savedToDb ? 'mysql_and_cache' : 'cache_snapshot',
        ]);
    }

    private function normalizeRow(
        array $item,
        string $defaultTgl,
        string $waktuSync,
        int $toleransiMenit,
        int $fallbackId
    ): array {
        $tgl = !empty($item['tgl_pelayanan']) && is_string($item['tgl_pelayanan'])
            ? date('Y-m-d', strtotime($item['tgl_pelayanan']) ?: time())
            : $defaultTgl;

        $dayNum    = (int) date('N', strtotime($tgl));
        $hariKerja = !empty($item['hari_kerja'])
            ? strtoupper(substr(trim((string) $item['hari_kerja']), 0, 15))
            : (self::HARI_INDO[$dayNum] ?? 'SENIN');

        $kdPoli   = strtoupper(substr(trim((string) ($item['kd_poli'] ?? '-')), 0, 15));
        $nmPoli   = substr(trim((string) ($item['nm_poli'] ?? 'Poliklinik')), 0, 100);
        $kdDokter = substr(trim((string) ($item['kd_dokter'] ?? '-')), 0, 20);
        $nmDokter = substr(trim((string) ($item['nm_dokter'] ?? 'Dokter Spesialis')), 0, 150);

        $jamMulaiJadwal   = $this->normalizeTime((string) ($item['jam_mulai_jadwal'] ?? '08:00'), false);
        $jamSelesaiJadwal = $this->normalizeTime((string) ($item['jam_selesai_jadwal'] ?? '12:00'), false);

        $jamPeriksaPertama  = $this->normalizeTime((string) ($item['jam_periksa_pertama'] ?? '-'), true);
        $jamPeriksaTerakhir = $this->normalizeTime((string) ($item['jam_periksa_terakhir'] ?? '-'), true);

        $noRawatPertama  = !empty($item['no_rawat_pertama']) ? substr(trim((string) $item['no_rawat_pertama']), 0, 20) : null;
        $nmPasienPertama = !empty($item['nm_pasien_pertama']) ? substr(trim((string) $item['nm_pasien_pertama']), 0, 100) : null;

        $totalReg     = max(0, (int) ($item['total_pasien_reg'] ?? 0));
        $totalPeriksa = max(0, (int) ($item['total_pasien_periksa'] ?? 0));

        // Evaluasi Mundur: Hitung selisih menit antara jam_periksa_pertama dan jam_mulai_jadwal
        $selisihMenit = null;
        $status       = 'BELUM_MULAI';
        $catatan      = 'Tidak ada pasien terdaftar pada sesi jadwal ini';

        if ($jamPeriksaPertama !== '-' && $jamPeriksaPertama !== '') {
            $tsJadwal  = strtotime($tgl . ' ' . substr($jamMulaiJadwal, 0, 5) . ':00');
            $tsPeriksa = strtotime($tgl . ' ' . (strlen($jamPeriksaPertama) === 5 ? $jamPeriksaPertama . ':00' : $jamPeriksaPertama));

            if ($tsJadwal !== false && $tsPeriksa !== false) {
                $selisihMenit = (int) round(($tsPeriksa - $tsJadwal) / 60);

                if ($selisihMenit <= $toleransiMenit) {
                    $status = 'TEPAT_WAKTU';
                    if ($selisihMenit < 0) {
                        $catatan = 'Mulai ' . abs($selisihMenit) . ' menit lebih awal dari jadwal';
                    } elseif ($selisihMenit === 0) {
                        $catatan = 'Mulai tepat sesuai jam buka jadwal poli';
                    } else {
                        $catatan = "Tepat waktu (+{$selisihMenit} menit, dalam toleransi {$toleransiMenit} menit)";
                    }
                } else {
                    $status  = 'TERLAMBAT';
                    $catatan = "Terlambat +{$selisihMenit} menit dari jadwal buka ({$jamMulaiJadwal})";
                }
            }
        } elseif ($totalReg > 0) {
            $status  = 'BELUM_MULAI';
            $catatan = "Terdapat {$totalReg} pasien terdaftar, namun SOAP pemeriksaan tidak diisi";
        }

        if (!empty($item['catatan_kepatuhan']) && is_string($item['catatan_kepatuhan'])) {
            $catatan = trim($item['catatan_kepatuhan']);
        }

        return [
            'id'                   => (int) ($item['id'] ?? $fallbackId),
            'tgl_pelayanan'        => $tgl,
            'hari_kerja'           => $hariKerja,
            'kd_poli'              => $kdPoli,
            'nm_poli'              => $nmPoli,
            'kd_dokter'            => $kdDokter,
            'nm_dokter'            => $nmDokter,
            'jam_mulai_jadwal'     => $jamMulaiJadwal,
            'jam_selesai_jadwal'   => $jamSelesaiJadwal,
            'jam_periksa_pertama'  => $jamPeriksaPertama,
            'jam_periksa_terakhir' => $jamPeriksaTerakhir,
            'no_rawat_pertama'     => $noRawatPertama,
            'nm_pasien_pertama'    => $nmPasienPertama,
            'total_pasien_reg'     => $totalReg,
            'total_pasien_periksa' => $totalPeriksa,
            'selisih_menit'        => $selisihMenit,
            'status_kepatuhan'     => $status,
            'catatan_kepatuhan'    => $catatan,
            'synced_at'            => $waktuSync,
        ];
    }

    private function normalizeTime(string $raw, bool $allowDash = false): string
    {
        $trimmed = trim($raw);
        if ($allowDash && ($trimmed === '' || $trimmed === '-')) {
            return '-';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $trimmed, $m)) {
            $hh = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mm = $m[2];
            $ss = isset($m[3]) && $m[3] !== '' ? ':' . $m[3] : '';
            return $hh . ':' . $mm . $ss;
        }
        return $allowDash ? '-' : '08:00';
    }

    /**
     * Tentukan rentang tanggal evaluasi mundur default berdasarkan tanggal yang tersedia di database
     * Prioritas: tanggal yang memiliki pelayanan pasien (total_pasien_reg > 0 atau total_pasien_periksa > 0)
     *
     * @return array{tgl_awal: string, tgl_akhir: string, available_dates: array<int, string>}
     */
    public function getDefaultDateRange(int $daysBack = 7): array
    {
        $availableDates = [];

        if ($this->pdo !== null) {
            try {
                // Utamakan tanggal yang memiliki transaksi pasien agar evaluasi mundur bermakna
                $stmt = $this->pdo->query(
                    "SELECT DISTINCT `tgl_pelayanan`
                     FROM `jam_poliklinik`
                     WHERE `total_pasien_periksa` > 0 OR `total_pasien_reg` > 0
                     ORDER BY `tgl_pelayanan` DESC
                     LIMIT 60"
                );
                $availableDates = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

                if (empty($availableDates)) {
                    $stmtAll = $this->pdo->query(
                        "SELECT DISTINCT `tgl_pelayanan`
                         FROM `jam_poliklinik`
                         ORDER BY `tgl_pelayanan` DESC
                         LIMIT 60"
                    );
                    $availableDates = $stmtAll ? $stmtAll->fetchAll(PDO::FETCH_COLUMN) : [];
                }
            } catch (Throwable $e) {
                $availableDates = [];
            }
        }

        if (empty($availableDates)) {
            $allSnapshots   = $this->readAllSnapshotsFromFile();
            $availableDates = array_keys($allSnapshots);
            rsort($availableDates);
        }

        if (!empty($availableDates)) {
            $maxDate = (string) $availableDates[0];
            // Ambil mundur sejumlah hari dari maxDate, atau tanggal ke-$daysBack yang tersedia
            $slice   = array_slice($availableDates, 0, max(1, $daysBack));
            $minDate = (string) end($slice);
            return [
                'tgl_awal'        => $minDate,
                'tgl_akhir'       => $maxDate,
                'available_dates' => array_values(array_map('strval', $availableDates)),
            ];
        }

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $weekAgo   = date('Y-m-d', strtotime("-{$daysBack} days"));
        return [
            'tgl_awal'        => $weekAgo,
            'tgl_akhir'       => $yesterday,
            'available_dates' => [],
        ];
    }

    /**
     * Ambil daftar rincian harian evaluasi jam mulai poliklinik berdasarkan filter rentang tanggal
     */
    public function getList(array $filters = []): array
    {
        $tglAwal      = trim((string) ($filters['tgl_awal'] ?? ($filters['tanggal'] ?? '')));
        $tglAkhir     = trim((string) ($filters['tgl_akhir'] ?? ($filters['tanggal'] ?? '')));
        $poliFilter   = strtoupper(trim((string) ($filters['kd_poli'] ?? '')));
        $statusFilter = strtoupper(trim((string) ($filters['status'] ?? '')));
        $keyword      = strtolower(trim((string) ($filters['q'] ?? '')));
        $hanyaAktif   = !isset($filters['semua_jadwal']) || !$filters['semua_jadwal'];

        $rows = $this->fetchRowsByDateRange($tglAwal, $tglAkhir);

        return array_values(array_filter($rows, static function (array $row) use ($poliFilter, $statusFilter, $keyword, $hanyaAktif): bool {
            $reg     = (int) ($row['total_pasien_reg'] ?? 0);
            $periksa = (int) ($row['total_pasien_periksa'] ?? 0);
            $st      = strtoupper((string) ($row['status_kepatuhan'] ?? ''));

            if ($poliFilter !== '' && strtoupper((string) ($row['kd_poli'] ?? '')) !== $poliFilter) {
                return false;
            }

            if ($statusFilter !== '') {
                if ($statusFilter === 'TANPA_SOAP') {
                    if (!($st === 'BELUM_MULAI' && $reg > 0)) {
                        return false;
                    }
                } elseif ($statusFilter === 'KOSONG') {
                    if (!($st === 'BELUM_MULAI' && $reg === 0 && $periksa === 0)) {
                        return false;
                    }
                } elseif ($st !== $statusFilter) {
                    return false;
                }
            } elseif ($hanyaAktif) {
                // Default evaluasi mundur: tampilkan sesi yang ada pasien terdaftar atau ada pemeriksaan
                if ($reg === 0 && $periksa === 0) {
                    return false;
                }
            }

            if ($keyword !== '') {
                $haystack = strtolower(implode(' ', [
                    $row['nm_dokter'] ?? '',
                    $row['kd_dokter'] ?? '',
                    $row['nm_poli'] ?? '',
                    $row['kd_poli'] ?? '',
                    $row['nm_pasien_pertama'] ?? '',
                    $row['no_rawat_pertama'] ?? '',
                ]));
                if (strpos($haystack, $keyword) === false) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Rekapitulasi Rapor Kepatuhan per Dokter Spesialis dalam rentang tanggal evaluasi mundur
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRekapDokter(array $filters = []): array
    {
        $tglAwal    = trim((string) ($filters['tgl_awal'] ?? ''));
        $tglAkhir   = trim((string) ($filters['tgl_akhir'] ?? ''));
        $poliFilter = strtoupper(trim((string) ($filters['kd_poli'] ?? '')));
        $keyword    = strtolower(trim((string) ($filters['q'] ?? '')));

        $rows = $this->fetchRowsByDateRange($tglAwal, $tglAkhir);
        $grouped = [];

        foreach ($rows as $row) {
            $reg     = (int) ($row['total_pasien_reg'] ?? 0);
            $periksa = (int) ($row['total_pasien_periksa'] ?? 0);

            // Hanya evaluasi sesi di mana dokter memiliki pasien terdaftar atau melakukan pemeriksaan
            if ($reg === 0 && $periksa === 0) {
                continue;
            }

            if ($poliFilter !== '' && strtoupper((string) ($row['kd_poli'] ?? '')) !== $poliFilter) {
                continue;
            }

            if ($keyword !== '') {
                $haystack = strtolower(($row['nm_dokter'] ?? '') . ' ' . ($row['kd_dokter'] ?? '') . ' ' . ($row['nm_poli'] ?? ''));
                if (strpos($haystack, $keyword) === false) {
                    continue;
                }
            }

            $key = ($row['kd_dokter'] ?? '-') . '|' . ($row['kd_poli'] ?? '-');
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'kd_dokter'            => (string) ($row['kd_dokter'] ?? '-'),
                    'nm_dokter'            => (string) ($row['nm_dokter'] ?? '-'),
                    'kd_poli'              => (string) ($row['kd_poli'] ?? '-'),
                    'nm_poli'              => (string) ($row['nm_poli'] ?? '-'),
                    'total_sesi'           => 0,
                    'tepat_waktu'          => 0,
                    'terlambat'            => 0,
                    'tanpa_soap'           => 0,
                    'total_pasien_reg'     => 0,
                    'total_pasien_periksa' => 0,
                    'sum_selisih_positif'  => 0,
                    'count_selisih'        => 0,
                ];
            }

            $grouped[$key]['total_sesi']++;
            $grouped[$key]['total_pasien_reg']     += $reg;
            $grouped[$key]['total_pasien_periksa'] += $periksa;

            $st = strtoupper((string) ($row['status_kepatuhan'] ?? ''));
            if ($st === 'TEPAT_WAKTU') {
                $grouped[$key]['tepat_waktu']++;
            } elseif ($st === 'TERLAMBAT') {
                $grouped[$key]['terlambat']++;
            } else {
                $grouped[$key]['tanpa_soap']++;
            }

            if ($row['selisih_menit'] !== null && $row['selisih_menit'] !== '') {
                $grouped[$key]['sum_selisih_positif'] += max(0, (int) $row['selisih_menit']);
                $grouped[$key]['count_selisih']++;
            }
        }

        $result = [];
        foreach ($grouped as $g) {
            $terlaksana = $g['tepat_waktu'] + $g['terlambat'];
            $persen     = $terlaksana > 0 ? round(($g['tepat_waktu'] / $terlaksana) * 100, 1) : 0.0;
            $rataMenit  = $g['count_selisih'] > 0 ? (int) round($g['sum_selisih_positif'] / $g['count_selisih']) : 0;

            $g['persentase_patuh']   = $persen;
            $g['rata_selisih_menit'] = $rataMenit;
            $result[] = $g;
        }

        // Urutkan dari yang paling sering terlambat / kepatuhan terendah agar mudah dievaluasi pimpinan
        usort($result, static function (array $a, array $b): int {
            if ($a['terlambat'] !== $b['terlambat']) {
                return $b['terlambat'] <=> $a['terlambat'];
            }
            return $a['persentase_patuh'] <=> $b['persentase_patuh'];
        });

        return $result;
    }

    /**
     * Ambil ringkasan KPI untuk kartu eksekutif Evaluasi Jam Mulai Poliklinik (Rentang Tanggal)
     */
    public function getSummaryKpi(string $tglAwal = '', string $tglAkhir = ''): array
    {
        $rows = $this->fetchRowsByDateRange($tglAwal, $tglAkhir);

        $totalSesiAktif   = 0;
        $totalSesiKosong  = 0;
        $totalTepatWaktu  = 0;
        $totalTerlambat   = 0;
        $totalTanpaSoap   = 0;
        $totalPasienReg   = 0;
        $totalPasienPerik = 0;
        $sumSelisih       = 0;
        $countSelisih     = 0;
        $lastSyncedAt     = null;
        $distinctDates    = [];

        foreach ($rows as $row) {
            $reg     = (int) ($row['total_pasien_reg'] ?? 0);
            $periksa = (int) ($row['total_pasien_periksa'] ?? 0);

            if (!empty($row['synced_at'])) {
                $lastSyncedAt = (string) $row['synced_at'];
            }

            if ($reg === 0 && $periksa === 0) {
                $totalSesiKosong++;
                continue;
            }

            $totalSesiAktif++;
            if (!empty($row['tgl_pelayanan'])) {
                $distinctDates[(string) $row['tgl_pelayanan']] = true;
            }

            $st = strtoupper((string) ($row['status_kepatuhan'] ?? ''));
            if ($st === 'TEPAT_WAKTU') {
                $totalTepatWaktu++;
            } elseif ($st === 'TERLAMBAT') {
                $totalTerlambat++;
            } else {
                $totalTanpaSoap++;
            }

            $totalPasienReg   += $reg;
            $totalPasienPerik += $periksa;

            if ($row['selisih_menit'] !== null && $row['selisih_menit'] !== '') {
                $sumSelisih += max(0, (int) $row['selisih_menit']);
                $countSelisih++;
            }
        }

        $sudahMulai = $totalTepatWaktu + $totalTerlambat;
        $persentase = $sudahMulai > 0 ? round(($totalTepatWaktu / $sudahMulai) * 100, 1) : 0.0;
        $rataMenit  = $countSelisih > 0 ? (int) round($sumSelisih / $countSelisih) : 0;

        return [
            'tgl_awal'               => $tglAwal,
            'tgl_akhir'              => $tglAkhir,
            'jumlah_hari_aktif'      => count($distinctDates),
            'total_jadwal'           => $totalSesiAktif,
            'total_sesi_kosong'      => $totalSesiKosong,
            'total_tepat_waktu'      => $totalTepatWaktu,
            'total_terlambat'        => $totalTerlambat,
            'total_belum_mulai'      => $totalTanpaSoap,
            'total_pasien_reg'       => $totalPasienReg,
            'total_pasien_periksa'   => $totalPasienPerik,
            'rata_selisih_menit'     => $rataMenit,
            'persentase_tepat_waktu' => $persentase,
            'synced_at'              => $lastSyncedAt,
        ];
    }

    /**
     * Ambil baris berdasarkan rentang tanggal dari MySQL atau fallback file snapshot
     */
    private function fetchRowsByDateRange(string $tglAwal = '', string $tglAkhir = ''): array
    {
        if ($tglAwal === '' || $tglAkhir === '') {
            $def      = $this->getDefaultDateRange(7);
            $tglAwal  = $tglAwal !== '' ? $tglAwal : $def['tgl_awal'];
            $tglAkhir = $tglAkhir !== '' ? $tglAkhir : $def['tgl_akhir'];
        }

        if ($tglAwal > $tglAkhir) {
            [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];
        }

        if ($this->pdo !== null) {
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM `jam_poliklinik`
                     WHERE `tgl_pelayanan` BETWEEN :t1 AND :t2
                     ORDER BY `tgl_pelayanan` DESC, `jam_mulai_jadwal` ASC, `nm_poli` ASC, `nm_dokter` ASC"
                );
                $stmt->execute([
                    ':t1' => $tglAwal,
                    ':t2' => $tglAkhir,
                ]);
                $rows = $stmt ? $stmt->fetchAll() : [];
                if (is_array($rows) && !empty($rows)) {
                    return $rows;
                }
            } catch (Throwable $e) {
                // Fallback ke file cache
            }
        }

        $allSnapshots = $this->readAllSnapshotsFromFile();
        if (empty($allSnapshots)) {
            return [];
        }

        $merged = [];
        foreach ($allSnapshots as $dateKey => $entry) {
            if ($dateKey >= $tglAwal && $dateKey <= $tglAkhir && isset($entry['data']) && is_array($entry['data'])) {
                foreach ($entry['data'] as $r) {
                    $merged[] = $r;
                }
            }
        }

        return $merged;
    }

    private function readAllSnapshotsFromFile(): array
    {
        if (!is_file($this->cacheFile)) {
            return [];
        }
        $decoded = json_decode((string) @file_get_contents($this->cacheFile), true);
        return is_array($decoded) ? $decoded : [];
    }
}
