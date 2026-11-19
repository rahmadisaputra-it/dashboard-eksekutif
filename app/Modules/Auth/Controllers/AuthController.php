<?php
declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Modules\Auth\Models\UserModel;

class AuthController extends Controller
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function showLogin(?string $error = null, string $oldUsername = ''): void
    {
        if (Auth::check()) {
            $this->redirect(Auth::defaultRoute());
        }

        $this->renderModule('Auth', 'login', [
            'error'       => $error,
            'oldUsername' => $oldUsername,
            'csrfToken'   => Auth::csrfToken(),
        ], false);
    }

    public function processLogin(): void
    {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $this->showLogin('Sesi formulir kedaluwarsa. Silakan coba masuk kembali.');
            return;
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->showLogin('Username dan password wajib diisi.', $username);
            return;
        }

        $user = $this->userModel->authenticate($username, $password);
        if ($user === null) {
            // Pesan sengaja generik: tidak membedakan username salah, password salah, atau akun nonaktif.
            $this->showLogin('Username atau password tidak sesuai, atau akun tidak aktif.', $username);
            return;
        }

        Auth::login($user);
        $this->redirect(Auth::defaultRoute());
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('index.php?module=auth&page=login');
    }

    /**
     * Halaman Ganti Password Mandiri untuk user yang sedang login
     */
    public function showChangePassword(?string $flashSuccess = null, ?string $flashError = null): void
    {
        if (!Auth::check() || !Auth::revalidate()) {
            $this->redirect('index.php?module=auth&page=login');
        }

        if (!empty($_GET['msg']) && $flashSuccess === null) {
            $flashSuccess = (string) $_GET['msg'];
        }

        $this->renderModule('Auth', 'ganti_password', [
            'currentModule' => 'auth',
            'currentPage'   => 'ganti-password',
            'pageTitle'     => 'Ganti Password Akun',
            'pageSubtitle'  => 'Keamanan Akun — Perbarui password masuk Anda secara berkala',
            'printable'     => false,
            'csrfToken'     => Auth::csrfToken(),
            'flashSuccess'  => $flashSuccess,
            'flashError'    => $flashError,
        ]);
    }

    /**
     * Proses penyimpanan password baru mandiri
     */
    public function processChangePassword(): void
    {
        if (!Auth::check() || !Auth::revalidate()) {
            $this->redirect('index.php?module=auth&page=login');
        }

        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            $this->showChangePassword(null, 'Sesi formulir kedaluwarsa. Silakan ulangi kembali.');
            return;
        }

        $oldPass     = (string) ($_POST['password_lama'] ?? '');
        $newPass     = (string) ($_POST['password_baru'] ?? '');
        $confirmPass = (string) ($_POST['konfirmasi_password'] ?? '');

        if ($oldPass === '' || $newPass === '' || $confirmPass === '') {
            $this->showChangePassword(null, 'Seluruh kolom password wajib diisi.');
            return;
        }

        if ($newPass !== $confirmPass) {
            $this->showChangePassword(null, 'Konfirmasi password baru tidak cocok.');
            return;
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $result = $this->userModel->changeOwnPassword($userId, $oldPass, $newPass);

        if ($result['ok']) {
            $this->showChangePassword($result['message'], null);
        } else {
            $this->showChangePassword(null, $result['message']);
        }
    }
}
