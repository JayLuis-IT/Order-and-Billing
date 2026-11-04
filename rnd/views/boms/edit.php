<?php
if (!function_exists('formatBomValue')) {
    function formatBomValue($val) {
        return rtrim(rtrim(sprintf('%.4f', $val), '0'), '.');
    }
}
?>
<div class="d-flex justify-content-between mb-4">
    <div class="d-flex gap-2">
        <a href="?controller=rnd&action=boms" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to BOMs</a>
    </div>
</div>

<?php if (!empty($bom['is_legacy_formula'])): ?>
<div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <div>
        <strong>&#9888;&#65039; This BOM uses legacy lot-based calculations.</strong>
        Please edit and re-save with percentage dosages and fill volume.
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- BOM Header Card -->
    <div class="col-md-5">
        <div class="card data-card mb-4">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>BOM Details</div>
            <div class="card-body">
                <form method="POST" action="?controller=rnd&action=bomUpdate">
                    <input type="hidden" name="bom_id" value="<?= $bom['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label">Finished Good / SFG</label>
                        <select name="fg_item_id" class="form-select filter-select" required>
                            <?php foreach ($allItems as $item): ?>
                            <option value="<?= $item['item_id'] ?>" <?= $item['item_id'] == $bom['fg_item_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($item['item_code'] . ' - ' . $item['item_description']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">BOM Code *</label>
                        <input type="text" name="bom_code" class="form-control" value="<?= htmlspecialchars($bom['bom_code'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer *</label>
                        <select name="customer_id" class="form-select filter-select" required>
                            <option value="">-- Select Customer --</option>
                            <?php foreach (($customers ?? []) as $cust): ?>
                            <option value="<?= (int)$cust['customer_id'] ?>" <?= (int)($bom['customer_id'] ?? 0) === (int)$cust['customer_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(trim(($cust['customer_code'] ?? '') . ' - ' . ($cust['customer_name'] ?? ''))) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">MRP filters FG/SFG by this assignment.</small>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Fill Volume *</label>
                            <input type="number" step="0.0001" min="0.0001" name="fill_volume" class="form-control" value="<?= formatBomValue($bom['fill_volume'] ?? $bom['batch_qty'] ?? 0) ?>" required>
                            <small class="text-muted">Net volume/weight per piece.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">UOM *</label>
                            <select name="uom" class="form-select filter-select" required>
                                <?php
                                $currentUom = $bom['uom'] ?? $bom['batch_uom'] ?? 'PCS';
                                $uoms = ['Kg','g','mg','L','mL','PCS','Set','Box','Pack','Roll','Meter','cm','mm','ft','inch','yd','Pairs','Pcs/Case','Pallet','Sheet','Bag','Drum','Barrel','Carton','Lot','Unit'];
                                foreach ($uoms as $u): ?>
                                    <option value="<?= $u ?>" <?= $u === $currentUom ? 'selected' : '' ?>><?= $u ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">UOM Divisor *</label>
                            <input type="number" step="any" min="0.0001" name="batch_unit_divisor" class="form-control" value="<?= formatBomValue($bom['batch_unit_divisor'] ?? 1000) ?>" required>
                            <small class="text-muted">(Order Qty &times; Fill Volume) / UOM Divisor.</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Created</label>
                        <input type="text" class="form-control" value="<?= date('Y-m-d H:i', strtotime($bom['created_at'])) ?>" disabled>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Update BOM</button>
                </form>
            </div>
        </div>
    </div>

    <!-- BOM Line Items Card -->
    <div class="col-md-7">
        <div class="card data-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul me-2"></i>BOM Components (<?= count($bomItems) ?>)</span>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal"><i class="bi bi-plus-lg me-1"></i>Add Component</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Phase</th>
                            <th>Ingredient</th>
                            <th>Code</th>
                            <th>UOM</th>
                            <th>Dosage Rate</th>
                            <th>Wastage %</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bomItemsBody">
                        <?php foreach ($bomItems as $bi): ?>
                        <tr data-item-id="<?= $bi['id'] ?>">
                            <td><code><?= htmlspecialchars($bi['phase_code'] ?? '101') ?></code></td>
                            <td><?= htmlspecialchars($bi['rm_name']) ?></td>
                            <td><code><?= htmlspecialchars($bi['rm_code']) ?></code></td>
                            <td><?= htmlspecialchars(($bi['uom'] ?? '') !== '' ? $bi['uom'] : $bi['rm_uom']) ?></td>
                            <td><?= formatBomValue($bi['dosage_rate']) ?><?= ($bi['item_type'] ?? '') === 'RM' ? '%' : '' ?></td>
                            <td><?= formatBomValue($bi['wastage_allowance_pct']) ?>%</td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-bom-item"
                                    data-bom-item-id="<?= $bi['id'] ?>"
                                    data-item-id="<?= $bi['item_id'] ?>"
                                    data-dosage="<?= $bi['dosage_rate'] ?>"
                                    data-wastage="<?= $bi['wastage_allowance_pct'] ?>"
                                    data-phase="<?= htmlspecialchars($bi['phase_code'] ?? '101') ?>"
                                    data-uom="<?= htmlspecialchars(($bi['uom'] ?? '') !== '' ? $bi['uom'] : $bi['rm_uom']) ?>"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-danger remove-bom-item" data-item-id="<?= $bi['id'] ?>"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($bomItems)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No components added yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Component Modal -->
<div class="modal fade" id="addItemModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Component</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="add_bom_id" value="<?= $bom['id'] ?>">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Phase / Comp Code *</label>
                        <input type="text" list="phaseCodeList" id="add_phase_code" class="form-control" maxlength="20" required placeholder="e.g. 101" autocomplete="off">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Ingredient *</label>
                        <select id="add_item_id" class="form-select filter-select" required>
                            <option value="">-- Select Ingredient --</option>
                            <?php foreach ($allIngredients as $ing): ?>
                            <option value="<?= $ing['item_id'] ?>" data-uom="<?= htmlspecialchars($ing['item_uom']) ?>"><?= htmlspecialchars($ing['item_code'] . ' - ' . $ing['item_description'] . ' (' . $ing['item_uom'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Dosage / Qty *</label>
                        <input type="number" step="0.000001" id="add_dosage" class="form-control" value="0" required>
                        <small class="text-muted">RM: % of bulk batch &middot; PM/SFG: qty per unit</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Wastage Allowance %</label>
                        <input type="number" step="0.01" id="add_wastage" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">UOM</label>
                        <select id="add_uom" class="form-select">
                            <option value="">Select UOM</option>
                            <?php foreach (['Kg', 'g', 'mL', 'L', 'Pc', 'Pcs', 'Box', 'Roll', 'Set'] as $u): ?>
                            <option value="<?= $u ?>"><?= $u ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Defaults to the ingredient's UOM</small>
                    </div>
                </div>
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
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="saveAddItem">Add</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Component Modal -->
<div class="modal fade" id="editItemModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Edit Component</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="edit_bom_item_id">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Phase / Comp Code *</label>
                        <input type="text" list="phaseCodeList" id="edit_phase_code" class="form-control" maxlength="20" required placeholder="e.g. 101" autocomplete="off">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Ingredient *</label>
                        <select id="edit_item_id" class="form-select filter-select" required>
                            <option value="">-- Select Ingredient --</option>
                            <?php foreach ($allIngredients as $ing): ?>
                            <option value="<?= $ing['item_id'] ?>" data-uom="<?= htmlspecialchars($ing['item_uom']) ?>"><?= htmlspecialchars($ing['item_code'] . ' - ' . $ing['item_description'] . ' (' . $ing['item_uom'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Dosage / Qty *</label>
                        <input type="number" step="0.000001" id="edit_dosage" class="form-control" required>
                        <small class="text-muted">RM: % of bulk batch &middot; PM/SFG: qty per unit</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Wastage Allowance %</label>
                        <input type="number" step="0.01" id="edit_wastage" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">UOM</label>
                        <select id="edit_uom" class="form-select">
                            <option value="">Select UOM</option>
                            <?php foreach (['Kg', 'g', 'mL', 'L', 'Pc', 'Pcs', 'Box', 'Roll', 'Set'] as $u): ?>
                            <option value="<?= $u ?>"><?= $u ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Defaults to the ingredient's UOM</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="saveEditItem">Update</button>
            </div>
        </div>
    </div>
</div>

<script>
function ensureUomOption(selectEl, uom) {
    if (!uom) return;
    for (var i = 0; i < selectEl.options.length; i++) {
        if (selectEl.options[i].value === uom) return;
    }
    var opt = document.createElement('option');
    opt.value = uom;
    opt.textContent = uom;
    selectEl.appendChild(opt);
}

function ingredientUom(ingredientSelect) {
    var opt = ingredientSelect.options[ingredientSelect.selectedIndex];
    return opt ? (opt.getAttribute('data-uom') || '') : '';
}

function autoFillUom(ingredientId, uomId) {
    var ing = document.getElementById(ingredientId);
    var uom = document.getElementById(uomId);
    if (!ing || !uom) return;
    ing.addEventListener('change', function() {
        var value = ingredientUom(ing);
        if (!value) return;
        ensureUomOption(uom, value);
        uom.value = value;
    });
}

autoFillUom('add_item_id', 'add_uom');
autoFillUom('edit_item_id', 'edit_uom');

document.getElementById('saveAddItem').addEventListener('click', function() {
    var bomId = document.getElementById('add_bom_id').value;
    var itemId = document.getElementById('add_item_id').value;
    var dosage = document.getElementById('add_dosage').value;
    var wastage = document.getElementById('add_wastage').value;
    var phaseCode = document.getElementById('add_phase_code').value.trim();
    var uom = document.getElementById('add_uom').value.trim();

    if (!itemId) { Dlg.alert('Select an ingredient'); return; }
    if (!phaseCode) { Dlg.alert('Phase / Comp Code is required'); return; }

    var formData = new FormData();
    formData.append('bom_id', bomId);
    formData.append('item_id', itemId);
    formData.append('dosage_rate', dosage);
    formData.append('wastage_allowance_pct', wastage);
    formData.append('phase_code', phaseCode);
    formData.append('uom', uom);

    fetch('?controller=rnd&action=bomAddItem', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                Dlg.alert(data.error || 'Failed to add component');
            }
        })
        .catch(function() { Dlg.alert('Network error'); });
});

document.querySelectorAll('.edit-bom-item').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_bom_item_id').value = this.getAttribute('data-bom-item-id');
        document.getElementById('edit_item_id').value = this.getAttribute('data-item-id');
        document.getElementById('edit_dosage').value = this.getAttribute('data-dosage');
        document.getElementById('edit_wastage').value = this.getAttribute('data-wastage');
        document.getElementById('edit_phase_code').value = this.getAttribute('data-phase') || '';
        var editUom = this.getAttribute('data-uom') || '';
        var editUomSelect = document.getElementById('edit_uom');
        ensureUomOption(editUomSelect, editUom);
        editUomSelect.value = editUom;
        new bootstrap.Modal(document.getElementById('editItemModal')).show();
    });
});

document.getElementById('saveEditItem').addEventListener('click', function() {
    var bomItemId = document.getElementById('edit_bom_item_id').value;
    var itemId = document.getElementById('edit_item_id').value;
    var dosage = document.getElementById('edit_dosage').value;
    var wastage = document.getElementById('edit_wastage').value;
    var phaseCode = document.getElementById('edit_phase_code').value.trim();
    var uom = document.getElementById('edit_uom').value.trim();

    if (!itemId) { Dlg.alert('Select an ingredient'); return; }
    if (!phaseCode) { Dlg.alert('Phase / Comp Code is required'); return; }

    var formData = new FormData();
    formData.append('bom_item_id', bomItemId);
    formData.append('item_id', itemId);
    formData.append('dosage_rate', dosage);
    formData.append('wastage_allowance_pct', wastage);
    formData.append('phase_code', phaseCode);
    formData.append('uom', uom);

    fetch('?controller=rnd&action=bomUpdateItem', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                Dlg.alert(data.error || 'Failed to update component');
            }
        })
        .catch(function() { Dlg.alert('Network error'); });
});

document.querySelectorAll('.remove-bom-item').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var itemId = this.getAttribute('data-item-id');
        Dlg.confirm('Remove this component?').then(function(ok) {
            if (!ok) return;
            var formData = new FormData();
            formData.append('item_id', itemId);

            fetch('?controller=rnd&action=bomRemoveItem', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        var row = document.querySelector('tr[data-item-id="' + itemId + '"]');
                        if (row) row.remove();
                    } else {
                        Dlg.alert(data.error || 'Failed to remove');
                    }
                })
                .catch(function() { Dlg.alert('Network error'); });
        });
    });
});
</script>
