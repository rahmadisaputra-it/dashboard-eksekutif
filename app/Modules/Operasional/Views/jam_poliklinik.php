<?php
declare(strict_types=1);

$statusOptions = [
    'TEPAT_WAKTU' => 'Tepat Waktu (Patuh)',
    'TERLAMBAT'   => 'Terlambat (>15 Menit)',
    'TANPA_SOAP'  => 'Ada Pasien — Tanpa Input SOAP',
    'KOSONG'      => 'Jadwal Kosong (0 Pasien)',
];

$tglAwal        = (string) ($filters['tgl_awal'] ?? date('Y-m-d', strtotime('-7 days')));
$tglAkhir       = (string) ($filters['tgl_akhir'] ?? date('Y-m-d', strtotime('-1 day')));
$availableDates = is_array($availableDates ?? null) ? $availableDates : [];
$rekapDokter    = is_array($rekapDokter ?? null) ? $rekapDokter : [];

$latestDate = !empty($availableDates[0]) ? (string) $availableDates[0] : $tglAkhir;
$slice7     = array_slice($availableDates, 0, 7);
$slice14    = array_slice($availableDates, 0, 14);
$date7Back  = !empty($slice7) ? (string) end($slice7) : date('Y-m-d', strtotime('-7 days', strtotime($latestDate)));
$date14Back = !empty($slice14) ? (string) end($slice14) : date('Y-m-d', strtotime('-14 days', strtotime($latestDate)));

$periodeLabel = ($tglAwal === $tglAkhir)
    ? tanggal_indo($tglAkhir, false)
    : tanggal_indo($tglAwal, true) . ' – ' . tanggal_indo($tglAkhir, true);
?>

<!-- 1. KARTU RINGKASAN EKSEKUTIF (EVALUASI MUNDUR JAM MULAI POLIKLINIK) -->
<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 no-print print:hidden">
  <!-- KPI 1: Total Sesi Pelayanan Aktif -->
  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Sesi Pelayanan Dievaluasi</span>
      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num"><?= (int) ($kpi['total_jadwal'] ?? 0) ?></span>
      <span class="text-xs text-slate-500 font-num">
        <?= angka((int) ($kpi['total_pasien_periksa'] ?? 0)) ?> / <?= angka((int) ($kpi['total_pasien_reg'] ?? 0)) ?> pasien diperiksa
      </span>
    </div>
  </div>

  <!-- KPI 2: Mulai Tepat Waktu -->
  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Mulai Tepat Waktu</span>
      <span class="w-2 h-2 rounded-full bg-emerald-600 inline-block"></span>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num"><?= (int) ($kpi['total_tepat_waktu'] ?? 0) ?></span>
      <span class="text-xs text-slate-500 font-num">
        <?= number_format((float) ($kpi['persentase_tepat_waktu'] ?? 0), 1, ',', '.') ?>% Kepatuhan
      </span>
    </div>
  </div>

  <!-- KPI 3: Mulai Terlambat -->
  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Mulai Terlambat (&gt;15 Menit)</span>
      <span class="w-2 h-2 rounded-full bg-red-600 inline-block"></span>
    </div>
    <div class="mt-3 flex items-baseline justify-between">
      <span class="text-2xl font-semibold text-slate-900 font-num"><?= (int) ($kpi['total_terlambat'] ?? 0) ?></span>
      <span class="text-xs text-slate-500 font-num">
        Rata-rata deviasi: +<?= (int) ($kpi['rata_selisih_menit'] ?? 0) ?> menit
      </span>
    </div>
  </div>

  <!-- KPI 4: Periode Evaluasi Mundur -->
  <div class="permata-card p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-slate-600">Periode Evaluasi (Mundur)</span>
      <span class="text-[11px] font-medium text-slate-500 font-num"><?= (int) ($kpi['jumlah_hari_aktif'] ?? 0) ?> Hari Layanan</span>
    </div>
    <div class="mt-3">
      <div class="text-xs font-semibold text-slate-900 font-num truncate"><?= e($periodeLabel) ?></div>
      <div class="text-xs text-slate-500 mt-0.5 truncate">
        Tanpa SOAP: <span class="font-num font-medium text-slate-700"><?= (int) ($kpi['total_belum_mulai'] ?? 0) ?> sesi</span>
      </div>
    </div>
  </div>
</section>

<!-- 2. BAR FILTER RENTANG TANGGAL EVALUASI MUNDUR -->
<section class="permata-card p-4 space-y-3 no-print print:hidden">
  <div class="flex flex-wrap items-center justify-between gap-2 pb-2.5 border-b border-slate-100 text-xs">
    <div class="text-slate-600">
      <span class="font-semibold text-slate-800">Mode Evaluasi Historis (Mundur):</span>
      Data ditarik dari pelayanan poliklinik yang telah selesai untuk mengevaluasi ketepatan waktu mulai pemeriksaan dokter.
    </div>
    <div class="flex items-center gap-1.5">
      <span class="text-slate-500 mr-1">Pintas Periode:</span>
      <a
        href="index.php?module=operasional&amp;page=jam-poliklinik&amp;tgl_awal=<?= urlencode($latestDate) ?>&amp;tgl_akhir=<?= urlencode($latestDate) ?>"
        class="btn-secondary px-2.5 py-1 text-xs font-medium <?= ($tglAwal === $latestDate && $tglAkhir === $latestDate) ? 'bg-slate-100 font-semibold text-slate-900' : '' ?>"
      >
        1 Hari Terakhir
      </a>
      <a
        href="index.php?module=operasional&amp;page=jam-poliklinik&amp;tgl_awal=<?= urlencode($date7Back) ?>&amp;tgl_akhir=<?= urlencode($latestDate) ?>"
        class="btn-secondary px-2.5 py-1 text-xs font-medium <?= ($tglAwal === $date7Back && $tglAkhir === $latestDate && $date7Back !== $latestDate) ? 'bg-slate-100 font-semibold text-slate-900' : '' ?>"
      >
        7 Hari Pelayanan
      </a>
      <a
        href="index.php?module=operasional&amp;page=jam-poliklinik&amp;tgl_awal=<?= urlencode($date14Back) ?>&amp;tgl_akhir=<?= urlencode($latestDate) ?>"
        class="btn-secondary px-2.5 py-1 text-xs font-medium <?= ($tglAwal === $date14Back && $tglAkhir === $latestDate && $date14Back !== $date7Back) ? 'bg-slate-100 font-semibold text-slate-900' : '' ?>"
      >
        14 Hari Pelayanan
      </a>
    </div>
  </div>

  <form method="GET" action="index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
    <input type="hidden" name="module" value="operasional">
    <input type="hidden" name="page" value="jam-poliklinik">

    <!-- Dari Tanggal -->
    <div class="lg:col-span-2">
      <label class="block text-[11px] font-medium text-slate-600 mb-1">Dari Tanggal</label>
      <input
        type="date"
        name="tgl_awal"
        value="<?= e($tglAwal) ?>"
        class="permata-input w-full px-2.5 py-2 text-xs font-num"
      >
    </div>

    <!-- Sampai Tanggal -->
    <div class="lg:col-span-2">
      <label class="block text-[11px] font-medium text-slate-600 mb-1">Sampai Tanggal</label>
      <input
        type="date"
        name="tgl_akhir"
        value="<?= e($tglAkhir) ?>"
        class="permata-input w-full px-2.5 py-2 text-xs font-num"
      >
    </div>

    <!-- Filter Poliklinik -->
    <div class="lg:col-span-2">
      <label class="block text-[11px] font-medium text-slate-600 mb-1">Poliklinik</label>
      <select name="kd_poli" class="permata-input w-full px-2.5 py-2 text-xs">
        <option value="">Semua Poliklinik</option>
        <?php foreach ($poliOptions as $kd => $nm): ?>
          <option value="<?= e((string) $kd) ?>" <?= strtoupper((string) ($filters['kd_poli'] ?? '')) === strtoupper((string) $kd) ? 'selected' : '' ?>>
            <?= e((string) $nm) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Filter Status Kepatuhan -->
    <div class="lg:col-span-2">
      <label class="block text-[11px] font-medium text-slate-600 mb-1">Hasil Evaluasi</label>
      <select name="status" class="permata-input w-full px-2.5 py-2 text-xs">
        <option value="">Semua Sesi Aktif</option>
        <?php foreach ($statusOptions as $stKey => $stLabel): ?>
          <option value="<?= e($stKey) ?>" <?= strtoupper((string) ($filters['status'] ?? '')) === $stKey ? 'selected' : '' ?>>
            <?= e($stLabel) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Pencarian Dokter / Pasien -->
    <div class="lg:col-span-2">
      <label class="block text-[11px] font-medium text-slate-600 mb-1">Cari Dokter / Pasien</label>
      <input
        type="text"
        name="q"
        value="<?= e((string) ($filters['q'] ?? '')) ?>"
        placeholder="Nama dokter / pasien..."
        class="permata-input w-full px-3 py-2 text-xs"
      >
    </div>

    <!-- Tombol Aksi -->
    <div class="lg:col-span-2 flex items-center gap-2">
      <button type="submit" class="btn-primary px-3.5 py-2 text-xs font-medium flex-1 cursor-pointer">
        Terapkan
      </button>
      <a
        href="index.php?module=operasional&amp;page=jam-poliklinik"
        class="btn-secondary px-3 py-2 text-xs font-medium text-center"
      >
        Reset
      </a>
    </div>
  </form>
</section>

<!-- 3. TABEL REKAPITULASI RAPOR KEPATUHAN PER DOKTER SPESIALIS -->
<?php if (!empty($rekapDokter)): ?>
<section class="permata-card overflow-hidden print-block">
  <div class="px-4 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
    <div>
      <h3 class="text-sm font-semibold text-slate-900">
        Rekapitulasi Rapor Kepatuhan Jam Mulai per Dokter (<?= e($periodeLabel) ?>)
      </h3>
      <p class="text-[11px] text-slate-500">
        Diurutkan berdasarkan frekuensi keterlambatan tertinggi untuk memudahkan evaluasi manajemen &amp; Komite Medik
      </p>
    </div>
    <span class="text-xs font-medium text-slate-600 font-num"><?= count($rekapDokter) ?> Dokter Aktif</span>
  </div>

  <div class="overflow-x-auto print-table-wrapper">
    <table class="permata-table">
      <thead>
        <tr>
          <th class="text-left w-10">No</th>
          <th class="text-left">Nama Dokter Spesialis</th>
          <th class="text-left">Poliklinik</th>
          <th class="text-center">Sesi Pelayanan</th>
          <th class="text-center">Tepat Waktu</th>
          <th class="text-center">Terlambat (&gt;15m)</th>
          <th class="text-center">Tanpa SOAP</th>
          <th class="text-center">Total Pasien</th>
          <th class="text-center">Rata-rata Deviasi</th>
          <th class="text-left w-48">Tingkat Kepatuhan</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rekapDokter as $idx => $rd): ?>
          <?php
            $patuh = (float) ($rd['persentase_patuh'] ?? 0);
            $rata  = (int) ($rd['rata_selisih_menit'] ?? 0);
          ?>
          <tr>
            <td class="font-num text-slate-500"><?= $idx + 1 ?></td>
            <td>
              <div class="font-medium text-slate-900"><?= e($rd['nm_dokter']) ?></div>
              <div class="text-[11px] text-slate-500 font-num">Kode: <?= e($rd['kd_dokter']) ?></div>
            </td>
            <td class="text-slate-700 font-medium"><?= e($rd['nm_poli']) ?></td>
            <td class="text-center font-num font-semibold text-slate-800"><?= (int) $rd['total_sesi'] ?> hari</td>
            <td class="text-center font-num text-slate-800"><?= (int) $rd['tepat_waktu'] ?></td>
            <td class="text-center font-num <?= (int) $rd['terlambat'] > 0 ? 'font-semibold text-red-700' : 'text-slate-600' ?>">
              <?= (int) $rd['terlambat'] ?>
            </td>
            <td class="text-center font-num <?= (int) $rd['tanpa_soap'] > 0 ? 'font-medium text-amber-700' : 'text-slate-500' ?>">
              <?= (int) $rd['tanpa_soap'] ?>
            </td>
            <td class="text-center font-num text-slate-700">
              <?= angka((int) $rd['total_pasien_periksa']) ?> / <?= angka((int) $rd['total_pasien_reg']) ?>
            </td>
            <td class="text-center font-num <?= $rata > 15 ? 'text-red-700 font-semibold' : 'text-slate-700' ?>">
              <?= ($rd['tepat_waktu'] + $rd['terlambat']) > 0 ? '+' . $rata . ' mnt' : '—' ?>
            </td>
            <td>
              <div class="flex items-center gap-2.5">
                <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden no-print print:hidden">
                  <div
                    class="h-full <?= $patuh >= 80 ? 'bg-emerald-600' : ($patuh >= 50 ? 'bg-amber-500' : 'bg-red-600') ?>"
                    style="width: <?= min(100, max(0, $patuh)) ?>%;"
                  ></div>
                </div>
                <span class="font-num text-xs font-semibold text-slate-800 w-12 text-right">
                  <?= number_format($patuh, 0, ',', '.') ?>%
                </span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- 4. TABEL RINCIAN HARIAN EVALUASI JAM MULAI POLIKLINIK -->
<section class="permata-card overflow-hidden print-block">
  <div class="px-4 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2 no-print print:hidden">
    <div>
      <h3 class="text-sm font-semibold text-slate-900">
        Rincian Harian Evaluasi Jam Mulai Pelayanan Poliklinik (<?= e($periodeLabel) ?>)
      </h3>
      <p class="text-[11px] text-slate-500">
        Menampilkan <span class="font-num font-semibold text-slate-700"><?= count($listData) ?></span> sesi pelayanan poliklinik berdasarkan waktu simpan pertama SOAP Rawat Jalan
      </p>
    </div>
    <div class="flex items-center gap-2">
      <?php if (($filters['status'] ?? '') !== 'TERLAMBAT'): ?>
        <a
          href="index.php?module=operasional&amp;page=jam-poliklinik&amp;tgl_awal=<?= urlencode($tglAwal) ?>&amp;tgl_akhir=<?= urlencode($tglAkhir) ?>&amp;status=TERLAMBAT"
          class="btn-secondary px-3 py-1.5 text-xs font-medium"
        >
          Filter Hanya yang Terlambat
        </a>
      <?php else: ?>
        <a
          href="index.php?module=operasional&amp;page=jam-poliklinik&amp;tgl_awal=<?= urlencode($tglAwal) ?>&amp;tgl_akhir=<?= urlencode($tglAkhir) ?>"
          class="btn-secondary px-3 py-1.5 text-xs font-medium"
        >
          Tampilkan Semua Sesi Aktif
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="overflow-x-auto print-table-wrapper">
    <table class="permata-table">
      <thead>
        <tr>
          <th class="text-left w-10">No</th>
          <th class="text-left">Tanggal &amp; Hari</th>
          <th class="text-left">Poliklinik</th>
          <th class="text-left">Nama Dokter Spesialis</th>
          <th class="text-center">Jadwal Buka</th>
          <th class="text-center">SOAP Pertama</th>
          <th class="text-left">Pasien Pertama Diperiksa</th>
          <th class="text-center">Selisih Waktu</th>
          <th class="text-center">Pasien (SOAP/Reg)</th>
          <th class="text-left">Status Evaluasi</th>
          <th class="text-left">Catatan Evaluasi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($listData)): ?>
          <tr>
            <td colspan="11" class="text-center py-8 text-xs text-slate-500">
              Tidak ditemukan sesi pelayanan poliklinik aktif pada rentang tanggal/filter ini.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($listData as $idx => $row): ?>
            <?php
              $status  = strtoupper((string) ($row['status_kepatuhan'] ?? 'BELUM_MULAI'));
              $selisih = $row['selisih_menit'];
              $regCnt  = (int) ($row['total_pasien_reg'] ?? 0);

              $statusStyle = 'color: #b45309; font-weight: 600;';
              $statusLabel = $regCnt > 0 ? 'Tanpa SOAP' : 'Tidak Ada Pasien';

              if ($status === 'TEPAT_WAKTU') {
                  $statusStyle = 'color: #15803d; font-weight: 600;';
                  $statusLabel = 'Tepat Waktu';
              } elseif ($status === 'TERLAMBAT') {
                  $statusStyle = 'color: #b91c1c; font-weight: 600;';
                  $statusLabel = 'Terlambat';
              }

              $selisihText  = '—';
              $selisihClass = 'text-slate-400';
              if ($selisih !== null && $selisih !== '') {
                  $val = (int) $selisih;
                  if ($val < 0) {
                      $selisihText  = $val . ' mnt';
                      $selisihClass = 'text-emerald-700 font-medium';
                  } elseif ($val === 0) {
                      $selisihText  = '0 mnt';
                      $selisihClass = 'text-emerald-700 font-medium';
                  } elseif ($val <= 15) {
                      $selisihText  = '+' . $val . ' mnt';
                      $selisihClass = 'text-emerald-700 font-medium';
                  } else {
                      $selisihText  = '+' . $val . ' mnt';
                      $selisihClass = 'text-red-700 font-semibold';
                  }
              }
            ?>
            <tr>
              <td class="font-num text-slate-500"><?= $idx + 1 ?></td>
              <td>
                <div class="font-num font-medium text-slate-900"><?= tanggal_indo((string) ($row['tgl_pelayanan'] ?? ''), true) ?></div>
                <div class="text-[11px] text-slate-500"><?= e((string) ($row['hari_kerja'] ?? '-')) ?></div>
              </td>
              <td>
                <div class="font-medium text-slate-900"><?= e((string) ($row['nm_poli'] ?? '-')) ?></div>
                <div class="text-[11px] text-slate-500 font-num">Kode: <?= e((string) ($row['kd_poli'] ?? '-')) ?></div>
              </td>
              <td>
                <div class="font-medium text-slate-900"><?= e((string) ($row['nm_dokter'] ?? '-')) ?></div>
                <div class="text-[11px] text-slate-500 font-num">Kode: <?= e((string) ($row['kd_dokter'] ?? '-')) ?></div>
              </td>
              <td class="text-center font-num font-medium text-slate-800">
                <?= e((string) ($row['jam_mulai_jadwal'] ?? '-')) ?> - <?= e((string) ($row['jam_selesai_jadwal'] ?? '-')) ?>
              </td>
              <td class="text-center font-num">
                <?php if (($row['jam_periksa_pertama'] ?? '-') === '-'): ?>
                  <span class="text-amber-700 font-medium">Tidak Diisi</span>
                <?php else: ?>
                  <div class="font-semibold text-slate-900"><?= e((string) $row['jam_periksa_pertama']) ?></div>
                  <?php if (!empty($row['jam_periksa_terakhir']) && $row['jam_periksa_terakhir'] !== '-'): ?>
                    <div class="text-[11px] text-slate-500">s/d <?= e((string) $row['jam_periksa_terakhir']) ?></div>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($row['no_rawat_pertama'])): ?>
                  <div class="font-medium text-slate-800"><?= e((string) ($row['nm_pasien_pertama'] ?? '-')) ?></div>
                  <div class="text-[11px] text-slate-500 font-num"><?= e((string) $row['no_rawat_pertama']) ?></div>
                <?php else: ?>
                  <span class="text-xs text-slate-400">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center font-num <?= $selisihClass ?>">
                <?= e($selisihText) ?>
              </td>
              <td class="text-center font-num">
                <span class="font-semibold text-slate-800"><?= (int) ($row['total_pasien_periksa'] ?? 0) ?></span>
                <span class="text-slate-400 mx-0.5">/</span>
                <span class="text-slate-600"><?= (int) ($row['total_pasien_reg'] ?? 0) ?></span>
              </td>
              <td>
                <span style="<?= $statusStyle ?>"><?= e($statusLabel) ?></span>
              </td>
              <td class="text-xs text-slate-600">
                <?= e((string) ($row['catatan_kepatuhan'] ?? '-')) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
