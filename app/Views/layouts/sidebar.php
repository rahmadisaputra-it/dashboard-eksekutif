<?php
declare(strict_types=1);

use App\Core\Auth;

$activeModule = (string) ($currentModule ?? Auth::defaultModule());
$activePage   = (string) ($currentPage ?? 'kendali-biaya');
$appName      = (string) ($hospital['nama_aplikasi'] ?? 'Dashboard Eksekutif');
$rsName       = (string) ($hospital['nama_instansi'] ?? 'RS Mitra SIMRS Khanza');
$logoUrl      = (string) ($hospital['logo_instansi'] ?? 'assets/images/logo-default.svg');

$keuanganMenus = [
    [
        'module' => 'keuangan',
        'page'   => 'kendali-biaya',
        'label'  => 'Kendali Biaya Ranap',
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    ],
    [
        'module' => 'keuangan',
        'page'   => 'tarif-inacbg',
        'label'  => 'Tarif Paket INA-CBG',
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/>',
    ],
];

$operasionalMenus = [
    [
        'module' => 'operasional',
        'page'   => 'jadwal-hfis',
        'label'  => 'Jadwal Dokter HFIS',
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>',
    ],
    [
        'module' => 'operasional',
        'page'   => 'jam-poliklinik',
        'label'  => 'Jam Mulai Poliklinik',
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    ],
    [
        'module' => 'operasional',
        'page'   => 'tempat-tidur',
        'label'  => 'Keterisian Tempat Tidur',
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>',
    ],
];

$userInitial = 'KH';
if (!empty($authUser['nama'])) {
    $words = preg_split('/\s+/', trim((string) $authUser['nama']));
    $userInitial = strtoupper(substr($words[0] ?? 'U', 0, 1) . substr($words[1] ?? '', 0, 1));
}
?>
<!-- SIDEBAR PROFESIONAL BERSIH (MENU MUNCUL SESUAI HAK AKSES USER) -->
<aside class="permata-sidebar no-print print:hidden" aria-label="Navigasi Modul <?= e($appName) ?>">
  <div>
    <!-- Identitas Instansi Sejajar Horizontal (Pojok Kiri Atas) -->
    <div class="flex items-center gap-3 px-2.5 pb-6 mb-5 border-b border-slate-100">
      <img
        src="<?= e($logoUrl) ?>"
        alt="Logo <?= e($rsName) ?>"
        width="36"
        height="36"
        class="w-9 h-9 object-contain shrink-0 rounded-lg"
        style="width: 36px; height: 36px; object-fit: contain;"
      >
      <div class="min-w-0">
        <h1 class="text-sm font-semibold text-slate-900 tracking-tight truncate">
          <?= e($appName) ?>
        </h1>
        <p class="text-[11px] text-slate-500 truncate" title="<?= e($rsName) ?>">
          <?= e($rsName) ?>
        </p>
      </div>
    </div>

    <!-- Daftar Modul Sesuai Hak Akses -->
    <div class="space-y-5">

      <?php if (Auth::canAccessModule('keuangan')): ?>
        <div>
          <div class="px-3 mb-1.5">
            <span class="text-[11px] font-medium text-slate-500">Keuangan</span>
          </div>
          <nav class="space-y-1">
            <?php foreach ($keuanganMenus as $item): ?>
              <?php $isActive = ($activeModule === $item['module'] && $activePage === $item['page']); ?>
              <a
                href="index.php?module=<?= e($item['module']) ?>&amp;page=<?= e($item['page']) ?>"
                class="flex items-center gap-3 px-3 py-2.5 rounded-md text-[13px] <?= $isActive ? 'nav-item-active' : 'nav-item-idle' ?>"
              >
                <svg class="w-4 h-4 shrink-0 <?= $isActive ? 'text-blue-700' : 'text-slate-500' ?>" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                  <?= $item['icon'] ?>
                </svg>
                <span class="truncate"><?= e($item['label']) ?></span>
              </a>
            <?php endforeach; ?>
          </nav>
        </div>
      <?php endif; ?>

      <?php if (Auth::canAccessModule('operasional')): ?>
        <div>
          <div class="px-3 mb-1.5">
            <span class="text-[11px] font-medium text-slate-500">Operasional &amp; Pelayanan</span>
          </div>
          <nav class="space-y-1">
            <?php foreach ($operasionalMenus as $item): ?>
              <?php $isActive = ($activeModule === $item['module'] && $activePage === $item['page']); ?>
              <a
                href="index.php?module=<?= e($item['module']) ?>&amp;page=<?= e($item['page']) ?>"
                class="flex items-center gap-3 px-3 py-2.5 rounded-md text-[13px] <?= $isActive ? 'nav-item-active' : 'nav-item-idle' ?>"
              >
                <svg class="w-4 h-4 shrink-0 <?= $isActive ? 'text-blue-700' : 'text-slate-500' ?>" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                  <?= $item['icon'] ?>
                </svg>
                <span class="truncate"><?= e($item['label']) ?></span>
              </a>
            <?php endforeach; ?>
          </nav>
        </div>
      <?php endif; ?>

      <?php if (Auth::isAdmin()): ?>
        <div>
          <div class="px-3 mb-1.5">
            <span class="text-[11px] font-medium text-slate-500">Administrasi Sistem</span>
          </div>
          <nav class="space-y-1">
            <?php $isAkunActive = ($activeModule === 'pengguna' && $activePage !== 'pengaturan-rs'); ?>
            <a
              href="index.php?module=pengguna&amp;page=manajemen-akun"
              class="flex items-center gap-3 px-3 py-2.5 rounded-md text-[13px] <?= $isAkunActive ? 'nav-item-active' : 'nav-item-idle' ?>"
            >
              <svg class="w-4 h-4 shrink-0 <?= $isAkunActive ? 'text-blue-700' : 'text-slate-500' ?>" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
              </svg>
              <span class="truncate">Manajemen Akun &amp; Akses</span>
            </a>

            <?php $isRsActive = ($activeModule === 'pengguna' && $activePage === 'pengaturan-rs'); ?>
            <a
              href="index.php?module=pengguna&amp;page=pengaturan-rs"
              class="flex items-center gap-3 px-3 py-2.5 rounded-md text-[13px] <?= $isRsActive ? 'nav-item-active' : 'nav-item-idle' ?>"
            >
              <svg class="w-4 h-4 shrink-0 <?= $isRsActive ? 'text-blue-700' : 'text-slate-500' ?>" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
              </svg>
              <span class="truncate">Pengaturan Rumah Sakit</span>
            </a>
          </nav>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- Bagian Bawah Sidebar: Profil User Ringkas -->
  <div class="pt-3 border-t border-slate-100 relative" id="sidebar-user-container">

    <!-- Sub-menu Akun (Ganti Password & Keluar) -->
    <div
      id="sidebar-user-Menu"
      class="hidden mb-2 ml-9 pl-2.5 border-l-2 border-slate-200 flex flex-col gap-1.5"
    >
      <a
        href="index.php?module=auth&amp;page=ganti-password"
        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200/80 transition-colors"
      >
        <svg class="w-3.5 h-3.5 shrink-0 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>
        </svg>
        <span>Ganti Password</span>
      </a>

      <a
        href="index.php?module=auth&amp;page=logout"
        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium text-red-600 bg-red-50/60 hover:bg-red-100/80 border border-red-200/60 transition-colors"
      >
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
        </svg>
        <span>Keluar</span>
      </a>
    </div>

    <!-- Tombol Induk Profil User -->
    <button
      type="button"
      onclick="toggleSidebarUserMenu(event)"
      class="w-full px-2.5 py-2 rounded-md hover:bg-slate-50 flex items-center justify-between gap-2.5 text-left transition-colors cursor-pointer"
      aria-expanded="false"
      id="sidebar-user-btn"
    >
      <div class="flex items-center gap-2.5 min-w-0 flex-1">
        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center text-xs font-semibold shrink-0 font-num">
          <?= e($userInitial) ?>
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-xs font-medium text-slate-800 truncate" title="<?= e($authUser['nama'] ?? 'User SIMRS') ?>">
            <?= e($authUser['nama'] ?? 'User SIMRS') ?>
          </p>
          <p class="font-num text-[11px] text-slate-500 truncate">
            <?= e($authUser['username'] ?? $authUser['id_user'] ?? '-') ?> · <?= e($authUser['jabatan'] ?? 'Petugas') ?>
          </p>
        </div>
      </div>
      <svg id="sidebar-user-chevron" class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-150" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/>
      </svg>
    </button>

    <script>
    function toggleSidebarUserMenu(e) {
      e.stopPropagation();
      const menu = document.getElementById('sidebar-user-Menu');
      const btn  = document.getElementById('sidebar-user-btn');
      if (!menu) return;
      const isHidden = menu.classList.contains('hidden');
      if (isHidden) {
        menu.classList.remove('hidden');
        if (btn) btn.setAttribute('aria-expanded', 'true');
      } else {
        menu.classList.add('hidden');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      }
    }

    document.addEventListener('click', function (e) {
      const container = document.getElementById('sidebar-user-container');
      const menu = document.getElementById('sidebar-user-Menu');
      const btn  = document.getElementById('sidebar-user-btn');
      if (container && menu && !container.contains(e.target)) {
        menu.classList.add('hidden');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      }
    });
    </script>
  </div>
</aside>
