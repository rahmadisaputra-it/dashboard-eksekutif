<?php
declare(strict_types=1);

$logoInstansi   = (string) ($hospital['logo_instansi'] ?? 'assets/images/logo-default.svg');
$logoAkreditasi = (string) ($hospital['logo_akreditasi'] ?? '');
?>
<!-- KOP SURAT RESMI & HEADER LAPORAN CETAK -->
<div class="print-only" style="margin-bottom: 10px;">
  <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 3px double #0f172a; padding-bottom: 8px;">
    <div style="width: 64px; text-align: left;">
      <img src="<?= e($logoInstansi) ?>" alt="Logo <?= e($hospital['nama_instansi'] ?? 'Rumah Sakit') ?>" style="width: 56px; height: 56px; object-fit: contain;">
    </div>
    <div style="flex: 1; text-align: center; padding: 0 10px;">
      <div style="font-size: 14pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; color: #0f172a;">
        <?= e($hospital['nama_instansi'] ?? 'RUMAH SAKIT') ?>
      </div>
      <div style="font-size: 9pt; color: #334155; margin-top: 1px;">
        <?= e($hospital['alamat_instansi'] ?? '-') ?>,
        <?= e($hospital['kabupaten'] ?? '-') ?> — <?= e($hospital['propinsi'] ?? '-') ?>
      </div>
      <div style="font-size: 8.5pt; color: #475569; margin-top: 1px;" class="font-num">
        Telp: <?= e($hospital['kontak'] ?? '-') ?> · Email: <?= e($hospital['email'] ?? '-') ?> · Kode PPK: <?= e($hospital['kode_ppk'] ?? '-') ?>
      </div>
    </div>
    <div style="width: 64px; text-align: right;">
      <?php if ($logoAkreditasi !== ''): ?>
        <img src="<?= e($logoAkreditasi) ?>" alt="Logo Akreditasi" style="width: 56px; height: 56px; object-fit: contain;">
      <?php endif; ?>
    </div>
  </div>

  <!-- Header Judul Laporan Resmi Saat Cetak -->
  <div style="margin-top: 6px; margin-bottom: 6px; text-align: center; color: #0f172a;">
    <div style="font-size: 11pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em;">
      <?= e($pageTitle ?? 'Laporan Dashboard Eksekutif') ?>
    </div>
    <div style="font-size: 8.5pt; color: #334155; margin-top: 2px; display: flex; justify-content: space-between; align-items: center;">
      <div>
        <strong>Subjek:</strong> <?= e($printSubject ?? $pageTitle ?? 'Laporan Dashboard Eksekutif') ?>
        <?php if (!empty($bangsal)): ?>
          · <strong>Bangsal:</strong> <?= e((string) $bangsal) ?>
        <?php endif; ?>
        <?php if (!empty($status)): ?>
          · <strong>Status:</strong> <?= e((string) $status) ?>
        <?php endif; ?>
        <?php if (isset($patients) && is_array($patients)): ?>
          · <strong>Total:</strong> <span class="font-num"><?= angka(count($patients)) ?></span> Pasien
        <?php endif; ?>
      </div>
      <div class="font-num">
        <strong>Dicetak:</strong> <?= tanggal_indo(date('Y-m-d'), false) ?>, <?= date('H:i') ?> WIB
      </div>
    </div>
  </div>
</div>
