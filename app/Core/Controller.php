<?php
declare(strict_types=1);

namespace App\Core;

use App\Config\Database;
use Throwable;

abstract class Controller
{
    /**
     * Render view milik modul tertentu (app/Modules/{Module}/Views/{view}.php)
     *
     * @param string $module Nama folder modul (misal: 'Keuangan', 'Auth')
     * @param string $view   Nama file view tanpa .php (misal: 'kendali_biaya')
     * @param array<string, mixed> $data Variabel untuk view
     * @param bool   $useLayout Gunakan kerangka utama (Sidebar + Main)
     */
    protected function renderModule(string $module, string $view, array $data = [], bool $useLayout = true): void
    {
        $data['hospital'] = $data['hospital'] ?? $this->getHospitalProfile();
        $data['dbInfo']   = $data['dbInfo']   ?? $this->getDbInfo();
        $data['authUser'] = $data['authUser'] ?? Auth::user();

        extract($data, EXTR_SKIP);

        $viewFile = dirname(__DIR__) . "/Modules/{$module}/Views/{$view}.php";
        if (!is_file($viewFile)) {
            http_response_code(404);
            echo "View modul [{$module}/{$view}] tidak ditemukan.";
            return;
        }

        if (!$useLayout) {
            require $viewFile;
            return;
        }

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        require dirname(__DIR__) . '/Views/layouts/app.php';
    }

    protected function getDbInfo(): array
    {
        $db = Database::getInstance();
        return [
            'connected' => $db->isConnected(),
            'db_name'   => $db->getActiveDbName(),
            'status'    => $db->getStatusMessage(),
        ];
    }

    protected function getHospitalProfile(): array
    {
        $default = [
            'nama_aplikasi'   => 'Dashboard Eksekutif',
            'nama_instansi'   => 'RS Mitra SIMRS Khanza',
            'alamat_instansi' => 'Jl. Kesehatan No. 1',
            'kabupaten'       => 'Indonesia',
            'propinsi'        => 'Indonesia',
            'kontak'          => '(021) 000000',
            'email'           => 'info@rumahsakit.id',
            'kode_ppk'        => '0000R000',
            'nama_direktur'   => '( ................................................. )',
            'logo_instansi'   => 'assets/images/logo-default.svg',
            'logo_akreditasi' => '',
        ];

        $appPdo = Database::getInstance()->getAppConnection();
        if ($appPdo !== null) {
            try {
                $stmt = $appPdo->query('SELECT * FROM `app_settings` WHERE `id` = 1 LIMIT 1');
                $row  = $stmt ? $stmt->fetch() : false;
                if (is_array($row) && !empty($row['nama_instansi'])) {
                    $profile = array_merge($default, $row);
                    $rootDir = dirname(__DIR__, 2);
                    if (empty($profile['logo_instansi']) || !is_file($rootDir . '/' . ltrim((string) $profile['logo_instansi'], '/'))) {
                        $profile['logo_instansi'] = 'assets/images/logo-default.svg';
                    }
                    if (!empty($profile['logo_akreditasi']) && !is_file($rootDir . '/' . ltrim((string) $profile['logo_akreditasi'], '/'))) {
                        $profile['logo_akreditasi'] = '';
                    }
                    return $profile;
                }
            } catch (Throwable $e) {
                // Gunakan default
            }
        }

        return $default;
    }

    protected function redirect(string $url): void
    {
        if (!headers_sent()) {
            header("Location: {$url}");
            exit;
        }
        echo '<script>window.location.href=' . json_encode($url) . ';</script>';
        exit;
    }

    protected function json(array $payload, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
