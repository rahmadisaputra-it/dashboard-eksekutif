<?php
declare(strict_types=1);

namespace App\Modules\Keuangan\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Modules\Keuangan\Models\PerkiraanBiayaModel;

class KendaliBiayaController extends Controller
{
    private PerkiraanBiayaModel $model;

    public function __construct()
    {
        $this->model = new PerkiraanBiayaModel();
    }

    /**
     * Halaman Utama: Kendali Biaya Rawat Inap Aktif
     */
    public function index(): void
    {
        $search  = trim((string) ($_REQUEST['search'] ?? ''));
        $bangsal = trim((string) ($_REQUEST['bangsal'] ?? ''));
        $status  = trim((string) ($_REQUEST['status'] ?? ''));

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refresh') {
            if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
                $qs = http_build_query([
                    'module'  => 'keuangan',
                    'page'    => 'kendali-biaya',
                    'search'  => $search,
                    'bangsal' => $bangsal,
                    'status'  => $status,
                ]);
                $this->redirect('index.php?' . $qs);
            }
            $this->model->clearCache($search, $bangsal);
            $qs = http_build_query([
                'module'    => 'keuangan',
                'page'      => 'kendali-biaya',
                'search'    => $search,
                'bangsal'   => $bangsal,
                'status'    => $status,
                'refreshed' => '1',
            ]);
            $this->redirect('index.php?' . $qs);
        }

        $forceRefresh = isset($_GET['refreshed']) && $_GET['refreshed'] === '1';
        $dashboard    = $this->model->getDashboardData([
            'search'  => $search,
            'bangsal' => $bangsal,
            'status'  => $status,
        ], $forceRefresh);

        $this->renderModule('Keuangan', 'kendali_biaya', [
            'currentModule'   => 'keuangan',
            'currentPage'     => 'kendali-biaya',
            'pageTitle'       => 'Kendali Biaya Rawat Inap',
            'pageSubtitle'    => 'Modul Keuangan — Pemantauan real-time akumulasi modal RS terhadap paket tarif INA-CBG pasien rawat inap aktif',
            'printable'       => true,
            'printSubject'    => 'Pasien Rawat Inap Aktif',
            'printSignerRole' => 'Petugas Pelaporan / Kendali Biaya',
            'csrfToken'       => Auth::csrfToken(),
            'patients'        => $dashboard['patients'],
            'totalsData'      => $dashboard['totalsData'],
            'stats'           => $dashboard['stats'],
            'bangsalList'     => $dashboard['bangsalList'],
            'cachedAt'        => $dashboard['cached_at'],
            'search'          => $search,
            'bangsal'         => $bangsal,
            'status'          => $status,
            'refreshed'       => $forceRefresh,
        ]);
    }

    /**
     * Sub-Fitur Modul Keuangan: Manajemen Tarif INA-CBG (tarif_inacbg)
     */
    public function tarifInacbg(): void
    {
        $flashSuccess = null;
        $flashError   = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
                $flashError = 'Sesi formulir kedaluwarsa. Silakan ulangi kembali.';
            } else {
                $action     = (string) ($_POST['action'] ?? 'save');
                $kdPenyakit = strtoupper(trim((string) ($_POST['kd_penyakit'] ?? '')));
                $biaya      = (float) ($_POST['biaya'] ?? 0);

                if ($action === 'delete' && $kdPenyakit !== '') {
                    $ok = $this->model->deleteInacbg($kdPenyakit);
                    $qs = http_build_query([
                        'module' => 'keuangan',
                        'page'   => 'tarif-inacbg',
                        'msg'    => $ok ? "Tarif {$kdPenyakit} berhasil dihapus." : '',
                    ]);
                    $this->redirect('index.php?' . $qs);
                }

                if ($kdPenyakit === '' || strlen($kdPenyakit) > 15 || $biaya < 0) {
                    $flashError = 'Kode penyakit ICD-10 (maks 15 karakter) dan nominal tarif wajib valid.';
                } else {
                    $ok = $this->model->upsertInacbg($kdPenyakit, $biaya);
                    if ($ok) {
                        $qs = http_build_query([
                            'module' => 'keuangan',
                            'page'   => 'tarif-inacbg',
                            'msg'    => "Tarif {$kdPenyakit} berhasil disimpan.",
                        ]);
                        $this->redirect('index.php?' . $qs);
                    } else {
                        $flashError = 'Gagal menyimpan tarif ke database.';
                    }
                }
            }
        }

        if (!empty($_GET['msg'])) {
            $flashSuccess = (string) $_GET['msg'];
        }

        $search = trim((string) ($_GET['search'] ?? ''));
        $items  = $this->model->getInacbgDummy($search);

        $this->renderModule('Keuangan', 'tarif_inacbg', [
            'currentModule' => 'keuangan',
            'currentPage'   => 'tarif-inacbg',
            'pageTitle'     => 'Manajemen Tarif Paket INA-CBG',
            'pageSubtitle'  => 'Modul Keuangan — Referensi tarif paket berbasis kode ICD-10 untuk kalkulasi Kendali Biaya Rawat Inap',
            'printable'     => false,
            'csrfToken'     => Auth::csrfToken(),
            'items'         => $items,
            'search'        => $search,
            'flashSuccess'  => $flashSuccess,
            'flashError'    => $flashError,
        ]);
    }
}
