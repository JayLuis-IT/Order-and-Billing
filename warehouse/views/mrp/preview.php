<?php
// formatQty() is global (app/helpers/format_helper.php, required by index.php);
// this guard only matters if the view is ever rendered standalone.
if (!function_exists('formatQty')) {
    function formatQty($val, $maxDecimals = 4) {
        $formatted = number_format((float)$val, $maxDecimals, '.', ',');
        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}

$mrpLackingRows = [];
if (!empty($mrpSection['components'])) {
    foreach ($mrpSection['components'] as $mrpRow) {
        if (floatval($mrpRow['lacking_qty']) > 0) {
            $mrpLackingRows[] = [
                'component_item_id' => (int)$mrpRow['component_item_id'],
                'lacking_qty' => $mrpRow['lacking_qty'],
            ];
        }
    }
}
$showSaveBtn = !empty($didCalculate) && !empty($mrpSection) && !empty($mrpSection['components']);
?>
<style>
.mrp-section { page-break-inside: avoid; margin-bottom: 20px; }
.mrp-fg-header { background: #e8ecef; padding: 8px 12px; border-radius: 4px; margin-bottom: 8px; font-size: 0.9rem; }
.mrp-fg-header strong { color: #1a1a2e; }
.mrp-meta { font-size: 0.8rem; color: #555; margin-top: 4px; }
.mrp-table th { background: #f0f0f0; font-size: 0.78rem; white-space: nowrap; }
.mrp-table td { font-size: 0.8rem; }
.mrp-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.mrp-lacking { color: #dc3545; font-weight: 700; }
.mrp-ok { color: #198754; font-weight: 600; }
.mrp-shortage-row { background: #fff8e1 !important; }
.mrp-empty { text-align: center; padding: 40px; color: #888; }
.mrp-signature { margin-top: 30px; display: flex; justify-content: space-between; gap: 20px; }
.mrp-signature .sig-block { flex: 1; }
.mrp-signature .sig-line { border-top: 1px solid #333; margin-top: 35px; padding-top: 5px; font-size: 0.8rem; }
.mrp-signature .sig-role { font-size: 0.7rem; color: #666; margin-top: 2px; }
.mrp-cat-badge { font-size: 0.7rem; }
@media print {
    .no-print { display: none !important; }
    .main-wrapper { margin: 0 !important; padding: 10px !important; }
    .sidebar { display: none !important; }
    .mrp-section { page-break-inside: avoid; }
    body { font-size: 10px; }
    .input-allocated-override { border: 0 !important; padding: 0 !important; appearance: textfield; }
    .input-allocated-override::-webkit-outer-spin-button,
    .input-allocated-override::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h4 class="mb-0"><i class="bi bi-calculator me-2"></i>MRP Sheet — Material Requirements</h4>
    <div class="d-flex gap-2">
        <?php if (!empty($poHeader)): ?>
        <?php if (!empty($hasExistingSnapshot)): ?>
            <span class="btn btn-success disabled">
                <i class="bi bi-check-circle me-1"></i>Snapshot Saved
            </span>
        <?php else: ?>
        <form method="POST" action="?controller=warehouse&action=saveMrpSnapshot" class="d-inline" id="saveMrpForm">
            <input type="hidden" name="po_id" value="<?= $selectedPO ?>">
            <input type="hidden" name="customer_id" value="<?= $selectedCustomer ?>">
            <button type="submit" class="btn btn-success" id="saveMrpBtn" data-confirm="Save current MRP as snapshot?">
                <i class="bi bi-save me-1"></i>Save Snapshot
            </button>
        </form>
        <script>
        document.getElementById('saveMrpForm')?.addEventListener('submit', function() {
            var btn = document.getElementById('saveMrpBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
        });
        </script>
        <?php endif; ?>
        <?php endif; ?>
        <a href="?controller=warehouse&action=mrpHistory" class="btn btn-outline-secondary">
            <i class="bi bi-clock-history me-1"></i>History
        </a>
        <?php if ($showSaveBtn): ?>
        <button type="button" class="btn btn-outline-dark" id="btnPrintMrpSheet" title="Print this MRP sheet">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <?php endif; ?>
        <?php if (!empty($poHeader)): ?>
        <a href="?controller=warehouse&action=mrpPDF&customer_id=<?= $selectedCustomer ?>&po_id=<?= $selectedPO ?>" class="btn btn-danger">
            <i class="bi bi-file-earmark-pdf me-1"></i>Print PDF
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Selection Bar: Customer + FG + Target Qty -->
<div class="card data-card mb-4 no-print">
    <div class="card-body">
        <form method="GET" id="mrpSelectionForm" class="row g-2 align-items-end">
            <input type="hidden" name="controller" value="warehouse">
            <input type="hidden" name="action" value="mrp">
            <!-- Switch BOM: chosen formulation; 0/absent = default BOM for the FG -->
            <input type="hidden" name="bom_id" id="mrpBomId" value="<?= (int) ($selectedBomId ?? 0) ?>">

            <div class="col-xl-3 col-md-3">
                <label class="form-label fw-bold">Select Customer</label>
                <select name="customer_id" id="mrpCustomer" class="form-select filter-select">
                    <option value="">-- All Customers --</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['customer_id'] ?>" <?= ($selectedCustomer == $c['customer_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['customer_code'] . ' - ' . $c['customer_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- FG / SFG select + integrated Switch BOM button. The input-group
                 keeps the button inside this column: toggling d-none resizes the
                 select only and never bleeds into the Target Quantity field. -->
            <div class="col-xl-4 col-md-4">
                <label class="form-label fw-bold">Select Finished Good / SFG</label>
                <div id="bomCurrentIndicator" class="small text-muted mb-1 d-none">
                    <i class="bi bi-check-circle-fill text-success me-1"></i>Current BOM: <code id="bomCurrentCode"></code>
                </div>
                <div class="input-group bom-switch-hidden" id="fgBomInputGroup">
                    <select name="fg_item_id" id="mrpFg" class="form-select filter-select" <?= empty($fgOptions) ? 'disabled' : '' ?>>
                        <?php if (empty($fgOptions)): ?>
                        <option value=""><?= !empty($noFgsForCustomer) ? 'No FG/SFG with active BOM for this customer' : 'No FG/SFG with active BOM' ?></option>
                        <?php else: ?>
                        <option value="">-- Select Finished Good / SFG --</option>
                        <?php foreach ($fgOptions as $fg): ?>
                        <option value="<?= $fg['item_id'] ?>" <?= ($selectedFg == $fg['item_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fg['item_code'] . ' — ' . $fg['item_description']) ?>
                        </option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <button type="button" id="btnSelectBomModal" class="btn btn-outline-primary d-none"
                            style="white-space: nowrap; z-index: 1;" title="Select Alternative Formulation">
                        <i class="bi bi-diagram-3"></i> Switch BOM <span id="bomCountBadge" class="badge bg-primary ms-1"></span>
                    </button>
                </div>
            </div>

            <!-- Target quantity with the Clear button nested in its input-group -->
            <div class="col-xl-2 col-md-2">
                <label class="form-label fw-bold">Target Quantity / To Produce</label>
                <div class="input-group">
                    <input type="number" name="target_qty" id="mrpTargetQty" class="form-control"
                           min="0" step="any" placeholder="e.g. 10000"
                           value="<?= $targetQty !== null && $targetQty !== '' ? htmlspecialchars($targetQty) : '' ?>">
                    <button type="button" id="btnClearMrp" class="btn btn-outline-secondary" title="Clear inputs">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Primary actions, aligned to the same baseline as the inputs -->
            <div class="col-xl-3 col-md-3 d-flex gap-2">
                <button type="submit" name="calculate" value="1" id="btnCalculateMrp"
                        class="btn btn-primary text-nowrap flex-shrink-0"
                        <?= empty($fgOptions) ? 'disabled' : '' ?>>
                    <i class="bi bi-calculator me-1"></i>Calculate
                </button>
                <?php if ($showSaveBtn): ?>
                <button type="button" id="saveMrpCalculationBtn" class="btn btn-success w-100"
                        data-lacking="<?= htmlspecialchars(json_encode($mrpLackingRows), ENT_QUOTES) ?>">
                    <i class="bi bi-cart-plus me-1"></i>Save MRP Calculation
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($noFgsForCustomer)): ?>
<div class="card data-card mb-4 no-print">
    <div class="mrp-empty">
        <i class="bi bi-box-seam" style="font-size: 3rem; opacity: 0.3;"></i>
        <h5 class="mt-3">No FG/SFG with active BOM for this customer</h5>
        <p class="text-muted">Create or activate a BOM for this customer's FG/SFG items, or choose another customer.</p>
    </div>
</div>
<?php endif; ?>

<?php if (empty($fgHeader) || empty($mrpSection)): ?>
<?php if (empty($noFgsForCustomer)): ?>
<div class="card data-card">
    <div class="mrp-empty">
        <i class="bi bi-calculator" style="font-size: 3rem; opacity: 0.3;"></i>
        <h5 class="mt-3">Select a Customer, Finished Good / SFG, and enter Target Quantity to calculate material requirements.</h5>
        <p class="text-muted">Choose a customer, pick an FG/SFG with an active BOM, enter the target production quantity, then click Calculate.</p>
    </div>
</div>
<?php endif; ?>

<?php else: ?>

<!-- FG / Target Header -->
<div class="card data-card mb-4">
    <div class="card-header"><i class="bi bi-info-circle me-2"></i>Calculation Basis</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong>FG Code:</strong> <code><?= htmlspecialchars($fgHeader['fg_code']) ?></code></div>
            <div class="col-md-4"><strong>Description:</strong> <?= htmlspecialchars($fgHeader['fg_name']) ?></div>
            <div class="col-md-2"><strong>Target Qty:</strong> <?= formatQty($fgHeader['target_qty']) ?> Pcs</div>
            <div class="col-md-3"><strong>BOM:</strong> <?= htmlspecialchars($fgHeader['bom_code']) ?>
                <?php if (!empty($fgHeader['is_default'])): ?>
                <span class="badge bg-success">Default</span>
                <?php else: ?>
                <span class="badge bg-info text-dark">Switched</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-3"><strong>Fill Volume:</strong> <?= formatQty($fgHeader['fill_volume']) ?> <?= htmlspecialchars($fgHeader['uom']) ?></div>
            <div class="col-md-3"><strong>UOM Divisor:</strong> <?= number_format($fgHeader['batch_unit_divisor'], 0) ?></div>
            <?php if (!empty($fgHeader['is_legacy_formula'])): ?>
            <div class="col-md-3"><span class="badge bg-warning text-dark">Legacy formula flag on BOM</span></div>
            <?php endif; ?>
        </div>
        <div class="mrp-meta mt-2 text-muted">
            RM: (Target &times; Fill Volume &divide; Divisor) &times; (Dosage&thinsp;/&thinsp;100) &times; (1 + Wastage&thinsp;/&thinsp;100)
            &nbsp;|&nbsp;
            PM/SFG: Target &times; Dosage &times; (1 + Wastage&thinsp;/&thinsp;100)
            &nbsp;|&nbsp;
            Lacking = max(0, Required &minus; max(0, SOH &minus; Open Customer PO / Allocated))
        </div>
    </div>
</div>

<?php if (empty($mrpSection['components'])): ?>
<div class="card data-card">
    <div class="mrp-empty">
        <h5>No BOM components found</h5>
        <p class="text-muted">Add components to this finished good's BOM.</p>
    </div>
</div>
<?php else: ?>

<!-- Components Table -->
<div class="card data-card mb-4 mrp-section">
    <div class="card-header">
        <i class="bi bi-list-check me-2"></i>
        <strong>Material Requirements — <?= htmlspecialchars($mrpSection['fg_code'] . ' - ' . $mrpSection['fg_name']) ?></strong>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0 mrp-table">
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
                <?php foreach ($mrpSection['components'] as $row): ?>
                <tr class="<?= $row['lacking_qty'] > 0 ? 'mrp-shortage-row' : '' ?>"
                    data-item-id="<?= (int) $row['component_item_id'] ?>">
                    <td><code><?= htmlspecialchars($row['item_code']) ?></code></td>
                    <td><?= htmlspecialchars($row['item_description']) ?></td>
                    <td><span class="badge bg-light text-dark border mrp-cat-badge"><?= htmlspecialchars($row['category']) ?></span></td>
                    <td class="num"><?= formatQty($row['required_qty']) ?> <?= htmlspecialchars($row['display_uom'] ?? $row['item_uom']) ?></td>
                    <td class="num"><?= formatQty($row['soh']) ?></td>
                    <td class="num">
                        <input type="number"
                               class="form-control form-control-sm input-allocated-override text-end"
                               value="<?= htmlspecialchars((string) (float) $row['allocated']) ?>"
                               data-soh="<?= htmlspecialchars((string) (float) $row['soh']) ?>"
                               data-required="<?= htmlspecialchars((string) (float) $row['required_qty']) ?>"
                               step="any" min="0"
                               aria-label="Open Customer PO / Allocated for <?= htmlspecialchars($row['item_code']) ?>">
                    </td>
                    <td class="num <?= floatval($row['pending_po_rr'] ?? 0) > 0 ? 'text-info fw-semibold' : '' ?>"><?= formatQty($row['pending_po_rr'] ?? 0) ?></td>
                    <td class="num cell-available <?= $row['available'] >= $row['required_qty'] ? 'text-success' : 'text-danger' ?> fw-bold"><?= formatQty($row['available']) ?></td>
                    <td class="num cell-lacking <?= $row['lacking_qty'] > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                        <?= formatQty($row['lacking_qty']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Signature Blocks -->
<div class="card data-card">
    <div class="card-body">
        <div class="mrp-signature">
            <div class="sig-block">
                <div class="sig-line">Prepared By: <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></div>
                <div class="sig-role"><?= htmlspecialchars(ucfirst($_SESSION['department'] ?? '')) ?></div>
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
</div>

<?php endif; ?>
<?php endif; ?>

<!-- Custom MRP Reference prompt (Save MRP Calculation) -->
<div class="modal fade" id="mrpRefModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-tag me-2"></i>MRP Reference</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2 text-muted" id="mrpRefModalSummary">Save lacking material(s) as Draft Requisitions — released to Procurement only after Warehouse Admin AND System Admin approval.</p>
                <label class="form-label fw-bold" for="mrpRefInput">MRP Reference Number (auto-generated):</label>
                <input type="text" class="form-control" id="mrpRefInput" maxlength="30"
                       placeholder="MRP#<?= date('Y') ?>-0001" autocomplete="off" spellcheck="false" readonly>
                <div class="text-muted small mt-1">Format: MRP#YYYY-XXXX — the sequence increments with every saved run.</div>
                <div class="text-danger small mt-1 d-none" id="mrpRefError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="mrpRefConfirmBtn">
                    <i class="bi bi-cart-plus me-1"></i>Save Draft Requisitions
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Select / Switch BOM modal (multi-formulation) -->
<div class="modal fade" id="bomSelectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-diagram-3 me-2"></i>Select BOM — <span id="bomModalItemCode">-</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="bomModalLoading" class="text-center py-3 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="text-muted small mt-2">Loading BOMs…</div>
                </div>
                <div id="bomModalError" class="alert alert-danger d-none"></div>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>BOM Code</th>
                                <th>Description / Supplier Variant</th>
                                <th>Status</th>
                                <th style="width: 210px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bomListRows">
                            <tr><td colspan="4" class="text-center text-muted py-3">Select a finished good first.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div id="bomPreviewPanel" class="d-none">
                    <h6 class="fw-bold text-primary mb-2">
                        <i class="bi bi-eye me-1"></i>Components — <span id="bomPreviewTitle">-</span>
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Item Code</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Phase</th>
                                    <th class="text-end">Dosage Rate</th>
                                    <th class="text-end">Wastage %</th>
                                    <th>UOM</th>
                                    <th class="text-end">SOH</th>
                                </tr>
                            </thead>
                            <tbody id="bomPreviewRows"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var customer = document.getElementById('mrpCustomer');
    var fg = document.getElementById('mrpFg');
    if (customer) {
        customer.addEventListener('change', function () {
            var params = new URLSearchParams(window.location.search);
            params.set('controller', 'warehouse');
            params.set('action', 'mrp');
            if (this.value) {
                params.set('customer_id', this.value);
            } else {
                params.delete('customer_id');
            }
            params.delete('fg_item_id');
            params.delete('target_qty');
            params.delete('calculate');
            params.delete('bom_id');
            window.location.search = params.toString();
        });
    }
    if (fg && typeof window.refreshSearchableDropdown === 'function') {
        window.refreshSearchableDropdown(fg);
    }
    if (customer && typeof window.refreshSearchableDropdown === 'function') {
        window.refreshSearchableDropdown(customer);
    }

    // Save MRP Calculation → preview the next auto-generated MRP reference
    // (MRP#YYYY-XXXX), then build a POST form (cannot nest inside the GET form).
    // The reference shown here is only a preview: the save endpoint regenerates
    // the sequence server-side inside its transaction, so the stored number is
    // always unique. Cancelling or submitting before the fetch lands aborts.
    //
    // Wired on DOMContentLoaded: this view is rendered from $content BEFORE the
    // layout loads public/js/bootstrap.bundle.min.js, so touching `bootstrap`
    // at parse time would throw a ReferenceError and silently skip attaching
    // this handler (same reason the receiving view defers its own modal code).
    document.addEventListener('DOMContentLoaded', function () {
        var saveBtn = document.getElementById('saveMrpCalculationBtn');
        var refModalEl = document.getElementById('mrpRefModal');

        // ─── Editable ALLOCATED — live recalculation of AVAILABLE / LACKING ──
        // Excel-style netting on every keystroke of the ALLOCATED input:
        //   available = max(0, SOH - edited allocated)
        //   lacking   = max(0, Required - available)
        // The Save MRP Calculation button's data-lacking payload is rebuilt from the
        // live cells, so the transfer follows the planner's overrides. Vanilla
        // JS — the project ships no jQuery.
        var fmtQty = function (n) {
            var parts = (Number(n) || 0).toFixed(4).split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return parts.join('.').replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
        };

        var netRow = function (input) {
            var soh = parseFloat(input.dataset.soh) || 0;
            var required = parseFloat(input.dataset.required) || 0;
            var allocated = parseFloat(input.value);
            if (isNaN(allocated) || allocated < 0) allocated = 0;   // empty / invalid → 0
            var available = Math.max(0, soh - allocated);
            return {
                allocated: allocated,
                available: available,
                lacking: Math.max(0, required - available),
                required: required
            };
        };

        var recalcRow = function (tr) {
            var input = tr.querySelector('.input-allocated-override');
            if (!input) return;
            var net = netRow(input);

            var availableCell = tr.querySelector('.cell-available');
            if (availableCell) {
                availableCell.textContent = fmtQty(net.available);
                availableCell.classList.toggle('text-success', net.available >= net.required);
                availableCell.classList.toggle('text-danger', net.available < net.required);
            }
            var lackingCell = tr.querySelector('.cell-lacking');
            if (lackingCell) {
                lackingCell.textContent = fmtQty(net.lacking);
                lackingCell.classList.toggle('text-danger', net.lacking > 0);
                lackingCell.classList.toggle('fw-bold', net.lacking > 0);
                lackingCell.classList.toggle('text-success', net.lacking <= 0);
            }
            tr.classList.toggle('mrp-shortage-row', net.lacking > 0);

            var edited = input.value !== input.defaultValue;
            input.classList.toggle('border-primary', edited);
            input.title = edited ? 'Open Customer PO overridden (original: ' + input.defaultValue + ')' : '';
        };

        var collectMrpRows = function () {
            var rows = [];
            document.querySelectorAll('.mrp-table tbody tr[data-item-id]').forEach(function (tr) {
                var input = tr.querySelector('.input-allocated-override');
                var itemId = parseInt(tr.getAttribute('data-item-id'), 10) || 0;
                if (!input || itemId <= 0) return;
                var net = netRow(input);
                rows.push({ component_item_id: itemId, allocated: net.allocated, lacking_qty: net.lacking });
            });
            return rows;
        };

        var refreshSaveState = function () {
            if (!saveBtn) return;
            var lackingRows = collectMrpRows().filter(function (r) {
                return r.lacking_qty > 0;
            }).map(function (r) {
                return { component_item_id: r.component_item_id, lacking_qty: r.lacking_qty };
            });
            saveBtn.setAttribute('data-lacking', JSON.stringify(lackingRows));
            // Saving is allowed even with zero lackings (pure planning snapshot:
            // the run lands in MRP History, no purchase requests are queued).
        };

        var onAllocatedInput = function (e) {
            var input = e.target && e.target.closest ? e.target.closest('.input-allocated-override') : null;
            if (!input) return;
            var tr = input.closest('tr');
            if (!tr) return;
            recalcRow(tr);
            refreshSaveState();
        };
        document.addEventListener('input', onAllocatedInput);
        document.addEventListener('change', onAllocatedInput);

        if (!saveBtn || !refModalEl) return;
        var refModal = bootstrap.Modal.getOrCreateInstance(refModalEl);
        var refInput = document.getElementById('mrpRefInput');
        var refError = document.getElementById('mrpRefError');
        var refConfirmBtn = document.getElementById('mrpRefConfirmBtn');
        var pendingRows = [];

        var showRefError = function (msg) {
            refError.textContent = msg;
            refError.classList.remove('d-none');
        };
        var clearRefError = function () {
            refError.textContent = '';
            refError.classList.add('d-none');
        };
        // Fetch the next sequence number for display; the value is readonly.
        var loadNextMrpRef = function () {
            refInput.value = '';
            refConfirmBtn.disabled = true;
            fetch('?controller=warehouse&action=getNextMrpRef', { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (!res.success || !res.next_mrp_number) {
                        throw new Error(res.error || 'No number returned.');
                    }
                    refInput.value = res.next_mrp_number;
                    refConfirmBtn.disabled = false;
                })
                .catch(function () {
                    showRefError('Could not generate the next MRP number. Reopen the dialog to retry.');
                });
        };
        // Runs whenever the modal closes (Cancel, Esc, backdrop) — unless the
        // save has already been committed and the page is navigating away.
        var resetRefPrompt = function () {
            pendingRows = [];
            refInput.value = '';
            refConfirmBtn.disabled = false;
            clearRefError();
            // Re-derive the disabled state from the live cells instead of
            // blindly re-enabling: the lacking set may have changed.
            if (!saveBtn.dataset.saving) refreshSaveState();
        };

        saveBtn.addEventListener('click', function () {
            if (saveBtn.dataset.saving) return;   // debounce: ignore further clicks
            var rows = [];
            try { rows = JSON.parse(saveBtn.getAttribute('data-lacking') || '[]'); } catch (e) {}

            pendingRows = rows;
            document.getElementById('mrpRefModalSummary').textContent = rows.length
                ? 'Save ' + rows.length + ' lacking material(s) as Draft Requisitions — they land in MRP History as For Approval and reach Purchasing PO only after WHSE + System Admin approval.'
                : 'Save this MRP calculation to MRP History — no lacking items, so no purchase requests will be created.';
            clearRefError();
            refModal.show();
            loadNextMrpRef();
        });

        refModalEl.addEventListener('shown.bs.modal', function () { refInput.focus(); });

        var commitRefPrompt = function () {
            var ref = (refInput.value || '').trim();
            if (!ref) {
                showRefError('The next MRP number has not loaded yet — wait a moment and try again.');
                refInput.focus();
                return;                       // abort: nothing is saved
            }
            if (ref.length > 30) {
                showRefError('The reference may be at most 30 characters.');
                refInput.focus();
                return;                       // abort
            }

            // Commit: lock the button so a double click cannot submit twice,
            // then close the prompt and submit the form.
            saveBtn.disabled = true;
            saveBtn.dataset.saving = '1';
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
            refModal.hide();

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '?controller=warehouse&action=saveMrpCalculation';
            var fields = {
                customer_id: <?= (int)($selectedCustomer ?? 0) ?>,
                fg_item_id: <?= (int)($selectedFg ?? 0) ?>,
                target_qty: <?= json_encode((string)($targetQty ?? '')) ?>,
                // BOM the on-screen calculation actually used (server-resolved
                // at render: explicit Switch BOM choice or the default).
                bom_id: <?= (int)($selectedBomId ?? 0) ?>,
                calculate: '1',
                lacking_json: JSON.stringify(pendingRows),
                // Every row's live ALLOCATED override + the LACKING QTY
                // recalculated from it, so the server nets with the planner's
                // numbers instead of the raw inventory values.
                rows_json: JSON.stringify(collectMrpRows()),
                custom_mrp_ref: ref
            };
            Object.keys(fields).forEach(function (name) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = fields[name];
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        };

        document.getElementById('mrpRefConfirmBtn').addEventListener('click', commitRefPrompt);
        refInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); commitRefPrompt(); }
        });
        refInput.addEventListener('input', clearRefError);
        refModalEl.addEventListener('hidden.bs.modal', resetRefPrompt);

        // ─── Print (browser print of the on-screen sheet) ────────────────────
        var printBtn = document.getElementById('btnPrintMrpSheet');
        if (printBtn) {
            printBtn.addEventListener('click', function () { window.print(); });
        }
    });
})();
</script>

<script>
// ─── Switch BOM (multi-formulation) ──────────────────────────────────────────
// An FG/SFG may carry several active BOMs. The calculation always has a BOM:
// when #mrpBomId is empty the server resolves the default (is_default = 1,
// customer match first). This block only reveals the switcher when there is
// more than one formulation, and lets the user inspect and pick one.
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var fgSelect = document.getElementById('mrpFg');
        var switchBtn = document.getElementById('btnSelectBomModal');
        var countBadge = document.getElementById('bomCountBadge');
        var bomIdInput = document.getElementById('mrpBomId');
        var modalEl = document.getElementById('bomSelectModal');
        if (!fgSelect || !switchBtn || !bomIdInput || !modalEl) return;

        var modal = null;
        var boms = [];
        var activeFgId = 0;
        var activeFgLabel = '';
        var previewToken = 0;   // guards against a stale eye-button response

        var escapeHtml = function (value) {
            return String(value === null || value === undefined ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        var fmtQty = function (n) {
            return String(parseFloat(Number(n || 0).toFixed(4)));
        };

        var formatDate = function (value) {
            if (!value) return '';
            var d = new Date(String(value).replace(' ', 'T'));
            if (isNaN(d.getTime())) return String(value);
            var mm = String(d.getMonth() + 1);
            var dd = String(d.getDate());
            return (mm.length < 2 ? '0' + mm : mm) + '/' +
                   (dd.length < 2 ? '0' + dd : dd) + '/' + d.getFullYear();
        };

        // ── Dynamic visibility of the Switch BOM button ───────────────────────
        // The button lives inside the FG select's input-group (#fgBomInputGroup),
        // so toggling d-none only changes the select's share of that group —
        // neighbouring grid columns (Target Quantity) never move. The group
        // class keeps the rounded end on the select while the button is hidden.
        var switchGroup = document.getElementById('fgBomInputGroup');

        var hideSwitchButton = function () {
            switchBtn.classList.add('d-none');
            countBadge.textContent = '';
            if (switchGroup) switchGroup.classList.add('bom-switch-hidden');
        };

        var updateBomSwitchButton = function (list) {
            if (!list || list.length <= 1) {
                hideSwitchButton();
                return;
            }
            countBadge.textContent = list.length + ' BOMs';
            switchBtn.classList.remove('d-none');
            if (switchGroup) switchGroup.classList.remove('bom-switch-hidden');
        };

        // The row Calculate would use when no BOM is explicitly chosen:
        // the server orders the list the same way getBomForFg() resolves it.
        var effectiveBomId = function () {
            var chosen = parseInt(bomIdInput.value, 10) || 0;
            if (chosen) return chosen;
            return boms.length ? boms[0].bom_id : 0;
        };

        // Persistent on-page indicator: shows the BOM code Calculate will use,
        // so the active formulation is visible without opening the modal.
        var currentInd = document.getElementById('bomCurrentIndicator');
        var currentCodeEl = document.getElementById('bomCurrentCode');
        var updateCurrentBomLabel = function () {
            if (!currentInd || !currentCodeEl) return;
            var inUse = effectiveBomId();
            var match = null;
            boms.forEach(function (bom) {
                if (parseInt(bom.bom_id, 10) === inUse) match = bom;
            });
            if (match) {
                currentCodeEl.textContent = match.bom_code;
                currentInd.classList.remove('d-none');
            } else {
                currentInd.classList.add('d-none');
            }
        };

        var renderBomList = function () {
            var tbody = document.getElementById('bomListRows');
            if (!tbody) return;
            if (!boms.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">' +
                    'No active BOM found for this finished good.</td></tr>';
                return;
            }
            var inUse = effectiveBomId();
            var html = '';
            boms.forEach(function (bom) {
                var isDefault = parseInt(bom.is_default, 10) === 1;
                var isInUse = parseInt(bom.bom_id, 10) === inUse;
                var status = isDefault
                    ? '<span class="badge bg-success">Default</span>'
                    : '<span class="badge bg-secondary">Alternative</span>';
                if (isInUse) status += ' <span class="badge bg-primary">Active Selection</span>';

                var variant = [];
                if (bom.fg_name) variant.push(bom.fg_name);
                variant.push('Fill volume: ' + fmtQty(bom.fill_volume) + ' ' + (bom.uom || ''));
                if (bom.component_count !== undefined && bom.component_count !== null) {
                    variant.push(bom.component_count + ' component(s)');
                }
                if (bom.created_at) variant.push('Created ' + formatDate(bom.created_at));

                html += '<tr' + (isInUse ? ' class="table-active"' : '') + '>';
                html += '<td><code>' + escapeHtml(bom.bom_code) + '</code></td>';
                html += '<td>' + escapeHtml(variant.join(' · ')) + '</td>';
                html += '<td>' + status + '</td>';
                html += '<td class="text-nowrap">';
                html += '<button type="button" class="btn btn-sm btn-info btn-preview-bom" data-bom="' +
                    escapeHtml(bom.bom_id) + '" title="View components"><i class="bi bi-eye"></i> View</button> ';
                if (isInUse) {
                    html += '<button type="button" class="btn btn-sm btn-outline-secondary" disabled>Currently Selected</button>';
                } else {
                    html += '<button type="button" class="btn btn-sm btn-success btn-use-bom" data-bom="' +
                        escapeHtml(bom.bom_id) + '">Use This BOM</button>';
                }
                html += '</td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
            updateCurrentBomLabel();
        };

        var loadBomList = function () {
            var fgId = parseInt(fgSelect.value, 10) || 0;
            activeFgId = fgId;
            activeFgLabel = '';
            var option = fgSelect.options[fgSelect.selectedIndex];
            if (fgId && option) {
                activeFgLabel = option.textContent.replace(/\s+/g, ' ').trim();
            }
            if (!fgId) {
                boms = [];
                hideSwitchButton();
                renderBomList();
                updateCurrentBomLabel();
                return;
            }

            var customerId = document.getElementById('mrpCustomer');
            var url = '?controller=warehouse&action=getItemBoms&fg_item_id=' + encodeURIComponent(fgId);
            if (customerId && customerId.value) {
                url += '&customer_id=' + encodeURIComponent(customerId.value);
            }

            fetch(url, { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    // FG may have changed while the request was in flight.
                    if (activeFgId !== fgId) return;
                    if (!res.success) {
                        boms = [];
                        hideSwitchButton();
                        updateCurrentBomLabel();
                        return;
                    }
                    boms = res.boms || [];
                    updateBomSwitchButton(boms);
                    updateCurrentBomLabel();
                })
                .catch(function () {
                    boms = [];
                    hideSwitchButton();
                    updateCurrentBomLabel();
                });
        };

        // FG switched → the previous formulation no longer applies; the server
        // falls back to the default BOM of the new item until the user picks one.
        fgSelect.addEventListener('change', function () {
            bomIdInput.value = '';
            hideSwitchButton();
            loadBomList();
        });
        loadBomList();

        // ── Modal open ────────────────────────────────────────────────────────
        switchBtn.addEventListener('click', function () {
            var label = document.getElementById('bomModalItemCode');
            label.textContent = activeFgLabel || '-';
            renderBomList();
            document.getElementById('bomPreviewPanel').classList.add('d-none');
            document.getElementById('bomModalError').classList.add('d-none');
            if (!modal) modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        });

        // ── Eye button: preview the components of one BOM ─────────────────────
        var loadBomPreview = function (bomId, bomCode) {
            var panel = document.getElementById('bomPreviewPanel');
            var rows = document.getElementById('bomPreviewRows');
            var errorBox = document.getElementById('bomModalError');
            var token = ++previewToken;

            errorBox.classList.add('d-none');
            panel.classList.remove('d-none');
            document.getElementById('bomPreviewTitle').textContent = bomCode || '-';
            rows.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">' +
                '<span class="spinner-border spinner-border-sm me-1"></span>Loading components…</td></tr>';

            fetch('?controller=warehouse&action=getBomComponents&bom_id=' + encodeURIComponent(bomId),
                  { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (token !== previewToken) return;   // superseded by a newer click
                    if (!res.success) {
                        throw new Error(res.error || 'Could not load components.');
                    }
                    var components = res.components || [];
                    if (!components.length) {
                        rows.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">' +
                            'This BOM has no components yet.</td></tr>';
                        return;
                    }
                    var html = '';
                    components.forEach(function (c) {
                        html += '<tr>';
                        html += '<td><code>' + escapeHtml(c.component_code) + '</code></td>';
                        html += '<td>' + escapeHtml(c.description) + '</td>';
                        html += '<td><span class="badge bg-light text-dark border">' +
                            escapeHtml(c.category) + '</span></td>';
                        html += '<td>' + escapeHtml(c.phase_code) + '</td>';
                        html += '<td class="text-end">' + fmtQty(c.dosage_rate) + '</td>';
                        html += '<td class="text-end">' + fmtQty(c.wastage_allowance_pct) + '</td>';
                        html += '<td>' + escapeHtml(c.uom) + '</td>';
                        html += '<td class="text-end">' + fmtQty(c.soh) + '</td>';
                        html += '</tr>';
                    });
                    rows.innerHTML = html;
                })
                .catch(function (err) {
                    if (token !== previewToken) return;
                    rows.innerHTML = '';
                    errorBox.textContent = err.message || 'Could not load components.';
                    errorBox.classList.remove('d-none');
                });
        };

        // ── Use This BOM: pin the choice, then recalculate ────────────────────
        var useBom = function (bomId) {
            bomIdInput.value = String(bomId);
            renderBomList();
            if (modal) modal.hide();

            var target = document.getElementById('mrpTargetQty');
            var qty = target ? parseFloat(target.value) : 0;
            if (qty > 0) {
                // Target already entered → re-run the calculation right away so
                // the results reflect the newly chosen formulation.
                var calcBtn = document.querySelector('button[name="calculate"]');
                if (calcBtn) {
                    calcBtn.click();
                    return;
                }
            }
            // Otherwise the choice is kept for the next Calculate click.
        };

        document.getElementById('bomListRows').addEventListener('click', function (e) {
            var previewBtn = e.target.closest ? e.target.closest('.btn-preview-bom') : null;
            if (previewBtn) {
                var previewId = parseInt(previewBtn.getAttribute('data-bom'), 10) || 0;
                var previewCode = '';
                boms.forEach(function (bom) {
                    if (parseInt(bom.bom_id, 10) === previewId) previewCode = bom.bom_code;
                });
                loadBomPreview(previewId, previewCode);
                return;
            }
            var useBtn = e.target.closest ? e.target.closest('.btn-use-bom') : null;
            if (useBtn) {
                var useId = parseInt(useBtn.getAttribute('data-bom'), 10) || 0;
                if (useId) useBom(useId);
            }
        });
    });
})();

// Clear button nested in the Target Quantity input-group: resets the whole
// selection and any on-screen results (same target as the old Clear link).
document.addEventListener('DOMContentLoaded', function () {
    var clearBtn = document.getElementById('btnClearMrp');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            window.location.href = '?controller=warehouse&action=mrp';
        });
    }
});
</script>
