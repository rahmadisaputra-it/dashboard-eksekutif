<?php
declare(strict_types=1);

$isPrintable = !isset($printable) || (bool) $printable;
$appName     = (string) ($hospital['nama_aplikasi'] ?? 'Dashboard Eksekutif');
$rsName      = (string) ($hospital['nama_instansi'] ?? 'RS Mitra SIMRS Khanza');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Kendali Biaya Rawat Inap') ?> — <?= e($appName) ?> <?= e($rsName) ?></title>
  <link rel="stylesheet" href="assets/css/permata-bevel.css?v=<?= @filemtime(dirname(__DIR__, 3) . '/assets/css/permata-bevel.css') ?: '4' ?>">
  <script src="assets/css/tailwind.cdn.js"></script>
  <script>
    if (typeof tailwind === 'undefined') {
      document.write('<script src="https://cdn.tailwindcss.com"><\/script>');
    }
  </script>
</head>
<body class="canvas-clean text-slate-800 antialiased">

  <!-- WORKSPACE UTAMA MENYATU (SIDEBAR KIRI + AREA KERJA KANAN) -->
  <div class="permata-shell">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- AREA KERJA UTAMA -->
    <main class="permata-main">
      <div class="flex-1 flex flex-col print-block">

        <?php if ($isPrintable): ?>
          <!-- Kop Surat & Header Laporan Saat Cetak -->
          <?php require __DIR__ . '/print_header.php'; ?>
        <?php endif; ?>

        <!-- Header Halaman Utama (Hanya Layar) -->
        <header class="no-print print:hidden mb-6 flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 class="text-2xl font-semibold text-slate-900 tracking-tight">
              <?= e($pageTitle ?? 'Kendali Biaya Rawat Inap') ?>
            </h2>
            <p class="text-xs text-slate-500 mt-1">
              <?= e($pageSubtitle ?? ($appName . ' — ' . $rsName)) ?>
            </p>
          </div>

          <div class="flex items-center gap-2.5">
            <div class="bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs font-num text-slate-600">
              <?= tanggal_indo(date('Y-m-d'), false) ?>
            </div>

            <?php if ($isPrintable): ?>
            <button
              type="button"
              onclick="window.print()"
              class="btn-primary px-4 py-2 min-h-[38px] text-xs font-medium flex items-center gap-2 cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 2.25 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/>
              </svg>
              <span>Cetak Laporan</span>
            </button>
            <?php endif; ?>
          </div>
        </header>

        <!-- Isi Konten Modul Dinamis -->
        <div class="flex-1 space-y-5 print-block">
          <?= $content ?>
        </div>

        <?php if ($isPrintable): ?>
          <!-- Blok Tanda Tangan Resmi (Hanya Tampil Saat Cetak) -->
          <div class="print-only" style="margin-top: 14px; page-break-inside: avoid;">
            <div style="display: flex; justify-content: space-between; font-size: 9pt;">
              <div style="text-align: center; width: 38%;">
                <div>Mengetahui,</div>
                <div style="font-weight: 600;">Direktur <?= e($rsName) ?></div>
                <div style="height: 44px;"></div>
                <div style="font-weight: 600; text-decoration: underline;">
                  <?= e(!empty($hospital['nama_direktur']) ? (string) $hospital['nama_direktur'] : '( ................................................. )') ?>
                </div>
                <div style="font-size: 8pt; color: #475569;">Direktur / Pimpinan RS</div>
              </div>
              <div style="text-align: center; width: 38%;">
                <div><?= e($hospital['kabupaten'] ?? 'Indonesia') ?>, <?= tanggal_indo(date('Y-m-d'), false) ?></div>
                <div style="font-weight: 600;"><?= e($printSignerRole ?? 'Petugas Pelaporan') ?></div>
                <div style="height: 44px;"></div>
                <div style="font-weight: 600; text-decoration: underline;">
                  <?= e($authUser['nama'] ?? '( ................................................. )') ?>
                </div>
                <div style="font-size: 8pt; color: #475569;" class="font-num">
                  ID: <?= e($authUser['id_user'] ?? '-') ?> · <?= e($authUser['jabatan'] ?? 'Petugas') ?>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

      </div>

      <!-- Sticky Footer Bersih di Bawah Area Kerja -->
      <footer class="no-print print:hidden mt-8 pt-4 border-t border-slate-200/70 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400">
        <div>
          <span class="font-medium text-slate-600"><?= e($appName) ?></span>
          <span class="mx-1.5">·</span>
          <span><?= e($rsName) ?></span>
        </div>
        <div>
          <span>&copy; <?= date('Y') ?> Developed by <strong class="font-medium text-slate-600">Rahmadi Saputra</strong></span>
        </div>
      </footer>
    </main>

  </div>

</body>
</html>
