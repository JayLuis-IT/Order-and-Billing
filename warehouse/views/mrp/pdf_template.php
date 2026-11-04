<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@page { size: A4 landscape; margin: 12mm 15mm; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #000; margin: 0; padding: 0; }
.calc-basis { border: 1px solid #bbb; background: #f7f8fa; padding: 8px 12px; margin-bottom: 12px; font-size: 8px; }
.calc-basis .row { margin-bottom: 3px; overflow: hidden; }
.calc-basis .col { float: left; margin-right: 20px; }
.calc-basis strong { color: #1a1a2e; }
.calc-basis code { color: #c0392b; font-weight: bold; }
.badge-default { background: #198754; color: #fff; font-size: 7px; padding: 1px 5px; border-radius: 3px; font-weight: bold; }
.badge-switched { background: #0dcaf0; color: #000; font-size: 7px; padding: 1px 5px; border-radius: 3px; font-weight: bold; }
.calc-basis .formula { color: #555; font-size: 7px; margin-top: 4px; border-top: 1px dotted #ccc; padding-top: 4px; }
.section-title { font-size: 10px; font-weight: bold; margin: 0 0 6px 0; color: #1a1a2e; }
.fg-section { margin-bottom: 14px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
th { background: #f0f0f0; border: 1px solid #999; padding: 4px 5px; font-size: 8px; font-weight: bold; text-align: left; white-space: nowrap; text-transform: uppercase; }
td { border: 1px solid #ccc; padding: 3px 5px; font-size: 8px; }
.num { text-align: right; }
.shortage-row { background: #fffde7; }
.lacking-pos { color: #cc0000; font-weight: bold; }
.available-ok { color: #006600; font-weight: bold; }
.available-short { color: #cc0000; font-weight: bold; }
.item-code { color: #c0392b; font-weight: bold; }
.cat-badge { font-size: 7px; border: 1px solid #999; padding: 1px 4px; background: #f5f5f5; }
.signature-blocks { margin-top: 36px; overflow: hidden; }
.sig-block { float: left; width: 30%; margin-right: 3%; }
.sig-block:last-child { margin-right: 0; }
.sig-line { border-top: 1px solid #333; margin-top: 34px; padding-top: 4px; font-size: 8px; }
.sig-role { font-size: 7px; color: #555; margin-top: 2px; }
.page-break { page-break-before: always; }
</style>
</head>
<body>

<?php foreach ($mrpSections as $sec): ?>
<div class="fg-section">
    <!-- Calculation Basis -->
    <div class="calc-basis">
        <div class="row">
            <div class="col"><strong>FG Code:</strong> <code><?= htmlspecialchars($sec['fg_code'] ?? '') ?></code></div>
            <div class="col"><strong>Description:</strong> <?= htmlspecialchars($sec['fg_name'] ?? '') ?></div>
            <div class="col"><strong>Target Qty:</strong> <?= number_format(floatval($sec['target_qty'] ?? 0)) ?> <?= htmlspecialchars($sec['item_uom'] ?: 'Pcs') ?></div>
            <?php if (!empty($sec['bom_code'])): ?>
            <div class="col"><strong>BOM:</strong> <?= htmlspecialchars($sec['bom_code']) ?>
                <?= !empty($sec['is_default']) ? '<span class="badge-default">Default</span>' : '<span class="badge-switched">Switched</span>' ?></div>
            <?php endif; ?>
        </div>
        <div class="row">
            <div class="col"><strong>Fill Volume:</strong> <?= formatQty($sec['fill_volume'] ?? 0) ?> <?= htmlspecialchars($sec['uom'] ?? '') ?></div>
            <div class="col"><strong>UOM Divisor:</strong> <?= number_format(floatval($sec['batch_unit_divisor'] ?? 1000)) ?></div>
        </div>
        <div class="formula">
            RM: (Target &times; Fill Volume &divide; Divisor) &times; (Dosage / 100) &times; (1 + Wastage / 100)
            &nbsp;|&nbsp; PM/SFG: Target &times; Dosage &times; (1 + Wastage / 100)
            &nbsp;|&nbsp; Lacking = max(0, Required &minus; max(0, SOH &minus; Open Customer PO / Allocated))
        </div>
    </div>

    <!-- Material Requirements -->
    <div class="section-title">Material Requirements — <?= htmlspecialchars(($sec['fg_code'] ?? '') . ' - ' . ($sec['fg_name'] ?? '')) ?></div>
    <table>
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Description</th>
                <th>Category</th>
                <th class="num">Required Qty</th>
                <th class="num">SOH</th>
                <th class="num">Open Customer PO / Allocated</th>
                <th class="num">Pending PO / RR</th>
                <th class="num">Available</th>
                <th class="num">Lacking Qty</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sec['components'] as $row): ?>
            <?php
                $available = max(0, floatval($row['soh']) - floatval($row['allocated']));
                $lacking = max(0, floatval($row['total_reqt']) - $available);
            ?>
            <tr class="<?= $lacking > 0 ? 'shortage-row' : '' ?>">
                <td class="item-code"><?= htmlspecialchars($row['item_code']) ?></td>
                <td><?= htmlspecialchars($row['item_description']) ?></td>
                <td><span class="cat-badge"><?= htmlspecialchars($row['category'] ?? '-') ?></span></td>
                <td class="num"><?= formatQty($row['total_reqt']) ?> <?= htmlspecialchars($row['display_uom'] ?? $row['item_uom']) ?></td>
                <td class="num"><?= formatQty($row['soh']) ?></td>
                <td class="num"><?= formatQty($row['allocated']) ?></td>
                <td class="num"><?= formatQty($row['pending']) ?></td>
                <td class="num <?= $lacking > 0 ? 'available-short' : 'available-ok' ?>"><?= formatQty($available) ?></td>
                <td class="num <?= $lacking > 0 ? 'lacking-pos' : '' ?>"><?= formatQty($lacking) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<!-- Signature Blocks -->
<div class="signature-blocks">
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

</body>
</html>
