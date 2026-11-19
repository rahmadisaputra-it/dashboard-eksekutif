<?php
declare(strict_types=1);

use App\Core\Auth;

$currentAdminId   = (int) ($authUser['id'] ?? 0);
$availableModules = $availableModules ?? Auth::MODULES;
?>

<!-- KARTU RINGKASAN KPI MANAJEMEN PENGGUNA -->
<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 no-print print:hidden">
  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Total Akun Terdaftar</span>
      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
      </svg>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num"><?= (int) ($kpi['total'] ?? 0) ?></span>
      <span class="text-xs text-slate-500">Pengguna Terdaftar</span>
    </div>
  </div>

  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Akun Status Aktif</span>
      <span class="w-2 h-2 rounded-full bg-emerald-600 inline-block"></span>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num"><?= (int) ($kpi['aktif'] ?? 0) ?></span>
      <span class="text-xs text-slate-500">Dapat Login</span>
    </div>
  </div>

  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Akun Dinonaktifkan</span>
      <span class="w-2 h-2 rounded-full bg-slate-400 inline-block"></span>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num"><?= (int) ($kpi['nonaktif'] ?? 0) ?></span>
      <span class="text-xs text-slate-500">Akses Dibekukan</span>
    </div>
  </div>

  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Administrator Sistem</span>
      <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num"><?= (int) ($kpi['admin'] ?? 0) ?></span>
      <span class="text-xs text-slate-500">Hak Akses Penuh</span>
    </div>
  </div>
</section>

<!-- NOTIFIKASI FLASH -->
<?php if (!empty($flashSuccess)): ?>
  <div class="p-3.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center justify-between gap-3 no-print print:hidden" role="status">
    <div class="flex items-center gap-2.5">
      <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <span class="font-medium"><?= e($flashSuccess) ?></span>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($flashError)): ?>
  <div class="p-3.5 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700 flex items-center justify-between gap-3 no-print print:hidden" role="alert">
    <div class="flex items-center gap-2.5">
      <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <span class="font-medium"><?= e($flashError) ?></span>
    </div>
  </div>
<?php endif; ?>

<!-- BAR PENCARIAN & TOMBOL TAMBAH PENGGUNA -->
<section class="permata-card p-4 no-print print:hidden">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <form method="GET" action="index.php" class="flex flex-wrap items-center gap-2.5 flex-1">
      <input type="hidden" name="module" value="pengguna">
      <input type="hidden" name="page" value="manajemen-akun">

      <div class="relative min-w-[220px] flex-1 max-w-xs">
        <input
          type="text"
          name="q"
          value="<?= e($filters['q'] ?? '') ?>"
          placeholder="Cari username, nama, jabatan..."
          class="permata-input w-full px-3.5 py-2 min-h-[38px] text-xs"
        >
      </div>

      <select name="role" class="permata-input px-3 py-2 min-h-[38px] text-xs">
        <option value="">Semua Role</option>
        <option value="admin" <?= ($filters['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
        <option value="user" <?= ($filters['role'] ?? '') === 'user' ? 'selected' : '' ?>>Pengguna Modul</option>
      </select>

      <select name="status" class="permata-input px-3 py-2 min-h-[38px] text-xs">
        <option value="">Semua Status</option>
        <option value="aktif" <?= ($filters['status'] ?? '') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
        <option value="nonaktif" <?= ($filters['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
      </select>

      <button type="submit" class="px-3.5 py-2 min-h-[38px] rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-medium text-slate-700 cursor-pointer">
        Terapkan Filter
      </button>

      <?php if (!empty($filters['q']) || !empty($filters['role']) || !empty($filters['status'])): ?>
        <a href="index.php?module=pengguna&amp;page=manajemen-akun" class="px-3 py-2 min-h-[38px] rounded-lg text-xs text-slate-500 hover:text-slate-800 inline-flex items-center">
          Reset
        </a>
      <?php endif; ?>
    </form>

    <button
      type="button"
      onclick="openCreateUserModal()"
      class="btn-primary px-4 py-2 min-h-[38px] text-xs font-medium inline-flex items-center gap-2 cursor-pointer"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
      </svg>
      <span>Buat Akun Baru</span>
    </button>
  </div>
</section>

<!-- TABEL DAFTAR AKUN PENGGUNA -->
<section class="permata-card overflow-hidden">
  <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
    <div>
      <h3 class="text-sm font-semibold text-slate-900">Daftar Akun Pengguna Dashboard Eksekutif</h3>
      <p class="text-[11px] text-slate-500">Hanya Administrator yang dapat membuat akun dan mengubah otorisasi modul</p>
    </div>
    <span class="text-xs text-slate-500 font-num"><?= count($users) ?> Akun</span>
  </div>

  <div class="overflow-x-auto">
    <table class="permata-table">
      <thead>
        <tr>
          <th class="text-left w-10">No</th>
          <th class="text-left">Username</th>
          <th class="text-left">Nama Lengkap &amp; Jabatan</th>
          <th class="text-left">Role</th>
          <th class="text-left">Hak Akses Modul</th>
          <th class="text-center">Status</th>
          <th class="text-left">Login Terakhir</th>
          <th class="text-right no-print print:hidden">Tindakan Admin</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr>
            <td colspan="8" class="text-center py-8 text-xs text-slate-400">
              Tidak ada data akun pengguna yang ditemukan.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($users as $idx => $u): ?>
            <?php
              $uid       = (int) ($u['id'] ?? 0);
              $isSelf    = ($uid === $currentAdminId);
              $uRole     = (string) ($u['role'] ?? 'user');
              $uStatus   = (string) ($u['status'] ?? 'aktif');
              $uModules  = is_array($u['modules_list'] ?? null) ? $u['modules_list'] : [];
              $jsonPayload = htmlspecialchars(json_encode([
                  'id'          => $uid,
                  'username'    => (string) ($u['username'] ?? ''),
                  'nama'        => (string) ($u['nama'] ?? ''),
                  'nik_pegawai' => (string) ($u['nik_pegawai'] ?? ''),
                  'jabatan'     => (string) ($u['jabatan'] ?? 'Petugas'),
                  'departemen'  => (string) ($u['departemen'] ?? '-'),
                  'role'        => $uRole,
                  'status'      => $uStatus,
                  'modules'     => $uModules,
                  'is_self'     => $isSelf,
              ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
            ?>
            <tr>
              <td class="font-num text-slate-500"><?= $idx + 1 ?></td>
              <td>
                <div class="font-num font-semibold text-slate-900">
                  <?= e($u['username'] ?? '-') ?>
                  <?php if ($isSelf): ?>
                    <span class="ml-1 text-[11px] font-normal text-slate-500">(Anda)</span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($u['nik_pegawai'])): ?>
                  <div class="text-[11px] text-slate-500 font-num">NIK: <?= e($u['nik_pegawai']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="font-medium text-slate-900"><?= e($u['nama'] ?? '-') ?></div>
                <div class="text-[11px] text-slate-500"><?= e($u['jabatan'] ?? 'Petugas') ?> · <?= e($u['departemen'] ?? '-') ?></div>
              </td>
              <td>
                <?php if ($uRole === 'admin'): ?>
                  <span class="text-xs font-semibold text-blue-700">Administrator</span>
                <?php else: ?>
                  <span class="text-xs font-medium text-slate-700">Pengguna</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($uRole === 'admin'): ?>
                  <span class="text-xs font-medium text-slate-800">Seluruh Modul</span>
                <?php elseif (!empty($uModules)): ?>
                  <?php
                    $modLabels = [];
                    foreach ($uModules as $mKey) {
                        $modLabels[] = (string) ($availableModules[$mKey]['label'] ?? ucfirst((string) $mKey));
                    }
                  ?>
                  <span class="text-xs text-slate-700"><?= e(implode(', ', $modLabels)) ?></span>
                <?php else: ?>
                  <span class="text-xs text-red-600">Belum diatur</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($uStatus === 'aktif'): ?>
                  <span class="text-xs font-semibold text-emerald-700">Aktif</span>
                <?php else: ?>
                  <span class="text-xs font-semibold text-red-700">Nonaktif</span>
                <?php endif; ?>
              </td>
              <td class="font-num text-xs text-slate-600">
                <?= !empty($u['last_login']) ? e((string) $u['last_login']) : '<span class="text-slate-400">Belum pernah</span>' ?>
              </td>
              <td class="text-right no-print print:hidden">
                <div class="inline-flex items-center justify-end gap-1.5">
                  <button
                    type="button"
                    data-user="<?= $jsonPayload ?>"
                    onclick="openEditUserModal(this)"
                    class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-[11px] font-medium text-slate-700 cursor-pointer"
                  >
                    Hak Akses &amp; Edit
                  </button>

                  <button
                    type="button"
                    data-user="<?= $jsonPayload ?>"
                    onclick="openResetPasswordModal(this)"
                    class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-[11px] font-medium text-blue-700 cursor-pointer"
                  >
                    Reset Password
                  </button>

                  <?php if (!$isSelf): ?>
                    <form method="POST" action="index.php?module=pengguna&amp;page=manajemen-akun" class="inline">
                      <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="user_id" value="<?= $uid ?>">
                      <button
                        type="submit"
                        class="px-2.5 py-1.5 rounded-lg border text-[11px] font-medium cursor-pointer <?= $uStatus === 'aktif' ? 'border-red-200 bg-red-50/60 hover:bg-red-100/70 text-red-700' : 'border-emerald-200 bg-emerald-50/60 hover:bg-emerald-100/70 text-emerald-700' ?>"
                      >
                        <?= $uStatus === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- LOG PENGIRIMAN DATA SATU ARAH (PUSH API DARI SERVICE SIMRS KHANZA) -->
<section class="permata-card overflow-hidden no-print print:hidden">
  <div class="px-4 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h3 class="text-sm font-semibold text-slate-900">Riwayat Pengiriman Data API (Outbound Push dari Service Khanza)</h3>
      <p class="text-[11px] text-slate-500">
        Arsitektur Zero Inbound — Server Khanza mengirim data satu arah ke database mandiri <span class="font-num font-medium text-slate-700">dashboard_eksekutif</span>
      </p>
    </div>
  </div>

  <?php
    $syncCards = [
        [
            'key'      => 'kendali_biaya',
            'label'    => 'Kendali Biaya Ranap',
            'endpoint' => '/api/sync-kendali-biaya.php',
            'unit'     => 'pasien aktif',
        ],
        [
            'key'      => 'tempat_tidur',
            'label'    => 'Keterisian Tempat Tidur',
            'endpoint' => '/api/sync-tempat-tidur.php',
            'unit'     => 'tempat tidur',
        ],
        [
            'key'      => 'jadwal_hfis',
            'label'    => 'Jadwal Dokter HFIS',
            'endpoint' => '/api/sync-jadwal-hfis.php',
            'unit'     => 'sesi jadwal',
        ],
        [
            'key'      => 'jam_poliklinik',
            'label'    => 'Jam Mulai Poliklinik',
            'endpoint' => '/api/sync-jam-poliklinik.php',
            'unit'     => 'sesi evaluasi',
        ],
    ];
  ?>
  <div class="px-4 py-3 bg-slate-50/70 border-b border-slate-200/80 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
    <?php foreach ($syncCards as $sc): ?>
      <?php
        $stData   = $moduleSyncStats[$sc['key']] ?? ['count' => 0, 'last_sync' => '-'];
        $cnt      = (int) ($stData['count'] ?? 0);
        $lastSync = (string) ($stData['last_sync'] ?? '-');
        $hasData  = ($cnt > 0 && $lastSync !== '-');
      ?>
      <div class="bg-white border border-slate-200 rounded-lg p-3 flex flex-col justify-between gap-1.5">
        <div class="flex items-center justify-between gap-2">
          <span class="text-xs font-semibold text-slate-800 truncate"><?= e($sc['label']) ?></span>
          <span class="w-2 h-2 rounded-full shrink-0 <?= $hasData ? 'bg-emerald-600' : 'bg-slate-300' ?>"></span>
        </div>
        <div class="text-[11px] font-num text-blue-700 truncate">POST <?= e($sc['endpoint']) ?></div>
        <div class="flex items-baseline justify-between gap-2 pt-1 border-t border-slate-100">
          <span class="text-xs font-num font-semibold text-slate-900">
            <?= angka($cnt) ?> <span class="font-normal text-[11px] text-slate-500"><?= e($sc['unit']) ?></span>
          </span>
          <span class="text-[10px] font-num text-slate-500 truncate" title="Sinkronisasi terakhir: <?= e($lastSync) ?>">
            <?= $lastSync !== '-' ? e($lastSync) : 'Belum sync' ?>
          </span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="overflow-x-auto">
    <table class="permata-table">
      <thead>
        <tr>
          <th class="text-left">Waktu Push</th>
          <th class="text-left">Modul / Jenis Data</th>
          <th class="text-center">Jumlah Record</th>
          <th class="text-left">Service Pengirim</th>
          <th class="text-left">IP Pengirim</th>
          <th class="text-center">Status</th>
          <th class="text-left">Ringkasan Hasil</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($apiLogs)): ?>
          <tr>
            <td colspan="7" class="text-center py-6 text-xs text-slate-400">
              Belum ada histori pengiriman API yang tercatat.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($apiLogs as $lg): ?>
            <tr>
              <td class="font-num text-xs text-slate-600"><?= e((string) ($lg['waktu_sync'] ?? '-')) ?></td>
              <td>
                <span class="px-2 py-0.5 rounded text-[11px] font-num font-medium bg-blue-50 text-blue-700 border border-blue-200">
                  <?= e((string) ($lg['jenis_data'] ?? '-')) ?>
                </span>
              </td>
              <td class="text-center font-num font-semibold text-slate-900"><?= angka((int) ($lg['total_record'] ?? 0)) ?></td>
              <td class="font-num text-xs text-slate-600"><?= e((string) ($lg['pengirim'] ?? '-')) ?></td>
              <td class="font-num text-xs text-slate-500"><?= e((string) ($lg['ip_pengirim'] ?? '127.0.0.1')) ?></td>
              <td class="text-center">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                  <?= e((string) ($lg['status'] ?? 'SUKSES')) ?>
                </span>
              </td>
              <td class="text-xs text-slate-600"><?= e((string) ($lg['keterangan'] ?? '-')) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- MODAL 1: TAMBAH AKUN PENGGUNA BARU -->
<div id="modal-create-user" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4 no-print print:hidden">
  <div class="permata-card w-full max-w-lg p-6">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
      <div>
        <h3 class="text-sm font-semibold text-slate-900">Buat Akun Pengguna Baru</h3>
        <p class="text-[11px] text-slate-500">Pengguna dapat mengganti password awalnya sendiri setelah login</p>
      </div>
      <button type="button" onclick="closeModal('modal-create-user')" class="text-slate-400 hover:text-slate-700 p-1 cursor-pointer" aria-label="Tutup">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <form method="POST" action="index.php?module=pengguna&amp;page=manajemen-akun" class="space-y-3.5" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">
      <input type="hidden" name="action" value="create">

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Username Login *</label>
          <input type="text" name="username" required pattern="[a-zA-Z0-9._-]{3,50}" placeholder="misal: budi.keuangan" class="permata-input w-full px-3 py-2 text-xs font-num">
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Password Awal *</label>
          <input type="password" name="password" required minlength="6" autocomplete="new-password" placeholder="Min. 6 karakter" class="permata-input w-full px-3 py-2 text-xs">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Nama Lengkap *</label>
          <input type="text" name="nama" required placeholder="Nama lengkap beserta gelar" class="permata-input w-full px-3 py-2 text-xs">
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">NIK / NIP Pegawai <span class="text-slate-400 font-normal">(Opsional)</span></label>
          <input type="text" name="nik_pegawai" placeholder="Nomor induk pegawai" class="permata-input w-full px-3 py-2 text-xs font-num">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Jabatan</label>
          <input type="text" name="jabatan" value="Petugas" class="permata-input w-full px-3 py-2 text-xs">
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Unit / Departemen</label>
          <input type="text" name="departemen" value="-" class="permata-input w-full px-3 py-2 text-xs">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Peran Akun (Role)</label>
          <select name="role" id="create-role" onchange="toggleModulesChecklist('create')" class="permata-input w-full px-3 py-2 text-xs">
            <option value="user">Pengguna Modul (Sesuai Checklist)</option>
            <option value="admin">Administrator (Akses Penuh)</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Status Awal</label>
          <select name="status" class="permata-input w-full px-3 py-2 text-xs">
            <option value="aktif">Aktif</option>
            <option value="nonaktif">Nonaktif</option>
          </select>
        </div>
      </div>

      <div id="create-modules-box" class="p-3 rounded-lg bg-slate-50 border border-slate-200">
        <span class="block text-xs font-medium text-slate-700 mb-2">Hak Akses Modul yang Diizinkan *</span>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <?php foreach ($availableModules as $mKey => $mMeta): ?>
            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
              <input type="checkbox" name="modules[]" value="<?= e($mKey) ?>" checked class="rounded border-slate-300 text-blue-700">
              <span><?= e($mMeta['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
        <button type="button" onclick="closeModal('modal-create-user')" class="px-3.5 py-2 rounded-lg border border-slate-200 bg-white text-xs font-medium text-slate-700 cursor-pointer">
          Batal
        </button>
        <button type="submit" class="btn-primary px-4 py-2 text-xs font-medium cursor-pointer">
          Simpan Akun Baru
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL 2: EDIT PROFIL & HAK AKSES MODUL -->
<div id="modal-edit-user" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4 no-print print:hidden">
  <div class="permata-card w-full max-w-lg p-6">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
      <div>
        <h3 class="text-sm font-semibold text-slate-900">Atur Hak Akses &amp; Profil Pengguna</h3>
        <p class="text-[11px] text-slate-500 font-num" id="edit-username-label">@username</p>
      </div>
      <button type="button" onclick="closeModal('modal-edit-user')" class="text-slate-400 hover:text-slate-700 p-1 cursor-pointer" aria-label="Tutup">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <form method="POST" action="index.php?module=pengguna&amp;page=manajemen-akun" class="space-y-3.5">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="user_id" id="edit-user-id" value="">

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Nama Lengkap *</label>
          <input type="text" name="nama" id="edit-nama" required class="permata-input w-full px-3 py-2 text-xs">
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">NIK / NIP Pegawai</label>
          <input type="text" name="nik_pegawai" id="edit-nik" class="permata-input w-full px-3 py-2 text-xs font-num">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Jabatan</label>
          <input type="text" name="jabatan" id="edit-jabatan" class="permata-input w-full px-3 py-2 text-xs">
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Unit / Departemen</label>
          <input type="text" name="departemen" id="edit-departemen" class="permata-input w-full px-3 py-2 text-xs">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Peran Akun (Role)</label>
          <select name="role" id="edit-role" onchange="toggleModulesChecklist('edit')" class="permata-input w-full px-3 py-2 text-xs">
            <option value="user">Pengguna Modul</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Status Akun</label>
          <select name="status" id="edit-status" class="permata-input w-full px-3 py-2 text-xs">
            <option value="aktif">Aktif</option>
            <option value="nonaktif">Nonaktif</option>
          </select>
        </div>
      </div>

      <div id="edit-modules-box" class="p-3 rounded-lg bg-slate-50 border border-slate-200">
        <span class="block text-xs font-medium text-slate-700 mb-2">Hak Akses Modul yang Diizinkan *</span>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <?php foreach ($availableModules as $mKey => $mMeta): ?>
            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
              <input type="checkbox" name="modules[]" value="<?= e($mKey) ?>" class="edit-mod-checkbox rounded border-slate-300 text-blue-700">
              <span><?= e($mMeta['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
        <button type="button" onclick="closeModal('modal-edit-user')" class="px-3.5 py-2 rounded-lg border border-slate-200 bg-white text-xs font-medium text-slate-700 cursor-pointer">
          Batal
        </button>
        <button type="submit" class="btn-primary px-4 py-2 text-xs font-medium cursor-pointer">
          Simpan Perubahan
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL 3: RESET PASSWORD OLEH ADMIN -->
<div id="modal-reset-password" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4 no-print print:hidden">
  <div class="permata-card w-full max-w-md p-6">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
      <div>
        <h3 class="text-sm font-semibold text-slate-900">Reset Password Pengguna</h3>
        <p class="text-[11px] text-slate-500 font-num" id="reset-username-label">@username</p>
      </div>
      <button type="button" onclick="closeModal('modal-reset-password')" class="text-slate-400 hover:text-slate-700 p-1 cursor-pointer" aria-label="Tutup">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <form method="POST" action="index.php?module=pengguna&amp;page=manajemen-akun" class="space-y-4" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="reset-user-id" value="">

      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1.5">Password Baru (Minimal 6 karakter) *</label>
        <input
          type="password"
          name="new_password"
          required
          minlength="6"
          autocomplete="new-password"
          placeholder="Masukkan password baru untuk pengguna ini"
          class="permata-input w-full px-3.5 py-2.5 text-xs"
        >
        <p class="text-[11px] text-slate-400 mt-1.5">
          Berikan password baru ini kepada pengguna terkait agar segera diganti melalui menu Ganti Password.
        </p>
      </div>

      <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
        <button type="button" onclick="closeModal('modal-reset-password')" class="px-3.5 py-2 rounded-lg border border-slate-200 bg-white text-xs font-medium text-slate-700 cursor-pointer">
          Batal
        </button>
        <button type="submit" class="btn-primary px-4 py-2 text-xs font-medium cursor-pointer">
          Reset Password Sekarang
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openCreateUserModal() {
  const modal = document.getElementById('modal-create-user');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    toggleModulesChecklist('create');
  }
}

function openEditUserModal(btn) {
  const raw = btn.getAttribute('data-user');
  if (!raw) return;
  const u = JSON.parse(raw);

  document.getElementById('edit-user-id').value = u.id;
  document.getElementById('edit-username-label').textContent = '@' + u.username + ' — ' + u.nama;
  document.getElementById('edit-nama').value = u.nama || '';
  document.getElementById('edit-nik').value = u.nik_pegawai || '';
  document.getElementById('edit-jabatan').value = u.jabatan || 'Petugas';
  document.getElementById('edit-departemen').value = u.departemen || '-';
  document.getElementById('edit-role').value = u.role || 'user';
  document.getElementById('edit-status').value = u.status || 'aktif';

  const boxes = document.querySelectorAll('.edit-mod-checkbox');
  boxes.forEach(function (cb) {
    cb.checked = Array.isArray(u.modules) && u.modules.indexOf(cb.value) !== -1;
  });

  toggleModulesChecklist('edit');

  const modal = document.getElementById('modal-edit-user');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }
}

function openResetPasswordModal(btn) {
  const raw = btn.getAttribute('data-user');
  if (!raw) return;
  const u = JSON.parse(raw);

  document.getElementById('reset-user-id').value = u.id;
  document.getElementById('reset-username-label').textContent = '@' + u.username + ' (' + u.nama + ')';

  const modal = document.getElementById('modal-reset-password');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

function toggleModulesChecklist(prefix) {
  const roleSelect = document.getElementById(prefix + '-role');
  const box = document.getElementById(prefix + '-modules-box');
  if (!roleSelect || !box) return;
  if (roleSelect.value === 'admin') {
    box.classList.add('opacity-50', 'pointer-events-none');
  } else {
    box.classList.remove('opacity-50', 'pointer-events-none');
  }
}
</script>
