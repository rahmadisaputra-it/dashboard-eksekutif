<?php
declare(strict_types=1);

$hariOptions = ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'MINGGU'];
$statusOptions = [
    'KONFLIK'           => 'Semua yang Bermasalah (Tidak Sinkron)',
    'SINKRON'           => 'Sinkron (Sesuai)',
    'BEDA_JAM'          => 'Beda Jam Praktek',
    'BEDA_KUOTA'        => 'Beda Kuota Antrean',
    'BEDA_JAM_DAN_KUOTA'=> 'Beda Jam & Kuota',
    'BELUM_MAPPING'     => 'Belum Mapping Kode BPJS',
    'TIDAK_ADA_DI_HFIS' => 'Belum Terdaftar di HFIS',
];

$lastSync = $kpi['last_sync'] ?? null;
$waktuSyncText = !empty($lastSync['waktu_sync'])
    ? tanggal_indo(substr((string) $lastSync['waktu_sync'], 0, 10), false) . ' · ' . substr((string) $lastSync['waktu_sync'], 11, 5) . ' WIB'
    : 'Belum ada kiriman API';
?>

<!-- KARTU RINGKASAN EKSEKUTIF (KPI) -->
<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 no-print print:hidden">
  <!-- KPI 1: Total Jadwal -->
  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Total Jadwal Dokter</span>
      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
      </svg>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num"><?= (int) ($kpi['total_jadwal'] ?? 0) ?></span>
      <span class="text-xs text-slate-500">Slot Praktek Poliklinik</span>
    </div>
  </div>

  <!-- KPI 2: Jadwal Sinkron -->
  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Jadwal Sinkron (Sesuai)</span>
      <span class="w-2 h-2 rounded-full bg-emerald-600 inline-block"></span>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num"><?= (int) ($kpi['total_sinkron'] ?? 0) ?></span>
      <span class="text-xs text-slate-500 font-num"><?= number_format((float) ($kpi['persentase_kepatuhan'] ?? 0), 1, ',', '.') ?>% Kepatuhan</span>
    </div>
  </div>

  <!-- KPI 3: Konflik / Tidak Sinkron -->
  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Selisih / Belum Sinkron</span>
      <span class="w-2 h-2 rounded-full bg-red-600 inline-block"></span>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num"><?= (int) ($kpi['total_konflik'] ?? 0) ?></span>
      <span class="text-xs text-slate-500 font-num">
        Jam: <?= (int) ($kpi['total_beda_jam'] ?? 0) ?> · Kuota: <?= (int) ($kpi['total_beda_kuota'] ?? 0) ?> · HFIS: <?= (int) ($kpi['total_tidak_ada_hfis'] ?? 0) ?>
      </span>
    </div>
  </div>

  <!-- KPI 4: Status Sinkronisasi Terakhir -->
  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Sinkronisasi Terakhir</span>
      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
    </div>
    <div class="mt-3">
      <div class="text-xs font-semibold text-slate-900 font-num truncate"><?= e($waktuSyncText) ?></div>
    </div>
  </div>
</section>

<!-- BAR FILTER & PENCARIAN -->
<section class="permata-card p-4 no-print print:hidden">
  <form method="GET" action="index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
    <input type="hidden" name="module" value="operasional">
    <input type="hidden" name="page" value="jadwal-hfis">

    <!-- Filter Hari -->
    <div class="lg:col-span-2">
      <label class="block text-[11px] font-medium text-slate-600 mb-1">Hari Praktek</label>
      <select name="hari" class="permata-input w-full px-3 py-2 text-xs">
        <option value="">Semua Hari</option>
        <?php foreach ($hariOptions as $h): ?>
          <option value="<?= e($h) ?>" <?= strtoupper((string) ($filters['hari'] ?? '')) === $h ? 'selected' : '' ?>>
            <?= e($h) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Filter Poliklinik -->
    <div class="lg:col-span-3">
      <label class="block text-[11px] font-medium text-slate-600 mb-1">Poliklinik</label>
      <select name="kd_poli" class="permata-input w-full px-3 py-2 text-xs">
        <option value="">Semua Poliklinik</option>
        <?php foreach ($poliOptions as $kd => $nm): ?>
          <option value="<?= e((string) $kd) ?>" <?= strtoupper((string) ($filters['kd_poli'] ?? '')) === strtoupper((string) $kd) ? 'selected' : '' ?>>
            <?= e((string) $nm) ?> (<?= e((string) $kd) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Filter Status Sinkron -->
    <div class="lg:col-span-3">
      <label class="block text-[11px] font-medium text-slate-600 mb-1">Status Sinkronisasi</label>
      <select name="status" class="permata-input w-full px-3 py-2 text-xs">
        <option value="">Semua Status</option>
        <?php foreach ($statusOptions as $stKey => $stLabel): ?>
          <option value="<?= e($stKey) ?>" <?= strtoupper((string) ($filters['status'] ?? '')) === $stKey ? 'selected' : '' ?>>
            <?= e($stLabel) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Pencarian Dokter / Poli -->
    <div class="lg:col-span-2">
      <label class="block text-[11px] font-medium text-slate-600 mb-1">Cari Dokter / Kode</label>
      <input
        type="text"
        name="q"
        value="<?= e((string) ($filters['q'] ?? '')) ?>"
        placeholder="Nama dokter / kode..."
        class="permata-input w-full px-3 py-2 text-xs"
      >
    </div>

    <!-- Tombol Aksi -->
    <div class="lg:col-span-2 flex items-center gap-2">
      <button type="submit" class="btn-primary px-3.5 py-2 text-xs font-medium flex-1 cursor-pointer">
        Terapkan
      </button>
      <a
        href="index.php?module=operasional&amp;page=jadwal-hfis"
        class="btn-secondary px-3 py-2 text-xs font-medium text-center"
      >
        Reset
      </a>
    </div>
  </form>
</section>

<!-- TABEL KOMPARASI JADWAL DOKTER HFIS VS SIMRS -->
<section class="permata-card overflow-hidden print-block">
  <div class="px-4 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2 no-print print:hidden">
    <div>
      <h3 class="text-sm font-semibold text-slate-900">Tabel Komparasi Jadwal Dokter (HFIS BPJS vs SIMRS Khanza)</h3>
      <p class="text-[11px] text-slate-500">
        Menampilkan <span class="font-num font-semibold text-slate-700"><?= count($jadwalList) ?></span> baris jadwal poliklinik
      </p>
    </div>
    <div class="flex items-center gap-2">
      <?php if (($filters['status'] ?? '') !== 'KONFLIK'): ?>
        <a
          href="index.php?module=operasional&amp;page=jadwal-hfis&amp;status=KONFLIK"
          class="btn-secondary px-3 py-1.5 text-xs font-medium"
        >
          Filter Hanya yang Bermasalah
        </a>
      <?php else: ?>
        <a
          href="index.php?module=operasional&amp;page=jadwal-hfis"
          class="btn-secondary px-3 py-1.5 text-xs font-medium"
        >
          Tampilkan Semua Jadwal
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="overflow-x-auto print-table-wrapper">
    <table class="permata-table">
      <thead>
        <tr>
          <th class="text-left w-10">No</th>
          <th class="text-left">Hari</th>
          <th class="text-left">Poliklinik (RS / BPJS)</th>
          <th class="text-left">Nama Dokter Spesialis</th>
          <th class="text-center">Jadwal SIMRS (RS)</th>
          <th class="text-center">Jadwal HFIS (BPJS)</th>
          <th class="text-center">Kuota RS / HFIS</th>
          <th class="text-left">Status Sinkron</th>
          <th class="text-left">Keterangan Evaluasi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($jadwalList)): ?>
          <tr>
            <td colspan="9" class="text-center py-8 text-xs text-slate-400">
              Tidak ada data jadwal dokter yang sesuai dengan filter pencarian.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($jadwalList as $idx => $row): ?>
            <?php
              $status = strtoupper((string) ($row['status_sinkron'] ?? ''));
              $badgeStyle = 'color: #15803d; font-weight: 600;';
              $statusLabel = 'Sinkron';

              if ($status === 'BEDA_JAM') {
                  $badgeStyle = 'color: #b91c1c; font-weight: 600;';
                  $statusLabel = 'Beda Jam Praktek';
              } elseif ($status === 'BEDA_KUOTA') {
                  $badgeStyle = 'color: #b45309; font-weight: 600;';
                  $statusLabel = 'Selisih Kuota';
              } elseif ($status === 'BEDA_JAM_DAN_KUOTA') {
                  $badgeStyle = 'color: #b91c1c; font-weight: 600;';
                  $statusLabel = 'Beda Jam & Kuota';
              } elseif ($status === 'BELUM_MAPPING') {
                  $badgeStyle = 'color: #475569; font-weight: 600;';
                  $statusLabel = 'Belum Mapping BPJS';
              } elseif ($status === 'TIDAK_ADA_DI_HFIS') {
                  $badgeStyle = 'color: #b91c1c; font-weight: 600;';
                  $statusLabel = 'Belum Ada di HFIS';
              }
            ?>
            <tr>
              <td class="font-num text-slate-500"><?= $idx + 1 ?></td>
              <td>
                <span class="font-medium text-slate-800 text-xs"><?= e((string) ($row['hari_kerja'] ?? '-')) ?></span>
              </td>
              <td>
                <div class="font-medium text-slate-900"><?= e((string) ($row['nm_poli_rs'] ?? '-')) ?></div>
                <div class="text-[11px] text-slate-500 font-num">
                  RS: <?= e((string) ($row['kd_poli_rs'] ?? '-')) ?> · BPJS: <?= e((string) ($row['kd_poli_bpjs'] ?? 'Belum Mapping')) ?>
                </div>
              </td>
              <td>
                <div class="font-medium text-slate-900"><?= e((string) ($row['nm_dokter_rs'] ?? '-')) ?></div>
                <div class="text-[11px] text-slate-500 font-num">
                  Kode RS: <?= e((string) ($row['kd_dokter_rs'] ?? '-')) ?> · HFIS: <?= e((string) ($row['kd_dokter_bpjs'] ?? 'Belum Mapping')) ?>
                </div>
              </td>
              <td class="text-center font-num font-medium text-slate-800">
                <?= e((string) ($row['jam_mulai_rs'] ?? '-')) ?> - <?= e((string) ($row['jam_selesai_rs'] ?? '-')) ?>
              </td>
              <td class="text-center font-num font-medium <?= ($row['jam_mulai_hfis'] ?? '-') === '-' ? 'text-red-600' : 'text-slate-800' ?>">
                <?php if (($row['jam_mulai_hfis'] ?? '-') === '-'): ?>
                  Tidak Terdaftar
                <?php else: ?>
                  <?= e((string) $row['jam_mulai_hfis']) ?> - <?= e((string) ($row['jam_selesai_hfis'] ?? '-')) ?>
                <?php endif; ?>
              </td>
              <td class="text-center font-num">
                <span class="font-medium text-slate-800"><?= (int) ($row['kuota_rs'] ?? 0) ?></span>
                <span class="text-slate-400 mx-1">/</span>
                <span class="font-medium <?= ((int) ($row['kuota_rs'] ?? 0) !== (int) ($row['kuota_hfis'] ?? 0)) ? 'text-amber-700' : 'text-slate-800' ?>">
                  <?= (int) ($row['kuota_hfis'] ?? 0) ?>
                </span>
              </td>
              <td>
                <span style="<?= $badgeStyle ?>"><?= e($statusLabel) ?></span>
              </td>
              <td class="text-xs text-slate-600">
                <?= e((string) ($row['catatan_selisih'] ?? '-')) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
