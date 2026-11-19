<?php
declare(strict_types=1);

$appName = (string) ($hospital['nama_aplikasi'] ?? 'Dashboard Eksekutif');
$rsName  = (string) ($hospital['nama_instansi'] ?? 'RS Mitra SIMRS Khanza');
$logoUrl = (string) ($hospital['logo_instansi'] ?? 'assets/images/logo-default.svg');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login <?= e($appName) ?> — <?= e($rsName) ?></title>
  <link rel="stylesheet" href="assets/css/permata-bevel.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/assets/css/permata-bevel.css') ?: '4' ?>">
  <script src="assets/css/tailwind.cdn.js"></script>
  <script>
    if (typeof tailwind === 'undefined') {
      document.write('<script src="https://cdn.tailwindcss.com"><\/script>');
    }
  </script>
</head>
<body class="canvas-clean text-slate-800 antialiased min-h-screen flex items-center justify-center p-4">

  <!-- SATU KARTU LOGIN PROFESIONAL BERSIH -->
  <main class="permata-card w-full max-w-[390px] p-7">

    <!-- Header Identitas Aplikasi & Instansi -->
    <div class="text-center pb-5 mb-6 border-b border-slate-100">
      <div class="inline-flex items-center justify-center mb-3">
        <img
          src="<?= e($logoUrl) ?>"
          alt="Logo <?= e($rsName) ?>"
          width="56"
          height="56"
          class="w-14 h-14 login-logo object-contain rounded-xl"
          style="width: 56px; height: 56px; object-fit: contain;"
        >
      </div>
      <h1 class="text-base font-semibold text-slate-900 tracking-tight">
        <?= e($appName) ?>
      </h1>
      <p class="text-xs text-slate-500 mt-1">
        <?= e($rsName) ?>
      </p>
    </div>

    <!-- Pesan Error Validasi -->
    <?php if (!empty($error)): ?>
      <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700 flex items-start gap-2.5" role="alert">
        <svg class="w-4 h-4 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span class="font-medium leading-relaxed"><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <!-- Form Login Akun Dashboard Eksekutif -->
    <form method="POST" action="index.php?module=auth&amp;page=login" class="space-y-4" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">
      <input type="text" name="prevent_autofill_user" style="display:none" tabindex="-1" autocomplete="off">
      <input type="password" name="prevent_autofill_pass" style="display:none" tabindex="-1" autocomplete="new-password">

      <div>
        <label for="input-username" class="block text-xs font-medium text-slate-700 mb-1.5">
          Username
        </label>
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
            </svg>
          </div>
          <input
            type="text"
            id="input-username"
            name="username"
            value="<?= e($oldUsername ?? '') ?>"
            required
            readonly
            onfocus="this.removeAttribute('readonly');"
            autocomplete="off"
            placeholder="Masukkan username"
            class="permata-input w-full pl-10 pr-3.5 py-2.5 min-h-[42px] text-xs font-num"
          >
        </div>
      </div>

      <div>
        <label for="input-password" class="block text-xs font-medium text-slate-700 mb-1.5">
          Password
        </label>
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
            </svg>
          </div>
          <input
            type="password"
            id="input-password"
            name="password"
            value=""
            required
            readonly
            onfocus="this.removeAttribute('readonly');"
            autocomplete="new-password"
            placeholder="Masukkan password"
            class="permata-input w-full pl-10 pr-10 py-2.5 min-h-[42px] text-xs"
          >
          <button
            type="button"
            onclick="togglePassword()"
            aria-label="Tampilkan Password"
            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-700 cursor-pointer"
          >
            <svg id="eye-icon" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="pt-1">
        <button
          type="submit"
          class="btn-primary w-full min-h-[42px] py-2.5 px-4 text-xs font-medium flex items-center justify-center gap-2 cursor-pointer"
        >
          <span>Masuk ke <?= e($appName) ?></span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
          </svg>
        </button>
      </div>
    </form>

    <div class="mt-6 pt-4 border-t border-slate-100 text-center text-[11px] text-slate-400">
      <?= e($appName) ?> &copy; <?= date('Y') ?> <span class="font-medium text-slate-600">Rahmadi Saputra</span>
    </div>

  </main>

  <script>
  window.addEventListener('DOMContentLoaded', function () {
    const u = document.getElementById('input-username');
    const p = document.getElementById('input-password');
    <?php if (empty($oldUsername)): ?>
    if (u) u.value = '';
    <?php endif; ?>
    if (p) p.value = '';
    setTimeout(function () {
      if (u) u.removeAttribute('readonly');
      if (p) p.removeAttribute('readonly');
    }, 120);
  });

  function togglePassword() {
    const input = document.getElementById('input-password');
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
  }
  </script>
</body>
</html>
