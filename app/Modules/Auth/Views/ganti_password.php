<?php
declare(strict_types=1);

use App\Core\Auth;

$user    = $authUser ?? Auth::user() ?? [];
$modules = is_array($user['modules'] ?? null) ? $user['modules'] : [];
$isAdmin = ($user['role'] ?? '') === 'admin';
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-5 no-print print:hidden">

  <!-- Kartu Informasi Profil Pengguna -->
  <section class="lg:col-span-5 permata-card p-6 flex flex-col justify-between">
    <div>
      <div class="flex items-center gap-3 pb-4 mb-4 border-b border-slate-100">
        <div class="w-11 h-11 rounded-full bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center text-sm font-semibold font-num shrink-0">
          <?= e(strtoupper(substr((string) ($user['nama'] ?? 'U'), 0, 2))) ?>
        </div>
        <div class="min-w-0">
          <h3 class="text-sm font-semibold text-slate-900 truncate"><?= e($user['nama'] ?? '-') ?></h3>
          <p class="text-xs text-slate-500 font-num truncate">@<?= e($user['username'] ?? '-') ?></p>
        </div>
      </div>

      <dl class="space-y-3 text-xs">
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <dt class="text-slate-500">Username Login</dt>
          <dd class="font-semibold text-slate-800 font-num"><?= e($user['username'] ?? '-') ?></dd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <dt class="text-slate-500">Jabatan / Unit</dt>
          <dd class="font-medium text-slate-700"><?= e($user['jabatan'] ?? '-') ?> · <?= e($user['departemen'] ?? '-') ?></dd>
        </div>
        <div class="flex items-center justify-between py-1.5 border-b border-slate-100">
          <dt class="text-slate-500">Peran Akun (Role)</dt>
          <dd>
            <?php if ($isAdmin): ?>
              <span class="text-xs font-semibold text-blue-700">Administrator</span>
            <?php else: ?>
              <span class="text-xs font-medium text-slate-700">Pengguna Modul</span>
            <?php endif; ?>
          </dd>
        </div>
        <div class="flex items-center justify-between py-1.5">
          <dt class="text-slate-500">Hak Akses Modul</dt>
          <dd class="text-right">
            <?php if ($isAdmin): ?>
              <span class="font-medium text-slate-800">Seluruh Modul</span>
            <?php elseif (!empty($modules)): ?>
              <?php
                $modLabels = [];
                foreach ($modules as $modKey) {
                    $modLabels[] = (string) (Auth::MODULES[$modKey]['label'] ?? ucfirst((string) $modKey));
                }
              ?>
              <span class="font-medium text-slate-800"><?= e(implode(', ', $modLabels)) ?></span>
            <?php else: ?>
              <span class="text-xs text-slate-500">Belum ada modul</span>
            <?php endif; ?>
          </dd>
        </div>
      </dl>
    </div>

    <div class="mt-6 p-3.5 rounded-lg bg-slate-50 border border-slate-200/80 text-[11px] text-slate-500 leading-relaxed">
      <p class="font-medium text-slate-700 mb-1">Panduan Keamanan Password:</p>
      <ul class="list-disc list-inside space-y-0.5">
        <li>Gunakan minimal <span class="font-num font-semibold text-slate-700">6</span> karakter kombinasi huruf dan angka.</li>
        <li>Password disimpan dengan enkripsi satu arah <span class="font-num font-medium">BCRYPT</span>.</li>
        <li>Hubungi Administrator jika Anda memerlukan perubahan hak akses modul.</li>
      </ul>
    </div>
  </section>

  <!-- Form Ganti Password Mandiri -->
  <section class="lg:col-span-7 permata-card p-6">
    <div class="pb-4 mb-5 border-b border-slate-100">
      <h3 class="text-sm font-semibold text-slate-900">Formulir Perbarui Password</h3>
      <p class="text-xs text-slate-500 mt-0.5">Masukkan password Anda saat ini sebelum menetapkan password baru</p>
    </div>

    <?php if (!empty($flashSuccess)): ?>
      <div class="mb-4 p-3.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-start gap-2.5" role="status">
        <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="font-medium"><?= e($flashSuccess) ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
      <div class="mb-4 p-3.5 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700 flex items-start gap-2.5" role="alert">
        <svg class="w-4 h-4 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span class="font-medium"><?= e($flashError) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="index.php?module=auth&amp;page=ganti-password" class="space-y-4" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">

      <div>
        <label for="password_lama" class="block text-xs font-medium text-slate-700 mb-1.5">
          Password Saat Ini
        </label>
        <input
          type="password"
          id="password_lama"
          name="password_lama"
          required
          autocomplete="current-password"
          placeholder="Masukkan password saat ini"
          class="permata-input w-full px-3.5 py-2.5 min-h-[40px] text-xs"
        >
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="password_baru" class="block text-xs font-medium text-slate-700 mb-1.5">
            Password Baru <span class="text-slate-400 font-normal">(Min. 6 karakter)</span>
          </label>
          <input
            type="password"
            id="password_baru"
            name="password_baru"
            required
            minlength="6"
            autocomplete="new-password"
            placeholder="Masukkan password baru"
            class="permata-input w-full px-3.5 py-2.5 min-h-[40px] text-xs"
          >
        </div>

        <div>
          <label for="konfirmasi_password" class="block text-xs font-medium text-slate-700 mb-1.5">
            Konfirmasi Password Baru
          </label>
          <input
            type="password"
            id="konfirmasi_password"
            name="konfirmasi_password"
            required
            minlength="6"
            autocomplete="new-password"
            placeholder="Ulangi password baru"
            class="permata-input w-full px-3.5 py-2.5 min-h-[40px] text-xs"
          >
        </div>
      </div>

      <div class="pt-3 flex items-center justify-end gap-2.5 border-t border-slate-100">
        <a
          href="<?= e(Auth::defaultRoute()) ?>"
          class="px-4 py-2 min-h-[38px] rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-medium text-slate-700 inline-flex items-center transition-colors"
        >
          Kembali
        </a>
        <button
          type="submit"
          class="btn-primary px-4 py-2 min-h-[38px] text-xs font-medium inline-flex items-center gap-2 cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
          </svg>
          <span>Simpan Password Baru</span>
        </button>
      </div>
    </form>
  </section>

</div>
