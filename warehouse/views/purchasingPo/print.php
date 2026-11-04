<?php
/**
 * Purchasing PO — printable view (printer icon on every row).
 * Standalone page (no layout): header + line table + totals + signatures,
 * then browser Print. Works for grouped lines (whole group) and single rows.
 * Data: getPoGroupForPrint() — $lines (with item_code, item_description,
 * item_uom, created_by_name), $pageTitle, $first (first line).
 */
if (!function_exists('formatQty')) {
    function formatQty($val, $maxDecimals = 4) {
        $formatted = number_format((float)$val, $maxDecimals, '.', ',');
        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}
if (!function_exists('formatMoney')) {
    function formatMoney($val) {
        return number_format((float)$val, 2, '.', ',');
    }
}

$lines = $lines ?? [];
$first = $first ?? ($lines[0] ?? null);
$groupId = $first ? (int) ($first['po_group_id'] ?: $first['supplier_order_id']) : 0;
$statusLabels = [
    'requested' => 'Requested',
    'pending_whse_approval' => 'Pending WHSE Admin Approval',
    'pending_sysadmin_approval' => 'Pending System Admin Approval',
    'pending_qc_approval' => 'Pending QC Admin Approval',
    'pending_admin_approval' => 'Pending System Admin Approval (PO Draft)',
    'pending' => 'Active (Awaiting Receipt)',
    'processed' => 'Processed',
    'partially_received' => 'Partially Received',
    'For Inspection' => 'For Inspection',
    'received' => 'Received',
    'cancelled' => 'Cancelled',
    'rejected' => 'Rejected',
    'completed' => 'Completed',
    'PO_Created' => 'PO Created (consolidated)',
];
$grandTotal = 0.0;
$supplierTotals = [];
// MRP-source prints span the whole run, so statuses can mix (some lines
// Received, others still Active) — show a Status column only then.
$mixedStatuses = count(array_unique(array_map(function ($l) { return $l['status']; }, $lines))) > 1;
$colCount = $mixedStatuses ? 9 : 8;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($pageTitle ?? 'Purchasing Order') ?></title>
<link href="public/css/bootstrap.min.css" rel="stylesheet">
<link href="public/css/bootstrap-icons.min.css" rel="stylesheet">
<style>
body { background: #f1f5f9; font-family: 'Inter', Arial, sans-serif; font-size: 12px; color: #000; }
.print-sheet { background: #fff; max-width: 1100px; margin: 15px auto; padding: 24px 30px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
.toolbar { max-width: 1100px; margin: 15px auto 0; display: flex; justify-content: space-between; align-items: center; }
.po-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1a1a2e; padding-bottom: 10px; margin-bottom: 14px; }
.po-title { font-size: 20px; font-weight: bold; color: #1a1a2e; margin: 0; }
.po-sub { font-size: 11px; color: #555; }
.po-ref-box { text-align: right; font-size: 12px; }
.po-ref-box .po-ref { font-size: 15px; font-weight: bold; color: #c0392b; }
.po-meta { display: flex; flex-wrap: wrap; gap: 4%; margin-bottom: 14px; font-size: 12px; }
.po-meta > div { min-width: 180px; }
.po-meta .label { color: #666; font-size: 10px; text-transform: uppercase; }
.po-meta .value { font-weight: 600; }
.po-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.po-table th { background: #f0f0f0; border: 1px solid #999; padding: 6px 8px; font-size: 11px; font-weight: bold; text-align: left; text-transform: uppercase; white-space: nowrap; }
.po-table td { border: 1px solid #ccc; padding: 5px 8px; font-size: 12px; }
.num { text-align: right; }
.totals { display: flex; justify-content: flex-end; }
.totals table { border-collapse: collapse; min-width: 320px; }
.totals td { padding: 4px 10px; font-size: 12px; }
.totals .grand { font-weight: bold; font-size: 13px; border-top: 2px solid #1a1a2e; }
.status-line { margin-bottom: 12px; font-size: 12px; }
.status-line .badge-print { display: inline-block; border: 1px solid #888; border-radius: 3px; padding: 2px 8px; font-size: 11px; font-weight: bold; background: #fff8e6; }
.signatures { display: flex; margin-top: 48px; gap: 4%; }
.sig-block { flex: 1; }
.sig-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 6px; font-size: 12px; }
.sig-role { font-size: 10px; color: #555; margin-top: 2px; }
@media print {
    .no-print { display: none !important; }
    body { background: #fff; font-size: 11px; }
    .print-sheet { box-shadow: none; margin: 0; padding: 0; max-width: none; }
}
</style>
</head>
<body>
<div class="toolbar no-print">
    <h5 class="mb-0"><i class="bi bi-cart3 me-2"></i>Purchasing Order<?= $lines && count($lines) > 1 ? ' (' . count($lines) . ' lines)' : '' ?></h5>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
        <button class="btn btn-secondary" onclick="history.back()"><i class="bi bi-arrow-left me-1"></i> Back</button>
    </div>
</div>

<div class="print-sheet">
    <div class="po-head">
        <div>
            <h2 class="po-title">PURCHASING ORDER</h2>
            <div class="po-sub">Manufacturing Management System</div>
        </div>
        <div class="po-ref-box">
            <div class="label text-muted" style="font-size:10px;text-transform:uppercase;">PO Reference</div>
            <div class="po-ref"><?= htmlspecialchars($first['po_ref'] ?: ('Group #' . $groupId)) ?></div>
            <?php if (!empty($first['mrp_ref'])): ?>
            <div class="mt-1" style="font-size:11px;">MRP: <strong><?= htmlspecialchars($first['mrp_ref']) ?></strong></div>
            <?php endif; ?>
            <div style="font-size:11px;color:#555;">Printed <?= date('m/d/Y H:i') ?></div>
        </div>
    </div>

    <div class="po-meta">
        <div>
            <div class="label">Status</div>
            <div class="value"><span class="badge-print"><?= htmlspecialchars($statusLabels[$first['status']] ?? ucfirst($first['status'])) ?></span></div>
        </div>
        <div>
            <div class="label">Order Date</div>
            <div class="value"><?= $first['order_date'] ? date('m/d/Y', strtotime($first['order_date'])) : '-' ?></div>
        </div>
        <div>
            <div class="label">Expected Delivery</div>
            <div class="value"><?= $first['expected_date'] ? date('m/d/Y', strtotime($first['expected_date'])) : '-' ?></div>
        </div>
        <div>
            <div class="label">Created By</div>
            <div class="value"><?= htmlspecialchars($first['created_by_name'] ?? '') ?></div>
        </div>
        <div>
            <div class="label">Date Created</div>
            <div class="value"><?= $first['date_created'] ? date('m/d/Y H:i', strtotime($first['date_created'])) : '-' ?></div>
        </div>
    </div>

    <table class="po-table">
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th>Supplier</th>
                <th>Item Code</th>
                <th>Description</th>
                <th class="num">Qty</th>
                <th>UOM</th>
                <th class="num">Unit Cost</th>
                <th class="num">Amount</th>
                <?php if ($mixedStatuses): ?><th>Status</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lines as $i => $l):
                $amount = (float) $l['quantity'] * (float) $l['unit_cost'];
                $grandTotal += $amount;
                $sup = $l['supplier_name'];
                if (!isset($supplierTotals[$sup])) $supplierTotals[$sup] = 0.0;
                $supplierTotals[$sup] += $amount;
            ?>
            <tr>
                <td class="num"><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($l['supplier_name']) ?></td>
                <td><code><?= htmlspecialchars($l['item_code']) ?></code></td>
                <td><?= htmlspecialchars($l['item_description']) ?></td>
                <td class="num"><?= formatQty($l['quantity']) ?></td>
                <td><?= htmlspecialchars($l['item_uom'] ?? '') ?></td>
                <td class="num"><?= formatMoney($l['unit_cost']) ?></td>
                <td class="num"><?= formatMoney($amount) ?></td>
                <?php if ($mixedStatuses): ?>
                <td><?= htmlspecialchars($statusLabels[$l['status']] ?? ucfirst($l['status'])) ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <?php if (!$lines): ?>
            <tr><td colspan="<?= $colCount ?>" class="text-center text-muted">No lines found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (count($supplierTotals) > 1): ?>
    <div class="mb-2" style="font-size:11px;color:#444;">
        Per-supplier totals:
        <?php $parts = []; foreach ($supplierTotals as $supName => $supTotal) { $parts[] = htmlspecialchars($supName) . ': <strong>' . formatMoney($supTotal) . '</strong>'; } ?>
        <?= implode(' &nbsp;|&nbsp; ', $parts) ?>
    </div>
    <?php endif; ?>

    <div class="totals">
        <table>
            <tr>
                <td>Grand Total</td>
                <td class="num grand"><?= formatMoney($grandTotal) ?></td>
            </tr>
        </table>
    </div>

    <div class="signatures">
        <div class="sig-block">
            <div class="sig-line"><?= htmlspecialchars($first['created_by_name'] ?? '') ?></div>
            <div class="sig-role">Prepared By</div>
        </div>
        <div class="sig-block">
            <div class="sig-line">&nbsp;</div>
            <div class="sig-role">Approved By</div>
        </div>
        <div class="sig-block">
            <div class="sig-line">&nbsp;</div>
            <div class="sig-role">Received By</div>
        </div>
    </div>
</div>
</body>
</html>
