<?php $hasActionsCol = ($canEdit ?? false) || ($canEditItem ?? false); ?>
<div class="d-flex justify-content-between mb-4">
    <div class="d-flex gap-2">
        <?php if ($canEdit ?? false): ?>
        <a href="?controller=warehouse&action=itemImportForm" class="btn btn-success"><i class="bi bi-upload me-1"></i>Import ERIC Items</a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="bi bi-plus-lg me-1"></i> Add Item
        </button>
        <?php endif; ?>
    </div>
    <form method="GET" class="d-flex gap-2" style="width:50%">
        <input type="hidden" name="controller" value="<?= htmlspecialchars($itemsController ?? 'warehouse') ?>">
        <input type="hidden" name="action" value="items">
        <input type="text" id="searchInput" name="search" class="form-control" placeholder="Search code, description..." value="<?= htmlspecialchars($search ?? '') ?>" data-server-search="<?= htmlspecialchars($search ?? '') ?>" autocomplete="off">
        <select name="item_type" class="form-select filter-select" style="width:auto" onchange="this.form.submit()">
            <option value="">All Types</option>
            <option value="RM" <?= ($typeFilter ?? '') === 'RM' ? 'selected' : '' ?>>Raw Material</option>
            <option value="PM" <?= ($typeFilter ?? '') === 'PM' ? 'selected' : '' ?>>Packaging</option>
            <option value="FG" <?= ($typeFilter ?? '') === 'FG' ? 'selected' : '' ?>>Finished Good</option>
            <option value="SFG" <?= ($typeFilter ?? '') === 'SFG' ? 'selected' : '' ?>>SFG - Semi-Finished Goods (Bulk)</option>
            <option value="SUPPLIES" <?= ($typeFilter ?? '') === 'SUPPLIES' ? 'selected' : '' ?>>Supplies</option>
        </select>
        <button type="submit" class="d-none">Search</button>
    </form>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th>UOM</th>
                    <th>Supplier</th>
                    <th>Price</th>
                    <th>SOH</th>
                    <th>Allocated</th>
                    <th>Available</th>
                    <th>Status</th>
                    <?php if ($hasActionsCol): ?><th class="text-end">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="itemsTableBody">
                <?php foreach ($items as $item): ?>
                <tr data-item-row>
                    <td>
                        <?php if ($canEditItem ?? false): ?>
                        <a href="#" class="edit-item-btn" title="Edit item, suppliers & prices"
                           data-item-id="<?= (int)$item['item_id'] ?>"
                           data-code="<?= htmlspecialchars($item['item_code']) ?>"
                           data-description="<?= htmlspecialchars($item['item_description']) ?>"
                           data-type="<?= htmlspecialchars($item['item_type'] ?? '') ?>"
                           data-uom="<?= htmlspecialchars($item['item_uom']) ?>"
                           data-supplier="<?= (int)($item['supplier_id'] ?? 0) ?>"
                           data-price="<?= htmlspecialchars((string)($item['item_amount'] ?? '0')) ?>"><strong class="text-primary"><?= htmlspecialchars($item['item_code']) ?></strong></a>
                        <?php else: ?>
                        <strong class="text-primary"><?= htmlspecialchars($item['item_code']) ?></strong>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($item['item_description']) ?></td>
                    <td>
                        <?php
                        $typeBadges = ['RM' => 'danger', 'PM' => 'info', 'FG' => 'success', 'SFG' => 'warning', 'SUPPLIES' => 'secondary'];
                        $badge = $typeBadges[$item['item_type']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $badge ?>"><?= $item['item_type'] ?? '—' ?></span>
                    </td>
                    <td><?= htmlspecialchars($item['item_uom']) ?></td>
                    <td><small><?= htmlspecialchars(($item['supplier_name'] ?? '') !== '' ? $item['supplier_name'] : '—') ?></small></td>
                    <td class="text-end"><?= number_format((float)($item['item_amount'] ?? 0), 2) ?></td>
                    <td><?= formatQty($item['total_soh'] ?? 0) ?></td>
                    <td><?= formatQty($item['total_allocated'] ?? 0) ?></td>
                    <td><strong><?= formatQty($item['available_stock'] ?? 0) ?></strong></td>
                    <td>
                        <?php
                        $statusColors = ['OUT OF STOCK' => 'danger', 'LOW STOCK' => 'warning', 'IN STOCK' => 'success'];
                        $color = $statusColors[$item['inventory_status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $color ?>"><?= $item['inventory_status'] ?></span>
                    </td>
                    <?php if ($hasActionsCol): ?>
                    <td class="text-end">
                        <?php if ($canEditItem ?? false): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary edit-item-btn"
                                data-item-id="<?= (int)$item['item_id'] ?>"
                                data-code="<?= htmlspecialchars($item['item_code']) ?>"
                                data-description="<?= htmlspecialchars($item['item_description']) ?>"
                                data-type="<?= htmlspecialchars($item['item_type'] ?? '') ?>"
                                data-uom="<?= htmlspecialchars($item['item_uom']) ?>"
                                data-supplier="<?= (int)($item['supplier_id'] ?? 0) ?>"
                                data-price="<?= htmlspecialchars((string)($item['item_amount'] ?? '0')) ?>"
                                title="Edit Item" aria-label="Edit Item">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($canEdit ?? false): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary adjust-soh-btn"
                                data-item-id="<?= (int)$item['item_id'] ?>"
                                data-code="<?= htmlspecialchars($item['item_code']) ?>"
                                data-description="<?= htmlspecialchars($item['item_description']) ?>"
                                title="Adjust SOH" aria-label="Adjust SOH">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                <tr data-empty-row><td colspan="<?= $hasActionsCol ? 11 : 10 ?>" class="text-center text-muted py-4">No items found</td></tr>
<?php endif; ?>
                <tr data-no-match-row style="display:none"><td colspan="<?= $hasActionsCol ? 11 : 10 ?>" class="text-center text-muted py-4">No items match your search</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php if (($totalPages ?? 1) > 1): ?>
<?php $pages = \App\Helpers\Pagination::getPageRange($page, $totalPages); ?>
<nav>
    <ul class="pagination justify-content-center mt-4">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=<?= htmlspecialchars($itemsController ?? 'warehouse') ?>&action=items&page=<?= $page - 1 ?>&search=<?= urlencode($search ?? '') ?>&item_type=<?= urlencode($typeFilter ?? '') ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="?controller=<?= htmlspecialchars($itemsController ?? 'warehouse') ?>&action=items&page=<?= $p ?>&search=<?= urlencode($search ?? '') ?>&item_type=<?= urlencode($typeFilter ?? '') ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=<?= htmlspecialchars($itemsController ?? 'warehouse') ?>&action=items&page=<?= $page + 1 ?>&search=<?= urlencode($search ?? '') ?>&item_type=<?= urlencode($typeFilter ?? '') ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php if ($canEdit ?? false): ?>
<!-- ADD ITEM MODAL -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="?controller=warehouse&action=storeItem">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalLabel"><i class="bi bi-plus-lg me-2"></i>Add Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="addItemCode" class="form-label">Item Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="addItemCode" name="code" maxlength="50" placeholder="e.g. RM-0001" required>
                </div>
                <div class="mb-3">
                    <label for="addItemDescription" class="form-label">Description <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="addItemDescription" name="description" maxlength="255" placeholder="Item description" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="addItemType" class="form-label">Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="addItemType" name="type" required>
                            <option value="">Select type...</option>
                            <option value="RM">RM - Raw Material</option>
                            <option value="PM">PM - Packaging</option>
                            <option value="FG">FG - Finished Good</option>
                            <option value="SFG">SFG - Semi-Finished Good</option>
                            <option value="SUPPLIES">SUPPLIES</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="addItemUom" class="form-label">UOM <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="addItemUom" name="uom" list="addItemUomList" maxlength="50" placeholder="e.g. Kg" required>
                        <datalist id="addItemUomList">
                            <option value="Kg"></option>
                            <option value="g"></option>
                            <option value="L"></option>
                            <option value="mL"></option>
                            <option value="Pc"></option>
                            <option value="PCS"></option>
                            <option value="CS"></option>
                            <option value="Ltr"></option>
                        </datalist>
                    </div>
                </div>
                <div class="mb-1">
                    <label for="addItemSoh" class="form-label">Initial SOH</label>
                    <input type="number" class="form-control" id="addItemSoh" name="soh" step="0.0001" min="0" value="0.0000">
                    <small class="text-muted">Status is derived automatically: IN STOCK when SOH &gt; 0, OUT OF STOCK when 0.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create Item</button>
            </div>
        </form>
    </div>
</div>

<!-- ADJUST SOH MODAL -->
<div class="modal fade" id="adjustSohModal" tabindex="-1" aria-labelledby="adjustSohModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="?controller=warehouse&action=adjustItemSoh">
            <input type="hidden" name="item_id" id="adjustSohItemId" value="">
            <input type="hidden" name="back_search" value="<?= htmlspecialchars($search ?? '') ?>">
            <input type="hidden" name="back_type" value="<?= htmlspecialchars($typeFilter ?? '') ?>">
            <input type="hidden" name="back_page" value="<?= (int)($page ?? 1) ?>">
            <div class="modal-header">
                <h5 class="modal-title" id="adjustSohModalLabel"><i class="bi bi-pencil-square me-2"></i>Adjust SOH</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="fw-bold text-primary" id="adjustSohCode">&mdash;</div>
                    <div class="text-muted small" id="adjustSohDesc"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <span class="text-muted small">Total SOH (all sites)</span>
                        <div class="fw-semibold" id="adjustSohTotal">&mdash;</div>
                    </div>
                    <div class="col-6">
                        <span class="text-muted small">Allocated</span>
                        <div class="fw-semibold" id="adjustSohAllocated">&mdash;</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="adjustSohSite" class="form-label">Site</label>
                    <select class="form-select" id="adjustSohSite" name="site_code" required>
                        <option value="">Loading...</option>
                    </select>
                </div>
                <div class="mb-1">
                    <label for="adjustSohQty" class="form-label">New SOH for this site <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="adjustSohQty" name="qty_on_hand" step="0.0001" min="0" required>
                    <small class="text-muted">Sets <code>qty_on_hand</code> for the selected site only &mdash; the item's total SOH is the sum of all sites. Allocated quantity is not changed.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canEditItem ?? false): ?>
<!-- EDIT ITEM MODAL -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <form class="modal-content" method="POST" action="?controller=<?= htmlspecialchars($itemsController ?? 'warehouse') ?>&action=itemUpdate&back_search=<?= urlencode($search ?? '') ?>&back_type=<?= urlencode($typeFilter ?? '') ?>&back_page=<?= (int)($page ?? 1) ?>">
            <input type="hidden" name="item_id" id="editItemId" value="">
            <input type="hidden" name="supplier_id" id="editItemSupplierId" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="editItemModalLabel"><i class="bi bi-pencil me-2"></i>Edit Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="fw-bold text-primary" id="editItemCode">&mdash;</div>
                    <div class="text-muted small">Item code cannot be changed here.</div>
                </div>
                <div class="mb-3">
                    <label for="editItemDescription" class="form-label">Description <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="editItemDescription" name="item_description" maxlength="255" placeholder="Item description" required>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="editItemType" class="form-label">Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="editItemType" name="item_type" required>
                            <option value="">Select type...</option>
                            <option value="RM">RM - Raw Material</option>
                            <option value="PM">PM - Packaging</option>
                            <option value="FG">FG - Finished Good</option>
                            <option value="SFG">SFG - Semi-Finished Good</option>
                            <option value="SUPPLIES">SUPPLIES</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="editItemUom" class="form-label">UOM <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editItemUom" name="item_uom" list="editItemUomList" maxlength="50" placeholder="e.g. Kg" required>
                        <datalist id="editItemUomList">
                            <option value="Kg"></option>
                            <option value="g"></option>
                            <option value="L"></option>
                            <option value="mL"></option>
                            <option value="Pc"></option>
                            <option value="PCS"></option>
                            <option value="CS"></option>
                            <option value="Ltr"></option>
                        </datalist>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="editItemPrice" class="form-label">Price (current)</label>
                        <input type="number" class="form-control" id="editItemPrice" name="item_amount" step="0.01" min="0" value="0.00">
                        <small class="text-muted">Manual override; re-syncs when prices below change.</small>
                    </div>
                </div>

                <hr>
                <div class="row g-3">
                    <!-- LEFT: suppliers where this item is bought -->
                    <div class="col-md-5">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><i class="bi bi-truck me-1"></i>Suppliers</h6>
                            <button type="button" class="btn btn-sm btn-success" id="apToggle"><i class="bi bi-plus-lg me-1"></i>Add Supplier</button>
                        </div>
                        <div class="border rounded p-2 mb-2 d-none" id="apForm">
                            <div class="row g-2">
                                <div class="col-12">
                                    <select class="form-select form-select-sm" id="apSupplier"><option value="">Select supplier...</option></select>
                                </div>
                                <div class="col-12">
                                    <input type="number" class="form-control form-control-sm" id="apPrice" step="0.01" min="0" placeholder="Price">
                                </div>
                                <div class="col-12 d-grid">
                                    <button type="button" class="btn btn-sm btn-primary" id="apSave">Add Price</button>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive" style="max-height:240px; overflow-y:auto;">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light"><tr><th>Supplier</th><th class="text-end">Latest</th><th class="text-center">#</th></tr></thead>
                                <tbody id="apSuppliersBody"></tbody>
                            </table>
                        </div>
                        <div class="text-muted small mt-1" id="apEmpty">No suppliers yet — use Add Supplier to record the first price.</div>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-2 d-none" id="apRemoveBtn"><i class="bi bi-trash me-1"></i>Delete Supplier</button>
                    </div>
                    <!-- RIGHT: selected supplier's dated price history -->
                    <div class="col-md-7">
                        <h6 class="mb-2" id="apHistoryTitle">Price history</h6>
                        <div class="table-responsive" style="max-height:300px; overflow-y:auto;">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Effective date</th><th class="text-end">Price</th></tr></thead>
                                <tbody id="apPricesBody"></tbody>
                            </table>
                        </div>
                        <div class="text-muted small mt-1 d-none" id="apPricesEmpty">No prices for this supplier.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModalEl = document.getElementById('editItemModal');
    if (!editModalEl) return;

    const itemsController = <?= json_encode($itemsController ?? 'warehouse') ?>;
    const BASE = '?controller=' + itemsController + '&action=';
    let currentItemId = 0;
    let currentSupplierId = 0;
    let lastData = null;
    let loadSeq = 0;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ── Panes ──────────────────────────────────────────────────────────────
    function renderPanes(data) {
        var suppliers = (data && data.suppliers) || [];
        var ids = suppliers.map(function(s) { return s.supplier_id; });
        if (ids.indexOf(currentSupplierId) === -1) {
            currentSupplierId = ids.length ? ids[0] : 0;
        }

        var supBody = document.getElementById('apSuppliersBody');
        supBody.innerHTML = suppliers.map(function(s) {
            var sel = s.supplier_id === currentSupplierId ? 'table-active' : '';
            return '<tr class="' + sel + ' ap-sup-row" data-supplier-id="' + s.supplier_id + '" style="cursor:pointer">' +
                '<td><strong>' + esc(s.supplier_name) + '</strong>' + (s.status === 0 ? ' <span class="badge bg-secondary">inactive</span>' : '') + '</td>' +
                '<td class="text-end">₱' + Number(s.latest).toFixed(2) + '</td>' +
                '<td class="text-center">' + s.count + '</td></tr>';
        }).join('');
        document.getElementById('apEmpty').style.display = suppliers.length ? 'none' : '';
        document.getElementById('apRemoveBtn').classList.toggle('d-none', !currentSupplierId);
        renderPricesPane(suppliers);
    }

    function renderPricesPane(suppliers) {
        var sup = null;
        (suppliers || []).forEach(function(s) { if (s.supplier_id === currentSupplierId) sup = s; });
        document.getElementById('apHistoryTitle').innerHTML = sup
            ? '<i class="bi bi-clock-history me-1"></i>Price history — ' + esc(sup.supplier_name)
            : '<i class="bi bi-clock-history me-1"></i>Price history';

        var body = document.getElementById('apPricesBody');
        var empty = document.getElementById('apPricesEmpty');
        if (!sup || !sup.prices.length) {
            body.innerHTML = '';
            empty.classList.remove('d-none');
            return;
        }
        empty.classList.add('d-none');
        body.innerHTML = sup.prices.map(function(p) {
            return '<tr>' +
                '<td>' + esc(p.effective_date) + '</td>' +
                '<td class="text-end"><strong>₱' + Number(p.price).toFixed(2) + '</strong></td>' +
                '</tr>';
        }).join('');
    }

    function loadSuppliersPrices() {
        var seq = ++loadSeq;
        document.getElementById('apSuppliersBody').innerHTML = '<tr><td colspan="3" class="text-center text-muted small py-3">Loading…</td></tr>';
        document.getElementById('apPricesBody').innerHTML = '<tr><td colspan="2" class="text-center text-muted small py-3">Loading…</td></tr>';
        fetch(BASE + 'getItemSuppliersPrices&item_id=' + currentItemId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (seq !== loadSeq) return;   // stale — modal was reopened
                if (!data.success) { Dlg.alert(data.error || 'Failed to load supplier prices.'); return; }
                lastData = data;
                var sel = document.getElementById('apSupplier');
                var keep = sel.value;
                sel.innerHTML = '<option value="">Select supplier...</option>' + (data.active_suppliers || []).map(function(s) {
                    return '<option value="' + s.supplier_id + '">' + esc(s.supplier_name) + '</option>';
                }).join('');
                sel.value = keep;
                renderPanes(data);
            })
            .catch(function() {
                if (seq === loadSeq) Dlg.alert('Failed to load supplier prices.');
            });
    }

    function postJson(action, params) {
        var fd = new FormData();
        Object.keys(params).forEach(function(k) { fd.append(k, params[k]); });
        return fetch(BASE + action, { method: 'POST', body: fd }).then(function(r) { return r.json(); });
    }

    // ── Open ───────────────────────────────────────────────────────────────
    function openEditModal(btn) {
        currentItemId = parseInt(btn.dataset.itemId || '0', 10);
        currentSupplierId = 0;
        lastData = null;
        document.getElementById('editItemId').value = btn.dataset.itemId || '';
        document.getElementById('editItemCode').textContent = btn.dataset.code || '—';
        document.getElementById('editItemDescription').value = btn.dataset.description || '';
        document.getElementById('editItemType').value = btn.dataset.type || '';
        document.getElementById('editItemUom').value = btn.dataset.uom || '';
        document.getElementById('editItemSupplierId').value = btn.dataset.supplier || '';
        document.getElementById('editItemPrice').value = btn.dataset.price || '0.00';
        document.getElementById('apForm').classList.add('d-none');
        document.getElementById('apPrice').value = '';
        document.getElementById('apSupplier').innerHTML = '<option value="">Select supplier...</option>';
        bootstrap.Modal.getOrCreateInstance(editModalEl).show();
        loadSuppliersPrices();
    }

    document.querySelectorAll('.edit-item-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            openEditModal(btn);
        });
    });

    // Supplier row → select + re-render right pane
    document.getElementById('apSuppliersBody').addEventListener('click', function(e) {
        var row = e.target.closest('.ap-sup-row');
        if (!row) return;
        currentSupplierId = parseInt(row.dataset.supplierId, 10);
        renderPanes(lastData || { suppliers: [] });
    });

    // Add Supplier form toggle
    document.getElementById('apToggle').addEventListener('click', function() {
        var f = document.getElementById('apForm');
        f.classList.toggle('d-none');
        if (!f.classList.contains('d-none')) document.getElementById('apSupplier').focus();
    });

    // Add price (supplier + first/next price in one step; date = today server-side)
    document.getElementById('apSave').addEventListener('click', function() {
        var supplierId = document.getElementById('apSupplier').value;
        var price = document.getElementById('apPrice').value;
        if (!supplierId) { Dlg.alert('Please select a supplier.'); return; }
        if (price === '' || isNaN(price) || parseFloat(price) < 0) { Dlg.alert('Valid price is required.'); return; }
        var btn = this;
        btn.disabled = true;
        postJson('itemPriceAdd', {
            item_id: currentItemId,
            supplier_id: supplierId,
            price: price
        }).then(function(res) {
            btn.disabled = false;
            if (!res.success) { Dlg.alert(res.error || 'Failed to add price.'); return; }
            currentSupplierId = parseInt(supplierId, 10);
            document.getElementById('apPrice').value = '';
            document.getElementById('apForm').classList.add('d-none');
            loadSuppliersPrices();
        }).catch(function() { btn.disabled = false; Dlg.alert('Failed to add price.'); });
    });

    // Delete Supplier → removes all of its price rows for this item
    document.getElementById('apRemoveBtn').addEventListener('click', function() {
        if (!currentSupplierId || !lastData) return;
        var name = '';
        lastData.suppliers.forEach(function(s) { if (s.supplier_id === currentSupplierId) name = s.supplier_name; });
        Dlg.confirm('Remove "' + name + '" from this item? All of its price entries will be deleted.').then(function(ok) {
            if (!ok) return;
            postJson('itemSupplierRemove', { item_id: currentItemId, supplier_id: currentSupplierId }).then(function(res) {
                if (!res.success) { Dlg.alert(res.error || 'Failed to remove supplier.'); return; }
                currentSupplierId = 0;
                loadSuppliersPrices();
            }).catch(function() { Dlg.alert('Failed to remove supplier.'); });
        });
    });
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput') || document.querySelector('input[placeholder*="Search"]');
    const tbody = document.getElementById('itemsTableBody');
    if (!searchInput || !tbody) return;

    const form = searchInput.form;
    const serverSearch = searchInput.dataset.serverSearch || '';
    const rows = tbody.querySelectorAll('tr[data-item-row]');
    const noMatchRow = tbody.querySelector('tr[data-no-match-row]');
    let debounceTimer = null;

    function filterRows() {
        const query = searchInput.value.toLowerCase().trim();
        let visible = 0;
        rows.forEach(function(row) {
            const code = (row.children[0] ? row.children[0].textContent : '').toLowerCase();
            const desc = (row.children[1] ? row.children[1].textContent : '').toLowerCase();
            const match = code.includes(query) || desc.includes(query);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        if (noMatchRow) {
            noMatchRow.style.display = (query !== '' && rows.length > 0 && visible === 0) ? '' : 'none';
        }
    }

    function submitServerSearch() {
        try { sessionStorage.setItem('itemsSearchFocus', '1'); } catch (err) {}
        form.submit();
    }

    searchInput.addEventListener('input', function() {
        filterRows();
        clearTimeout(debounceTimer);
        if (searchInput.value === serverSearch) return;
        debounceTimer = setTimeout(submitServerSearch, 600);
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(debounceTimer);
            if (searchInput.value !== serverSearch) submitServerSearch();
        }
    });

    filterRows();

    if (sessionStorage.getItem('itemsSearchFocus')) {
        sessionStorage.removeItem('itemsSearchFocus');
        searchInput.focus();
        const len = searchInput.value.length;
        searchInput.setSelectionRange(len, len);
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('adjustSohModal');
    if (!modalEl) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const idInput = document.getElementById('adjustSohItemId');
    const codeEl = document.getElementById('adjustSohCode');
    const descEl = document.getElementById('adjustSohDesc');
    const totalEl = document.getElementById('adjustSohTotal');
    const allocEl = document.getElementById('adjustSohAllocated');
    const siteSelect = document.getElementById('adjustSohSite');
    const qtyInput = document.getElementById('adjustSohQty');

    function fmtQty(v) {
        const n = Number(v || 0);
        return n.toLocaleString(undefined, { maximumFractionDigits: 4 });
    }

    function fillSites(balances) {
        const sites = {};
        balances.forEach(function(b) { sites[b.site_code] = Number(b.qty_on_hand); });
        if (!('001' in sites)) sites['001'] = null;

        siteSelect.innerHTML = '';
        Object.keys(sites).forEach(function(code) {
            const qty = sites[code];
            const opt = document.createElement('option');
            opt.value = code;
            if (qty === null) {
                opt.textContent = code + ' (new)';
            } else {
                opt.textContent = code + ' \u2014 ' + fmtQty(qty);
                opt.dataset.qty = qty;
            }
            siteSelect.appendChild(opt);
        });

        const firstStocked = Array.from(siteSelect.options).find(function(o) { return o.dataset.qty !== undefined; });
        siteSelect.value = (firstStocked || siteSelect.options[0]).value;
        const sel = siteSelect.selectedOptions[0];
        qtyInput.value = sel && sel.dataset.qty !== undefined ? sel.dataset.qty : 0;
    }

    siteSelect.addEventListener('change', function() {
        const opt = siteSelect.selectedOptions[0];
        qtyInput.value = opt && opt.dataset.qty !== undefined ? opt.dataset.qty : 0;
    });

    document.querySelectorAll('.adjust-soh-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const itemId = btn.dataset.itemId;
            idInput.value = itemId;
            codeEl.textContent = btn.dataset.code;
            descEl.textContent = btn.dataset.description;
            totalEl.textContent = '\u2014';
            allocEl.textContent = '\u2014';
            siteSelect.innerHTML = '<option value="">Loading...</option>';
            qtyInput.value = '';
            qtyInput.disabled = true;
            modal.show();

            fetch('?controller=warehouse&action=getItemBalances&item_id=' + encodeURIComponent(itemId))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) throw new Error(data.message || 'Failed to load item.');
                    totalEl.textContent = fmtQty(data.total_soh);
                    allocEl.textContent = fmtQty(data.total_allocated);
                    fillSites(data.balances || []);
                    qtyInput.disabled = false;
                })
                .catch(function(err) {
                    siteSelect.innerHTML = '<option value="">Failed to load balances</option>';
                    console.error(err);
                });
        });
    });
});
</script>
