<style>
    .lmr-card {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
    }

    .lmr-section-title {
        font-size: 0.78rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
    }

    .lmr-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }

    .table-soft {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    .table-soft thead th {
        background: #f8fafc;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
    }

    .table-soft tbody td {
        vertical-align: middle;
        border-bottom: 1px solid #edf2f7;
        padding: 0.7rem 0.75rem;
        font-size: 0.9rem;
    }

    .sticky-toolbar {
        position: sticky;
        top: 0;
        z-index: 3;
        background: rgba(255,255,255,0.96);
        backdrop-filter: blur(8px);
    }

    #formValidationAlert {
        font-size: 0.88rem;
    }

    #formValidationAlert ul {
        margin-bottom: 0;
        padding-left: 1.2rem;
    }

    #lmrEntryForm .is-invalid,
    #lmrEntryForm select.is-invalid,
    #lmrEntryForm input.is-invalid {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
    }

</style>

<div class="lmr-toolbar sticky-toolbar pb-2">
    <div>
        <h5 class="mb-1"><i class="bi bi-clipboard-check me-2 text-primary"></i>LMR Entry</h5>
        <small class="text-muted">PPIC work order entry and material line tracking</small>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="?controller=lmr&action=index" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to LMR List
        </a>
        <button type="button" id="btn-save-lmr" class="btn btn-primary btn-sm">
            <i class="bi bi-check2-circle me-1"></i>Save LMR
        </button>
    </div>
</div>

<div id="formValidationAlert" class="alert alert-danger d-none" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2 mt-1 flex-shrink-0"></i>
    <span id="formValidationMessage" class="flex-grow-1"></span>
    <button type="button" class="btn-close ms-2 mt-1" id="formValidationClose" aria-label="Close"></button>
</div>

<form id="lmrEntryForm" class="lmr-card p-4" method="POST" action="<?= !empty($lmr) ? '?controller=lmr&action=update' : '?controller=lmr&action=store' ?>" novalidate>
    <?php if (!empty($lmr)): ?>
        <input type="hidden" name="lmr_id" value="<?= (int) $lmr['lmr_id'] ?>">
    <?php endif; ?>
    <input type="hidden" name="customer_id" id="customerIdHidden">
    <input type="hidden" name="customer_name" id="customerNameHidden">
    <input type="hidden" name="customer_code" id="customerCodeHidden">
    <div class="row g-4 mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">LMR Number <span class="text-danger">*</span></label>
                <input type="text" name="lmr_number" class="form-control" placeholder="Enter LMR number" value="<?= htmlspecialchars($lmr['lmr_number'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">LMR Site</label>
                <select name="lmr_site" class="form-select filter-select">
                    <option value="001 - Sterling Technopark" selected>001 - Sterling Technopark</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Customer Code</label>
                <select name="customer_code" id="customerCode" class="form-select filter-select">
                    <option value="">Select customer</option>
                    <?php foreach ($customers ?? [] as $customer): ?>
                        <option value="<?= htmlspecialchars($customer['customer_id']) ?>" data-name="<?= htmlspecialchars($customer['customer_name'] ?? '') ?>" data-code="<?= htmlspecialchars($customer['customer_code'] ?? '') ?>" <?= (int) ($lmr['customer_id'] ?? 0) === (int) $customer['customer_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($customer['customer_code'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Customer Name</label>
                <input type="text" id="customerName" class="form-control" readonly placeholder="Auto-fills after selecting customer" value="<?= htmlspecialchars($lmr['customer_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Formulation (BOM Code) <span class="text-danger">*</span></label>
                <select name="bom_code" id="bomCode" class="form-select filter-select" required data-selected-bom="<?= htmlspecialchars($lmr['bom_code'] ?? '') ?>">
                    <option value="">Select customer to load formulations</option>
                    <?php foreach ($boms ?? [] as $bom): ?>
                        <option value="<?= htmlspecialchars($bom['bom_code']) ?>" data-item-id="<?= htmlspecialchars($bom['fg_item_id']) ?>" data-item-code="<?= htmlspecialchars($bom['item_code'] ?? '') ?>" data-item-description="<?= htmlspecialchars($bom['item_description'] ?? '') ?>" data-customer-id="<?= htmlspecialchars($bom['customer_id'] ?? '') ?>">
                            <?= htmlspecialchars($bom['bom_code']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Item Number</label>
                <input type="text" name="item_code" id="headerItemCode" class="form-control" readonly placeholder="Auto-fills from formulation" value="<?= htmlspecialchars($lmr['item_code'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Item Description</label>
                <input type="text" name="item_description" id="headerItemDescription" class="form-control" readonly placeholder="Auto-fills from formulation" value="<?= htmlspecialchars($lmr['item_description'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Qty Ordered <span class="text-danger">*</span></label>
                <input type="number" name="qty_ordered" id="headerQtyOrdered" class="form-control" placeholder="Required" value="<?= htmlspecialchars($lmr['qty_ordered'] ?? '') ?>" min="0.01" step="any" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Batch / Lot Series</label>
                <input type="text" name="lot_series" class="form-control" placeholder="Enter batch / lot series" value="<?= htmlspecialchars($lmr['lot_series'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Lot Number</label>
                <input type="text" name="lot_number" class="form-control" placeholder="Optional" value="<?= htmlspecialchars($lmr['lot_number'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Date Started</label>
                <input type="date" name="date_started" class="form-control" value="<?= htmlspecialchars($lmr['date_started'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Date Finished</label>
                <input type="date" name="date_finished" class="form-control" value="<?= htmlspecialchars($lmr['date_finished'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Expiry Date</label>
                <input type="date" name="expiry_date" class="form-control" value="<?= htmlspecialchars($lmr['expiry_date'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="mb-4">
        <div class="lmr-section-title mb-3">Component Breakdown <span class="text-muted fs-6 fw-normal">(updates from Formulation &amp; Qty Ordered)</span></div>
        <div id="bomStatusAlert" style="display:none;"></div>
        <div id="bomShortageWarning" style="display:none;"></div>
        <div id="bomComponentPreview" style="display:none;">
            <div class="table-responsive border rounded-3">
                <table class="table table-sm mb-0 align-middle" id="bomComponentTable">
                    <thead class="table-light">
                        <tr>
                            <th>Component Item</th>
                            <th>UOM</th>
                            <th class="text-end">Req. Qty</th>
                            <th class="text-end">SOH</th>
                            <th class="text-end">Allocated</th>
                            <th class="text-end">Available</th>
                            <th>Status</th>
                            <th>Lots</th>
                        </tr>
                    </thead>
                    <tbody id="bomComponentBody"></tbody>
                </table>
            </div>
            <small class="text-muted d-block mt-1" id="bomBatchInfo"></small>
        </div>
    </div>
</form>

<!-- Lot allocation modal -->
<div class="modal fade" id="lotAllocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lotAllocTitle">Allocate Lots</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="lotAllocModalError" class="alert alert-danger py-2" style="display:none;"></div>
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div>
                        <strong>Required:</strong> <span id="lotAllocRequired">0</span>
                        &nbsp;|&nbsp; <strong>SOH:</strong> <span id="lotAllocSoh">—</span>
                        &nbsp;|&nbsp; <strong>Allocated (MRP):</strong> <span id="lotAllocMrpAllocated" class="text-warning fw-bold">—</span>
                        &nbsp;|&nbsp; <strong>Available (SOH − Allocated):</strong> <span id="lotAllocMrpAvailable">—</span>
                    </div>
                    <div><strong>Allocated:</strong> <span id="lotAllocTotal">0</span> &nbsp;|&nbsp; <strong>Remaining:</strong> <span id="lotAllocRemaining">0</span></div>
                </div>
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Lot No.</th>
                            <th>Received</th>
                            <th>Expiry</th>
                            <th class="text-end">SOH</th>
                            <th class="text-end">Reserved (LMR)</th>
                            <th class="text-end">Available Qty</th>
                            <th style="width:150px;">Allocate Qty</th>
                        </tr>
                    </thead>
                    <tbody id="lotAllocBody"></tbody>
                </table>
                <small class="text-muted d-block">Lots are sorted FIFO — oldest received stock first. <span class="text-warning fw-semibold">Reserved (LMR)</span> = qty allocated by other active LMR lot allocations (MRP books its reservation at item level — see Allocated (MRP) in the header); fully-reserved lots are locked.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary me-auto" id="lotAllocFifo"><i class="bi bi-arrow-down-up me-1"></i>Auto FIFO</button>
                <button type="button" class="btn btn-outline-danger" id="lotAllocClear">Clear</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="lotAllocConfirm">Save Allocation</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const customerSelect = document.getElementById('customerCode');
    const customerNameInput = document.getElementById('customerName');
    const customerIdHidden = document.getElementById('customerIdHidden');
    const customerNameHidden = document.getElementById('customerNameHidden');
    const customerCodeHidden = document.getElementById('customerCodeHidden');
    const bomCodeSelect = document.getElementById('bomCode');
    const headerItemCode = document.getElementById('headerItemCode');
    const headerItemDescription = document.getElementById('headerItemDescription');
    const headerQtyOrdered = document.getElementById('headerQtyOrdered');
    const bomStatusAlert = document.getElementById('bomStatusAlert');
    const bomComponentPreview = document.getElementById('bomComponentPreview');
    const bomComponentBody = document.getElementById('bomComponentBody');
    const bomBatchInfo = document.getElementById('bomBatchInfo');
    const bomShortageWarning = document.getElementById('bomShortageWarning');

    let bomFetchTimeout = null;

    // Multi-lot allocation state: { component_item_id: { item_code, description, required, allocs: [...] } }
    var lotAllocState = {};
    var currentAllocItemId = null;
    var currentLotList = [];
    // Edit mode: prefill previously saved lot allocations
    (function () {
        var existing = <?= json_encode($lot_allocations ?? []) ?>;
        (existing || []).forEach(function (a) {
            var iid = String(a.item_id);
            if (!lotAllocState[iid]) {
                lotAllocState[iid] = { item_code: a.item_code || '', description: '', required: null, allocs: [] };
            }
            lotAllocState[iid].allocs.push({ lot_number: a.lot_number, allocated_qty: parseFloat(a.allocated_qty) });
        });
    })();

    var allBomOptions = [];
    var bomOptionsCaptured = false;

    function filterBomOptionsByCustomer(customerId, customerCode) {
        if (!bomOptionsCaptured) {
            bomCodeSelect.querySelectorAll('option[data-customer-id]').forEach(function (opt) {
                allBomOptions.push(opt.cloneNode(true));
            });
            bomOptionsCaptured = true;
        }

        var matchCount = 0;
        var prevSel = bomCodeSelect.dataset.selectedBom || '';

        bomCodeSelect.innerHTML = '';

        if (String(customerId) === '') {
            var customerPlaceholder = document.createElement('option');
            customerPlaceholder.value = '';
            customerPlaceholder.textContent = 'Select customer to load formulations';
            bomCodeSelect.appendChild(customerPlaceholder);
        } else {
            allBomOptions.forEach(function (opt) {
                if ((opt.dataset.customerId || '').trim() === String(customerId)) {
                    var clone = opt.cloneNode(true);
                    if (opt.value === prevSel) {
                        clone.selected = true;
                    }
                    bomCodeSelect.appendChild(clone);
                    matchCount++;
                }
            });
            var emptyPlaceholder = document.createElement('option');
            emptyPlaceholder.value = '';
            if (matchCount === 0) {
                emptyPlaceholder.textContent = 'No formulation yet for ' + (customerCode || 'this customer');
            } else {
                emptyPlaceholder.textContent = 'Select formulation';
            }
            bomCodeSelect.insertBefore(emptyPlaceholder, bomCodeSelect.firstChild);
        }

        if (matchCount > 0 && bomCodeSelect.value === prevSel) {
            var selOpt = bomCodeSelect.options[bomCodeSelect.selectedIndex] || null;
            if (selOpt) {
                headerItemCode.value = selOpt.getAttribute('data-item-code') || '';
                headerItemDescription.value = selOpt.getAttribute('data-item-description') || '';
                return;
            }
        }
        bomCodeSelect.value = '';
        headerItemCode.value = '';
        headerItemDescription.value = '';
        resetBomPreview();
        delete bomCodeSelect.dataset.selectedBom;
    }

    function populateCustomerName() {
        const selectedOption = customerSelect.selectedOptions[0];
        const name = selectedOption ? selectedOption.dataset.name || '' : '';
        const code = selectedOption ? selectedOption.dataset.code || '' : '';
        customerNameInput.value = name;
        customerIdHidden.value = selectedOption ? selectedOption.value : '';
        customerNameHidden.value = name;
        customerCodeHidden.value = code;

        const customerId = selectedOption ? selectedOption.value : '';
        filterBomOptionsByCustomer(customerId, code);

        if (!customerId) {
            return;
        }
    }

    function resetBomPreview() {
        bomStatusAlert.style.display = 'none';
        bomStatusAlert.innerHTML = '';
        bomComponentPreview.style.display = 'none';
        bomComponentBody.innerHTML = '';
        bomBatchInfo.textContent = '';
        bomShortageWarning.style.display = 'none';
        bomShortageWarning.innerHTML = '';
    }

    function renderBomPreview(data) {
        bomStatusAlert.style.display = 'block';
        bomComponentPreview.style.display = 'none';
        bomComponentBody.innerHTML = '';
        bomBatchInfo.textContent = '';
        bomShortageWarning.style.display = 'none';
        bomShortageWarning.innerHTML = '';

        if (data.no_bom) {
            bomStatusAlert.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i><strong>No active BOM found</strong> for this item.</div>';
            return;
        }

        if (data.not_found) {
            bomStatusAlert.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>BOM <strong>' + (data.bom_code || '') + '</strong> not found in system.</div>';
            return;
        }

        if (!data.components || data.components.length === 0) {
            bomStatusAlert.innerHTML = '<div class="alert alert-success py-2 mb-0"><i class="bi bi-check-circle me-1"></i>BOM <strong>' + data.bom_code + '</strong> found. No components to check.</div>';
            return;
        }

        var batchInfoText;
        if (data.is_legacy_formula) {
            batchInfoText = 'Batch size: ' + data.batch_qty + ' | Batches needed: ' + Number(data.batches_needed || 0).toFixed(4) + ' (legacy formula)';
        } else {
            batchInfoText = 'Fill volume: ' + (data.fill_volume ?? data.batch_qty) + ' ' + (data.uom ?? data.batch_uom ?? '') +
                ' | UOM Divisor: ' + (data.batch_unit_divisor ?? 1000) +
                ' | Bulk batch: ' + Number(data.bulk_batch || 0).toFixed(4);
        }
        bomBatchInfo.textContent = batchInfoText;

        var hasShortage = data.components.some(function (c) { return Number(c.shortage) > 0; });
        if (hasShortage) {
            bomStatusAlert.innerHTML = '<div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>BOM <strong>' + data.bom_code + '</strong> found — stock shortages detected below.</div>';
            bomShortageWarning.style.display = 'block';
            var shortageItems = data.components.filter(function (c) { return Number(c.shortage) > 0; });
            var shortageListHtml = shortageItems.map(function (c) {
                return '<li><strong>' + c.item_code + '</strong> (' + (c.item_description || '') + ') — Short by <strong>' + c.shortage.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 4}) + ' ' + (c.item_uom || '') + '</strong></li>';
            }).join('');
            bomShortageWarning.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="bi bi-x-circle me-1"></i><strong>Shortage Summary:</strong><ul class="mb-0 mt-1 ps-3">' + shortageListHtml + '</ul></div>';
        } else {
            bomStatusAlert.innerHTML = '<div class="alert alert-success py-2 mb-0"><i class="bi bi-check-circle me-1"></i>BOM <strong>' + data.bom_code + '</strong> found — all materials available.</div>';
        }

        bomComponentPreview.style.display = 'block';
        var html = '';
        data.components.forEach(function (c) {
            var statusBadge = c.status === 'available'
                ? '<span class="badge bg-success"><i class="bi bi-check-lg"></i> In Stock</span>'
                : '<span class="badge bg-danger"><i class="bi bi-x-lg"></i> Shortage ' + c.shortage.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 4}) + '</span>';

            // Sync multi-lot allocation state with the current breakdown
            var compId = c.component_item_id ? String(c.component_item_id) : '';
            if (compId && Number(c.required_qty) > 0) {
                var st = lotAllocState[compId];
                if (!st) {
                    st = lotAllocState[compId] = { item_code: c.item_code, description: c.item_description || '', required: null, allocs: [] };
                }
                st.item_code = c.item_code;
                st.description = c.item_description || '';
                st.required = parseFloat(c.required_qty);
            }

            var allocCell = '';
            if (compId) {
                var stNow = lotAllocState[compId];
                var allocSummary = (stNow && stNow.allocs.length) ? stNow.allocs.length + ' lot(s)' : 'Allocate';
                allocCell = '<a href="#" class="lot-alloc-trigger" data-item-id="' + escapeHtml(compId) + '"' +
                    ' data-item-code="' + escapeHtml(c.item_code) + '"' +
                    ' data-description="' + escapeHtml(c.item_description || '') + '"' +
                    ' data-required="' + c.required_qty + '">' +
                    '<span class="badge ' + (stNow && stNow.allocs.length ? 'bg-info text-dark' : 'text-body-secondary border') + '"' + (stNow && stNow.allocs.length ? '' : ' style="border:1px solid;text-decoration:none;"') + '>' +
                    '<i class="bi bi-boxes me-1"></i>' + allocSummary + '</span></a>';
            }
            html += '<tr data-comp-item-id="' + escapeHtml(compId) + '">';
            html += '<td><strong>' + c.item_code + '</strong> ' + (c.item_description || '') + '</td>';
            html += '<td>' + (c.item_uom || '') + '</td>';
            html += '<td class="text-end">' + c.required_qty.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 4}) + '</td>';
            html += '<td class="text-end">' + c.soh.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 4}) + '</td>';
            html += '<td class="text-end">' + (c.allocated != null ? c.allocated : 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 4}) + '</td>';
            html += '<td class="text-end">' + Math.max(0, c.soh - (c.allocated || 0)).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 4}) + '</td>';
            html += '<td>' + statusBadge + '</td>';
            html += '<td class="lot-alloc-cell">' + (allocCell || '<span class="text-muted">—</span>') + '</td>';
            html += '</tr>';
        });
        bomComponentBody.innerHTML = html;
        updateAllRowAllocBadges();
    }

    function fetchBomBreakdown(itemId, qty) {
        if (bomFetchTimeout) {
            clearTimeout(bomFetchTimeout);
        }
        if (!itemId || !qty || qty <= 0) {
            resetBomPreview();
            return;
        }
        bomFetchTimeout = setTimeout(function () {
            fetch('?controller=lmr&action=getBomByItem&item_id=' + encodeURIComponent(itemId) + '&qty=' + encodeURIComponent(qty))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) {
                        resetBomPreview();
                        return;
                    }
                    renderBomPreview(data);
                })
                .catch(function () {
                    resetBomPreview();
                });
        }, 250);
    }

    function refreshHeaderBomBreakdown() {
        var opt = bomCodeSelect.options[bomCodeSelect.selectedIndex] || null;
        var itemId = (bomCodeSelect.value && opt) ? (opt.getAttribute('data-item-id') || '') : '';
        fetchBomBreakdown(itemId, headerQtyOrdered.value || 0);
    }

    customerSelect.addEventListener('change', populateCustomerName);

    bomCodeSelect.addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        headerItemCode.value = this.value ? (opt.getAttribute('data-item-code') || '') : '';
        headerItemDescription.value = this.value ? (opt.getAttribute('data-item-description') || '') : '';
        refreshHeaderBomBreakdown();
    });

    headerQtyOrdered.addEventListener('input', refreshHeaderBomBreakdown);

    // ---------- Multi-lot allocation (FIFO) ----------
    var lotAllocModalEl = document.getElementById('lotAllocModal');
    var lotAllocErrorEl = document.getElementById('lotAllocModalError');
    var lotAllocBody = document.getElementById('lotAllocBody');
    var lotAllocBs = (typeof bootstrap !== 'undefined' && bootstrap.Modal)
        ? new bootstrap.Modal(lotAllocModalEl) : null;

    function fmtQty(v) {
        return Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 });
    }

    function showLotAllocError(msg) {
        lotAllocErrorEl.textContent = msg;
        lotAllocErrorEl.style.display = 'block';
    }

    function hideLotAllocError() {
        lotAllocErrorEl.style.display = 'none';
        lotAllocErrorEl.textContent = '';
    }

    function getLmrIdValue() {
        var hidden = document.querySelector('#lmrEntryForm input[name="lmr_id"]');
        return hidden ? parseInt(hidden.value, 10) || 0 : 0;
    }

    function allocInputs() {
        return Array.prototype.slice.call(lotAllocBody.querySelectorAll('input[data-lot-number]'));
    }

    function allocCurrentTotals() {
        var st = lotAllocState[currentAllocItemId];
        var required = st && st.required != null ? parseFloat(st.required) : 0;
        var sum = allocInputs().reduce(function (acc, inp) {
            return acc + (parseFloat(inp.value) || 0);
        }, 0);
        return { required: required, sum: sum, remaining: required - sum };
    }

    function refreshAllocSummary() {
        var t = allocCurrentTotals();
        document.getElementById('lotAllocRequired').textContent = fmtQty(t.required);
        var totalEl = document.getElementById('lotAllocTotal');
        var remEl = document.getElementById('lotAllocRemaining');
        totalEl.textContent = fmtQty(t.sum);
        totalEl.classList.toggle('text-success', Math.abs(t.sum - t.required) < 1e-6 && t.required > 0);
        totalEl.classList.toggle('text-danger', t.sum - t.required > 1e-6);
        remEl.textContent = fmtQty(t.remaining);
        remEl.classList.toggle('text-success', Math.abs(t.remaining) < 1e-6 && t.required > 0);
        remEl.classList.toggle('text-warning', t.remaining > 1e-6);
    }

    function openLotAllocModal(itemId, itemCode, description, required) {
        currentAllocItemId = String(itemId);
        var st = lotAllocState[currentAllocItemId] || { item_code: itemCode, description: description, required: required, allocs: [] };
        st.required = required;
        lotAllocState[currentAllocItemId] = st;
        document.getElementById('lotAllocTitle').textContent =
            'Allocate Lots — ' + (itemCode || '') + (description ? ' (' + description + ')' : '');
        document.getElementById('lotAllocRequired').textContent = fmtQty(required);
        hideLotAllocError();
        document.getElementById('lotAllocSoh').textContent = '—';
        document.getElementById('lotAllocMrpAllocated').textContent = '—';
        document.getElementById('lotAllocMrpAvailable').textContent = '—';
        lotAllocBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-1"></span>Loading lots...</td></tr>';

        var lmrId = getLmrIdValue();
        if (lotAllocBs) lotAllocBs.show();

        fetch('?controller=lmr&action=getLotStock&item_id=' + encodeURIComponent(itemId) + '&lmr_id=' + encodeURIComponent(lmrId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    lotAllocBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3">' + escapeHtml(data.error) + '</td></tr>';
                    return;
                }
                document.getElementById('lotAllocSoh').textContent = fmtQty(data.soh);
                document.getElementById('lotAllocMrpAllocated').textContent = fmtQty(data.allocated);
                document.getElementById('lotAllocMrpAvailable').textContent = fmtQty(data.available_stock);
                currentLotList = data.lots || [];
                if (!currentLotList.length) {
                    lotAllocBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No inventory lots on hand for this item.</td></tr>';
                    refreshAllocSummary();
                    return;
                }
                var existingByLot = {};
                (st.allocs || []).forEach(function (a) {
                    existingByLot[a.lot_number] = (existingByLot[a.lot_number] || 0) + parseFloat(a.allocated_qty || 0);
                });
                // Merge in existing saved allocations that are not in the live lot list
                Object.keys(existingByLot).forEach(function (lot) {
                    var found = currentLotList.some(function (l) { return l.lot_number === lot; });
                    if (!found) {
                        currentLotList.push({ lot_number: lot, available_qty: existingByLot[lot], qty_on_hand: existingByLot[lot], reserved_qty: 0, received_date: null, expiry_date: null });
                    }
                });
                var rows = currentLotList.map(function (l) {
                    var allocQty = existingByLot[l.lot_number] || 0;
                    var fullyReserved = parseFloat(l.available_qty) <= 1e-9 && allocQty <= 0;
                    var rowClass = fullyReserved ? ' class="table-light text-muted"' : '';
                    var input;
                    if (fullyReserved) {
                        input = '<input type="number" class="form-control form-control-sm text-end" value="0" disabled title="Fully reserved by other active LMR(s)">';
                    } else {
                        input = '<input type="number" class="form-control form-control-sm text-end" min="0" step="0.0001" ' +
                            'data-lot-number="' + escapeHtml(l.lot_number) + '" data-available="' + l.available_qty + '" value="' + allocQty + '">';
                    }
                    return '<tr' + rowClass + '>' +
                        '<td><strong>' + escapeHtml(l.lot_number) + '</strong>' + (fullyReserved ? ' <i class="bi bi-lock-fill small text-secondary" title="Fully reserved by other active LMR allocation(s)"></i>' : '') + '</td>' +
                        '<td>' + (l.received_date || '—') + '</td>' +
                        '<td>' + (l.expiry_date || '—') + '</td>' +
                        '<td class="text-end">' + fmtQty(l.qty_on_hand) + '</td>' +
                        '<td class="text-end">' + (parseFloat(l.reserved_qty) > 0 ? '<span class="text-warning fw-semibold">' + fmtQty(l.reserved_qty) + '</span>' : fmtQty(0)) + '</td>' +
                        '<td class="text-end">' + fmtQty(l.available_qty) + '</td>' +
                        '<td>' + input + '</td>' +
                        '</tr>';
                }).join('');
                lotAllocBody.innerHTML = rows;
                refreshAllocSummary();
            })
            .catch(function () {
                lotAllocBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3">Failed to load lots.</td></tr>';
            });
    }

    bomComponentBody.addEventListener('click', function (e) {
        var trigger = e.target.closest('.lot-alloc-trigger');
        if (!trigger) return;
        e.preventDefault();
        openLotAllocModal(
            trigger.dataset.itemId,
            trigger.dataset.itemCode || '',
            trigger.dataset.description || '',
            parseFloat(trigger.dataset.required || 0)
        );
    });

    lotAllocBody.addEventListener('input', function (e) {
        if (!e.target.matches('input[data-lot-number]')) return;
        var inp = e.target;
        var avail = parseFloat(inp.dataset.available || 0);
        var v = parseFloat(inp.value);
        if (!isNaN(v) && v > avail) inp.value = avail;  // cap to available balance
        refreshAllocSummary();
    });

    document.getElementById('lotAllocFifo').addEventListener('click', function () {
        if (!currentLotList.length) return;
        if (currentAllocItemId) hideLotAllocError();
        var remaining = allocCurrentTotals().required;
        currentLotList.forEach(function (l) {
            var inp = lotAllocBody.querySelector('input[data-lot-number="' + CSS.escape(l.lot_number) + '"]');
            if (!inp || remaining <= 1e-6) return;
            var qty = Math.min(remaining, parseFloat(l.available_qty));
            inp.value = Math.round(qty * 10000) / 10000;
            remaining -= qty;
        });
        refreshAllocSummary();
    });

    document.getElementById('lotAllocClear').addEventListener('click', function () {
        allocInputs().forEach(function (inp) { inp.value = ''; });
        refreshAllocSummary();
    });

    function updateRowAllocBadge(itemId) {
        var trigger = bomComponentBody.querySelector('.lot-alloc-trigger[data-item-id="' + CSS.escape(String(itemId)) + '"]');
        if (!trigger) return;
        var st = lotAllocState[currentAllocItemId === String(itemId) ? currentAllocItemId : String(itemId)];
        var count = (st && st.allocs && st.allocs.length) ? st.allocs.length : 0;
        trigger.innerHTML = '<span class="badge ' + (count ? 'bg-info text-dark' : 'text-body-secondary border') + '"' + (count ? '' : ' style="border:1px solid;text-decoration:none;"') + '>' +
            '<i class="bi bi-boxes me-1"></i>' + (count ? count + ' lot(s)' : 'Allocate') + '</span>';
    }

    function updateAllRowAllocBadges() {
        Object.keys(lotAllocState).forEach(function (itemId) {
            var st = lotAllocState[itemId];
            if (st && st.allocs && st.allocs.length) updateRowAllocBadge(itemId);
        });
    }

    document.getElementById('lotAllocConfirm').addEventListener('click', function () {
        var st = lotAllocState[currentAllocItemId];
        if (!st) { return; }
        hideLotAllocError();
        var allocs = [];
        var overAlloc = false;
        allocInputs().forEach(function (inp) {
            var q = parseFloat(inp.value);
            if (!isNaN(q) && q > 0) {
                var avail = parseFloat(inp.dataset.available || 0);
                if (q > avail + 1e-6) {
                    overAlloc = true;
                    showLotAllocError('Allocated ' + fmtQty(q) + ' from lot "' + escapeHtml(inp.dataset.lotNumber) + '" exceeds its available balance of ' + fmtQty(avail) + '.');
                }
                allocs.push({ lot_number: inp.dataset.lotNumber, allocated_qty: q });
            }
        });
        if (overAlloc) return;

        var t = allocCurrentTotals();
        if (t.required > 0 && Math.abs(t.sum - t.required) > 1e-6) {
            showLotAllocError('Total allocated (' + fmtQty(t.sum) + ') must equal the required quantity (' + fmtQty(t.required) + '). Difference: ' + fmtQty(t.remaining) + '.');
            return;
        }

        st.allocs = allocs;
        updateRowAllocBadge(currentAllocItemId);
        if (lotAllocBs) lotAllocBs.hide();
    });
    // ---------- end multi-lot allocation ----------

    var FORM_ALERT_FADE_DELAY = 4500;
    var FORM_ALERT_FADE_MS = 600;
    var validationAlertEl = document.getElementById('formValidationAlert');

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function hideValidationAlertNow() {
        clearTimeout(validationAlertEl._fadeTimer);
        clearTimeout(validationAlertEl._hideTimer);
        validationAlertEl.classList.add('d-none');
        validationAlertEl.style.opacity = '1';
        document.getElementById('formValidationMessage').innerHTML = '';
        document.querySelectorAll('#lmrEntryForm .is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
    }

    function showFormValidation(message) {
        clearTimeout(validationAlertEl._fadeTimer);
        clearTimeout(validationAlertEl._hideTimer);
        validationAlertEl.style.transition = 'opacity ' + (FORM_ALERT_FADE_MS / 1000) + 's ease';
        validationAlertEl.style.opacity = '1';
        document.getElementById('formValidationMessage').innerHTML = message;
        validationAlertEl.classList.remove('d-none');
        validationAlertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

        validationAlertEl._fadeTimer = setTimeout(function () {
            validationAlertEl.style.opacity = '0';
            validationAlertEl._hideTimer = setTimeout(hideValidationAlertNow, FORM_ALERT_FADE_MS);
        }, FORM_ALERT_FADE_DELAY);
    }

    document.getElementById('formValidationClose').addEventListener('click', hideValidationAlertNow);

    function clearFormValidation() {
        clearTimeout(validationAlertEl._fadeTimer);
        validationAlertEl.classList.add('d-none');
        validationAlertEl.style.opacity = '1';
        document.getElementById('formValidationMessage').innerHTML = '';
        document.querySelectorAll('#lmrEntryForm .is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
    }

    function setSubmitLoading(loading) {
        var btn = document.getElementById('btn-save-lmr');
        if (!btn) return;
        if (loading) {
            btn.disabled = true;
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Saving...';
        } else {
            btn.disabled = false;
            if (btn.dataset.originalHtml) {
                btn.innerHTML = btn.dataset.originalHtml;
                delete btn.dataset.originalHtml;
            }
        }
    }

    document.getElementById('btn-save-lmr').addEventListener('click', async function () {
        console.log('Save LMR button clicked!');
        try {
            clearFormValidation();

            var errors = [];
            var moNumberInput = document.querySelector('input[name="lmr_number"]');
            var customerSelectEl = document.getElementById('customerCode');
            var firstInvalid = null;

            if (!moNumberInput.value.trim()) {
                errors.push('LMR Number is required.');
                moNumberInput.classList.add('is-invalid');
                if (!firstInvalid) firstInvalid = moNumberInput;
            }

            if (!customerSelectEl.value) {
                errors.push('Please select a Customer Code.');
                customerSelectEl.classList.add('is-invalid');
                if (!firstInvalid) firstInvalid = customerSelectEl;
            }

            var qtyOrderedEl = document.getElementById('headerQtyOrdered');
            var bomSelectEl = document.getElementById('bomCode');
            if (!qtyOrderedEl.value || parseFloat(qtyOrderedEl.value) <= 0) {
                errors.push('Qty Ordered must be greater than zero so the component breakdown can be calculated.');
                qtyOrderedEl.classList.add('is-invalid');
                if (!firstInvalid) firstInvalid = qtyOrderedEl;
            }

            if (!bomSelectEl.value) {
                errors.push('Formulation (BOM Code) is required so raw material availability can be checked.');
                bomSelectEl.classList.add('is-invalid');
                if (!firstInvalid) firstInvalid = bomSelectEl;
            }
            else if (bomShortageWarning.style.display === 'block') {
                errors.push('Cannot save: stock shortages exist for component items listed in the Component Breakdown.');
            }

            // Lot allocation validation: sums must match the required quantity
            var allocPayload = Object.keys(lotAllocState).map(function (itemId) {
                var st = lotAllocState[itemId];
                if (!st || !st.allocs) return null;
                var allocSum = st.allocs.reduce(function (acc, a) { return acc + (parseFloat(a.allocated_qty) || 0); }, 0);
                if (allocSum <= 1e-6) return null;
                if (st.required != null && Math.abs(allocSum - parseFloat(st.required)) > 1e-6) {
                    errors.push('Lot allocation for <strong>' + escapeHtml(st.item_code || itemId) + '</strong> totals ' + fmtQty(allocSum) + ' but must equal the required quantity ' + fmtQty(st.required) + '.');
                    return null;
                }
                return st.allocs.map(function (a) {
                    return { item_id: itemId, item_code: st.item_code, lot_number: a.lot_number, allocated_qty: a.allocated_qty };
                });
            }).filter(Boolean);

            if (errors.length > 0) {
                showFormValidation('<strong>Please fix the following:</strong><ul><li>' + errors.join('</li><li>') + '</li></ul>');
                if (firstInvalid) firstInvalid.focus();
                console.warn('Form validation failed:', errors);
                return;
            }

            console.log('Form validation passed. Submitting via AJAX...');

            setSubmitLoading(true);

            var formData = new FormData(document.getElementById('lmrEntryForm'));
            formData.append('lot_allocations_json', JSON.stringify(allocPayload.flatMap ? allocPayload.flat() : Array.prototype.concat.apply([], allocPayload)));

            var response = await fetch('?controller=lmr&action=store', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            console.log('HTTP ' + response.status + ' ' + response.statusText);

            var data;
            try {
                data = await response.json();
            } catch (parseErr) {
                console.error('Failed to parse server response as JSON:', parseErr);
                var rawText = await response.text().catch(function () { return '(could not read body)'; });
                console.error('Raw response body:', rawText);
                throw new Error('Server returned an invalid response (HTTP ' + response.status + '). Check the console for details.');
            }

            console.log('Server response:', data);

            if (!response.ok) {
                var serverMsg = (data && data.error) ? data.error : 'Server error (HTTP ' + response.status + ' ' + response.statusText + ').';
                console.error('Backend error — HTTP ' + response.status + ':', data);
                throw new Error(serverMsg);
            }

            if (data.success) {
                console.log('LMR saved successfully. Redirecting to:', data.redirect);
                window.location.href = data.redirect || '?controller=lmr&action=index';
            } else {
                setSubmitLoading(false);
                var errMsg = data.error || 'An unknown error occurred. Please try again.';
                console.error('Save failed:', errMsg);
                showFormValidation(errMsg);
            }
        } catch (err) {
            setSubmitLoading(false);
            console.error('Client JS Error:', err);
            Dlg.alert('Script error: ' + err.message);
        }
    });

    // Instant-clear banners as soon as the user starts typing/changing values.
    var entryFormEl = document.getElementById('lmrEntryForm');
    if (entryFormEl) {
        entryFormEl.addEventListener('input', hideValidationAlertNow);
        entryFormEl.addEventListener('change', hideValidationAlertNow);
    }

    // Edit mode: populateCustomerName() filters BOM options by the selected
    // customer and re-selects the saved formulation; then populate item fields
    // and re-fetch the breakdown.
    populateCustomerName();
    if (bomCodeSelect.value) {
        var initOpt = bomCodeSelect.options[bomCodeSelect.selectedIndex];
        headerItemCode.value = initOpt ? (initOpt.getAttribute('data-item-code') || '') : '';
        headerItemDescription.value = initOpt ? (initOpt.getAttribute('data-item-description') || '') : '';
        refreshHeaderBomBreakdown();
    }
});
</script>
