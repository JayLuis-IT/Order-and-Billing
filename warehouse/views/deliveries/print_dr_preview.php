<?php
$gcCustomerName = trim(($po['customer_name'] ?? '') ?: ($dr_deliveries[0]['customer_name'] ?? ''));
$isGreenCross = strcasecmp($gcCustomerName, 'GREEN CROSS INC.') === 0;

$gcGroups = [];
$gcTotalCases = 0;
$gcTotalRem = 0;
$gcGrandPcs = 0;
$gcTotalLabel = '';
$gcPallets = null;

if ($isGreenCross) {
    $gcShortCodes = [
        'FG0351-GCHSFLOCRE500' => 'QFS500',
        'FG0352-GCHSCITCLN500' => 'QCS500',
        'FG0353-GCHSFLOCRE450' => 'QFS450',
        'FG0354-GCHSCITCLN450' => 'QCS450',
        'FG0375-GCHSCITCLN225' => 'QCS225',
    ];
    foreach ($dr_deliveries as $d) {
        $qty = intval($d['delivery_quantity'] ?? 0);
        $conv = $d['actual_uom_conversion'] ?? $d['uom_conversion'] ?? null;
        $conv = ($conv && intval($conv) > 0) ? intval($conv) : null;
        $itemUom = $d['item_uom'] ?? '';
        $key = ($d['item_code'] ?? '') . '|' . intval($conv);

        if (!isset($gcGroups[$key])) {
            $gcGroups[$key] = [
                'item_code' => $d['item_code'] ?? '',
                'item_description' => $d['item_description'] ?? '',
                'conv' => $conv,
                'case_lines' => [],
                'pcs_lines' => [],
                'case_pcs' => 0,
                'rem_pcs' => 0,
            ];
        }

        if ($itemUom === 'CASE') {
            $cases = $qty;
            $rem = 0;
        } elseif ($conv) {
            $cases = intdiv($qty, $conv);
            $rem = $qty % $conv;
        } else {
            $cases = 0;
            $rem = $qty;
        }

        if ($cases > 0) {
            $gcGroups[$key]['case_lines'][] = ['lot' => $d['lot_number'] ?? '', 'qty' => $cases];
            $gcGroups[$key]['case_pcs'] += $conv ? $cases * $conv : $cases;
            $gcTotalCases += $cases;
        }
        if ($rem > 0) {
            $gcGroups[$key]['pcs_lines'][] = ['lot' => $d['lot_number'] ?? '', 'qty' => $rem];
            $gcGroups[$key]['rem_pcs'] += $rem;
            $gcTotalRem += $rem;
        }
    }

    foreach ($gcGroups as $g) {
        $gcGrandPcs += $g['case_pcs'] + $g['rem_pcs'];
    }

    if ($gcTotalCases > 0 && $gcTotalRem > 0) {
        $gcTotalLabel = 'TOTAL:  ' . $gcTotalCases . ' CS & ' . $gcTotalRem . ' PCS';
    } elseif ($gcTotalCases > 0) {
        $gcTotalLabel = 'TOTAL:  ' . $gcTotalCases . ' CS';
    } else {
        $gcTotalLabel = 'TOTAL:  ' . $gcTotalRem . ' PCS';
    }

    $gcPalletsInput = $pallets ?? null;
    $gcPallets = ($gcPalletsInput !== null && $gcPalletsInput !== '' && intval($gcPalletsInput) > 0) ? intval($gcPalletsInput) : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Receipt - <?= htmlspecialchars($dr_number) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: Calibri, sans-serif;
            font-size: 11pt;
            color: #000;
            background: #e0e0e0;
        }
        .no-print { display: block; text-align: center; padding: 12px; }
        .no-print button {
            padding: 10px 30px;
            font-size: 14px;
            cursor: pointer;
            background: #0d6efd;
            color: #fff;
            border: none;
            border-radius: 5px;
        }
        .no-print button:hover { background: #0b5ed7; }

        /* ─── Page Container ─── */
        .dr-page {
            width: 8.5in;
            height: 11in;
            position: relative;
            overflow: hidden;
            background: #fff;
            margin: 10px auto;
            font-family: Calibri, sans-serif;
        }

        /* ─── HEADER FIELDS ─── */

        .dr-field-delivered-to {
            position: absolute;
            top: 2.36in;
            left: 1.70in;
            width: 4.00in;
            font-family: Calibri, sans-serif;
            font-size: 11pt;
            text-align: left;
        }

        .dr-field-date {
            position: absolute;
            top: 2.38in;
            right: .50in;
            width: 2.00in;
            font-family: Calibri, sans-serif;
            font-size: 11pt;
            text-align: left;
        }

        .dr-field-address {
            position: absolute;
            top: 2.57in;
            left: 1.70in;
            width: 5.50in;
            font-family: Calibri, sans-serif;
            font-size: 11pt;
            text-align: left;
        }

        .dr-field-tin {
            position: absolute;
            top: 2.95in;
            left: 1.70in;
            width: 3.00in;
            font-family: Calibri, sans-serif;
            font-size: 11pt;
            text-align: left;
        }

        .dr-field-terms {
            position: absolute;
            top: 2.85in;
            left: 4.65in;
            width: 1.50in;
            font-family: Calibri, sans-serif;
            font-size: 11pt;
            text-align: left;
        }

        .dr-field-po-number {
            position: absolute;
            top: 2.85in;
            right: .45in;
            width: 2.00in;
            font-family: Calibri, sans-serif;
            font-size: 11pt;
            text-align: left;
        }

        /* ─── TABLE BODY START ─── */
        .dr-table-start {
            position: absolute;
            top: 3.60in;
            left: 0.50in;
            right: 0.50in;
        }

        /* ─── TABLE ROW (repeated per lot) ─── */
        .dr-row {
            display: flex;
            align-items: flex-start;
            min-height: 0.35in;
            border-bottom: 1px solid transparent;
        }

        .dr-col-qty {
            width: 1.00in;
            font-family: Calibri, sans-serif;
            font-size: 11pt;
            text-align: center;
            padding: 0 4px;
            flex-shrink: 0;
        }

        .dr-col-unit {
            width: .85in;
            font-family: Calibri, sans-serif;
            font-size: 11pt;
            text-align: center;
            padding: 0 4px;
            flex-shrink: 0;
        }

        .dr-col-desc {
            flex: 1;
            min-width: 0;
            margin-left: .35in;
            font-family: Calibri, sans-serif;
            font-size: 11pt;
            text-align: left;
            padding: 0 4px;
        }

        .dr-desc-wrap {
            white-space: normal;
            overflow-wrap: anywhere;
        }

        /* ─── FOOTER FIELDS ─── */
        .dr-remarks {
            position: absolute;
            bottom: 2.79in;
            left: 1.40in;
            font-family: Calibri, sans-serif;
            font-size: 11pt;
        }
        .dr-plate-no {
            position: absolute;
            bottom: 1.83in;
            left: 3.52in;
            font-family: Calibri, sans-serif;
            font-size: 11pt;
        }

        /* ─── Green Cross format ─── */
        .gc-batch {
            display: flex;
            align-items: baseline;
        }
        .gc-batch-no {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
        }
        .gc-batch-qty {
            width: .55in;
            text-align: right;
            flex-shrink: 0;
            padding-right: .12in;
        }
        .gc-batch-unit {
            width: .60in;
            text-align: left;
            flex-shrink: 0;
        }
        .gc-total-row {
            border-top: 1px dashed #000;
        }
        .gc-total-line {
            text-align: right;
        }

        /* ─── Print Styles ─── */
        @media print {
            @page { size: 8.5in 11in; margin: 0 !important; }
            html, body { padding: 0 !important; margin: 0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; background: #fff; }
            .no-print { display: none !important; }
            .dr-page { margin: 0 !important; box-shadow: none; background: #fff; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <a href="?controller=warehouse&action=deliveries" class="btn btn-secondary" style="margin-right: 10px;"><i class="bi bi-arrow-left me-1"></i> Back</a>
    <button onclick="window.print()"><i class="bi bi-printer"></i> Print Delivery Receipt</button>
</div>

<div class="dr-page">

    <!-- HEADER FIELDS -->
    <div class="dr-field-delivered-to"><?= htmlspecialchars($po['customer_name'] ?? '') ?></div>
    <div class="dr-field-date"><?= !empty($dr_deliveries[0]['delivery_date']) ? date('d-M-Y', strtotime($dr_deliveries[0]['delivery_date'])) : date('d-M-Y') ?></div>
    <div class="dr-field-address"><?= htmlspecialchars($po['customer_address'] ?? '') ?></div>
    <div class="dr-field-tin"><?= htmlspecialchars($po['customer_tin'] ?? '') ?></div>
    <div class="dr-field-terms"><?= htmlspecialchars($po['customer_terms'] ?? '') ?> DAYS</div>
    <div class="dr-field-po-number"><?= htmlspecialchars($po['customer_po_number'] ?? '') ?></div>

    <!-- TABLE BODY -->
    <div class="dr-table-start">
        <?php if ($isGreenCross): ?>
            <?php if (!empty($gcGroups)): ?>
                <?php foreach ($gcGroups as $g):
                    $gcCode = $gcShortCodes[$g['item_code']] ?? $g['item_code'];
                    $gcDesc = $g['item_description'];
                    $gcSuffix = ($g['conv'] && !preg_match('/x\s*' . $g['conv'] . '\s*$/i', $gcDesc)) ? '&nbsp;&nbsp;x' . $g['conv'] : '';
                    $gcHeader = htmlspecialchars($gcCode) . '&nbsp;&nbsp;&nbsp;' . htmlspecialchars($gcDesc) . $gcSuffix;
                ?>
                    <?php if (!empty($g['case_lines'])): ?>
                    <div class="dr-row">
                        <div class="dr-col-qty"><?= number_format($g['case_pcs']) ?></div>
                        <div class="dr-col-unit">PCS</div>
                        <div class="dr-col-desc"><?= $gcHeader ?></div>
                    </div>
                    <?php foreach ($g['case_lines'] as $i => $line): ?>
                    <div class="dr-row">
                        <div class="dr-col-qty"></div>
                        <div class="dr-col-unit"></div>
                        <div class="dr-col-desc gc-batch">
                            <span class="gc-batch-no"><?= ($i + 1) ?>. BATCH # <?= htmlspecialchars($line['lot']) ?></span>
                            <span class="gc-batch-qty"><?= number_format($line['qty']) ?></span>
                            <span class="gc-batch-unit">case</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($g['pcs_lines'])): ?>
                    <div class="dr-row">
                        <div class="dr-col-qty"><?= number_format($g['rem_pcs']) ?></div>
                        <div class="dr-col-unit">PCS</div>
                        <div class="dr-col-desc"><?= $gcHeader ?></div>
                    </div>
                    <?php foreach ($g['pcs_lines'] as $i => $line): ?>
                    <div class="dr-row">
                        <div class="dr-col-qty"></div>
                        <div class="dr-col-unit"></div>
                        <div class="dr-col-desc gc-batch">
                            <span class="gc-batch-no"><?= ($i + 1) ?>. BATCH # <?= htmlspecialchars($line['lot']) ?></span>
                            <span class="gc-batch-qty"><?= number_format($line['qty']) ?></span>
                            <span class="gc-batch-unit">pcs</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="dr-row gc-total-row">
                    <div class="dr-col-qty"><?= number_format($gcGrandPcs) ?></div>
                    <div class="dr-col-unit">pcs</div>
                    <div class="dr-col-desc gc-total-line"><?= $gcTotalLabel ?></div>
                </div>
                <?php if ($gcPallets !== null): ?>
                <div class="dr-row">
                    <div class="dr-col-qty"></div>
                    <div class="dr-col-unit"></div>
                    <div class="dr-col-desc gc-total-line">PLT QTY: <?= $gcPallets ?> PALLETS</div>
                </div>
                <?php endif; ?>
                <div class="dr-row" style="margin-top: 4px;">
                    <div class="dr-col-qty"></div>
                    <div class="dr-col-unit"></div>
                    <div class="dr-col-desc" style="font-style: italic; font-weight: bold;">*** Nothing follows ***</div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php if (!empty($dr_deliveries)): ?>
                <?php foreach ($dr_deliveries as $d):
                    $qty = $d['delivery_quantity'] ?? 0;
                    $conv = $d['actual_uom_conversion'] ?? $d['uom_conversion'] ?? null;
                    $itemUom = $d['item_uom'] ?? '';
                    $cases = ($conv && $itemUom !== 'CASE') ? floor($qty / $conv) : 0;

                    $descParts = [];
                    if (!empty($d['item_description'])) $descParts[] = $d['item_description'];
                    if ($cases > 0) $descParts[] = $cases . ' CASE';
                    if (!empty($d['lot_number'])) $descParts[] = 'Lot # ' . $d['lot_number'];
                    $fullDesc = implode(' | ', $descParts);
                ?>
                <div class="dr-row">
                    <div class="dr-col-qty"><?= number_format($qty) ?></div>
                    <div class="dr-col-unit">Pcs</div>
                    <div class="dr-col-desc dr-desc-wrap"><?= htmlspecialchars($fullDesc) ?></div>
                </div>
                <?php endforeach; ?>
                <div class="dr-row" style="margin-top: 4px;">
                    <div class="dr-col-qty"></div>
                    <div class="dr-col-unit"></div>
                    <div class="dr-col-desc" style="font-style: italic; font-weight: bold;">============Nothing follows.============</div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php
    $allRemarks = [];
    $plateNumber = '';
    $vehicleType = '';
    $logisticProvider = '';
    foreach ($dr_deliveries as $dd) {
        $r = trim($dd['remarks'] ?? '');
        if ($r !== '' && !in_array($r, $allRemarks)) $allRemarks[] = $r;
        if (empty($plateNumber) && !empty($dd['plate_number'])) $plateNumber = $dd['plate_number'];
        if (empty($vehicleType) && !empty($dd['vehicle_type'])) $vehicleType = $dd['vehicle_type'];
        if (empty($logisticProvider) && !empty($dd['logistic_provider'])) $logisticProvider = $dd['logistic_provider'];
    }
    ?>
    <?php if (!empty($allRemarks)): ?>
    <div class="dr-remarks"><?= $isGreenCross ? 'REMARKS: ' : ' ' ?><?= htmlspecialchars(implode('; ', $allRemarks)) ?></div>
    <?php endif; ?>
    <?php if (!empty($plateNumber)): ?>
    <div class="dr-plate-no"> <?= htmlspecialchars($plateNumber) ?></div>
    <?php endif; ?>

</div>

</body>
</html>
