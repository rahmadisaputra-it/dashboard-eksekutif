<?php
declare(strict_types=1);

$appName        = (string) ($settings['nama_aplikasi'] ?? 'Dashboard Eksekutif');
$rsName         = (string) ($settings['nama_instansi'] ?? 'RS Mitra SIMRS Khanza');
$alamat         = (string) ($settings['alamat_instansi'] ?? '');
$kabupaten      = (string) ($settings['kabupaten'] ?? '');
$propinsi       = (string) ($settings['propinsi'] ?? '');
$kontak         = (string) ($settings['kontak'] ?? '');
$email          = (string) ($settings['email'] ?? '');
$kodePpk        = (string) ($settings['kode_ppk'] ?? '');
$namaDirektur   = (string) ($settings['nama_direktur'] ?? '( ................................................. )');
$logoInstansi   = (string) ($settings['logo_instansi'] ?? 'assets/images/logo-default.svg');
$logoAkreditasi = (string) ($settings['logo_akreditasi'] ?? '');
?>

<?php if (!empty($flashSuccess)): ?>
  <div class="p-3.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center justify-between gap-3">
    <div class="flex items-center gap-2.5">
      <span class="w-2 h-2 rounded-full bg-emerald-600 shrink-0"></span>
      <span class="font-medium"><?= e($flashSuccess) ?></span>
    </div>
    <a href="index.php?module=pengguna&amp;page=pengaturan-rs" class="text-emerald-700 hover:underline font-medium">Tutup</a>
  </div>
<?php endif; ?>

<?php if (!empty($flashError)): ?>
  <div class="p-3.5 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700 flex items-center justify-between gap-3">
    <div class="flex items-center gap-2.5">
      <span class="w-2 h-2 rounded-full bg-red-600 shrink-0"></span>
      <span class="font-medium"><?= e($flashError) ?></span>
    </div>
    <a href="index.php?module=pengguna&amp;page=pengaturan-rs" class="text-red-700 hover:underline font-medium">Tutup</a>
  </div>
<?php endif; ?>

<!-- 1. PRATINJAU (LIVE PREVIEW) KOP SURAT CETAK LAPORAN & IDENTITAS APLIKASI -->
<section class="permata-card p-5">
  <div class="flex flex-wrap items-center justify-between gap-3 pb-4 mb-4 border-b border-slate-100">
    <div>
      <h3 class="text-sm font-semibold text-slate-900">Pratinjau Kop Surat Cetak Laporan &amp; Identitas Aplikasi</h3>
      <p class="text-xs text-slate-500 mt-0.5">
        Tampilan di bawah ini adalah pratinjau kop surat resmi saat mencetak laporan serta identitas pada halaman Login &amp; Sidebar:
        <strong class="text-slate-700"><?= e($appName) ?> — <?= e($rsName) ?></strong>
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <!-- Form Tarik Otomatis dari Tabel setting SIMRS Khanza -->
      <form method="POST" action="index.php?module=pengguna&amp;page=pengaturan-rs">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">
        <input type="hidden" name="action" value="sync_khanza">
        <button
          type="submit"
          class="btn-secondary px-3.5 py-2 min-h-[36px] text-xs font-medium inline-flex items-center gap-2 cursor-pointer"
          title="Ambil nama, alamat, kontak, email, dan kode PPK langsung dari tabel setting SIMRS Khanza jika terhubung"
        >
          <svg class="w-3.5 h-3.5 text-blue-700" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
          </svg>
          <span>Tarik dari Tabel Setting Khanza</span>
        </button>
      </form>
    </div>
  </div>

  <!-- Kotak Simulasi Kop Surat Cetak -->
  <div class="bg-slate-50/70 border border-slate-200 rounded-xl p-5">
    <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-2xs">
      <div class="flex items-center justify-between pb-3" style="border-bottom: 3px double #0f172a;">
        <div class="w-16 flex items-center justify-start">
          <img
            src="<?= e($logoInstansi) ?>"
            alt="Logo Instansi"
            class="w-14 h-14 object-contain rounded-lg"
            style="width: 56px; height: 56px; object-fit: contain;"
          >
        </div>

        <div class="flex-1 text-center px-4">
          <div class="text-base font-bold uppercase tracking-wide text-slate-900">
            <?= e($rsName) ?>
          </div>
          <div class="text-xs text-slate-600 mt-0.5">
            <?= e($alamat) ?>, <?= e($kabupaten) ?> — <?= e($propinsi) ?>
          </div>
          <div class="text-[11px] text-slate-500 mt-0.5 font-num">
            Telp: <?= e($kontak) ?> · Email: <?= e($email) ?> · Kode PPK: <?= e($kodePpk) ?>
          </div>
        </div>

        <div class="w-16 flex items-center justify-end">
          <?php if ($logoAkreditasi !== ''): ?>
            <img
              src="<?= e($logoAkreditasi) ?>"
              alt="Logo Akreditasi"
              class="w-14 h-14 object-contain"
              style="width: 56px; height: 56px; object-fit: contain;"
            >
          <?php else: ?>
            <div class="w-12 h-12 rounded-lg border border-dashed border-slate-200 flex items-center justify-center text-[10px] text-slate-400 text-center leading-tight">
              Logo<br>Kanan
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500">
        <div>
          Badge Aplikasi: <strong class="text-slate-800"><?= e($appName) ?> — <?= e($rsName) ?></strong>
        </div>
        <div>
          Penandatangan Direktur: <strong class="text-slate-800"><?= e($namaDirektur) ?></strong>
          <span class="mx-1.5">·</span>
          Kota Cetak: <strong class="text-slate-800"><?= e($kabupaten) ?></strong>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. FORMULIR PENGATURAN PROFIL RUMAH SAKIT, ALAMAT CETAK & UPLOAD LOGO -->
<section class="permata-card p-6">
  <div class="pb-4 mb-5 border-b border-slate-100">
    <h3 class="text-sm font-semibold text-slate-900">Formulir Identitas Rumah Sakit, Alamat Kop Cetak &amp; Logo</h3>
    <p class="text-xs text-slate-500 mt-0.5">
      Sesuaikan profil rumah sakit Anda di bawah ini. Seluruh halaman aplikasi dan cetakan laporan akan otomatis mengikuti pengaturan ini.
    </p>
  </div>

  <form method="POST" action="index.php?module=pengguna&amp;page=pengaturan-rs" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">
    <input type="hidden" name="action" value="save_settings">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1.5">
          Judul Aplikasi <span class="text-red-500">*</span>
        </label>
        <input
          type="text"
          name="nama_aplikasi"
          value="<?= e($appName) ?>"
          required
          placeholder="Dashboard Eksekutif"
          class="permata-input w-full px-3.5 py-2.5 min-h-[40px] text-xs"
        >
        <p class="text-[11px] text-slate-400 mt-1">Default: <strong>Dashboard Eksekutif</strong></p>
      </div>

      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1.5">
          Nama Rumah Sakit / Fasyankes <span class="text-red-500">*</span>
        </label>
        <input
          type="text"
          name="nama_instansi"
          value="<?= e($rsName) ?>"
          required
          placeholder="Contoh: RS Umum Harapan Bunda"
          class="permata-input w-full px-3.5 py-2.5 min-h-[40px] text-xs"
        >
        <p class="text-[11px] text-slate-400 mt-1">Akan tampil di Login, Sidebar, Footer, dan Kop Cetak Laporan.</p>
      </div>
    </div>

    <div>
      <label class="block text-xs font-medium text-slate-700 mb-1.5">
        Alamat Lengkap Rumah Sakit (Untuk Kop Cetak Laporan) <span class="text-red-500">*</span>
      </label>
      <input
        type="text"
        name="alamat_instansi"
        value="<?= e($alamat) ?>"
        required
        placeholder="Contoh: Jl. Jend. Sudirman No. 123, Kel. Sukamaju, Kec. Kota Baru"
        class="permata-input w-full px-3.5 py-2.5 min-h-[40px] text-xs"
      >
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1.5">
          Kabupaten / Kota (Untuk Kop &amp; Tanggal Tanda Tangan)
        </label>
        <input
          type="text"
          name="kabupaten"
          value="<?= e($kabupaten) ?>"
          placeholder="Contoh: Jakarta Pusat"
          class="permata-input w-full px-3.5 py-2.5 min-h-[40px] text-xs"
        >
      </div>

      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1.5">
          Provinsi
        </label>
        <input
          type="text"
          name="propinsi"
          value="<?= e($propinsi) ?>"
          placeholder="Contoh: DKI Jakarta"
          class="permata-input w-full px-3.5 py-2.5 min-h-[40px] text-xs"
        >
      </div>

      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1.5">
          Kode PPK BPJS
        </label>
        <input
          type="text"
          name="kode_ppk"
          value="<?= e($kodePpk) ?>"
          placeholder="Contoh: 0001R001"
          class="permata-input w-full px-3.5 py-2.5 min-h-[40px] text-xs font-num"
        >
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1.5">
          Nomor Telepon / Kontak Resmi
        </label>
        <input
          type="text"
          name="kontak"
          value="<?= e($kontak) ?>"
          placeholder="Contoh: (021) 555-0199"
          class="permata-input w-full px-3.5 py-2.5 min-h-[40px] text-xs font-num"
        >
      </div>

      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1.5">
          Email Resmi Rumah Sakit
        </label>
        <input
          type="text"
          name="email"
          value="<?= e($email) ?>"
          placeholder="Contoh: info@rumahsakit.id"
          class="permata-input w-full px-3.5 py-2.5 min-h-[40px] text-xs font-num"
        >
      </div>

      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1.5">
          Nama Direktur / Pimpinan RS (Tanda Tangan Cetak)
        </label>
        <input
          type="text"
          name="nama_direktur"
          value="<?= e($namaDirektur) ?>"
          placeholder="Contoh: dr. Ahmad Sulaiman, MARS"
          class="permata-input w-full px-3.5 py-2.5 min-h-[40px] text-xs"
        >
      </div>
    </div>

    <!-- Bagian Upload Logo -->
    <div class="pt-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-5">
      <!-- Upload Logo Utama RS -->
      <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 flex items-start gap-4">
        <img
          src="<?= e($logoInstansi) ?>"
          alt="Logo Saat Ini"
          class="w-14 h-14 object-contain rounded-lg bg-white border border-slate-200 p-1.5 shrink-0"
          style="width: 56px; height: 56px; object-fit: contain;"
        >
        <div class="flex-1 min-w-0">
          <label class="block text-xs font-semibold text-slate-800 mb-1">
            Logo Utama Rumah Sakit
          </label>
          <p class="text-[11px] text-slate-500 mb-2.5">
            Tampil di halaman Login, pojok kiri atas Sidebar, dan sebelah kiri Kop Cetak. Format: PNG, JPG, SVG, WEBP (Maks. 2 MB).
          </p>
          <input
            type="file"
            name="logo_instansi"
            accept=".png,.jpg,.jpeg,.svg,.webp"
            class="block w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-slate-300 file:text-xs file:font-medium file:bg-white file:text-slate-700 hover:file:bg-slate-50 cursor-pointer"
          >
        </div>
      </div>

      <!-- Upload Logo Akreditasi / Kanan Kop Cetak -->
      <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 flex items-start gap-4">
        <?php if ($logoAkreditasi !== ''): ?>
          <img
            src="<?= e($logoAkreditasi) ?>"
            alt="Logo Akreditasi Saat Ini"
            class="w-14 h-14 object-contain rounded-lg bg-white border border-slate-200 p-1.5 shrink-0"
            style="width: 56px; height: 56px; object-fit: contain;"
          >
        <?php else: ?>
          <div class="w-14 h-14 rounded-lg bg-white border border-dashed border-slate-300 flex items-center justify-center text-[10px] text-slate-400 text-center shrink-0">
            Opsional
          </div>
        <?php endif; ?>
        <div class="flex-1 min-w-0">
          <label class="block text-xs font-semibold text-slate-800 mb-1">
            Logo Akreditasi / Logo Kanan Kop Cetak (Opsional)
          </label>
          <p class="text-[11px] text-slate-500 mb-2.5">
            Tampil di sebelah kanan Kop Surat saat mencetak laporan (misal logo KARS / LARSI / Pemda).
          </p>
          <input
            type="file"
            name="logo_akreditasi"
            accept=".png,.jpg,.jpeg,.svg,.webp"
            class="block w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-slate-300 file:text-xs file:font-medium file:bg-white file:text-slate-700 hover:file:bg-slate-50 cursor-pointer"
          >
          <?php if ($logoAkreditasi !== ''): ?>
            <label class="inline-flex items-center gap-2 mt-2 text-xs text-red-600 cursor-pointer">
              <input type="checkbox" name="hapus_logo_akreditasi" value="1" class="rounded border-slate-300">
              <span>Hapus logo kanan saat disimpan</span>
            </label>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
      <div class="text-xs text-slate-500">
        Terakhir diperbarui: <span class="font-num font-medium text-slate-700"><?= e((string) ($settings['updated_at'] ?? '-')) ?></span>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          type="submit"
          class="btn-primary px-5 py-2.5 min-h-[40px] text-xs font-medium inline-flex items-center gap-2 cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
          </svg>
          <span>Simpan Pengaturan Rumah Sakit</span>
        </button>
      </div>
    </div>
  </form>

  <?php if ($logoInstansi !== 'assets/images/logo-default.svg'): ?>
    <div class="mt-4 pt-3 border-t border-slate-100 flex justify-end">
      <form method="POST" action="index.php?module=pengguna&amp;page=pengaturan-rs">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">
        <input type="hidden" name="action" value="reset_logo">
        <button
          type="submit"
          class="text-xs text-slate-500 hover:text-red-600 underline cursor-pointer"
        >
          Kembalikan Logo Utama ke Ikon Default Dashboard Eksekutif
        </button>
      </form>
    </div>
  <?php endif; ?>
</section>
