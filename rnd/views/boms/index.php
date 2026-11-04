<div class="d-flex justify-content-between mb-4">
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bomModal">Add BOM</button>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importBomModal"><i class="bi bi-upload me-1"></i>Import BOMs</button>
    </div>
    <form method="GET" class="d-flex" style="width:30%">
        <input type="hidden" name="controller" value="rnd">
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
                    <th>Fill Volume</th>
                    <th>UoM</th>
                    <th>UOM Divisor</th>
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
                    <td><?= formatQty($b['fill_volume'] ?? $b['batch_qty'] ?? 1) ?></td>
                    <td><?= htmlspecialchars($b['uom'] ?? $b['batch_uom'] ?? 'PCS') ?></td>
                    <td><?= formatQty($b['batch_unit_divisor'] ?? 1000) ?></td>
                    <td><?= date('Y-m-d', strtotime($b['created_at'])) ?></td>
                    <td>
                        <a href="?controller=rnd&action=bomEdit&id=<?= $b['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
                        <a href="?controller=rnd&action=bomDelete&id=<?= $b['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Delete this BOM and all its line items?"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($boms)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No BOMs found</td></tr>
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
            <a class="page-link" href="?controller=rnd&action=boms&page=<?= $page - 1 ?>&search=<?= urlencode($search ?? '') ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="?controller=rnd&action=boms&page=<?= $p ?>&search=<?= urlencode($search ?? '') ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=rnd&action=boms&page=<?= $page + 1 ?>&search=<?= urlencode($search ?? '') ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<!-- Create BOM Modal -->
<div class="modal fade" id="bomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Create New BOM</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="?controller=rnd&action=bomCreate" id="bomCreateForm">
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Finished Good / SFG Item *</label>
                            <select name="fg_item_id" id="rndFgItemSelect" class="form-select filter-select" required>
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
                        <div class="col-md-4">
                            <label class="form-label">BOM Code *</label>
                            <input type="text" name="bom_code" id="bomCodeInput" class="form-control" placeholder="e.g. BOM-001" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Customer *</label>
                            <select name="customer_id" id="customerSelect" class="form-select filter-select" required>
                                <option value="">-- Select Customer --</option>
                                <?php foreach (($customers ?? []) as $cust): ?>
                                <option value="<?= (int)$cust['customer_id'] ?>"><?= htmlspecialchars(trim(($cust['customer_code'] ?? '') . ' - ' . ($cust['customer_name'] ?? ''))) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Used to filter FG/SFG and demand on the MRP sheet.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fill Volume *</label>
                            <input type="number" step="0.0001" min="0.0001" name="fill_volume" id="fillVolumeInput" class="form-control" value="0" required>
                            <small class="text-muted">Net volume/weight per piece (e.g. 300 for a 300mL bottle).</small>
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
                            <small class="text-muted">UOM of the fill volume.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">UOM Divisor *</label>
                            <input type="number" step="any" min="0.0001" name="batch_unit_divisor" id="uomDivisorInput" class="form-control" value="1000" required>
                            <small class="text-muted">Unit scaling factor used in the MRP/LMR material requirement calculation formula: (Order Qty &times; Fill Volume) / UOM Divisor. For example, a divisor of 1000 converts mL fill volume into bulk batch Kg or L.</small>
                        </div>
                        <div class="col-12" id="bomAlternativeNotice" style="display:none">
                            <div class="alert alert-info py-2 mb-0">
                                <i class="bi bi-info-circle me-1"></i>This item already has an active BOM. This entry will be saved as an Alternative Formulation.
                            </div>
                        </div>
                    </div>

                    <label class="form-label fw-bold">Components / Ingredients</label>
                    <small class="d-block text-muted mb-2">RM dosage = % of bulk batch &middot; PM/SFG dosage = qty per unit &middot; Phase codes group ingredients into LMR steps.</small>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="componentsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:14%">Phase / Comp Code *</th>
                                    <th style="width:32%">Item (RM/PM/SFG) *</th>
                                    <th style="width:16%">Dosage / Qty *</th>
                                    <th style="width:13%">Wastage %</th>
                                    <th style="width:14%">UoM</th>
                                    <th style="width:11%"></th>
                                </tr>
                            </thead>
                            <tbody id="componentsBody">
                                <tr class="component-row">
                                    <td>
                                        <input type="text" list="phaseCodeList" name="component_phase_code[]" class="form-control form-control-sm" maxlength="20" required placeholder="e.g. 101" autocomplete="off">
                                    </td>
                                    <td>
                                        <select name="component_item_id[]" class="form-select form-select-sm component-item-select filter-select" onchange="onComponentItemSelect(this)" required>
                                            <option value="">-- Select Ingredient --</option>
                                            <?php foreach ($allIngredients as $ing): ?>
                                            <option value="<?= $ing['item_id'] ?>" data-uom="<?= htmlspecialchars($ing['item_uom']) ?>" data-type="<?= htmlspecialchars($ing['item_type'] ?? '') ?>"><?= htmlspecialchars($ing['item_code'] . ' - ' . $ing['item_description']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.000001" name="component_dosage[]" class="form-control form-control-sm dosage-input" min="0.000001" required placeholder="Qty per unit"></td>
                                    <td><input type="number" step="0.01" name="component_wastage[]" class="form-control form-control-sm" min="0" max="100" value="0" placeholder="%"></td>
                                    <td class="component-uom-cell">
                                        <select name="component_uom[]" class="form-select form-select-sm component-uom-select" aria-label="Component UOM">
                                            <option value="">Auto (item UOM)</option>
                                            <?php foreach (['Kg', 'g', 'mL', 'L', 'Pc', 'Pcs', 'Box', 'Roll', 'Set'] as $cu): ?>
                                            <option value="<?= $cu ?>"><?= $cu ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addComponentBtn"><i class="bi bi-plus-lg me-1"></i>Add Ingredient</button>

                    <datalist id="phaseCodeList">
                        <option value="101">RM Phase 101</option>
                        <option value="102">RM Phase 102</option>
                        <option value="103">RM Phase 103</option>
                        <option value="104">RM Phase 104</option>
                        <option value="105">RM Phase 105</option>
                        <option value="106">RM Phase 106</option>
                        <option value="301">PM Phase 301</option>
                        <option value="302">PM Phase 302</option>
                        <option value="303">PM Phase 303</option>
                        <option value="304">PM Phase 304</option>
                        <option value="305">PM Phase 305</option>
                        <option value="306">PM Phase 306</option>
                    </datalist>

                    <div class="alert alert-warning py-2 mb-0 mt-3" id="noComponentsWarning" style="display:none">
                        <i class="bi bi-exclamation-triangle me-1"></i>Please add at least one component.
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create BOM</button>
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
            <form method="POST" action="?controller=rnd&action=bomImportPreview" enctype="multipart/form-data">
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
// the informational banner and pre-fills the form from that item's primary BOM.
// Formulations are told apart by their BOM code.
function updateBomVariantUi() {
    var sel = document.getElementById('rndFgItemSelect');
    if (!sel) return;
    var opt = sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex] : null;
    var hasBom = !!opt && opt.getAttribute('data-has-bom') === '1';
    var notice = document.getElementById('bomAlternativeNotice');
    if (notice) notice.style.display = hasBom ? '' : 'none';
}

// ── Pre-fill from an existing formulation ────────────────────────────────────
var bomPrefill = { requestId: 0, applied: false, lastItemId: '' };

// Keep app.js's searchable button label in sync when a <select> is set in code.
function setSelectValue(sel, value) {
    if (!sel) return;
    sel.value = (value === null || value === undefined) ? '' : String(value);
    if (typeof refreshSearchableDropdown === 'function') refreshSearchableDropdown(sel);
}

// Make sure a value exists as an option (BOM data can carry a UOM that is not
// in the fixed list), then select it.
function ensureOptionValue(sel, value, label, dataUom, dataType) {
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
        if (dataUom) opt.setAttribute('data-uom', dataUom);
        if (dataType) opt.setAttribute('data-type', dataType);
        sel.appendChild(opt);
    }
    sel.value = str;
    if (typeof refreshSearchableDropdown === 'function') refreshSearchableDropdown(sel);
}

function emptyComponentRow(row) {
    row.querySelectorAll('select, input').forEach(function (el) {
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else if (el.name && el.name.indexOf('wastage') !== -1) el.value = '0';
        else el.value = '';
    });
    var dosage = row.querySelector('.dosage-input');
    if (dosage) dosage.placeholder = 'Qty per unit';
}

function fillComponentRow(row, comp) {
    var itemSel = row.querySelector('select[name="component_item_id[]"]');
    // Select the ingredient first: it syncs this row's UOM select and the
    // dosage placeholder from the option's data-uom / data-type.
    ensureOptionValue(itemSel,
        comp.item_id,
        comp.item_code + (comp.item_name ? ' - ' + comp.item_name : ''),
        comp.uom || '',
        comp.item_type || '');
    if (itemSel && itemSel.value) onComponentItemSelect(itemSel);

    var phase = row.querySelector('input[name="component_phase_code[]"]');
    if (phase) phase.value = comp.phase_code || '';
    var dosage = row.querySelector('input[name="component_dosage[]"]');
    if (dosage) dosage.value = comp.dosage_rate;
    var wastage = row.querySelector('input[name="component_wastage[]"]');
    if (wastage) wastage.value = comp.wastage_pct;

    // BOM line UOM: an explicit value wins, otherwise back to "Auto (item UOM)".
    var uomSel = row.querySelector('select[name="component_uom[]"]');
    if (uomSel) {
        if (comp.uom) {
            ensureComponentUomOption(uomSel, comp.uom);
            uomSel.value = comp.uom;
        } else {
            uomSel.selectedIndex = 0;
        }
    }

    // cloneNode() does not carry the searchable dropdown's listeners: re-init
    // this row's ingredient select so it stays clickable after pre-filling.
    if (itemSel && typeof refreshSearchableDropdown === 'function') {
        refreshSearchableDropdown(itemSel);
    }
}

// Rebuild the ingredient table from scratch.
// CRITICAL: flush every existing node first — the previous contents (default
// blank row, user rows, an earlier pre-fill) must never survive, so no empty
// row is left at the top or bottom of the list. Pre-filled BOMs render filled
// rows only; a fresh/empty BOM gets exactly one blank row.
function rebuildComponentsTable(components) {
    var tbody = document.getElementById('componentsBody');
    if (!tbody) return;
    var template = tbody.querySelector('.component-row');
    if (!template) return;
    emptyComponentRow(template);

    // 1. Flush the container completely (equivalent of .empty()).
    while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

    // 2. Fresh BOM: exactly one blank row for manual entry.
    if (!components || !components.length) {
        tbody.appendChild(template);
        var blankSelect = template.querySelector('.component-item-select');
        if (blankSelect && typeof refreshSearchableDropdown === 'function') {
            refreshSearchableDropdown(blankSelect);
        }
        return;
    }

    // 3. Pre-filled BOM: rendered rows only — never an extra empty one.
    components.forEach(function (comp) {
        var row = template.cloneNode(true);
        tbody.appendChild(row);
        fillComponentRow(row, comp);
    });
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
    rebuildComponentsTable([]);
    bomPrefill.applied = false;
}

function applyExistingBomPrefill(fgItemId) {
    var requestId = ++bomPrefill.requestId;
    var url = '?controller=rnd&action=getPrimaryBomDetails&fg_item_id=' + encodeURIComponent(fgItemId);
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
            rebuildComponentsTable(res.components || []);
            bomPrefill.applied = true;
        })
        .catch(function () {
            // Network failure: leave whatever the user already typed alone.
        });
}

(function () {
    var sel = document.getElementById('rndFgItemSelect');
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
        // reopen and forget the previous pre-fill (rows included).
        modal.addEventListener('shown.bs.modal', updateBomVariantUi);
        modal.addEventListener('hidden.bs.modal', function () {
            bomPrefill.lastItemId = '';
            bomPrefill.applied = false;
            rebuildComponentsTable([]);
        });
    }
    updateBomVariantUi();
})();

function ensureComponentUomOption(selectEl, uom) {
    if (!uom) return;
    for (var i = 0; i < selectEl.options.length; i++) {
        if (selectEl.options[i].value === uom) return;
    }
    var opt = document.createElement('option');
    opt.value = uom;
    opt.textContent = uom;
    selectEl.appendChild(opt);
}

function onComponentItemSelect(sel) {
    var opt = sel.options[sel.selectedIndex];
    var uom = opt.getAttribute('data-uom') || '';
    var type = opt.getAttribute('data-type') || '';
    var uomSelect = sel.closest('tr').querySelector('.component-uom-select');
    if (uomSelect) {
        ensureComponentUomOption(uomSelect, uom);
        uomSelect.value = uom;
    }
    var dosageInput = sel.closest('tr').querySelector('.dosage-input');
    if (dosageInput) {
        dosageInput.placeholder = (type === 'RM') ? '% of bulk batch' : 'Qty per unit';
    }
}

document.getElementById('addComponentBtn').addEventListener('click', function() {
    var tbody = document.getElementById('componentsBody');
    var firstRow = tbody.querySelector('.component-row');
    var newRow = firstRow.cloneNode(true);
    newRow.querySelectorAll('select, input').forEach(function(el) {
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else if (el.name && el.name.indexOf('wastage') !== -1) el.value = '0';
        else if (el.name && el.name.indexOf('phase') !== -1) el.value = '';
        else el.value = '';
    });
    var dosageInput = newRow.querySelector('.dosage-input');
    if (dosageInput) dosageInput.placeholder = 'Qty per unit';
    tbody.appendChild(newRow);
    var newCompSelect = newRow.querySelector('.component-item-select');
    if (newCompSelect) refreshSearchableDropdown(newCompSelect);
});

document.getElementById('componentsBody').addEventListener('click', function(e) {
    var btn = e.target.closest('.remove-row');
    if (!btn) return;
    var tbody = document.getElementById('componentsBody');
    if (tbody.querySelectorAll('.component-row').length > 1) {
        btn.closest('tr').remove();
    }
});

document.getElementById('bomCreateForm').addEventListener('submit', function(e) {
    var rows = document.querySelectorAll('#componentsBody .component-row');
    var valid = false;
    rows.forEach(function(row) {
        if (row.querySelector('select').value) valid = true;
    });
    if (!valid) {
        e.preventDefault();
        document.getElementById('noComponentsWarning').style.display = 'block';
    }
});
</script>
