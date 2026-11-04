<?php
/**
 * MRP Snapshot — printable view (History printer icon).
 * Standalone page (no layout): Calculation Basis + snapshot table with an
 * EDITABLE Remarks column, then browser Print. Remarks are print-only —
 * they are NOT saved back to the frozen snapshot.
 * Data: getMrpRunSnapshotData() — $mrpSections, $userName, $userDept, $runId.
 */
if (!function_exists('formatQty')) {
    function formatQty($val, $maxDecimals = 4) {
        $formatted = number_format((float)$val, $maxDecimals, '.', ',');
        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MRP Snapshot #<?= (int) ($runId ?? 0) ?> — Print</title>
<link href="public/css/bootstrap.min.css" rel="stylesheet">
<link href="public/css/bootstrap-icons.min.css" rel="stylesheet">
<style>
body { background: #f1f5f9; font-family: 'Inter', Arial, sans-serif; font-size: 12px; color: #000; }
.print-sheet { background: #fff; max-width: 1500px; margin: 15px auto; padding: 18px 22px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
.toolbar { max-width: 1500px; margin: 15px auto 0; display: flex; justify-content: space-between; align-items: center; }
.snapshot-ref { text-align: right; font-size: 11px; color: #333; margin-bottom: 8px; }
.snapshot-ref strong { color: #c0392b; }
.calc-basis { border: 1px solid #bbb; background: #f7f8fa; padding: 10px 14px; margin-bottom: 14px; }
.calc-basis table { width: 100%; border: 0; }
.calc-basis td { border: 0; padding: 2px 0; font-size: 12px; }
.calc-basis strong { color: #1a1a2e; }
.calc-basis code { color: #c0392b; font-weight: bold; }
.badge-default { background: #198754; color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 3px; font-weight: bold; }
.badge-switched { background: #0dcaf0; color: #000; font-size: 10px; padding: 2px 6px; border-radius: 3px; font-weight: bold; }
.formula { color: #555; font-size: 11px; margin-top: 6px; border-top: 1px dotted #ccc; padding-top: 6px; }
.section-title { font-size: 13px; font-weight: bold; margin: 0 0 6px 0; color: #1a1a2e; }
.sheet-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
.sheet-table th { background: #f0f0f0; border: 1px solid #999; padding: 6px 8px; font-size: 11px; font-weight: bold; text-align: left; text-transform: uppercase; white-space: nowrap; }
.sheet-table td { border: 1px solid #ccc; padding: 5px 8px; font-size: 12px; }
.num { text-align: right; }
.item-fg { color: #c0392b; font-weight: bold; }
.excess-neg { color: #cc0000; font-weight: bold; }
.remarks-input { width: 100%; min-width: 120px; border: 1px solid transparent; background: transparent; font-size: 12px; padding: 2px 4px; }
.remarks-input:hover { border-color: #ced4da; }
.remarks-input:focus { border-color: #86b7fe; background: #fff; outline: none; }
.signatures { display: flex; margin-top: 44px; gap: 3%; }
.sig-block { flex: 1; }
.sig-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 6px; font-size: 12px; }
.sig-role { font-size: 10px; color: #555; margin-top: 2px; }
@media print {
    .no-print { display: none !important; }
    body { background: #fff; font-size: 11px; }
    .print-sheet { box-shadow: none; margin: 0; padding: 0; max-width: none; }
    .remarks-input { border: 0 !important; }
}
</style>
</head>
<body>

<div class="toolbar no-print">
    <a href="?controller=warehouse&action=mrpHistory" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to History
    </a>
    <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print
    </button>
</div>

<div class="print-sheet">

    <div class="snapshot-ref">
        MRP Ref: <strong><?= htmlspecialchars($mrpRef ?? ('MRP-#' . (int) ($runId ?? 0))) ?></strong>
        &nbsp;|&nbsp; Saved: <?= !empty($snapshotDate) ? date('m/d/Y h:i A', strtotime($snapshotDate)) : '' ?>
    </div>

    <?php foreach ($mrpSections as $sec): ?>
    <!-- Calculation Basis -->
    <div class="calc-basis">
        <table>
            <tr>
                <td style="width:25%"><strong>FG Code:</strong> <code><?= htmlspecialchars($sec['fg_code'] ?? '') ?></code></td>
                <td style="width:30%"><strong>Description:</strong> <?= htmlspecialchars($sec['fg_name'] ?? '') ?></td>
                <td style="width:22%"><strong>Target Qty:</strong> <?= number_format(floatval($sec['target_qty'] ?? 0)) ?> <?= htmlspecialchars($sec['item_uom'] ?: 'Pcs') ?></td>
                <td style="width:23%"><strong>BOM:</strong> <?= htmlspecialchars($sec['bom_code'] ?? '-') ?>
                    <?php if (!empty($sec['bom_code'])): ?>
                        <?= !empty($sec['is_default']) ? '<span class="badge-default">Default</span>' : '<span class="badge-switched">Switched</span>' ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Fill Volume:</strong> <?= formatQty($sec['fill_volume'] ?? 0) ?> <?= htmlspecialchars($sec['uom'] ?? '') ?></td>
                <td><strong>UOM Divisor:</strong> <?= number_format(floatval($sec['batch_unit_divisor'] ?? 1000)) ?></td>
                <td colspan="2"></td>
            </tr>
        </table>
        <div class="formula">
            RM: (Target &times; Fill Volume &divide; Divisor) &times; (Dosage / 100) &times; (1 + Wastage / 100)
            &nbsp;|&nbsp; PM/SFG: Target &times; Dosage &times; (1 + Wastage / 100)
            &nbsp;|&nbsp; Lacking = max(0, Required - max(0, SOH - Open Customer PO / Allocated))
        </div>
    </div>

    <!-- Snapshot Table -->
    <div class="section-title">Material Requirements — <?= htmlspecialchars(($sec['fg_code'] ?? '') . ' - ' . ($sec['fg_name'] ?? '')) ?></div>
    <table class="sheet-table">
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Description</th>
                <th>UOM</th>
                <th class="num">Total Reqt</th>
                <th class="num">SOH</th>
                <th class="num">Open Customer PO / Allocated</th>
                <th class="num">Pending PO/RR</th>
                <th class="num">Excess</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sec['components'] as $row): $excess = floatval($row['excess']); ?>
            <tr>
                <td class="item-fg"><?= htmlspecialchars($row['item_code'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['item_description']) ?></td>
                <td><?= htmlspecialchars($row['display_uom'] ?? $row['item_uom']) ?></td>
                <td class="num"><?= formatQty($row['total_reqt']) ?></td>
                <td class="num"><?= formatQty($row['soh']) ?></td>
                <td class="num"><?= formatQty($row['allocated']) ?></td>
                <td class="num"><?= formatQty($row['pending']) ?></td>
                <td class="num <?= $excess < 0 ? 'excess-neg' : '' ?>"><?= $excess < 0 ? '-' . formatQty(abs($excess)) : formatQty($excess) ?></td>
                <td>
                    <input type="text" class="remarks-input" value="<?= htmlspecialchars($row['remarks'] ?? '') ?>"
                           aria-label="Remarks for <?= htmlspecialchars($row['item_description']) ?>">
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endforeach; ?>

    <!-- Signature Blocks -->
    <div class="signatures">
        <div class="sig-block">
            <div class="sig-line">Prepared By: <?= htmlspecialchars($userName ?? '') ?></div>
            <div class="sig-role"><?= htmlspecialchars(ucfirst($userDept ?? '')) ?></div>
        </div>
        <div class="sig-block">
            <div class="sig-line">Reviewed By: Reymar Prieto</div>
            <div class="sig-role">&nbsp;</div>
        </div>
        <div class="sig-block">
            <div class="sig-line">Approved By: Ferdinand Ginete</div>
            <div class="sig-role">&nbsp;</div>
        </div>
    </div>

</div>

</body>
</html>
