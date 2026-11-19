<?php
declare(strict_types=1);

$statusOptions = [
    'ISI'    => 'Terisi',
    'KOSONG' => 'Kosong (Tersedia)',
];
?>

<!-- KARTU RINGKASAN EKSEKUTIF (KPI KETERISIAN TEMPAT TIDUR) -->
<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 no-print print:hidden">
  <!-- KPI 1: Total Tempat Tidur -->
  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Total Tempat Tidur</span>
      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21"/>
      </svg>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num"><?= (int) ($kpi['total_bed'] ?? 0) ?></span>
      <span class="text-xs text-slate-500">Kapasitas Bed Aktif</span>
    </div>
  </div>

  <!-- KPI 2: Tempat Tidur Terisi -->
  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Tempat Tidur Terisi</span>
      <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num"><?= (int) ($kpi['total_terisi'] ?? 0) ?></span>
      <span class="text-xs text-slate-500">Sedang Ditempati Pasien</span>
    </div>
  </div>

  <!-- KPI 3: Tempat Tidur Kosong -->
  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Tempat Tidur Kosong</span>
      <span class="w-2 h-2 rounded-full bg-emerald-600 inline-block"></span>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num"><?= (int) ($kpi['total_kosong'] ?? 0) ?></span>
      <span class="text-xs text-slate-500">Siap Menerima Pasien</span>
    </div>
  </div>

  <!-- KPI 4: Persentase Keterisian (BOR) -->
  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Tingkat Keterisian (BOR)</span>
      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
      </svg>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num">
        <?= number_format((float) ($kpi['persentase_bor'] ?? 0), 1, ',', '.') ?>%
      </span>
      <span class="text-xs text-slate-500">Standar Ideal: 60%–85%</span>
    </div>
  </div>
</section>

<!-- REKAPITULASI KETERISIAN PER RUANGAN / BANGSAL -->
<section class="permata-card overflow-hidden print-block">
  <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
    <div>
      <h3 class="text-sm font-semibold text-slate-900">Rekapitulasi Keterisian per Ruangan / Bangsal</h3>
      <p class="text-[11px] text-slate-500">Ringkasan kapasitas tempat tidur terisi dan kosong per unit rawat inap</p>
    </div>
  </div>

  <div class="overflow-x-auto print-table-wrapper">
    <table class="permata-table">
      <thead>
        <tr>
          <th class="text-left w-10">No</th>
          <th class="text-left">Kode</th>
          <th class="text-left">Nama Ruangan / Bangsal</th>
          <th class="text-center">Total Tempat Tidur</th>
          <th class="text-center">Terisi</th>
          <th class="text-center">Kosong</th>
          <th class="text-left w-56">Tingkat Keterisian (BOR)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rekapBangsal as $idx => $rb): ?>
          <?php $bor = (float) ($rb['persentase_bor'] ?? 0); ?>
          <tr>
            <td class="font-num text-slate-500"><?= $idx + 1 ?></td>
            <td class="font-num font-medium text-slate-700"><?= e((string) $rb['kd_bangsal']) ?></td>
            <td class="font-medium text-slate-900"><?= e((string) $rb['nm_bangsal']) ?></td>
            <td class="text-center font-num font-semibold text-slate-800"><?= (int) $rb['total_bed'] ?></td>
            <td class="text-center font-num font-medium text-slate-800"><?= (int) $rb['terisi'] ?></td>
            <td class="text-center font-num font-medium text-slate-800"><?= (int) $rb['kosong'] ?></td>
            <td>
              <div class="flex items-center gap-2.5">
                <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden no-print print:hidden">
                  <div
                    class="h-full <?= $bor >= 85 ? 'bg-amber-600' : 'bg-blue-600' ?>"
                    style="width: <?= min(100, max(0, $bor)) ?>%;"
                  ></div>
                </div>
                <span class="font-num text-xs font-semibold text-slate-800 w-12 text-right">
                  <?= number_format($bor, 1, ',', '.') ?>%
                </span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- BAR FILTER & PENCARIAN DETAIL TEMPAT TIDUR -->
<section class="permata-card p-4 no-print print:hidden">
  <form method="GET" action="index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
    <input type="hidden" name="module" value="operasional">
    <input type="hidden" name="page" value="tempat-tidur">

    <!-- Filter Bangsal -->
    <div class="lg:col-span-4">
      <label class="block text-[11px] font-medium text-slate-600 mb-1">Ruangan / Bangsal</label>
      <select name="kd_bangsal" class="permata-input w-full px-3 py-2 text-xs">
        <option value="">Semua Ruangan / Bangsal</option>
        <?php foreach ($bangsalOptions as $kd => $nm): ?>
          <option value="<?= e((string) $kd) ?>" <?= strtoupper((string) ($filters['kd_bangsal'] ?? '')) === strtoupper((string) $kd) ? 'selected' : '' ?>>
            <?= e((string) $nm) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Filter Kelas -->
    <div class="lg:col-span-2">
      <label class="block text-[11px] font-medium text-slate-600 mb-1">Kelas Perawatan</label>
      <select name="kelas" class="permata-input w-full px-3 py-2 text-xs">
        <option value="">Semua Kelas</option>
        <?php foreach ($kelasOptions as $kls): ?>
          <option value="<?= e((string) $kls) ?>" <?= strtoupper((string) ($filters['kelas'] ?? '')) === strtoupper((string) $kls) ? 'selected' : '' ?>>
            <?= e((string) $kls) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Filter Status Kamar -->
    <div class="lg:col-span-2">
      <label class="block text-[11px] font-medium text-slate-600 mb-1">Status Tempat Tidur</label>
      <select name="status" class="permata-input w-full px-3 py-2 text-xs">
        <option value="">Semua Status</option>
        <?php foreach ($statusOptions as $stKey => $stLabel): ?>
          <option value="<?= e($stKey) ?>" <?= strtoupper((string) ($filters['status'] ?? '')) === $stKey ? 'selected' : '' ?>>
            <?= e($stLabel) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Pencarian Kode Kamar -->
    <div class="lg:col-span-2">
      <label class="block text-[11px] font-medium text-slate-600 mb-1">Cari Kode Kamar</label>
      <input
        type="text"
        name="q"
        value="<?= e((string) ($filters['q'] ?? '')) ?>"
        placeholder="Contoh: VIP-01 / KL1..."
        class="permata-input w-full px-3 py-2 text-xs"
      >
    </div>

    <!-- Tombol Aksi -->
    <div class="lg:col-span-2 flex items-center gap-2">
      <button type="submit" class="btn-primary px-3.5 py-2 text-xs font-medium flex-1 cursor-pointer">
        Terapkan
      </button>
      <a
        href="index.php?module=operasional&amp;page=tempat-tidur"
        class="btn-secondary px-3 py-2 text-xs font-medium text-center"
      >
        Reset
      </a>
    </div>
  </form>
</section>

<!-- TABEL DETAIL DAFTAR TEMPAT TIDUR -->
<section class="permata-card overflow-hidden print-block">
  <div class="px-4 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2 no-print print:hidden">
    <div>
      <h3 class="text-sm font-semibold text-slate-900">Daftar Detail Tempat Tidur (Kamar Rawat Inap)</h3>
      <p class="text-[11px] text-slate-500">
        Menampilkan <span class="font-num font-semibold text-slate-700"><?= count($listKamar) ?></span> tempat tidur
      </p>
    </div>
    <div class="flex items-center gap-2">
      <a
        href="index.php?module=operasional&amp;page=tempat-tidur&amp;status=KOSONG"
        class="btn-secondary px-3 py-1.5 text-xs font-medium"
      >
        Tampilkan Hanya yang Kosong
      </a>
      <a
        href="index.php?module=operasional&amp;page=tempat-tidur&amp;status=ISI"
        class="btn-secondary px-3 py-1.5 text-xs font-medium"
      >
        Tampilkan Hanya yang Terisi
      </a>
    </div>
  </div>

  <div class="overflow-x-auto print-table-wrapper">
    <table class="permata-table">
      <thead>
        <tr>
          <th class="text-left w-10">No</th>
          <th class="text-left">Kode Kamar / Bed</th>
          <th class="text-left">Nama Ruangan / Bangsal</th>
          <th class="text-left">Kelas Perawatan</th>
          <th class="text-right">Tarif Kamar / Hari</th>
          <th class="text-left">Status Ketersediaan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($listKamar)): ?>
          <tr>
            <td colspan="6" class="text-center py-8 text-xs text-slate-400">
              Tidak ada data tempat tidur yang sesuai dengan filter pencarian.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($listKamar as $idx => $row): ?>
            <?php
              $isIsi = strtoupper((string) ($row['status'] ?? '')) === 'ISI';
              $statusStyle = $isIsi
                  ? 'color: #1d4ed8; font-weight: 600;'
                  : 'color: #15803d; font-weight: 600;';
              $statusLabel = $isIsi ? 'Terisi' : 'Kosong';
            ?>
            <tr>
              <td class="font-num text-slate-500"><?= $idx + 1 ?></td>
              <td class="font-num font-semibold text-slate-900"><?= e((string) ($row['kd_kamar'] ?? '-')) ?></td>
              <td class="font-medium text-slate-800"><?= e((string) ($row['nm_bangsal'] ?? '-')) ?></td>
              <td class="text-slate-600"><?= e((string) ($row['kelas'] ?? '-')) ?></td>
              <td class="text-right font-num text-slate-800"><?= rupiah((float) ($row['trf_kamar'] ?? 0)) ?></td>
              <td>
                <span style="<?= $statusStyle ?>"><?= e($statusLabel) ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
