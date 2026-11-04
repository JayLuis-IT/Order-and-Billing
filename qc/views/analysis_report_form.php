<?php
/**
 * Shared Raw Material Analysis Report — modal form (official QCF-06 layout).
 * Used by MRP Check (per run item) and Receiving Inspection (per receipt).
 *
 * Set before include:
 *   $analysisFormAction — POST target (?controller=qc&action=...)
 * Hidden target fields (run_id + component_item_id OR receiving_item_id)
 * are injected by openAnalysisReport(...) from the page's Inspect button.
 */
?>
<style>
/* QCF-06 modal: beat the global 90vw rule in style.css (ID scope wins) and
   cap the dialog to the viewport so the header/footer stay pinned and only
   the body scrolls — no zooming out to see the whole report. */
#analysisReportModal .modal-dialog {
    width: min(96vw, 1040px) !important;
    max-width: min(96vw, 1040px) !important;
    height: calc(100vh - 1.5rem);
    max-height: calc(100vh - 1.5rem);
    margin: 0.75rem auto;
}
#analysisReportModal .modal-dialog-scrollable .modal-content {
    max-height: 100%;
}
/* The <form> wraps header/body/footer, so it must BE the flex column —
   otherwise it grows past the dialog and modal-content's overflow:hidden
   clips the footer with no scrollbar anywhere. */
#analysisReportModal .modal-content > form {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}
#analysisReportModal .modal-header,
#analysisReportModal .modal-footer {
    flex-shrink: 0;
}
#analysisReportModal .modal-dialog-scrollable .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
}
#analysisReportModal .qcf-doc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 10px;
}
#analysisReportModal .qcf-brand {
    display: flex;
    align-items: center;
    gap: 10px;
}
#analysisReportModal .qcf-brand img {
    width: 130px;
    height: auto;
    display: block;
}
#analysisReportModal .qcf-address {
    font-size: 0.7rem;
    color: #444;
    line-height: 1.3;
    max-width: 230px;
}
#analysisReportModal .qcf-doc-meta {
    font-size: 0.78rem;
    line-height: 1.6;
    text-align: right;
    white-space: nowrap;
}
#analysisReportModal .qcf-doc-meta .qcf-doc-line {
    display: flex;
    justify-content: flex-end;
    gap: 6px;
}
#analysisReportModal .qcf-doc-meta .qcf-doc-label {
    color: #333;
}
#analysisReportModal .qcf-title {
    text-align: center;
    margin-bottom: 12px;
}
#analysisReportModal .qcf-title .qcf-dept {
    font-size: 0.9rem;
    font-weight: 600;
}
#analysisReportModal .qcf-title .qcf-form-name {
    font-size: 1.05rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
/* Colon-aligned field pairs */
#analysisReportModal .qcf-field-row {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 6px;
}
#analysisReportModal .qcf-field-row .qcf-field-label {
    font-weight: 600;
    font-size: 0.82rem;
    white-space: nowrap;
    min-width: 108px;
}
#analysisReportModal .qcf-field-row .qcf-colon {
    font-weight: 600;
}
#analysisReportModal .qcf-field-row .form-control {
    flex: 1 1 auto;
    min-width: 0;
}
#analysisReportModal .qcf-fields-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 26px;
}
@media (max-width: 767.98px) {
    #analysisReportModal .qcf-fields-grid {
        grid-template-columns: 1fr;
    }
    #analysisReportModal .qcf-doc-header {
        flex-direction: column;
        align-items: flex-start;
    }
    #analysisReportModal .qcf-doc-meta {
        text-align: left;
    }
    #analysisReportModal .qcf-doc-meta .qcf-doc-line {
        justify-content: flex-start;
    }
}
@media (max-width: 575.98px) {
    #analysisReportModal .modal-body { padding: 0.75rem; }
    #analysisReportModal .modal-header { padding: 0.5rem 0.75rem; }
    #analysisReportModal .modal-footer { padding: 0.5rem 0.75rem; }
    #analysisReportModal .qcf-brand { flex-direction: column; align-items: flex-start; gap: 4px; }
    #analysisReportModal .qcf-field-row .qcf-field-label { min-width: 92px; font-size: 0.78rem; }
}
</style>

<div class="modal fade" id="analysisReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= htmlspecialchars($analysisFormAction) ?>" id="analysisReportForm">
                <div class="modal-header py-2">
                    <h5 class="modal-title mb-0"><i class="bi bi-clipboard2-pulse me-2"></i>Raw Material Analysis Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="run_id" id="arRunId" value="">
                    <input type="hidden" name="component_item_id" id="arItemId" value="">
                    <input type="hidden" name="receiving_item_id" id="arReceivingId" value="">

                    <!-- Official QCF-06 document header -->
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

                    <!-- Header fields: colon + underline, 2 columns → stacked on small -->
                    <div class="qcf-fields-grid mb-3">
                        <div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">RM Name</span><span class="qcf-colon">:</span>
                                <input type="text" name="rm_name" id="arRmName" class="form-control form-control-sm border-0 border-bottom rounded-0" required>
                            </div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">Supplier</span><span class="qcf-colon">:</span>
                                <input type="text" name="supplier_name" id="arSupplier" class="form-control form-control-sm border-0 border-bottom rounded-0">
                            </div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">Lot No.</span><span class="qcf-colon">:</span>
                                <input type="text" name="lot_number" id="arLot" class="form-control form-control-sm border-0 border-bottom rounded-0">
                            </div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">Old Expiry Date</span><span class="qcf-colon">:</span>
                                <input type="date" name="old_expiry_date" id="arOldExpiry" class="form-control form-control-sm border-0 border-bottom rounded-0">
                            </div>
                        </div>
                        <div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">RR Number</span><span class="qcf-colon">:</span>
                                <input type="text" name="rr_number" id="arRrNumber" class="form-control form-control-sm border-0 border-bottom rounded-0">
                            </div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">Date Delivered</span><span class="qcf-colon">:</span>
                                <input type="date" name="date_delivered" id="arDateDelivered" class="form-control form-control-sm border-0 border-bottom rounded-0">
                            </div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">Date Tested</span><span class="qcf-colon">:</span>
                                <input type="date" name="date_tested" id="arDateTested" class="form-control form-control-sm border-0 border-bottom rounded-0" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="qcf-field-row">
                                <span class="qcf-field-label">Expiry Date</span><span class="qcf-colon">:</span>
                                <input type="date" name="expiry_date" id="arExpiry" class="form-control form-control-sm border-0 border-bottom rounded-0">
                            </div>
                        </div>
                    </div>

                    <!-- Delivery type checkboxes (radio behavior) -->
                    <div class="d-flex flex-wrap gap-3 gap-md-4 justify-content-center mb-3" id="arDeliveryTypes">
                        <label class="d-flex align-items-center gap-1 small">
                            <input type="checkbox" class="form-check-input ar-delivery-type" name="delivery_type" value="new_delivery" checked> New Delivery
                        </label>
                        <label class="d-flex align-items-center gap-1 small">
                            <input type="checkbox" class="form-check-input ar-delivery-type" name="delivery_type" value="retest_1"> 1st Retest
                        </label>
                        <label class="d-flex align-items-center gap-1 small">
                            <input type="checkbox" class="form-check-input ar-delivery-type" name="delivery_type" value="retest_2"> 2nd Retest
                        </label>
                        <label class="d-flex align-items-center gap-1 small">
                            <input type="checkbox" class="form-check-input ar-delivery-type" name="delivery_type" value="retest_3"> 3rd Retest
                        </label>
                    </div>

                    <!-- Test parameters: horizontal scroll on narrow screens -->
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered mb-0 align-middle" id="arTestTable" style="min-width: 520px;">
                            <thead>
                                <tr class="table-light">
                                    <th style="width:28%">Test Parameters</th>
                                    <th style="width:36%">Specifications</th>
                                    <th style="width:36%">Results</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $arParams = [
                                    'appearance' => 'Appearance',
                                    'color' => 'Color',
                                    'odor' => 'Odor',
                                    'texture' => 'Texture',
                                    'ph' => 'pH',
                                    'viscosity' => 'Viscosity',
                                    'sg' => 'Specific Gravity',
                                    'solubility' => 'Solubility',
                                    'alcohol' => 'Alcohol Concentration',
                                    'microbial' => 'Microbial Result',
                                ];
                                foreach ($arParams as $key => $label): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($label) ?></td>
                                    <td><input type="text" name="<?= $key ?>_spec" class="form-control form-control-sm border-0 border-bottom rounded-0 w-100"></td>
                                    <td><input type="text" name="<?= $key ?>_result" class="form-control form-control-sm border-0 border-bottom rounded-0 w-100"></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Disposition (radio behavior) -->
                    <div class="mb-2"><strong>Disposition:</strong></div>
                    <div class="d-flex flex-wrap gap-3 gap-md-4 mb-3" id="arDispositions">
                        <label class="d-flex align-items-center gap-1">
                            <input type="checkbox" class="form-check-input ar-disposition" name="disposition" value="APPROVED"> Approved
                        </label>
                        <label class="d-flex align-items-center gap-1">
                            <input type="checkbox" class="form-check-input ar-disposition" name="disposition" value="REJECTED"> Rejected
                        </label>
                        <label class="d-flex align-items-center gap-1">
                            <input type="checkbox" class="form-check-input ar-disposition" name="disposition" value="HOLD"> Hold
                        </label>
                        <label class="d-flex align-items-center gap-1">
                            <input type="checkbox" class="form-check-input ar-disposition" name="disposition" value="WAIVED"> Waived
                        </label>
                    </div>

                    <div class="mb-2"><strong>Remarks:</strong></div>
                    <textarea name="remarks" id="arRemarks" class="form-control form-control-sm mb-2" rows="3"></textarea>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="save_and_print" value="1" class="btn btn-outline-dark btn-sm" id="arSavePrintBtn">
                        <i class="bi bi-printer me-1"></i>Save &amp; Print
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm" id="arSaveBtn">
                        <i class="bi bi-check-lg me-1"></i>Save Inspection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Wired on DOMContentLoaded: this partial is rendered inside $content BEFORE
// the layout loads bootstrap.bundle.min.js — touching `bootstrap` at parse
// time throws ReferenceError and kills the IIFE, so openAnalysisReport would
// never be defined for the page's Inspect click handlers.
document.addEventListener('DOMContentLoaded', function() {
    var modalEl = document.getElementById('analysisReportModal');
    if (!modalEl) return;
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var form = document.getElementById('analysisReportForm');

    // Delivery-type and disposition checkboxes behave as radios.
    function wireRadioGroup(selector) {
        document.querySelectorAll(selector).forEach(function(box) {
            box.addEventListener('change', function() {
                if (!this.checked) return;
                document.querySelectorAll(selector).forEach(function(other) {
                    if (other !== this) other.checked = false;
                }, this);
            });
        });
    }
    wireRadioGroup('.ar-delivery-type');
    wireRadioGroup('.ar-disposition');

    /**
     * Open the report for one target.
     * opts: { runId, itemId, receivingId, rmName, supplier, lot, rr, delivered, oldExpiry }
     * previous: optional latest report row (prefills spec/result + disposition).
     */
    window.openAnalysisReport = function(opts, previous) {
        form.reset();
        document.getElementById('arRunId').value = opts.runId || '';
        document.getElementById('arItemId').value = opts.itemId || '';
        document.getElementById('arReceivingId').value = opts.receivingId || '';
        document.getElementById('arRmName').value = opts.rmName || '';
        document.getElementById('arSupplier').value = opts.supplier || '';
        document.getElementById('arLot').value = opts.lot || '';
        document.getElementById('arRrNumber').value = opts.rr || '';
        document.getElementById('arDateDelivered').value = opts.delivered || '';
        document.getElementById('arOldExpiry').value = opts.oldExpiry || '';
        document.getElementById('arDateTested').value = '<?= date('Y-m-d') ?>';

        document.querySelectorAll('.ar-delivery-type').forEach(function(b) { b.checked = b.value === 'new_delivery'; });
        document.querySelectorAll('.ar-disposition').forEach(function(b) { b.checked = false; });

        if (previous) {
            document.getElementById('arSupplier').value = previous.supplier_name || '';
            document.getElementById('arLot').value = previous.lot_number || '';
            document.getElementById('arRrNumber').value = previous.rr_number || '';
            document.getElementById('arDateDelivered').value = previous.date_delivered || '';
            document.getElementById('arOldExpiry').value = previous.old_expiry_date || '';
            document.getElementById('arDateTested').value = previous.date_tested || '<?= date('Y-m-d') ?>';
            document.getElementById('arExpiry').value = previous.expiry_date || '';
            document.getElementById('arRemarks').value = previous.remarks || '';
            document.querySelectorAll('.ar-delivery-type').forEach(function(b) {
                b.checked = b.value === (previous.delivery_type || 'new_delivery');
            });
            document.querySelectorAll('.ar-disposition').forEach(function(b) {
                b.checked = b.value === (previous.disposition || '');
            });
            ['appearance', 'color', 'odor', 'texture', 'ph', 'viscosity', 'sg', 'solubility', 'alcohol', 'microbial'].forEach(function(p) {
                var spec = form.querySelector('[name="' + p + '_spec"]');
                var res = form.querySelector('[name="' + p + '_result"]');
                if (spec) spec.value = previous[p + '_spec'] || '';
                if (res) res.value = previous[p + '_result'] || '';
            });
        }
        modal.show();
    };

    // Require a disposition before saving.
    form.addEventListener('submit', function(e) {
        var anyChecked = false;
        document.querySelectorAll('.ar-disposition').forEach(function(b) { if (b.checked) anyChecked = true; });
        if (!anyChecked) {
            e.preventDefault();
            Dlg.alert('Please choose a disposition: Approved, Rejected, Hold, or Waived.');
            return false;
        }
        return true;
    });
});
</script>
