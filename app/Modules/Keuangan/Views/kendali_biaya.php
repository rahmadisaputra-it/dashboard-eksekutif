<?php
declare(strict_types=1);
?>
<!-- 1. ENAM KARTU KPI RINGKASAN KENDALI BIAYA RAWAT INAP (.bevel-card) -->
<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3.5 no-print print:hidden">

  <!-- Total Pasien -->
  <div class="bevel-card p-3.5 flex flex-col justify-between" id="card-total-pasien">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Total Pasien</span>
      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
    </div>
    <p class="font-num text-xl font-semibold text-slate-900 mt-1.5">
      <?= angka($stats['total_pasien'] ?? 0) ?>
    </p>
    <p class="text-xs text-slate-500 mt-1">Pasien aktif rawat inap</p>
  </div>

  <!-- Tidak Terkendali -->
  <div class="bevel-card p-3.5 flex flex-col justify-between" id="card-tidak-terkendali">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Tidak Terkendali</span>
      <span class="w-2 h-2 rounded-full bg-red-600 inline-block"></span>
    </div>
    <p class="font-num text-xl font-semibold text-slate-900 mt-1.5">
      <?= angka($stats['tidak_terkendali'] ?? 0) ?>
    </p>
    <p class="text-xs text-slate-500 mt-1 font-num">Rasio &gt; 100%</p>
  </div>

  <!-- Kurang Terkendali -->
  <div class="bevel-card p-3.5 flex flex-col justify-between" id="card-kurang-terkendali">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Kurang Terkendali</span>
      <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>
    </div>
    <p class="font-num text-xl font-semibold text-slate-900 mt-1.5">
      <?= angka($stats['kurang_terkendali'] ?? 0) ?>
    </p>
    <p class="text-xs text-slate-500 mt-1 font-num">Rasio 86–99%</p>
  </div>

  <!-- Cukup Terkendali -->
  <div class="bevel-card p-3.5 flex flex-col justify-between" id="card-cukup-terkendali">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Cukup Terkendali</span>
      <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
    </div>
    <p class="font-num text-xl font-semibold text-slate-900 mt-1.5">
      <?= angka($stats['cukup_terkendali'] ?? 0) ?>
    </p>
    <p class="text-xs text-slate-500 mt-1 font-num">Rasio 71–85%</p>
  </div>

  <!-- Total Tarif RS -->
  <div class="bevel-card p-3.5 flex flex-col justify-between" id="card-total-tarif-rs">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Total Tarif RS</span>
      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        <line x1="12" y1="1" x2="12" y2="23"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
      </svg>
    </div>
    <p class="font-num text-base font-semibold text-slate-900 mt-1.5 truncate" title="<?= e(rupiah($stats['total_tarif_rs'] ?? 0)) ?>">
      <?= rupiah($stats['total_tarif_rs'] ?? 0) ?>
    </p>
    <p class="text-xs text-slate-500 mt-1">Akumulasi modal RS</p>
  </div>

  <!-- Total Tarif INA-CBGs -->
  <div class="bevel-card p-3.5 flex flex-col justify-between" id="card-total-tarif-inacbg">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Total Tarif INAcbgs</span>
      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
    </div>
    <p class="font-num text-base font-semibold text-slate-900 mt-1.5 truncate" title="<?= e(rupiah($stats['total_tarif_inacbg'] ?? 0)) ?>">
      <?= rupiah($stats['total_tarif_inacbg'] ?? 0) ?>
    </p>
    <p class="text-xs text-slate-500 mt-1">Akumulasi paket BPJS</p>
  </div>

</section>

<!-- 2. BARIS KETERANGAN STATUS & FILTER PENCARIAN (.bevel-card) -->
<section class="bevel-card p-3.5 space-y-3 no-print print:hidden">

  <!-- Indikator Keterangan Rasio -->
  <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-200/80 text-xs">
    <div class="flex flex-wrap items-center gap-4">
      <span class="font-semibold text-slate-700">Keterangan Rasio:</span>
      <span class="inline-flex items-center gap-1.5 text-slate-700">
        <span class="w-2.5 h-2.5 rounded-full bg-red-600 inline-block"></span>
        <span>Tidak Terkendali <span class="text-slate-400 font-num">(&gt; 100%)</span></span>
      </span>
      <span class="inline-flex items-center gap-1.5 text-slate-700">
        <span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span>
        <span>Kurang Terkendali <span class="text-slate-400 font-num">(86–99%)</span></span>
      </span>
      <span class="inline-flex items-center gap-1.5 text-slate-700">
        <span class="w-2.5 h-2.5 rounded-full bg-blue-600 inline-block"></span>
        <span>Cukup Terkendali <span class="text-slate-400 font-num">(71–85%)</span></span>
      </span>
      <span class="inline-flex items-center gap-1.5 text-slate-700">
        <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 inline-block"></span>
        <span>Sangat Terkendali <span class="text-slate-400 font-num">(&lt; 71%)</span></span>
      </span>
    </div>

    <div class="text-slate-500 font-num">
      Sinkronisasi: <strong class="text-slate-700"><?= e($cachedAt ?? date('H:i:s')) ?> WIB</strong>
    </div>
  </div>

  <!-- Form Filter Pencarian, Bangsal, Status, dan Refresh -->
  <div class="flex flex-wrap items-center justify-between gap-3">
    <form method="GET" action="index.php" id="filter-form" class="flex flex-wrap items-center gap-2.5 flex-1">
      <input type="hidden" name="module" value="keuangan">
      <input type="hidden" name="page" value="kendali-biaya">

      <!-- Input Cari -->
      <div class="relative flex-1 min-w-[240px]">
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
        </div>
        <input
          type="text"
          name="search"
          id="input-search"
          value="<?= e($search ?? '') ?>"
          placeholder="Cari No. Rawat, No. RM, Nama Pasien, Kamar..."
          class="permata-input w-full pl-9 pr-3 py-2 min-h-[38px] text-xs"
        >
      </div>

      <!-- Filter Bangsal -->
      <select
        name="bangsal"
        id="filter-bangsal"
        aria-label="Filter Bangsal"
        class="permata-input text-xs px-3 py-2 min-h-[38px]"
      >
        <option value="">Semua Bangsal</option>
        <?php foreach ($bangsalList as $b): ?>
          <option value="<?= e($b) ?>" <?= ($bangsal ?? '') === $b ? 'selected' : '' ?>>
            <?= e($b) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Filter Status Kendali Biaya (Bebas Emoji Mentah) -->
      <select
        name="status"
        id="filter-status"
        aria-label="Filter Status Kendali Biaya"
        class="permata-input text-xs px-3 py-2 min-h-[38px]"
      >
        <option value="">Semua Status Kendali</option>
        <option value="Tidak Terkendali" <?= ($status ?? '') === 'Tidak Terkendali' ? 'selected' : '' ?>>
          Tidak Terkendali (&gt; 100%)
        </option>
        <option value="Kurang Terkendali" <?= ($status ?? '') === 'Kurang Terkendali' ? 'selected' : '' ?>>
          Kurang Terkendali (86–99%)
        </option>
        <option value="Cukup Terkendali" <?= ($status ?? '') === 'Cukup Terkendali' ? 'selected' : '' ?>>
          Cukup Terkendali (71–85%)
        </option>
        <option value="Sangat Terkendali" <?= ($status ?? '') === 'Sangat Terkendali' ? 'selected' : '' ?>>
          Sangat Terkendali (&lt; 71%)
        </option>
      </select>

      <!-- Tombol Terapkan Filter -->
      <button
        type="submit"
        class="btn-primary px-4 py-2 min-h-[38px] text-xs font-medium flex items-center gap-1.5 cursor-pointer"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
        </svg>
        <span>Filter</span>
      </button>

      <?php if (!empty($search) || !empty($bangsal) || !empty($status)): ?>
        <a
          href="index.php?module=keuangan&amp;page=kendali-biaya"
          class="btn-secondary px-3.5 py-2 min-h-[38px] text-xs font-medium flex items-center"
        >
          Reset
        </a>
      <?php endif; ?>
    </form>

    <!-- Form Refresh Cache Data Real-Time -->
    <form method="POST" action="index.php?module=keuangan&amp;page=kendali-biaya" class="shrink-0">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">
      <input type="hidden" name="action" value="refresh">
      <input type="hidden" name="search" value="<?= e($search ?? '') ?>">
      <input type="hidden" name="bangsal" value="<?= e($bangsal ?? '') ?>">
      <input type="hidden" name="status" value="<?= e($status ?? '') ?>">
      <button
        type="submit"
        class="btn-secondary px-3.5 py-2 min-h-[38px] text-xs font-medium flex items-center gap-1.5 cursor-pointer"
      >
        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <polyline points="23 4 23 10 17 10"/>
          <path stroke-linecap="round" stroke-linejoin="round" d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
        </svg>
        <span>Refresh Data</span>
      </button>
    </form>
  </div>

</section>

<!-- 3. TABEL RINCIAN FORMAT KENDALI BIAYA PASIEN RAWAT INAP AKTIF (.bevel-card) -->
<section class="bevel-card overflow-hidden flex flex-col">

  <div class="px-5 py-3.5 border-b border-slate-200/90 bg-slate-50/70 flex flex-wrap items-center justify-between gap-3 no-print print:hidden">
    <div class="flex items-center gap-2.5">
      <h3 class="text-sm font-semibold text-slate-900">
        Format Kendali Biaya — Pasien Rawat Inap Aktif
      </h3>
      <span class="font-num text-xs font-semibold text-blue-900 bg-blue-50 border border-blue-200/80 px-2.5 py-0.5 rounded-md">
        <?= angka(count($patients)) ?> Pasien
      </span>
      <?php if (!empty($refreshed)): ?>
        <span id="flash-refresh" class="text-xs font-medium text-emerald-700 flex items-center gap-1 transition-all duration-300 ease-out">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
          Data berhasil diperbarui dari SIMRS Khanza
        </span>
      <?php endif; ?>
    </div>

    <div class="flex items-center gap-2 no-print">
      <a
        href="index.php?module=keuangan&amp;page=tarif-inacbg"
        class="text-xs font-semibold text-blue-800 hover:underline flex items-center gap-1"
      >
        <span>Kelola Referensi Tarif INA-CBG</span>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
        </svg>
      </a>
    </div>
  </div>

  <!-- Tabel Scrollable 21 Kolom dengan Sticky Kolom Kiri -->
  <div class="overflow-x-auto max-h-[580px] overflow-y-auto relative print-table-wrapper">
    <table class="permata-table whitespace-nowrap">
      <thead class="sticky top-0 z-20 shadow-sm">
        <tr class="bg-slate-100 border-b border-slate-300 text-slate-700">
          <th class="text-center sticky left-0 bg-slate-100 z-20 w-11 min-w-[44px]">No</th>
          <th class="text-left sticky left-11 bg-slate-100 z-20 min-w-[210px] border-r border-slate-200">Nama Pasien</th>
          <th class="text-left">No. RM</th>
          <th class="text-left">Ruangan / Bangsal</th>
          <th class="text-left">Diagnosa Awal</th>
          <th class="text-right">Jasa Medis</th>
          <th class="text-right">Obat &amp; BHP</th>
          <th class="text-right">Lab</th>
          <th class="text-right">Radiologi</th>
          <th class="text-right">Darah</th>
          <th class="text-right">Oksigen</th>
          <th class="text-right">Diet</th>
          <th class="text-right">Kamar Rawatan</th>
          <th class="text-right">Sewa Alat+OK</th>
          <th class="text-right">Biaya Bayi</th>
          <th class="text-right">ADM</th>
          <th class="text-right font-semibold text-slate-900">Tarif RS</th>
          <th class="text-right font-semibold text-slate-900">Tarif INAcbgs</th>
          <th class="text-right font-semibold text-slate-900">Selisih</th>
          <th class="text-right font-semibold text-slate-900">Rasio</th>
          <th class="text-center">Keterangan</th>
        </tr>
        <tr class="bg-slate-50 text-slate-500 border-b border-slate-200 text-xs font-num">
          <td colspan="2" class="py-1.5 px-3.5 font-medium text-slate-600 sticky left-0 bg-slate-50 z-20 border-r border-slate-200">Rumus Dasar Perhitungan Modal</td>
          <td colspan="3" class="py-1.5 px-3 bg-slate-50"></td>
          <td class="py-1.5 px-3 text-right">35% × INAcbgs</td>
          <td class="py-1.5 px-3 text-right">HPP (÷1,41)</td>
          <td class="py-1.5 px-3 text-right">50% Tarif RS</td>
          <td class="py-1.5 px-3 text-right">50% Tarif RS</td>
          <td class="py-1.5 px-3 text-right">65% Tarif RS</td>
          <td class="py-1.5 px-3 text-right">40% Tarif RS</td>
          <td class="py-1.5 px-3 text-right">50% Tarif RS</td>
          <td class="py-1.5 px-3 text-right">50% Tarif RS</td>
          <td class="py-1.5 px-3 text-right">50% Tarif RS</td>
          <td class="py-1.5 px-3 text-right">250.000</td>
          <td class="py-1.5 px-3 text-right">Flat 150.000</td>
          <td class="py-1.5 px-3 text-right font-semibold text-slate-700">SUM Modal</td>
          <td class="py-1.5 px-3 text-right font-semibold text-slate-700">Paket ICD-10</td>
          <td class="py-1.5 px-3 text-right">INAcbgs − RS</td>
          <td class="py-1.5 px-3 text-right">RS ÷ INAcbgs</td>
          <td class="py-1.5 px-3 text-center">Status</td>
        </tr>
      </thead>

      <tbody>
        <?php if (!empty($patients)): ?>
          <?php foreach ($patients as $i => $row): ?>
            <?php
            $ket = (string) ($row['keterangan'] ?? 'N/A');
            ?>
            <tr class="bg-white hover:bg-slate-50">
              <td class="text-center font-num text-slate-500 sticky left-0 bg-white z-10 w-11 min-w-[44px]">
                <?= $i + 1 ?>
              </td>
              <td class="font-semibold text-slate-900 sticky left-11 bg-white z-10 min-w-[210px] border-r border-slate-200/90">
                <div><?= e($row['nm_pasien']) ?></div>
                <div class="font-num text-xs font-normal text-slate-500"><?= e($row['no_rawat']) ?></div>
              </td>
              <td class="font-num text-slate-700"><?= e($row['no_rkm_medis']) ?></td>
              <td>
                <div class="font-medium text-slate-800"><?= e($row['nm_bangsal']) ?></div>
                <div class="font-num text-xs text-slate-500"><?= e($row['lokasi']) ?></div>
              </td>
              <td class="font-num text-slate-700 max-w-[160px] truncate" title="<?= e($row['diagnosa_awal'] ?: '-') ?>">
                <?= e($row['diagnosa_awal'] ?: '-') ?>
              </td>

              <td class="text-right font-num"><?= angka($row['modal_jasa_medis']) ?></td>
              <td class="text-right font-num"><?= angka($row['modal_obat']) ?></td>
              <td class="text-right font-num"><?= angka($row['modal_lab']) ?></td>
              <td class="text-right font-num"><?= angka($row['modal_radiologi']) ?></td>
              <td class="text-right font-num"><?= angka($row['modal_darah']) ?></td>
              <td class="text-right font-num"><?= angka($row['modal_oksigen']) ?></td>
              <td class="text-right font-num"><?= angka($row['modal_diet']) ?></td>
              <td class="text-right font-num"><?= angka($row['modal_kamar']) ?></td>
              <td class="text-right font-num"><?= angka($row['modal_operasi']) ?></td>
              <td class="text-right font-num"><?= angka($row['modal_biaya_bayi']) ?></td>
              <td class="text-right font-num"><?= angka($row['modal_adm']) ?></td>

              <td class="text-right font-num font-semibold text-slate-900">
                <?= angka($row['tarif_rs']) ?>
              </td>
              <td class="text-right font-num font-semibold text-slate-900">
                <?php if ((float) $row['tarif_inacbg'] > 0): ?>
                  <?= angka($row['tarif_inacbg']) ?>
                <?php else: ?>
                  <span class="text-slate-400 font-normal">—</span>
                <?php endif; ?>
              </td>
              <td class="text-right font-num <?= (float) $row['selisih'] < 0 ? 'text-red-700 font-semibold' : 'text-slate-800' ?>">
                <?php if ((float) $row['tarif_inacbg'] > 0): ?>
                  <?= angka($row['selisih']) ?>
                <?php else: ?>
                  <span class="text-slate-400">—</span>
                <?php endif; ?>
              </td>
              <td class="text-right font-num font-semibold">
                <?php if ($row['rasio'] !== null): ?>
                  <?php
                  $rVal = (float) $row['rasio'];
                  $rColor = $rVal > 1.0
                      ? 'text-red-700'
                      : ($rVal >= 0.86 ? 'text-amber-700' : 'text-slate-800');
                  ?>
                  <span class="<?= $rColor ?>"><?= number_format($rVal * 100, 1, ',', '.') ?>%</span>
                <?php else: ?>
                  <span class="text-slate-400">—</span>
                <?php endif; ?>
              </td>

              <td class="text-center">
                <?php if ($ket === 'Tidak Terkendali'): ?>
                  <span class="text-xs font-semibold text-red-700">Tidak Terkendali</span>
                <?php elseif ($ket === 'Kurang Terkendali'): ?>
                  <span class="text-xs font-semibold text-amber-700">Kurang Terkendali</span>
                <?php elseif ($ket === 'Cukup Terkendali'): ?>
                  <span class="text-xs font-medium text-slate-700">Cukup Terkendali</span>
                <?php elseif ($ket === 'Sangat Terkendali'): ?>
                  <span class="text-xs font-medium text-emerald-700">Sangat Terkendali</span>
                <?php else: ?>
                  <span class="text-xs text-slate-500">Belum Ada INA-CBG</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="21" class="py-12 text-center text-slate-500">
              Tidak ada data pasien rawat inap aktif yang sesuai dengan kriteria filter.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>

      <?php if (!empty($patients)): ?>
        <tfoot class="bg-slate-100 border-t-2 border-slate-300 font-semibold text-slate-900">
          <tr>
            <td colspan="2" class="py-3 px-3.5 text-left sticky left-0 bg-slate-100 z-10 border-r border-slate-300">
              Total Akumulasi (<?= angka(count($patients)) ?> Pasien)
            </td>
            <td colspan="3" class="py-3 px-3.5 bg-slate-100"></td>
            <td class="py-3 px-3.5 text-right font-num"><?= angka($totalsData['jasa_medis'] ?? 0) ?></td>
            <td class="py-3 px-3.5 text-right font-num"><?= angka($totalsData['obat'] ?? 0) ?></td>
            <td class="py-3 px-3.5 text-right font-num"><?= angka($totalsData['lab'] ?? 0) ?></td>
            <td class="py-3 px-3.5 text-right font-num"><?= angka($totalsData['radiologi'] ?? 0) ?></td>
            <td class="py-3 px-3.5 text-right font-num"><?= angka($totalsData['darah'] ?? 0) ?></td>
            <td class="py-3 px-3.5 text-right font-num"><?= angka($totalsData['oksigen'] ?? 0) ?></td>
            <td class="py-3 px-3.5 text-right font-num"><?= angka($totalsData['diet'] ?? 0) ?></td>
            <td class="py-3 px-3.5 text-right font-num"><?= angka($totalsData['kamar'] ?? 0) ?></td>
            <td class="py-3 px-3.5 text-right font-num"><?= angka($totalsData['operasi'] ?? 0) ?></td>
            <td class="py-3 px-3.5 text-right font-num"><?= angka($totalsData['biaya_bayi'] ?? 0) ?></td>
            <td class="py-3 px-3.5 text-right font-num"><?= angka($totalsData['adm'] ?? 0) ?></td>
            <td class="py-3 px-3.5 text-right font-num text-slate-900">
              <?= angka($totalsData['tarif_rs'] ?? 0) ?>
            </td>
            <td class="py-3 px-3.5 text-right font-num text-slate-900">
              <?= angka($totalsData['tarif_inacbg'] ?? 0) ?>
            </td>
            <td class="py-3 px-3.5 text-right font-num <?= (float) ($totalsData['selisih'] ?? 0) < 0 ? 'text-red-700' : 'text-slate-900' ?>">
              <?= angka($totalsData['selisih'] ?? 0) ?>
            </td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>

</section>

<script>
window.addEventListener('DOMContentLoaded', function () {
  const el = document.getElementById('flash-refresh');
  if (el) {
    if (window.history && window.history.replaceState) {
      const url = new URL(window.location.href);
      url.searchParams.delete('refreshed');
      window.history.replaceState({}, document.title, url.toString());
    }
    setTimeout(function () {
      el.style.opacity = '0';
      setTimeout(function () {
        el.remove();
      }, 300);
    }, 3000);
  }
});
</script>
