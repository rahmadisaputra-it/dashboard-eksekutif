<?php
declare(strict_types=1);

namespace App\Modules\Operasional\Controllers;

use App\Config\Database;
use App\Core\Controller;
use App\Modules\Hfis\Models\JadwalHfisModel;
use App\Modules\Operasional\Models\JamPoliklinikModel;
use App\Modules\Operasional\Models\TempatTidurModel;

class OperasionalController extends Controller
{
    /**
     * Halaman Monitoring Sinkronisasi Jadwal Dokter HFIS BPJS vs SIMRS Khanza
     */
    public function jadwalHfis(): void
    {
        $model = new JadwalHfisModel();

        $filters = [
            'hari'    => isset($_GET['hari']) && is_string($_GET['hari']) ? trim($_GET['hari']) : '',
            'kd_poli' => isset($_GET['kd_poli']) && is_string($_GET['kd_poli']) ? trim($_GET['kd_poli']) : '',
            'status'  => isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : '',
            'q'       => isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '',
        ];

        $allRows    = $model->getJadwalList([]);
        $jadwalList = $model->getJadwalList($filters);
        $kpi        = $model->getSummaryKpi();

        $poliOptions = [];
        foreach ($allRows as $row) {
            $kd = (string) ($row['kd_poli_rs'] ?? '');
            if ($kd !== '' && !isset($poliOptions[$kd])) {
                $poliOptions[$kd] = (string) ($row['nm_poli_rs'] ?? $kd);
            }
        }
        asort($poliOptions);

        $this->renderModule('Operasional', 'jadwal_hfis', [
            'currentModule'   => 'operasional',
            'currentPage'     => 'jadwal-hfis',
            'pageTitle'       => 'Monitoring Jadwal Dokter HFIS vs SIMRS',
            'pageSubtitle'    => 'Sinkronisasi Jadwal Praktek & Kuota Antrean Mobile JKN (HFIS BPJS — SIMRS Khanza)',
            'printable'       => true,
            'printSubject'    => 'Sinkronisasi Jadwal Dokter HFIS BPJS vs SIMRS Khanza',
            'printSignerRole' => 'Petugas Operasional / Rekam Medis',
            'filters'         => $filters,
            'jadwalList'      => $jadwalList,
            'kpi'             => $kpi,
            'poliOptions'     => $poliOptions,
            'apiKey'          => Database::getApiKey(),
        ]);
    }

    /**
     * Halaman Evaluasi Mundur Kepatuhan Jam Mulai Poliklinik (Rentang Tanggal Historis & Rapor Dokter)
     */
    public function jamPoliklinik(): void
    {
        $model    = new JamPoliklinikModel();
        $defaults = $model->getDefaultDateRange(7);

        $tglSingle = isset($_GET['tanggal']) && is_string($_GET['tanggal']) ? trim($_GET['tanggal']) : '';
        $tglAwal   = isset($_GET['tgl_awal']) && is_string($_GET['tgl_awal']) && trim($_GET['tgl_awal']) !== ''
            ? trim($_GET['tgl_awal'])
            : ($tglSingle !== '' ? $tglSingle : $defaults['tgl_awal']);
        $tglAkhir  = isset($_GET['tgl_akhir']) && is_string($_GET['tgl_akhir']) && trim($_GET['tgl_akhir']) !== ''
            ? trim($_GET['tgl_akhir'])
            : ($tglSingle !== '' ? $tglSingle : $defaults['tgl_akhir']);

        if ($tglAwal > $tglAkhir) {
            [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];
        }

        $filters = [
            'tgl_awal'     => $tglAwal,
            'tgl_akhir'    => $tglAkhir,
            'kd_poli'      => isset($_GET['kd_poli']) && is_string($_GET['kd_poli']) ? trim($_GET['kd_poli']) : '',
            'status'       => isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : '',
            'q'            => isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '',
            'semua_jadwal' => isset($_GET['semua_jadwal']) && $_GET['semua_jadwal'] === '1',
        ];

        $kpi         = $model->getSummaryKpi($tglAwal, $tglAkhir);
        $rekapDokter = $model->getRekapDokter($filters);
        $allRows     = $model->getList(['tgl_awal' => $tglAwal, 'tgl_akhir' => $tglAkhir, 'semua_jadwal' => true]);
        $listData    = $model->getList($filters);

        $poliOptions = [];
        foreach ($allRows as $row) {
            $kd = (string) ($row['kd_poli'] ?? '');
            if ($kd !== '' && !isset($poliOptions[$kd])) {
                $poliOptions[$kd] = (string) ($row['nm_poli'] ?? $kd);
            }
        }
        asort($poliOptions);

        $this->renderModule('Operasional', 'jam_poliklinik', [
            'currentModule'   => 'operasional',
            'currentPage'     => 'jam-poliklinik',
            'pageTitle'       => 'Evaluasi Kepatuhan Jam Mulai Poliklinik',
            'pageSubtitle'    => 'Evaluasi Historis Waktu Mulai Pelayanan Dokter Spesialis Berdasarkan SOAP Pertama (Data Mundur)',
            'printable'       => true,
            'printSubject'    => "Evaluasi Kepatuhan Jam Mulai Poliklinik ({$tglAwal} s.d. {$tglAkhir})",
            'printSignerRole' => 'Petugas Operasional / Rawat Jalan',
            'filters'         => $filters,
            'availableDates'  => $defaults['available_dates'],
            'rekapDokter'     => $rekapDokter,
            'listData'        => $listData,
            'kpi'             => $kpi,
            'poliOptions'     => $poliOptions,
        ]);
    }

    /**
     * Halaman Monitoring Keterisian Tempat Tidur (Sensus Harian & BOR Rawat Inap)
     * Membaca snapshot mandiri dari database dashboard_eksekutif (hasil push satu arah dari SIMRS Khanza).
     */
    public function tempatTidur(): void
    {
        $filters = [
            'kd_bangsal' => isset($_GET['kd_bangsal']) && is_string($_GET['kd_bangsal']) ? trim($_GET['kd_bangsal']) : '',
            'kelas'      => isset($_GET['kelas']) && is_string($_GET['kelas']) ? trim($_GET['kelas']) : '',
            'status'     => isset($_GET['status']) && is_string($_GET['status']) ? strtoupper(trim($_GET['status'])) : '',
            'q'          => isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '',
        ];

        $model = new TempatTidurModel();
        $dash  = $model->getDashboardData($filters);

        $this->renderModule('Operasional', 'tempat_tidur', [
            'currentModule'   => 'operasional',
            'currentPage'     => 'tempat-tidur',
            'pageTitle'       => 'Keterisian Tempat Tidur (BOR Rawat Inap)',
            'pageSubtitle'    => 'Monitoring Kapasitas Tempat Tidur Terisi & Kosong per Ruangan Rawat Inap',
            'printable'       => true,
            'printSubject'    => 'Sensus Harian & Keterisian Tempat Tidur (BOR Rawat Inap)',
            'printSignerRole' => 'Petugas Operasional / Rawat Inap',
            'filters'         => $filters,
            'kpi'             => $dash['kpi'],
            'rekapBangsal'    => $dash['rekapBangsal'],
            'listKamar'       => $dash['listKamar'],
            'bangsalOptions'  => $dash['bangsalOptions'],
            'kelasOptions'    => $dash['kelasOptions'],
        ]);
    }
}
