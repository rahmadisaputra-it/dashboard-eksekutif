<?php
declare(strict_types=1);
?>
<?php if (!empty($flashSuccess)): ?>
  <div id="flash-alert" class="permata-card p-3.5 border border-emerald-300 bg-emerald-50/80 text-xs text-emerald-800 flex items-center justify-between no-print print:hidden transition-all duration-300 ease-out">
    <div class="flex items-center gap-2">
      <svg class="w-4 h-4 text-emerald-700 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      <span class="font-medium"><?= e($flashSuccess) ?></span>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($flashError)): ?>
  <div id="flash-alert-error" class="permata-card p-3.5 border border-red-300 bg-red-50/80 text-xs text-red-800 flex items-center justify-between no-print print:hidden transition-all duration-300 ease-out">
    <div class="flex items-center gap-2">
      <svg class="w-4 h-4 text-red-700 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <span class="font-medium"><?= e($flashError) ?></span>
    </div>
  </div>
<?php endif; ?>

<!-- BARIS PENCARIAN & FORM TAMBAH/EDIT TARIF INA-CBG (.bevel-card) -->
<section class="grid grid-cols-1 lg:grid-cols-12 gap-5">

  <!-- Kolom Kiri (4 Kolom): Form Tambah / Perbarui Tarif INA-CBG -->
  <div class="bevel-card p-5 lg:col-span-4 h-fit no-print">
    <div class="pb-3 mb-4 border-b border-slate-200/90">
      <h3 id="form-tarif-title" class="text-sm font-semibold text-slate-900">Tambah / Perbarui Tarif INA-CBG</h3>
      <p class="text-xs text-slate-500 mt-0.5">Masukkan kode ICD-10 dan besaran paket klaim BPJS</p>
    </div>

    <form method="POST" action="index.php?module=keuangan&amp;page=tarif-inacbg" class="space-y-3.5">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">
      <input type="hidden" name="action" value="save">

      <div>
        <label for="input-kd-penyakit" class="block text-xs font-semibold text-slate-700 mb-1">
          Kode Penyakit ICD-10
        </label>
        <input
          type="text"
          id="input-kd-penyakit"
          name="kd_penyakit"
          maxlength="15"
          required
          placeholder="Contoh: A09, I10, J18.9"
          class="permata-input w-full px-3 py-2 min-h-[38px] text-xs font-num uppercase"
        >
      </div>

      <div>
        <label for="input-biaya" class="block text-xs font-semibold text-slate-700 mb-1">
          Besaran Tarif Paket (Rp)
        </label>
        <input
          type="number"
          id="input-biaya"
          name="biaya"
          min="0"
          step="1000"
          required
          placeholder="Contoh: 3850000"
          class="permata-input w-full px-3 py-2 min-h-[38px] text-xs font-num"
        >
      </div>

      <div class="pt-1 flex items-center gap-2">
        <button
          type="submit"
          class="btn-primary flex-1 min-h-[38px] px-4 py-2 text-xs font-medium flex items-center justify-center gap-1.5 cursor-pointer"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
            <polyline points="17 21 17 13 7 13 7 21"/>
            <polyline points="7 3 7 8 15 8"/>
          </svg>
          <span>Simpan Tarif</span>
        </button>

        <button
          type="button"
          onclick="resetTarifForm()"
          class="btn-secondary min-h-[38px] px-3 py-2 text-xs font-medium cursor-pointer"
        >
          Batal
        </button>
      </div>
    </form>
  </div>

  <!-- Kolom Kanan (8 Kolom): Tabel Daftar Tarif INA-CBG -->
  <div class="bevel-card overflow-hidden lg:col-span-8 flex flex-col">
    <div class="p-4 border-b border-slate-200/90 bg-slate-50/70 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="text-sm font-semibold text-slate-900">Daftar Referensi Tarif INA-CBG</h3>
        <p class="text-xs text-slate-500">Total <strong class="font-num text-slate-800"><?= angka(count($items)) ?></strong> kode penyakit terdaftar</p>
      </div>

      <form method="GET" action="index.php" class="flex items-center gap-2 no-print">
        <input type="hidden" name="module" value="keuangan">
        <input type="hidden" name="page" value="tarif-inacbg">
        <input
          type="text"
          name="search"
          value="<?= e($search ?? '') ?>"
          placeholder="Cari kode / nama penyakit..."
          class="permata-input px-3 py-1.5 min-h-[38px] text-xs"
        >
        <button type="submit" class="btn-secondary px-3 py-1.5 min-h-[38px] text-xs font-medium cursor-pointer">
          Cari
        </button>
        <?php if (!empty($search)): ?>
          <a href="index.php?module=keuangan&amp;page=tarif-inacbg" class="btn-secondary px-3 py-1.5 min-h-[38px] text-xs font-medium flex items-center">
            Reset
          </a>
        <?php endif; ?>
      </form>
    </div>

    <div class="overflow-x-auto max-h-[520px] overflow-y-auto">
      <table class="permata-table">
        <thead class="sticky top-0 z-10">
          <tr>
            <th class="text-center w-12">No</th>
            <th class="text-left w-32">Kode ICD-10</th>
            <th class="text-left">Nama Penyakit / Diagnosis</th>
            <th class="text-right w-44">Tarif Paket (Rp)</th>
            <th class="text-center w-28 no-print">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($items)): ?>
            <?php foreach ($items as $idx => $item): ?>
              <tr>
                <td class="text-center font-num text-slate-500"><?= $idx + 1 ?></td>
                <td class="font-num font-semibold text-blue-900"><?= e($item['kd_penyakit']) ?></td>
                <td class="font-medium text-slate-800"><?= e($item['nm_penyakit'] ?: '—') ?></td>
                <td class="text-right font-num font-semibold text-slate-900"><?= rupiah($item['biaya']) ?></td>
                <td class="text-center no-print">
                  <div class="inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      onclick="isiEditTarif('<?= e($item['kd_penyakit']) ?>', '<?= e((string) $item['biaya']) ?>')"
                      class="btn-bevel-light px-2.5 py-1 text-xs font-medium text-blue-800 cursor-pointer"
                    >
                      Edit
                    </button>
                    <form method="POST" action="index.php?module=keuangan&amp;page=tarif-inacbg" onsubmit="return confirm('Hapus tarif kode <?= e($item['kd_penyakit']) ?>?');" class="inline">
                      <input type="hidden" name="csrf_token" value="<?= e($csrfToken ?? '') ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="kd_penyakit" value="<?= e($item['kd_penyakit']) ?>">
                      <button
                        type="submit"
                        class="btn-bevel-light px-2.5 py-1 text-xs font-medium text-red-700 cursor-pointer"
                      >
                        Hapus
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="py-10 text-center text-slate-500">
                Belum ada data tarif INA-CBG yang ditemukan.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</section>

<script>
function isiEditTarif(kd, biaya) {
  const inputKd = document.getElementById('input-kd-penyakit');
  const inputBiaya = document.getElementById('input-biaya');
  const title = document.getElementById('form-tarif-title');
  if (inputKd && inputBiaya) {
    inputKd.value = kd;
    inputBiaya.value = biaya;
    if (title) title.textContent = 'Edit Tarif INA-CBG: ' + kd;
    inputBiaya.focus();
  }
}

function resetTarifForm() {
  const inputKd = document.getElementById('input-kd-penyakit');
  const inputBiaya = document.getElementById('input-biaya');
  const title = document.getElementById('form-tarif-title');
  if (inputKd && inputBiaya) {
    inputKd.value = '';
    inputBiaya.value = '';
    if (title) title.textContent = 'Tambah / Perbarui Tarif INA-CBG';
  }
}

window.addEventListener('DOMContentLoaded', function () {
  const alerts = [
    document.getElementById('flash-alert'),
    document.getElementById('flash-alert-error')
  ].filter(Boolean);

  if (alerts.length > 0) {
    if (window.history && window.history.replaceState) {
      const url = new URL(window.location.href);
      url.searchParams.delete('msg');
      url.searchParams.delete('err');
      window.history.replaceState({}, document.title, url.toString());
    }

    setTimeout(function () {
      alerts.forEach(function (el) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(-4px)';
        setTimeout(function () {
          el.remove();
        }, 300);
      });
    }, 3000);
  }
});
</script>
