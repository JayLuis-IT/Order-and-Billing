<div class="d-flex justify-content-between mb-4">
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bomModal">Add BOM</button>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importBomModal"><i class="bi bi-upload me-1"></i>Import BOMs</button>
    </div>
    <form method="GET" class="d-flex" style="width:30%">
        <input type="hidden" name="controller" value="admin">
        <input type="hidden" name="action" value="boms">
        <input type="text" name="search" id="searchBom" class="form-control" placeholder="Search BOM code, item..." value="<?= htmlspecialchars($search ?? '') ?>">
    </form>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>BOM Code</th>
                    <th>Finished Good</th>
                    <th>FG Item Code</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($boms as $b): ?>
                <tr>
                    <td><?= $b['id'] ?></td>
                    <td>
                        <strong class="text-primary"><?= htmlspecialchars($b['bom_code'] ?? '-') ?></strong>
                        <?php if (!empty($b['is_legacy_formula'])): ?>
                        <span class="badge bg-warning text-dark" title="This BOM uses legacy lot-based calculations. Edit and re-save to convert.">Legacy</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($b['fg_name']) ?></td>
                    <td><?= htmlspecialchars($b['fg_code']) ?></td>
                    <td><?= date('Y-m-d', strtotime($b['created_at'])) ?></td>
                    <td>
                        <a href="?controller=admin&action=bomEdit&id=<?= $b['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
                        <a href="?controller=admin&action=bomDelete&id=<?= $b['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Delete this BOM and all its line items?"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($boms)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No BOMs found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (($totalPages ?? 1) > 1): ?>
<?php $pages = \App\Helpers\Pagination::getPageRange($page, $totalPages); ?>
<nav>
    <ul class="pagination justify-content-center mt-4">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=admin&action=boms&page=<?= $page - 1 ?>&search=<?= urlencode($search ?? '') ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="?controller=admin&action=boms&page=<?= $p ?>&search=<?= urlencode($search ?? '') ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=admin&action=boms&page=<?= $page + 1 ?>&search=<?= urlencode($search ?? '') ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<!-- Create BOM Modal -->
<div class="modal fade" id="bomModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Create New BOM</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="?controller=admin&action=bomCreate">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Finished Good / SFG Item *</label>
                        <select name="fg_item_id" id="adminFgItemSelect" class="form-select filter-select" required>
                            <option value="">-- Select Finished Good / SFG --</option>
                            <?php
                            // Multi-BOM: items with an existing BOM stay selectable — they are
                            // badged as an alternative formulation instead of disabled.
                            $bomCounts = $itemBomCounts ?? [];
                            foreach ($allItems as $item):
                                $bomCount = (int) ($bomCounts[$item['item_id']] ?? 0);
                                $alreadyHasBom = $bomCount > 0;
                            ?>
                            <option value="<?= $item['item_id'] ?>"
                                    data-has-bom="<?= $alreadyHasBom ? '1' : '0' ?>"
                                    data-bom-count="<?= $bomCount ?>"
                                    <?php // Amber highlight so existing-BOM items stand out in the native list
                                    ?>style="<?= $alreadyHasBom
                                        ? 'background-color:#fef3c7; color:#92400e; font-weight:bold;'
                                        : 'color:inherit;' ?>">
                                <?= htmlspecialchars($item['item_code'] . ' - ' . $item['item_description']) ?><?= $alreadyHasBom ? ' ⚠ (Existing BOM — Alternative Version)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Items tagged "Existing BOM" are saved as an Alternative formulation.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">BOM Code *</label>
                        <input type="text" name="bom_code" id="bomCodeInput" class="form-control" placeholder="e.g. BOM-001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer *</label>
                        <select name="customer_id" id="customerSelect" class="form-select filter-select" required>
                            <option value="">-- Select Customer --</option>
                            <?php foreach (($customers ?? []) as $cust): ?>
                            <option value="<?= (int)$cust['customer_id'] ?>"><?= htmlspecialchars(trim(($cust['customer_code'] ?? '') . ' - ' . ($cust['customer_name'] ?? ''))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Used to filter FG/SFG and demand on the MRP sheet.</small>
                    </div>
                    <div class="row g-3 mb-2">
                        <div class="col-md-4">
                            <label class="form-label">Fill Volume *</label>
                            <input type="number" step="0.0001" min="0.0001" name="fill_volume" id="fillVolumeInput" class="form-control" value="0" required>
                            <small class="text-muted">Net volume/weight per piece.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">UOM *</label>
                            <select name="uom" id="uomSelect" class="form-select filter-select" required>
                                <?php
                                $uoms = ['Kg','g','mg','L','mL','PCS','Set','Box','Pack','Roll','Meter','cm','mm','ft','inch','yd','Pairs','Pcs/Case','Pallet','Sheet','Bag','Drum','Barrel','Carton','Lot','Unit'];
                                foreach ($uoms as $u): ?>
                                    <option value="<?= $u ?>" <?= $u === 'mL' ? 'selected' : '' ?>><?= $u ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">UOM Divisor *</label>
                            <input type="number" step="any" min="0.0001" name="batch_unit_divisor" id="uomDivisorInput" class="form-control" value="1000" required>
                            <small class="text-muted">(Order Qty &times; Fill Volume) / UOM Divisor.</small>
                        </div>
                    </div>
                    <div id="bomAlternativeNotice" style="display:none">
                        <div class="alert alert-info py-2 mb-0">
                            <i class="bi bi-info-circle me-1"></i>This item already has an active BOM. This entry will be saved as an Alternative Formulation.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Create & Edit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import BOMs Modal -->
<div class="modal fade" id="importBomModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Import BOM Components</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="?controller=admin&action=bomImportPreview" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select File (.csv or .xlsx)</label>
                        <input type="file" name="import_file" class="form-control" accept=".csv,.xlsx" required>
                    </div>
                    <div class="alert alert-info py-2 mb-0">
                        <strong>Expected columns:</strong> FG_ITEM_CODE, BOM_CODE, RM_CODE, DOSAGE_RATE, WASTAGE_PCT<br>
                        <small>Multiple rows with same FG_ITEM_CODE = one BOM with multiple components. Importing replaces the entire BOM component list.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success"><i class="bi bi-upload me-1"></i>Upload & Preview</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var _searchTimer;
document.getElementById('searchBom').addEventListener('input', function() {
    clearTimeout(_searchTimer);
    var form = this.closest('form');
    _searchTimer = setTimeout(function() { form.submit(); }, 500);
});

// ── Multi-BOM: alternative-formulation mode in the Create New BOM modal ──────
// Items that already have an active BOM stay selectable; picking one reveals
// the informational banner and pre-fills the header from that item's primary
// BOM. Formulations are told apart by their BOM code.
function updateBomVariantUi() {
    var sel = document.getElementById('adminFgItemSelect');
    if (!sel) return;
    var opt = sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex] : null;
    var hasBom = !!opt && opt.getAttribute('data-has-bom') === '1';
    var notice = document.getElementById('bomAlternativeNotice');
    if (notice) notice.style.display = hasBom ? '' : 'none';
}

// ── Pre-fill header from an existing formulation ─────────────────────────────
var bomPrefill = { requestId: 0, applied: false, lastItemId: '' };

// Keep app.js's searchable button label in sync when a <select> is set in code.
function setSelectValue(sel, value) {
    if (!sel) return;
    sel.value = (value === null || value === undefined) ? '' : String(value);
    if (typeof refreshSearchableDropdown === 'function') refreshSearchableDropdown(sel);
}

// Make sure a value exists as an option (BOM data can carry a UOM that is not
// in the fixed list), then select it.
function ensureOptionValue(sel, value, label) {
    if (!sel || value === null || value === undefined || value === '') return;
    var str = String(value);
    var exists = false;
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === str) { exists = true; break; }
    }
    if (!exists) {
        var opt = document.createElement('option');
        opt.value = str;
        opt.textContent = label || str;
        sel.appendChild(opt);
    }
    sel.value = str;
    if (typeof refreshSearchableDropdown === 'function') refreshSearchableDropdown(sel);
}

function resetBomFormToBlank() {
    var code = document.getElementById('bomCodeInput');
    if (code) code.value = '';
    setSelectValue(document.getElementById('customerSelect'), '');
    var fill = document.getElementById('fillVolumeInput');
    if (fill) fill.value = '0';
    // Default UOM of this form is mL (see the option marked selected above).
    setSelectValue(document.getElementById('uomSelect'), 'mL');
    var div = document.getElementById('uomDivisorInput');
    if (div) div.value = '1000';
    bomPrefill.applied = false;
}

function applyExistingBomPrefill(fgItemId) {
    var requestId = ++bomPrefill.requestId;
    var url = '?controller=admin&action=getPrimaryBomDetails&fg_item_id=' + encodeURIComponent(fgItemId);
    fetch(url, { credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (requestId !== bomPrefill.requestId) return;   // selection moved on
            if (!res.success) { resetBomFormToBlank(); return; }

            var h = res.header || {};
            setSelectValue(document.getElementById('customerSelect'), h.customer_id);
            var fill = document.getElementById('fillVolumeInput');
            if (fill) fill.value = h.fill_volume;
            ensureOptionValue(document.getElementById('uomSelect'), h.uom, h.uom);
            var div = document.getElementById('uomDivisorInput');
            if (div) div.value = h.batch_unit_divisor;
            // Non-conflicting next code: existing code + _ALT / _ALT2 …
            var code = document.getElementById('bomCodeInput');
            if (code) code.value = res.suggested_bom_code || '';
            bomPrefill.applied = true;
        })
        .catch(function () {
            // Network failure: leave whatever the user already typed alone.
        });
}

(function () {
    var sel = document.getElementById('adminFgItemSelect');
    // Fires for the native select and for app.js's searchable panel alike
    // (the panel dispatches a native change on the underlying select).
    if (sel) {
        sel.addEventListener('change', function () {
            updateBomVariantUi();
            var opt = sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex] : null;
            var itemId = opt ? opt.value : '';
            var hasBom = !!opt && opt.getAttribute('data-has-bom') === '1';
            if (!itemId || itemId === bomPrefill.lastItemId) return;
            bomPrefill.lastItemId = itemId;
            if (hasBom) {
                applyExistingBomPrefill(itemId);
            } else if (bomPrefill.applied) {
                // Only undo our own pre-fill — never wipe what was typed by hand.
                resetBomFormToBlank();
            }
        });
    }
    var modal = document.getElementById('bomModal');
    if (modal) {
        // The layout resets the form when a modal closes, so re-evaluate on
        // reopen and forget the previous pre-fill.
        modal.addEventListener('shown.bs.modal', updateBomVariantUi);
        modal.addEventListener('hidden.bs.modal', function () {
            bomPrefill.lastItemId = '';
            bomPrefill.applied = false;
        });
    }
    updateBomVariantUi();
})();
</script>
