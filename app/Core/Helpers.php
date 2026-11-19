<?php
declare(strict_types=1);

/**
 * Sanitasi output HTML (Anti-XSS)
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format angka bulat dengan pemisah ribuan Indonesia
 */
function angka(int|float|string|null $num, int $decimals = 0): string
{
    $val = is_numeric($num) ? (float) $num : 0.0;
    return number_format($val, $decimals, ',', '.');
}

/**
 * Format nominal Rupiah lengkap
 */
function rupiah(int|float|string|null $nominal): string
{
    $val = is_numeric($nominal) ? (float) $nominal : 0.0;
    return 'Rp ' . number_format($val, 0, ',', '.');
}

/**
 * Format nominal Rupiah ringkas untuk kartu KPI Eksekutif (misal Rp 1,48 M / Rp 420,5 Jt)
 */
function rupiah_singkat(int|float|string|null $nominal): string
{
    $val = is_numeric($nominal) ? (float) $nominal : 0.0;
    if ($val >= 1_000_000_000) {
        return 'Rp ' . number_format($val / 1_000_000_000, 2, ',', '.') . ' M';
    }
    if ($val >= 1_000_000) {
        return 'Rp ' . number_format($val / 1_000_000, 1, ',', '.') . ' Jt';
    }
    return 'Rp ' . number_format($val, 0, ',', '.');
}

/**
 * Format tanggal Y-m-d ke bahasa Indonesia (contoh: 26 Okt 2026)
 */
function tanggal_indo(?string $dateStr, bool $shortMonth = true): string
{
    if (empty($dateStr)) {
        return '-';
    }
    $ts = strtotime($dateStr);
    if ($ts === false) {
        return e($dateStr);
    }
    $bulanPendek = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    $bulanPanjang = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $d = date('d', $ts);
    $m = (int) date('n', $ts);
    $y = date('Y', $ts);
    $namaBulan = $shortMonth ? ($bulanPendek[$m] ?? '') : ($bulanPanjang[$m] ?? '');
    return "{$d} {$namaBulan} {$y}";
}
