<?php
/** Printable Packaging Material Inspection Report — official QCF-007 (qc_admin). */
$report = $report ?? [];
$dispLabels = ['APPROVED' => 'Approved', 'REJECTED' => 'Rejected', 'HOLD' => 'Hold', 'WAIVED' => 'Waived'];
$fmtDate = function ($d) {
    return !empty($d) && $d !== '0000-00-00' ? date('m/d/Y', strtotime($d)) : '';
};
$dimensions = $report['dimensional_data'] ?? [];
$functional = $report['functional_data'] ?? [];
$appearance = $report['appearance_data'] ?? [];
$dimCols = count($dimensions);
?>
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <a href="?controller=qc&action=receivingInspection" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print
    </button>
</div>

<style>
.pm-page {
    background: #fff;
    max-width: 960px;
    margin: 0 auto;
    padding: 22px 26px;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
    color: #000;
    font-size: 12px;
}
.pm-doc-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; margin-bottom: 6px; }
.pm-brand { display: flex; align-items: center; gap: 12px; }
.pm-brand img { width: 170px; height: auto; display: block; }
.pm-address { font-size: 9.5px; color: #333; line-height: 1.35; max-width: 300px; }
.pm-doc-meta { font-size: 11px; line-height: 1.7; text-align: right; white-space: nowrap; }
.pm-doc-line { display: flex; justify-content: flex-end; gap: 8px; }
.pm-doc-label { color: #333; }
.pm-title { text-align: center; margin: 14px 0 16px; }
.pm-dept { font-size: 14px; font-weight: 600; }
.pm-form-name { font-size: 17px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.pm-fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 34px; margin-bottom: 14px; }
.pm-field-row { display: flex; align-items: baseline; gap: 6px; margin-bottom: 7px; }
.pm-field-label { font-weight: 700; white-space: nowrap; min-width: 130px; }
.pm-field-value { flex: 1 1 auto; border-bottom: 1px solid #000; min-height: 16px; padding-left: 4px; }
.pm-measures-row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px; }
.pm-section-title { font-weight: 700; font-size: 13px; margin: 12px 0 4px; }
.pm-aql { font-weight: 700; font-size: 12px; }

/* Dimensional table */
.pm-page table.pm-dim { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
.pm-page table.pm-dim th, .pm-page table.pm-dim td { border: 1.5px solid #000; padding: 4px 6px; font-size: 11px; text-align: center; }
.pm-page table.pm-dim th { font-weight: 700; background: #f0f0f0; }
.pm-page table.pm-dim td.row-label { font-weight: 700; background: #f8f8f8; white-space: nowrap; }

/* Functional + Appearance */
.pm-test-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 10px; }
.pm-page table.pm-test { width: 100%; border-collapse: collapse; }
.pm-page table.pm-test th, .pm-page table.pm-test td { border: 1.5px solid #000; padding: 4px 6px; font-size: 11px; }
.pm-page table.pm-test th { font-weight: 700; background: #f0f0f0; text-align: center; }
.pm-page table.pm-test td.param { font-weight: 700; }

/* Totals */
.pm-totals { display: flex; justify-content: flex-end; gap: 30px; margin-bottom: 12px; }
.pm-total-row { display: flex; align-items: baseline; gap: 6px; }
.pm-total-label { font-weight: 700; white-space: nowrap; }
.pm-total-value { border-bottom: 1px solid #000; min-width: 90px; min-height: 16px; padding-left: 4px; }

/* Disposition */
.pm-disp-label { font-weight: 700; margin-bottom: 8px; }
.pm-checks { display: flex; flex-wrap: wrap; gap: 30px; margin-bottom: 14px; }
.pm-check { display: flex; align-items: center; gap: 7px; font-weight: 600; }
.pm-check .box { display: inline-block; width: 15px; height: 15px; border: 1.5px solid #000; text-align: center; line-height: 13px; font-size: 12px; }

/* Remarks */
.pm-remarks-label { font-weight: 700; margin-bottom: 4px; }
.pm-remarks-line { border-bottom: 1.5px solid #000; min-height: 20px; margin-bottom: 6px; }

/* Footer */
.pm-footer { display: flex; gap: 34px; margin-top: 26px; }
.pm-sig { flex: 1; }
.pm-sig-row { border-bottom: 1.5px solid #000; padding-bottom: 3px; font-weight: 700; min-height: 22px; }
.pm-sig-caption { font-size: 9.5px; color: #333; text-align: center; margin-top: 3px; font-style: italic; }

@media (max-width: 700px) {
    .pm-fields-grid { grid-template-columns: 1fr; }
    .pm-doc-header { flex-direction: column; align-items: flex-start; }
    .pm-doc-meta { text-align: left; }
    .pm-doc-line { justify-content: flex-start; }
    .pm-test-cols { grid-template-columns: 1fr; }
    .pm-footer { flex-direction: column; gap: 22px; }
}
@media print {
    .no-print { display: none !important; }
    body { background: #fff; }
    .pm-page { box-shadow: none !important; max-width: none; padding: 0; }
}
</style>

<div class="pm-page" id="pmReportPrint">

    <!-- Official QCF-007 header -->
    <div class="pm-doc-header">
        <div class="pm-brand">
            <img src="public/images/logo.png" alt="CIANAN">
            <div class="pm-address">
                Lot 7 Block 7 Springbook St. Sterling Technopark,<br>
                Silang, Cavite, Philippines
            </div>
        </div>
        <div class="pm-doc-meta">
            <div class="pm-doc-line"><span class="pm-doc-label">Document No. :</span><strong>QCF-007</strong></div>
            <div class="pm-doc-line"><span class="pm-doc-label">Revision No. :</span><strong>3</strong></div>
            <div class="pm-doc-line"><span class="pm-doc-label">Effectivity Date :</span><strong>August 15, 2022</strong></div>
        </div>
    </div>

    <div class="pm-title">
        <div class="pm-dept">Quality Control Department</div>
        <div class="pm-form-name">Packaging Material Inspection Report</div>
    </div>

    <!-- Header fields -->
    <div class="pm-fields-grid">
        <div>
            <div class="pm-field-row"><span class="pm-field-label">PM Code</span>:<span class="pm-field-value"><?= htmlspecialchars($report['pm_code'] ?? '') ?></span></div>
            <div class="pm-field-row"><span class="pm-field-label">PM Name</span>:<span class="pm-field-value"><?= htmlspecialchars($report['pm_name'] ?? '') ?></span></div>
            <div class="pm-field-row"><span class="pm-field-label">Supplier</span>:<span class="pm-field-value"><?= htmlspecialchars($report['supplier_name'] ?? '') ?></span></div>
            <div class="pm-field-row"><span class="pm-field-label">Date Delivered</span>:<span class="pm-field-value"><?= htmlspecialchars($fmtDate($report['date_delivered'] ?? '')) ?></span></div>
        </div>
        <div>
            <div class="pm-field-row"><span class="pm-field-label">RR Number</span>:<span class="pm-field-value"><?= htmlspecialchars($report['rr_number'] ?? '') ?></span></div>
            <div class="pm-field-row"><span class="pm-field-label">Date Tested</span>:<span class="pm-field-value"><?= htmlspecialchars($fmtDate($report['date_tested'] ?? '')) ?></span></div>
            <div class="pm-field-row"><span class="pm-field-label">Quantity Delivered</span>:<span class="pm-field-value"><?= htmlspecialchars($report['quantity_delivered'] ?? '') ?></span></div>
            <div class="pm-field-row"><span class="pm-field-label">Qty Used for Inspection</span>:<span class="pm-field-value"><?= htmlspecialchars($report['qty_used'] ?? '0') ?></span></div>
        </div>
    </div>

    <!-- Test Measures -->
    <div class="pm-measures-row">
        <span class="pm-section-title" style="margin:0;">Test Measures</span>
        <span class="pm-field-label" style="min-width:auto;">Sample Qty : <span class="pm-field-value" style="display:inline-block;min-width:80px;border-bottom:1px solid #000;"><?= htmlspecialchars($report['sample_qty'] ?? '') ?></span></span>
    </div>

    <!-- VARIABLES (DIMENSIONAL) -->
    <?php if ($dimCols > 0): ?>
    <table class="pm-dim">
        <thead>
            <tr>
                <th style="background:#fff;border:none;"></th>
                <th colspan="<?= $dimCols ?>">VARIABLES (DIMENSIONAL)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="row-label">DIMENSION</td>
                <?php foreach ($dimensions as $d): ?>
                <td><?= htmlspecialchars($d['dimension'] ?? '') ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td class="row-label">Specifications</td>
                <?php foreach ($dimensions as $d): ?>
                <td><?= htmlspecialchars($d['specifications'] ?? '') ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td class="row-label">UOM</td>
                <?php foreach ($dimensions as $d): ?>
                <td><?= htmlspecialchars($d['uom'] ?? '') ?></td>
                <?php endforeach; ?>
            </tr>
            <?php for ($s = 0; $s < 10; $s++): ?>
            <tr>
                <td class="row-label"><?= $s + 1 ?></td>
                <?php foreach ($dimensions as $d): ?>
                <td><?= htmlspecialchars($d['samples'][$s] ?? '') ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endfor; ?>
            <tr>
                <td class="row-label">Ave</td>
                <?php foreach ($dimensions as $d):
                    $samples = array_filter($d['samples'] ?? [], function($v) { return $v !== '' && $v !== null; });
                    $nums = array_map('floatval', $samples);
                    $ave = count($nums) > 0 ? number_format(array_sum($nums) / count($nums), 2) : '';
                ?>
                <td><?= htmlspecialchars($ave) ?></td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>

    <!-- Result -->
    <div class="pm-field-row" style="max-width:360px;margin-bottom:12px;">
        <span class="pm-field-label" style="min-width:60px;">Result :</span>
        <span class="pm-field-value"><?= htmlspecialchars($report['result_dimensional'] ?? '') ?></span>
    </div>
    <?php endif; ?>

    <!-- Functional + Appearance -->
    <div class="pm-test-cols">
        <div>
            <div class="pm-section-title" style="text-align:center;">VARIABLES (FUNCTIONAL)</div>
            <table class="pm-test">
                <thead>
                    <tr>
                        <th style="width:44%">Test Parameters</th>
                        <th style="width:33%">Results</th>
                        <th style="width:23%">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($functional as $f): ?>
                    <tr>
                        <td class="param"><?= htmlspecialchars($f['test'] ?? '') ?></td>
                        <td><?= htmlspecialchars($f['result'] ?? '') ?></td>
                        <td><?= htmlspecialchars($f['qty'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div>
            <div class="d-flex justify-content-between">
                <span class="pm-section-title" style="text-align:center;flex:1;">VARIABLES (APPEARANCE / VISUAL)</span>
                <span class="pm-aql">(AQL Based)</span>
            </div>
            <table class="pm-test">
                <thead>
                    <tr>
                        <th style="width:44%">Defect Name</th>
                        <th style="width:33%">Results</th>
                        <th style="width:23%">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appearance)): ?>
                    <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                    <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                    <?php else: foreach ($appearance as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['defect_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($a['result'] ?? '') ?></td>
                        <td><?= htmlspecialchars($a['qty'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Totals -->
    <div class="pm-totals">
        <div class="pm-total-row">
            <span class="pm-total-label">Total Reject Qty :</span>
            <span class="pm-total-value"><?= htmlspecialchars($report['total_reject_qty'] ?? '') ?></span>
        </div>
        <div class="pm-total-row">
            <span class="pm-total-label">Total Reject Rate :</span>
            <span class="pm-total-value"><?= htmlspecialchars($report['total_reject_rate'] ?? '') ?></span>
        </div>
    </div>

    <!-- Disposition -->
    <div class="pm-disp-label">Disposition:</div>
    <div class="pm-checks">
        <?php foreach ($dispLabels as $key => $label): ?>
        <span class="pm-check">
            <span class="box"><?= (($report['disposition'] ?? '') === $key) ? '&#10003;' : '' ?></span> <?= htmlspecialchars($label) ?>
        </span>
        <?php endforeach; ?>
    </div>

    <!-- Remarks -->
    <div class="pm-remarks-label">Remarks:</div>
    <div class="pm-remarks-line"><?= htmlspecialchars($report['remarks'] ?? '') ?></div>
    <div class="pm-remarks-line">&nbsp;</div>

    <!-- Footer -->
    <div class="pm-footer">
        <div class="pm-sig">
            <div class="pm-sig-row">Inspected By/Date:</div>
            <div class="pm-sig-caption">Signature over Printed name</div>
        </div>
        <div class="pm-sig">
            <div class="pm-sig-row">Reviewed By/Date:</div>
            <div class="pm-sig-caption">Signature over Printed name</div>
        </div>
    </div>

</div>
