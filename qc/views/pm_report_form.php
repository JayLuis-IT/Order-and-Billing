<?php
/**
 * Packaging Material Inspection Report — modal form (official QCF-007 layout).
 * Used by Receiving Inspection when items.item_type = 'PM'.
 *
 * Set before include:
 *   $pmFormAction — POST target (?controller=qc&action=...)
 * Hidden receiving_item_id is injected by openPmReport(...) from the page's
 * Inspect button.
 */
$pmFormAction = $pmFormAction ?? '?controller=qc&action=savePmInspection';
$pmDimCols = 5; // number of editable dimension columns
?>
<style>
#pmReportModal .modal-dialog {
    width: min(96vw, 1100px) !important;
    max-width: min(96vw, 1100px) !important;
    height: calc(100vh - 1.5rem);
    max-height: calc(100vh - 1.5rem);
    margin: 0.75rem auto;
}
#pmReportModal .modal-dialog-scrollable .modal-content { max-height: 100%; }
#pmReportModal .modal-content > form {
    display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0;
}
#pmReportModal .modal-header, #pmReportModal .modal-footer { flex-shrink: 0; }
#pmReportModal .modal-dialog-scrollable .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; }

/* Document header (shared QCF style) */
#pmReportModal .qcf-doc-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 10px; }
#pmReportModal .qcf-brand { display: flex; align-items: center; gap: 10px; }
#pmReportModal .qcf-brand img { width: 130px; height: auto; display: block; }
#pmReportModal .qcf-address { font-size: 0.7rem; color: #444; line-height: 1.3; max-width: 230px; }
#pmReportModal .qcf-doc-meta { font-size: 0.78rem; line-height: 1.6; text-align: right; white-space: nowrap; }
#pmReportModal .qcf-doc-meta .qcf-doc-line { display: flex; justify-content: flex-end; gap: 6px; }
#pmReportModal .qcf-title { text-align: center; margin-bottom: 12px; }
#pmReportModal .qcf-title .qcf-dept { font-size: 0.9rem; font-weight: 600; }
#pmReportModal .qcf-title .qcf-form-name { font-size: 1.05rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; }

/* Field rows */
#pmReportModal .qcf-field-row { display: flex; align-items: center; gap: 4px; margin-bottom: 6px; }
#pmReportModal .qcf-field-row .qcf-field-label { font-weight: 600; font-size: 0.82rem; white-space: nowrap; min-width: 108px; }
#pmReportModal .qcf-field-row .qcf-colon { font-weight: 600; }
#pmReportModal .qcf-field-row .form-control { flex: 1 1 auto; min-width: 0; }
#pmReportModal .qcf-fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 26px; }

/* Section labels */
#pmReportModal .pm-section-label { font-weight: 700; font-size: 0.85rem; margin: 12px 0 4px; }
#pmReportModal .pm-aql-label { font-weight: 700; font-size: 0.8rem; text-align: right; }

/* Dimensional table */
#pmReportModal table.pm-dim-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
#pmReportModal table.pm-dim-table th, #pmReportModal table.pm-dim-table td { border: 1px solid #333; padding: 3px 4px; font-size: 0.78rem; }
#pmReportModal table.pm-dim-table th { background: #f0f0f0; font-weight: 700; text-align: center; }
#pmReportModal table.pm-dim-table td.row-label { font-weight: 700; background: #f8f8f8; text-align: center; white-space: nowrap; min-width: 70px; }
#pmReportModal table.pm-dim-table .form-control { border: 0; background: transparent; padding: 1px 2px; font-size: 0.78rem; text-align: center; }

/* Functional + Appearance side-by-side */
#pmReportModal .pm-test-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 900px) { #pmReportModal .pm-test-row { grid-template-columns: 1fr; } }
#pmReportModal table.pm-test-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
#pmReportModal table.pm-test-table th, #pmReportModal table.pm-test-table td { border: 1px solid #333; padding: 3px 4px; font-size: 0.78rem; }
#pmReportModal table.pm-test-table th { background: #f0f0f0; font-weight: 700; text-align: center; }
#pmReportModal table.pm-test-table td.param { font-weight: 700; white-space: nowrap; }
#pmReportModal table.pm-test-table .form-control { border: 0; background: transparent; padding: 1px 2px; font-size: 0.78rem; }

/* Disposition */
#pmReportModal .pm-disp-row { display: flex; flex-wrap: wrap; gap: 24px; margin-bottom: 10px; }
#pmReportModal .pm-disp-row label { display: flex; align-items: center; gap: 6px; font-weight: 600; font-size: 0.85rem; }

@media (max-width: 767.98px) {
    #pmReportModal .qcf-fields-grid { grid-template-columns: 1fr; }
    #pmReportModal .qcf-doc-header { flex-direction: column; align-items: flex-start; }
    #pmReportModal .qcf-doc-meta { text-align: left; }
    #pmReportModal .qcf-doc-meta .qcf-doc-line { justify-content: flex-start; }
}
@media (max-width: 575.98px) {
    #pmReportModal .modal-body { padding: 0.75rem; }
    #pmReportModal .qcf-brand { flex-direction: column; align-items: flex-start; gap: 4px; }
    #pmReportModal .qcf-field-row .qcf-field-label { min-width: 92px; font-size: 0.78rem; }
}
</style>

<div class="modal fade" id="pmReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= htmlspecialchars($pmFormAction) ?>" id="pmReportForm">
                <div class="modal-header py-2">
                    <h5 class="modal-title mb-0"><i class="bi bi-box-seam me-2"></i>Packaging Material Inspection Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="receiving_item_id" id="pmReceivingId" value="">

                    <!-- Official QCF-007 document header -->
                    <div class="qcf-doc-header">
                        <div class="qcf-brand">
                            <img src="public/images/logo.png" alt="CIANAN">
                            <div class="qcf-address">
                                Lot 7 Block 7 Springbook St. Sterling Technopark,<br>
                                Silang, Cavite, Philippines
                            </div>
                        </div>
                        <div class="qcf-doc-meta">
                            <div class="qcf-doc-line"><span>Document No. :</span><strong>QCF-007</strong></div>
                            <div class="qcf-doc-line"><span>Revision No. :</span><strong>3</strong></div>
                            <div class="qcf-doc-line"><span>Effectivity Date :</span><strong>August 15, 2022</strong></div>
                        </div>
                    </div>

                    <div class="qcf-title">
                        <div class="qcf-dept">Quality Control Department</div>
                        <div class="qcf-form-name">Packaging Material Inspection Report</div>
                    </div>

                    <!-- Header fields -->
                    <div class="qcf-fields-grid mb-3">
                        <div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">PM Code</span><span class="qcf-colon">:</span>
                                <input type="text" name="pm_code" id="pmCode" class="form-control form-control-sm border-0 border-bottom rounded-0">
                            </div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">PM Name</span><span class="qcf-colon">:</span>
                                <input type="text" name="pm_name" id="pmName" class="form-control form-control-sm border-0 border-bottom rounded-0" required>
                            </div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">Supplier</span><span class="qcf-colon">:</span>
                                <input type="text" name="supplier_name" id="pmSupplier" class="form-control form-control-sm border-0 border-bottom rounded-0">
                            </div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">Date Delivered</span><span class="qcf-colon">:</span>
                                <input type="date" name="date_delivered" id="pmDateDelivered" class="form-control form-control-sm border-0 border-bottom rounded-0">
                            </div>
                        </div>
                        <div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">RR Number</span><span class="qcf-colon">:</span>
                                <input type="text" name="rr_number" id="pmRrNumber" class="form-control form-control-sm border-0 border-bottom rounded-0">
                            </div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">Date Tested</span><span class="qcf-colon">:</span>
                                <input type="date" name="date_tested" id="pmDateTested" class="form-control form-control-sm border-0 border-bottom rounded-0" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">Quantity Delivered</span><span class="qcf-colon">:</span>
                                <input type="text" name="quantity_delivered" id="pmQtyDelivered" class="form-control form-control-sm border-0 border-bottom rounded-0">
                            </div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">Qty Used for Inspection</span><span class="qcf-colon">:</span>
                                <input type="number" name="qty_used" id="pmQtyUsed" class="form-control form-control-sm border-0 border-bottom rounded-0" min="0" step="any" placeholder="0">
                            </div>
                        </div>
                    </div>

                    <!-- Test Measures / Sample Qty -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="pm-section-label" style="margin:0;">Test Measures</div>
                        <div class="qcf-field-row" style="margin:0;">
                            <span class="qcf-field-label" style="min-width:auto;">Sample Qty :</span>
                            <input type="text" name="sample_qty" id="pmSampleQty" class="form-control form-control-sm border-0 border-bottom rounded-0" style="width:100px;">
                        </div>
                    </div>

                    <!-- VARIABLES (DIMENSIONAL) -->
                    <div class="table-responsive mb-2">
                        <table class="pm-dim-table" id="pmDimTable">
                            <thead>
                                <tr>
                                    <th colspan="1" style="background:#fff;border:none;"></th>
                                    <th colspan="<?= $pmDimCols ?>">VARIABLES (DIMENSIONAL)</th>
                                </tr>
                                <tr>
                                    <th style="background:#fff;border:none;"></th>
                                    <?php for ($c = 0; $c < $pmDimCols; $c++): ?>
                                    <th style="min-width:90px;"></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="row-label">DIMENSION</td>
                                    <?php for ($c = 0; $c < $pmDimCols; $c++): ?>
                                    <td><input type="text" name="dim_name[<?= $c ?>]" class="form-control"></td>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <td class="row-label">Specifications</td>
                                    <?php for ($c = 0; $c < $pmDimCols; $c++): ?>
                                    <td><input type="text" name="dim_specs[<?= $c ?>]" class="form-control"></td>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <td class="row-label">UOM</td>
                                    <?php for ($c = 0; $c < $pmDimCols; $c++): ?>
                                    <td><input type="text" name="dim_uom[<?= $c ?>]" class="form-control"></td>
                                    <?php endfor; ?>
                                </tr>
                                <?php for ($s = 1; $s <= 10; $s++): ?>
                                <tr>
                                    <td class="row-label"><?= $s ?></td>
                                    <?php for ($c = 0; $c < $pmDimCols; $c++): ?>
                                    <td><input type="text" name="dim_samples[<?= $c ?>][<?= $s ?>]" class="form-control dim-sample-input" data-col="<?= $c ?>" data-row="<?= $s ?>"></td>
                                    <?php endfor; ?>
                                </tr>
                                <?php endfor; ?>
                                <tr>
                                    <td class="row-label">Ave</td>
                                    <?php for ($c = 0; $c < $pmDimCols; $c++): ?>
                                    <td><input type="text" class="form-control dim-ave" data-col="<?= $c ?>" readonly tabindex="-1" style="background:#f5f5f5;"></td>
                                    <?php endfor; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Result (dimensional) -->
                    <div class="qcf-field-row mb-3" style="max-width:360px;">
                        <span class="qcf-field-label" style="min-width:60px;">Result :</span>
                        <input type="text" name="result_dimensional" id="pmResultDim" class="form-control form-control-sm border-0 border-bottom rounded-0">
                    </div>

                    <!-- Functional + Appearance side by side -->
                    <div class="pm-test-row mb-3">
                        <!-- VARIABLES (FUNCTIONAL) -->
                        <div>
                            <div class="pm-section-label" style="text-align:center;margin-top:0;">VARIABLES (FUNCTIONAL)</div>
                            <table class="pm-test-table">
                                <thead>
                                    <tr>
                                        <th style="width:44%">Test Parameters</th>
                                        <th style="width:33%">Results</th>
                                        <th style="width:23%">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $funcTests = ['Pump/Spray Test', 'Leak Test', 'Drop test', 'Adhesion Test', 'Tape Test', 'Others']; ?>
                                    <?php foreach ($funcTests as $idx => $test): ?>
                                    <tr>
                                        <td class="param"><?= htmlspecialchars($test) ?></td>
                                        <td><input type="text" name="func_result[<?= $idx ?>]" class="form-control"></td>
                                        <td><input type="text" name="func_qty[<?= $idx ?>]" class="form-control"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- VARIABLES (APPEARANCE / VISUAL) -->
                        <div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="pm-section-label" style="text-align:center;margin-top:0;flex:1;">VARIABLES (APPEARANCE / VISUAL)</div>
                                <span class="pm-aql-label">(AQL Based)</span>
                            </div>
                            <table class="pm-test-table" id="pmAppTable">
                                <thead>
                                    <tr>
                                        <th style="width:44%">Defect Name</th>
                                        <th style="width:33%">Results</th>
                                        <th style="width:23%">Qty</th>
                                    </tr>
                                </thead>
                                <tbody id="pmAppBody">
                                    <tr>
                                        <td><input type="text" name="app_name[]" class="form-control"></td>
                                        <td><input type="text" name="app_result[]" class="form-control"></td>
                                        <td><input type="text" name="app_qty[]" class="form-control"></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="pmAddAppBtn"><i class="bi bi-plus"></i> Add Defect</button>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="d-flex justify-content-end gap-4 mb-3">
                        <div class="qcf-field-row" style="margin:0;">
                            <span class="qcf-field-label" style="min-width:auto;">Total Reject Qty :</span>
                            <input type="text" name="total_reject_qty" id="pmTotalRejectQty" class="form-control form-control-sm border-0 border-bottom rounded-0" style="width:90px;">
                        </div>
                        <div class="qcf-field-row" style="margin:0;">
                            <span class="qcf-field-label" style="min-width:auto;">Total Reject Rate :</span>
                            <input type="text" name="total_reject_rate" id="pmTotalRejectRate" class="form-control form-control-sm border-0 border-bottom rounded-0" style="width:90px;">
                        </div>
                    </div>

                    <!-- Disposition -->
                    <div class="mb-2"><strong>Disposition:</strong></div>
                    <div class="pm-disp-row" id="pmDispositions">
                        <label><input type="checkbox" class="form-check-input pm-disposition" name="disposition" value="APPROVED"> Approved</label>
                        <label><input type="checkbox" class="form-check-input pm-disposition" name="disposition" value="REJECTED"> Rejected</label>
                        <label><input type="checkbox" class="form-check-input pm-disposition" name="disposition" value="HOLD"> Hold</label>
                        <label><input type="checkbox" class="form-check-input pm-disposition" name="disposition" value="WAIVED"> Waived</label>
                    </div>

                    <div class="mb-2"><strong>Remarks:</strong></div>
                    <textarea name="remarks" id="pmRemarks" class="form-control form-control-sm mb-2" rows="3"></textarea>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="save_and_print" value="1" class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-printer me-1"></i>Save &amp; Print
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Save Inspection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modalEl = document.getElementById('pmReportModal');
    if (!modalEl) return;
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var form = document.getElementById('pmReportForm');
    var dimCols = <?= $pmDimCols ?>;

    // Disposition checkboxes behave as radios.
    document.querySelectorAll('.pm-disposition').forEach(function(box) {
        box.addEventListener('change', function() {
            if (!this.checked) return;
            document.querySelectorAll('.pm-disposition').forEach(function(other) {
                if (other !== this) other.checked = false;
            }, this);
        });
    });

    // Auto-compute Ave row for each dimension column.
    function computeAverages() {
        for (var c = 0; c < dimCols; c++) {
            var sum = 0, count = 0;
            for (var r = 1; r <= 10; r++) {
                var input = form.querySelector('[name="dim_samples[' + c + '][' + r + ']"]');
                if (input && input.value.trim() !== '') {
                    var n = parseFloat(input.value);
                    if (!isNaN(n)) { sum += n; count++; }
                }
            }
            var aveInput = form.querySelector('.dim-ave[data-col="' + c + '"]');
            if (aveInput) {
                aveInput.value = count > 0 ? (sum / count).toFixed(2) : '';
            }
        }
    }
    form.addEventListener('input', function(e) {
        if (e.target.classList.contains('dim-sample-input')) computeAverages();
    });

    // Add appearance defect row.
    document.getElementById('pmAddAppBtn').addEventListener('click', function() {
        var body = document.getElementById('pmAppBody');
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" name="app_name[]" class="form-control"></td>' +
                       '<td><input type="text" name="app_result[]" class="form-control"></td>' +
                       '<td><input type="text" name="app_qty[]" class="form-control"></td>';
        body.appendChild(tr);
    });

    /**
     * Open the PM report for one receiving item.
     * opts: { receivingId, pmName, supplier, rr, delivered, pmCode, qtyDelivered }
     * previous: optional latest PM report row (prefills all sections).
     */
    window.openPmReport = function(opts, previous) {
        form.reset();
        document.getElementById('pmReceivingId').value = opts.receivingId || '';
        document.getElementById('pmCode').value = opts.pmCode || '';
        document.getElementById('pmName').value = opts.pmName || '';
        document.getElementById('pmSupplier').value = opts.supplier || '';
        document.getElementById('pmRrNumber').value = opts.rr || '';
        document.getElementById('pmDateDelivered').value = opts.delivered || '';
        document.getElementById('pmDateTested').value = '<?= date('Y-m-d') ?>';
        document.getElementById('pmQtyDelivered').value = opts.qtyDelivered || '';
        document.getElementById('pmQtyUsed').value = '0';
        document.querySelectorAll('.pm-disposition').forEach(function(b) { b.checked = false; });

        // Reset dynamic appearance rows to a single blank row.
        document.getElementById('pmAppBody').innerHTML =
            '<tr><td><input type="text" name="app_name[]" class="form-control"></td>' +
            '<td><input type="text" name="app_result[]" class="form-control"></td>' +
            '<td><input type="text" name="app_qty[]" class="form-control"></td></tr>';

        if (previous) {
            document.getElementById('pmCode').value = previous.pm_code || '';
            document.getElementById('pmName').value = previous.pm_name || opts.pmName || '';
            document.getElementById('pmSupplier').value = previous.supplier_name || '';
            document.getElementById('pmRrNumber').value = previous.rr_number || '';
            document.getElementById('pmDateDelivered').value = previous.date_delivered || '';
            document.getElementById('pmDateTested').value = previous.date_tested || '<?= date('Y-m-d') ?>';
            document.getElementById('pmQtyDelivered').value = previous.quantity_delivered || '';
            document.getElementById('pmSampleQty').value = previous.sample_qty || '';
            document.getElementById('pmQtyUsed').value = previous.qty_used || '0';
            document.getElementById('pmResultDim').value = previous.result_dimensional || '';
            document.getElementById('pmTotalRejectQty').value = previous.total_reject_qty || '';
            document.getElementById('pmTotalRejectRate').value = previous.total_reject_rate || '';
            document.getElementById('pmRemarks').value = previous.remarks || '';
            document.querySelectorAll('.pm-disposition').forEach(function(b) {
                b.checked = b.value === (previous.disposition || '');
            });

            // Dimensional data
            var dims = previous.dimensional_data || [];
            for (var c = 0; c < dimCols; c++) {
                var d = dims[c] || {};
                var nameInput = form.querySelector('[name="dim_name[' + c + ']"]');
                var specInput = form.querySelector('[name="dim_specs[' + c + ']"]');
                var uomInput = form.querySelector('[name="dim_uom[' + c + ']"]');
                if (nameInput) nameInput.value = d.dimension || '';
                if (specInput) specInput.value = d.specifications || '';
                if (uomInput) uomInput.value = d.uom || '';
                var samples = d.samples || [];
                for (var r = 1; r <= 10; r++) {
                    var sInput = form.querySelector('[name="dim_samples[' + c + '][' + r + ']"]');
                    if (sInput) sInput.value = samples[r - 1] || '';
                }
            }
            computeAverages();

            // Functional data
            var funcs = previous.functional_data || [];
            funcs.forEach(function(f, idx) {
                var rInput = form.querySelector('[name="func_result[' + idx + ']"]');
                var qInput = form.querySelector('[name="func_qty[' + idx + ']"]');
                if (rInput) rInput.value = f.result || '';
                if (qInput) qInput.value = f.qty || '';
            });

            // Appearance data
            var apps = previous.appearance_data || [];
            if (apps.length > 0) {
                var body = document.getElementById('pmAppBody');
                body.innerHTML = '';
                apps.forEach(function(a) {
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td><input type="text" name="app_name[]" class="form-control" value="' + esc(a.defect_name || '') + '"></td>' +
                        '<td><input type="text" name="app_result[]" class="form-control" value="' + esc(a.result || '') + '"></td>' +
                        '<td><input type="text" name="app_qty[]" class="form-control" value="' + esc(a.qty || '') + '"></td>';
                    body.appendChild(tr);
                });
            }
        }
        modal.show();
    };

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Require a disposition before saving.
    form.addEventListener('submit', function(e) {
        var anyChecked = false;
        document.querySelectorAll('.pm-disposition').forEach(function(b) { if (b.checked) anyChecked = true; });
        if (!anyChecked) {
            e.preventDefault();
            Dlg.alert('Please choose a disposition: Approved, Rejected, Hold, or Waived.');
            return false;
        }
        return true;
    });
});
</script>
