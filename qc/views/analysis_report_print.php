<?php
/** Printable Raw Material Analysis Report — official QCF-06 (qc_admin, both contexts). */
$report = $report ?? [];
$dtLabels = [
    'new_delivery' => 'New Delivery',
    'retest_1' => '1st Retest',
    'retest_2' => '2nd Retest',
    'retest_3' => '3rd Retest',
];
$testParams = [
    ['appearance', 'Appearance'],
    ['color', 'Color'],
    ['odor', 'Odor'],
    ['texture', 'Texture'],
    ['ph', 'pH'],
    ['viscosity', 'Viscosity'],
    ['sg', 'Specific Gravity'],
    ['solubility', 'Solubility'],
    ['alcohol', 'Alcohol Concentration'],
    ['microbial', 'Microbial Result'],
];
$dispLabels = ['APPROVED' => 'Approved', 'REJECTED' => 'Rejected', 'HOLD' => 'Hold', 'WAIVED' => 'Waived'];
$fmtDate = function ($d) {
    return !empty($d) && $d !== '0000-00-00' ? date('m/d/Y', strtotime($d)) : '';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <a href="<?= !empty($report['receiving_item_id']) ? '?controller=qc&action=receivingInspection' : '?controller=qc' ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print
    </button>
</div>

<style>
.qcf-page {
    background: #fff;
    max-width: 940px;
    margin: 0 auto;
    padding: 22px 26px;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
    color: #000;
    font-size: 12px;
}
.qcf-doc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 6px;
}
.qcf-brand { display: flex; align-items: center; gap: 12px; }
.qcf-brand img { width: 170px; height: auto; display: block; }
.qcf-address { font-size: 9.5px; color: #333; line-height: 1.35; max-width: 300px; }
.qcf-doc-meta { font-size: 11px; line-height: 1.7; text-align: right; white-space: nowrap; }
.qcf-doc-line { display: flex; justify-content: flex-end; gap: 8px; }
.qcf-doc-label { color: #333; }
.qcf-title { text-align: center; margin: 14px 0 16px; }
.qcf-dept { font-size: 14px; font-weight: 600; }
.qcf-form-name { font-size: 17px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.qcf-fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 34px; margin-bottom: 14px; }
.qcf-field-row { display: flex; align-items: baseline; gap: 6px; margin-bottom: 7px; }
.qcf-field-label { font-weight: 700; white-space: nowrap; min-width: 118px; }
.qcf-field-value { flex: 1 1 auto; border-bottom: 1px solid #000; min-height: 16px; padding-left: 4px; }
.qcf-checks { display: flex; flex-wrap: wrap; gap: 26px; justify-content: center; margin: 12px 0 16px; }
.qcf-check { display: flex; align-items: center; gap: 7px; font-weight: 600; }
.qcf-check .box { display: inline-block; width: 15px; height: 15px; border: 1.5px solid #000; text-align: center; line-height: 13px; font-size: 12px; }
.qcf-page table.qcf-tests { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
.qcf-page table.qcf-tests th,
.qcf-page table.qcf-tests td { border: 1.5px solid #000; padding: 5px 8px; font-size: 12px; }
.qcf-page table.qcf-tests th { font-weight: 700; }
.qcf-page table.qcf-tests td.param { font-weight: 700; width: 28%; }
.qcf-disp-label { font-weight: 700; margin-bottom: 8px; }
.qcf-remarks-label { font-weight: 700; margin-bottom: 4px; }
.qcf-remarks-line { border-bottom: 1.5px solid #000; min-height: 20px; margin-bottom: 6px; }
.qcf-footer { display: flex; gap: 34px; margin-top: 26px; }
.qcf-sig { flex: 1; }
.qcf-sig-row { border-bottom: 1.5px solid #000; padding-bottom: 3px; font-weight: 700; min-height: 22px; }
.qcf-sig-caption { font-size: 9.5px; color: #333; text-align: center; margin-top: 3px; font-style: italic; }
@media (max-width: 700px) {
    .qcf-fields-grid { grid-template-columns: 1fr; }
    .qcf-doc-header { flex-direction: column; align-items: flex-start; }
    .qcf-doc-meta { text-align: left; }
    .qcf-doc-line { justify-content: flex-start; }
    .qcf-footer { flex-direction: column; gap: 22px; }
}
@media print {
    .no-print { display: none !important; }
    body { background: #fff; }
    .qcf-page { box-shadow: none !important; max-width: none; padding: 0; }
}
</style>

<div class="qcf-page" id="analysisReportPrint">

    <!-- Official QCF-06 header -->
    <div class="qcf-doc-header">
        <div class="qcf-brand">
            <img src="public/images/logo.png" alt="CIANAN">
            <div class="qcf-address">
                Lot 7 Block 7 Springbook St. Sterling Technopark,<br>
                Silang, Cavite, Philippines
            </div>
        </div>
        <div class="qcf-doc-meta">
            <div class="qcf-doc-line"><span class="qcf-doc-label">Document No. :</span><strong>QCF-06</strong></div>
            <div class="qcf-doc-line"><span class="qcf-doc-label">Revision No. :</span><strong>03</strong></div>
            <div class="qcf-doc-line"><span class="qcf-doc-label">Effectivity Date :</span><strong>February 02, 2026</strong></div>
        </div>
    </div>

    <div class="qcf-title">
        <div class="qcf-dept">Quality Control Department</div>
        <div class="qcf-form-name">Raw Material Analysis Report</div>
    </div>

    <!-- Header fields -->
    <div class="qcf-fields-grid">
        <div>
            <div class="qcf-field-row"><span class="qcf-field-label">RM Name</span>:<span class="qcf-field-value"><?= htmlspecialchars($report['rm_name'] ?? '') ?></span></div>
            <div class="qcf-field-row"><span class="qcf-field-label">Supplier</span>:<span class="qcf-field-value"><?= htmlspecialchars($report['supplier_name'] ?? '') ?></span></div>
            <div class="qcf-field-row"><span class="qcf-field-label">Lot No.</span>:<span class="qcf-field-value"><?= htmlspecialchars($report['lot_number'] ?? '') ?></span></div>
            <div class="qcf-field-row"><span class="qcf-field-label">Old Expiry Date</span>:<span class="qcf-field-value"><?= htmlspecialchars($fmtDate($report['old_expiry_date'] ?? '')) ?></span></div>
        </div>
        <div>
            <div class="qcf-field-row"><span class="qcf-field-label">RR Number</span>:<span class="qcf-field-value"><?= htmlspecialchars($report['rr_number'] ?? '') ?></span></div>
            <div class="qcf-field-row"><span class="qcf-field-label">Date Delivered</span>:<span class="qcf-field-value"><?= htmlspecialchars($fmtDate($report['date_delivered'] ?? '')) ?></span></div>
            <div class="qcf-field-row"><span class="qcf-field-label">Date Tested</span>:<span class="qcf-field-value"><?= htmlspecialchars($fmtDate($report['date_tested'] ?? '')) ?></span></div>
            <div class="qcf-field-row"><span class="qcf-field-label">Expiry Date</span>:<span class="qcf-field-value"><?= htmlspecialchars($fmtDate($report['expiry_date'] ?? '')) ?></span></div>
        </div>
    </div>

    <!-- Delivery type -->
    <div class="qcf-checks">
        <?php foreach ($dtLabels as $key => $label): ?>
        <span class="qcf-check">
            <span class="box"><?= (($report['delivery_type'] ?? 'new_delivery') === $key) ? '&#10003;' : '' ?></span> <?= htmlspecialchars($label) ?>
        </span>
        <?php endforeach; ?>
    </div>

    <!-- Test parameters -->
    <table class="qcf-tests">
        <thead>
            <tr>
                <th style="width:28%">Test Parameters</th>
                <th style="width:36%">Specifications</th>
                <th style="width:36%">Results</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($testParams as [$key, $label]): ?>
            <tr>
                <td class="param"><?= htmlspecialchars($label) ?></td>
                <td><?= htmlspecialchars($report[$key . '_spec'] ?? '') ?></td>
                <td><?= htmlspecialchars($report[$key . '_result'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Disposition -->
    <div class="qcf-disp-label">Disposition:</div>
    <div class="qcf-checks" style="justify-content: flex-start; gap: 40px;">
        <?php foreach ($dispLabels as $key => $label): ?>
        <span class="qcf-check">
            <span class="box"><?= (($report['disposition'] ?? '') === $key) ? '&#10003;' : '' ?></span> <?= htmlspecialchars($label) ?>
        </span>
        <?php endforeach; ?>
    </div>

    <!-- Remarks on ruled lines -->
    <div class="qcf-remarks-label">Remarks:</div>
    <div class="qcf-remarks-line"><?= htmlspecialchars($report['remarks'] ?? '') ?></div>
    <div class="qcf-remarks-line">&nbsp;</div>

    <!-- Official footer: Inspected By/Date + Reviewed By/Date -->
    <div class="qcf-footer">
        <div class="qcf-sig">
            <div class="qcf-sig-row">Inspected By/Date: <?= htmlspecialchars($report['inspector_name'] ?? '') ?>, <?= htmlspecialchars($fmtDate($report['date_inspected'] ?? $report['date_tested'] ?? '')) ?></div>
            <div class="qcf-sig-caption">Signature over Printed name</div>
        </div>
        <div class="qcf-sig">
            <div class="qcf-sig-row">Reviewed By/Date:</div>
            <div class="qcf-sig-caption">Signature over Printed name</div>
        </div>
    </div>

</div>
