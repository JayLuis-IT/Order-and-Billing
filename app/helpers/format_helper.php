<?php
/**
 * Global quantity formatting.
 *
 * Prints whole numbers without trailing decimals (10000.0000 -> 10,000)
 * while keeping real fractions (2.5050 -> 2.505), up to $decimals places.
 *
 * Loaded once from index.php so every view can call formatQty() directly.
 */
if (!function_exists('formatQty')) {
    function formatQty($val, $decimals = 4)
    {
        $num = floatval($val);

        // Whole number (incl. negatives) -> no decimal point at all.
        if ($num == floor($num)) {
            return number_format($num, 0);
        }

        // Fractional -> keep up to $decimals places, drop trailing zeros
        // but never the thousands separators ("1,000" stays intact).
        return rtrim(rtrim(number_format($num, $decimals, '.', ','), '0'), '.');
    }
}

if (!function_exists('deptLabel')) {
    /** Human-readable department name for display (purchase requests, prints). */
    function deptLabel($dept)
    {
        $map = [
            'admin'      => 'System Admin',
            'warehouse'  => 'Warehouse',
            'production' => 'Production',
            'finance'    => 'Finance',
            'qc'         => 'QC',
            'qa'         => 'QA',
            'rnd'        => 'R&D',
        ];
        $dept = (string) $dept;
        if ($dept === '') {
            return '—';
        }
        return $map[$dept] ?? ucfirst($dept);
    }
}
