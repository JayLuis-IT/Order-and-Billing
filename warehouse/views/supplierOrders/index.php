<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSupplierOrderModal">
            <i class="bi bi-plus-circle me-1"></i> New Procurement PO
        </button>
        <button class="btn btn-success d-none" id="batchProcessBtn" onclick="openBatchProcessModal()">
            <i class="bi bi-lightning me-1"></i>Process Selected Items
            <span class="badge bg-white text-success ms-1" id="selectedCount">0</span>
        </button>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="controller" value="warehouse">
            <input type="hidden" name="action" value="supplierOrders">
            <select name="status" class="form-select form-select-sm filter-select" style="width:150px">
                <option value="">All Status</option>
                <option value="requested" <?= ($filters['status'] ?? '') === 'requested' ? 'selected' : '' ?>>Requested</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="received" <?= ($filters['status'] ?? '') === 'received' ? 'selected' : '' ?>>Received</option>
                <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search supplier, item, PO Ref..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="width:220px">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
            <a href="?controller=warehouse&action=supplierOrders" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
        </form>
    </div>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAllOrders" class="form-check-input"></th>
                    <th>#</th>
                    <th>Supplier</th>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end">Unit Cost</th>
                    <th>Order Date</th>
                    <th>Expected</th>
                    <th>PO Ref</th>
                    <th>Status</th>
                    <th class="text-end">Received Qty</th>
                    <th>Received Date</th>
                    <th>Created By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="15" class="text-center text-muted py-4">No procurement POs found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td>
                        <?php if ($o['status'] === 'requested'): ?>
                        <input type="checkbox" class="form-check-input order-checkbox"
                               data-id="<?= $o['supplier_order_id'] ?>"
                               data-item-code="<?= htmlspecialchars($o['item_code']) ?>"
                               data-item-desc="<?= htmlspecialchars($o['item_description']) ?>"
                               data-qty="<?= $o['quantity'] ?>"
                               data-po-ref="<?= htmlspecialchars($o['po_ref_display']) ?>">
                        <?php endif; ?>
                    </td>
                    <td><?= $o['supplier_order_id'] ?></td>
                    <td><?= htmlspecialchars($o['supplier_name']) ?></td>
                    <td><code><?= htmlspecialchars($o['item_code']) ?></code></td>
                    <td><?= htmlspecialchars($o['item_description']) ?></td>
                    <td class="text-end"><?= formatQty($o['quantity']) ?></td>
                    <td class="text-end"><?= number_format($o['unit_cost'], 2) ?></td>
                    <td><?= $o['order_date'] ? date('m/d/Y', strtotime($o['order_date'])) : '-' ?></td>
                    <td><?= $o['expected_date'] ? date('m/d/Y', strtotime($o['expected_date'])) : '-' ?></td>
                    <td><?= htmlspecialchars($o['po_ref_display']) ?></td>
                    <td>
                        <?php if ($o['status'] === 'requested'): ?>
                            <span class="badge bg-info">Requested</span>
                        <?php elseif ($o['status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php elseif ($o['status'] === 'completed'): ?>
                            <span class="badge bg-primary">Completed</span>
                        <?php elseif ($o['status'] === 'received'): ?>
                            <span class="badge bg-success">Received</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Cancelled</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= $o['received_qty'] > 0 ? formatQty($o['received_qty']) : '-' ?></td>
                    <td><?= $o['received_date'] ? date('m/d/Y', strtotime($o['received_date'])) : '-' ?></td>
                    <td><?= htmlspecialchars($o['created_by_name'] ?? '') ?></td>
                    <td>
                        <?php if ($o['status'] === 'requested'): ?>
                        <button class="btn btn-outline-primary btn-sm process-order-btn"
                                data-id="<?= $o['supplier_order_id'] ?>"
                                data-supplier="<?= htmlspecialchars($o['supplier_name']) ?>"
                                data-qty="<?= $o['quantity'] ?>"
                                data-cost="<?= $o['unit_cost'] ?>"
                                data-order-date="<?= $o['order_date'] ?>"
                                data-expected="<?= $o['expected_date'] ?>"
                                data-po-id="<?= $o['po_id'] ?? '' ?>"
                                title="Process Order">
                            <i class="bi bi-gear"></i> Process
                        </button>
                        <?php elseif ($o['status'] === 'pending'): ?>
                        <div class="btn-group btn-group-sm">
                            <a href="?controller=warehouse&action=receiveSupplierOrder&id=<?= $o['supplier_order_id'] ?>" class="btn btn-outline-success" title="Receive">
                                <i class="bi bi-box-arrow-in-down"></i>
                            </a>
                            <a href="?controller=warehouse&action=cancelSupplierOrder&id=<?= $o['supplier_order_id'] ?>" class="btn btn-outline-warning" title="Cancel" data-confirm="Cancel this order?">
                                <i class="bi bi-x-circle"></i>
                            </a>
                            <a href="?controller=warehouse&action=deleteSupplierOrder&id=<?= $o['supplier_order_id'] ?>" class="btn btn-outline-danger" title="Delete" data-confirm="Delete this order?">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Procurement PO Modal -->
<div class="modal fade" id="createSupplierOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?controller=warehouse&action=createSupplierOrder">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>New Procurement PO</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_name" class="form-control" required placeholder="e.g. ABC Chemical Corp.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Item <span class="text-danger">*</span></label>
                        <input type="text" id="soItemSearch" class="form-control" placeholder="Search item code or description..." autocomplete="off">
                        <input type="hidden" name="item_id" id="soItemId" required>
                        <div id="soItemDropdown" class="list-group mt-1 d-none" style="position:absolute;z-index:1050;width:calc(100% - 3rem);max-height:200px;overflow-y:auto;"></div>
                        <div id="soSelectedInfo" class="mt-1 d-none">
                            <span class="badge bg-info" id="soSelectedItem"></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" min="0.01" step="0.0001" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Unit Cost</label>
                            <input type="number" name="unit_cost" class="form-control" min="0" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Order Date</label>
                            <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Expected Date</label>
                            <input type="date" name="expected_date" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">PO REF</label>
                            <input type="text" name="po_ref" class="form-control" placeholder="e.g. PO-2026-001">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Process Order Modal -->
<div class="modal fade" id="processOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?controller=warehouse&action=processSupplierOrder">
                <input type="hidden" name="supplier_order_id" id="procOrderId">
                <input type="hidden" name="po_id" id="procPoId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-gear me-2"></i>Process Procurement PO</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3">
                        <i class="bi bi-info-circle me-1"></i>Convert this MRP-requested item into a pending procurement PO by selecting a supplier and confirming quantities.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_name" id="procSupplierName" class="form-control" required placeholder="e.g. ABC Chemical Corp.">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="procQuantity" class="form-control" min="0.01" step="0.0001" required>
                            <small class="text-muted">Adjust to supplier MOQ/packaging size.</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Unit Cost</label>
                            <input type="number" name="unit_cost" id="procUnitCost" class="form-control" min="0" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Order Date</label>
                            <input type="date" name="order_date" id="procOrderDate" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Expected Date</label>
                            <input type="date" name="expected_date" id="procExpectedDate" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">PO REF</label>
                            <input type="text" name="po_ref" id="procPoRef" class="form-control" placeholder="e.g. PO-2026-001">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Remarks</label>
                        <textarea name="remarks" id="procRemarks" class="form-control" rows="2" placeholder="e.g. expected delivery, notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Confirm & Process</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Batch Process Modal -->
<div class="modal fade" id="batchProcessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?controller=warehouse&action=batchProcessSupplierOrders">
                <input type="hidden" name="order_ids" id="batchOrderIds">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-lightning me-2"></i>Batch Process Procurement POs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3">
                        <i class="bi bi-info-circle me-1"></i>Consolidate multiple requested items of the same product into a single supplier transaction.
                    </div>
                    <div class="card bg-light mb-3">
                        <div class="card-body py-2">
                            <div class="row mb-1">
                                <div class="col-4 fw-bold">Item:</div>
                                <div class="col-8"><code id="batchItemCode"></code> - <span id="batchItemDesc"></span></div>
                            </div>
                            <div class="row mb-1">
                                <div class="col-4 fw-bold">Total Consolidated Qty:</div>
                                <div class="col-8" id="batchTotalQty"></div>
                            </div>
                            <div class="row">
                                <div class="col-4 fw-bold">Linked PO Refs:</div>
                                <div class="col-8" id="batchPoRefs"></div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_name" class="form-control" required placeholder="e.g. ABC Chemical Corp.">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Unit Cost</label>
                            <input type="number" name="unit_cost" class="form-control" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Expected Delivery Date</label>
                            <input type="date" name="expected_date" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Confirm & Process All</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('soItemSearch');
    var hiddenId = document.getElementById('soItemId');
    var dropdown = document.getElementById('soItemDropdown');
    var selectedInfo = document.getElementById('soSelectedInfo');
    var selectedName = document.getElementById('soSelectedItem');
    var timeout = null;

    searchInput.addEventListener('input', function() {
        var q = this.value.trim();
        clearTimeout(timeout);
        if (q.length < 1) { dropdown.classList.add('d-none'); return; }
        timeout = setTimeout(function() {
            fetch('?controller=warehouse&action=searchItems&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(items) {
                    dropdown.innerHTML = '';
                    if (!items || items.length === 0) {
                        dropdown.innerHTML = '<div class="list-group-item text-muted">No items found</div>';
                        dropdown.classList.remove('d-none');
                        return;
                    }
                    var seen = new Set();
                    items.forEach(function(item) {
                        if (seen.has(item.item_id)) return;
                        seen.add(item.item_id);
                        var a = document.createElement('a');
                        a.className = 'list-group-item list-group-item-action';
                        a.href = '#';
                        a.innerHTML = '<strong>' + (item.item_code || '') + '</strong> - ' + (item.item_description || '') + '<br><small class="text-muted">UOM: ' + (item.item_uom || '') + '</small>';
                        a.addEventListener('click', function(e) {
                            e.preventDefault();
                            hiddenId.value = item.item_id;
                            searchInput.value = item.item_code + ' - ' + item.item_description;
                            selectedName.textContent = item.item_code + ' - ' + item.item_description;
                            selectedInfo.classList.remove('d-none');
                            dropdown.classList.add('d-none');
                        });
                        dropdown.appendChild(a);
                    });
                    dropdown.classList.remove('d-none');
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#soItemSearch') && !e.target.closest('#soItemDropdown')) {
            dropdown.classList.add('d-none');
        }
    });

    document.querySelectorAll('.process-order-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('procOrderId').value = this.dataset.id;
            document.getElementById('procPoId').value = this.dataset.poId || '';
            document.getElementById('procSupplierName').value = this.dataset.supplier === 'Pending Selection' ? '' : this.dataset.supplier;
            document.getElementById('procQuantity').value = this.dataset.qty;
            document.getElementById('procUnitCost').value = this.dataset.cost;
            document.getElementById('procOrderDate').value = this.dataset.orderDate || '<?= date('Y-m-d') ?>';
            document.getElementById('procExpectedDate').value = this.dataset.expected || '';
            // PO Reference input is intentionally left blank for manual entry.
            new bootstrap.Modal(document.getElementById('processOrderModal')).show();
        });
    });

    // ─── Batch Process ─────────────────────────────────────────────────
    var selectAll = document.getElementById('selectAllOrders');
    var checkboxes = document.querySelectorAll('.order-checkbox');
    var batchBtn = document.getElementById('batchProcessBtn');
    var countBadge = document.getElementById('selectedCount');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
            updateBatchButton();
        });
    }

    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', updateBatchButton);
    });

    function updateBatchButton() {
        var checked = document.querySelectorAll('.order-checkbox:checked');
        if (checked.length > 0) {
            batchBtn.classList.remove('d-none');
            countBadge.textContent = checked.length;
        } else {
            batchBtn.classList.add('d-none');
        }
    }
});

function openBatchProcessModal() {
    var checked = document.querySelectorAll('.order-checkbox:checked');
    if (checked.length === 0) return;

    var groups = {};
    checked.forEach(function(cb) {
        var code = cb.dataset.itemCode;
        if (!groups[code]) {
            groups[code] = { code: code, desc: cb.dataset.itemDesc, qty: 0, poRefs: [] };
        }
        groups[code].qty += parseFloat(cb.dataset.qty);
        groups[code].poRefs.push(cb.dataset.poRef);
    });

    var keys = Object.keys(groups);
    if (keys.length > 1) {
        Dlg.alert('Please select items of the same product only. You have selected ' + keys.length + ' different items.');
        return;
    }

    var g = groups[keys[0]];
    document.getElementById('batchItemCode').textContent = g.code;
    document.getElementById('batchItemDesc').textContent = g.desc;
    document.getElementById('batchTotalQty').textContent = g.qty.toFixed(4);
    document.getElementById('batchPoRefs').textContent = g.poRefs.join(', ');
    document.getElementById('batchOrderIds').value = Array.from(checked).map(function(cb) { return cb.dataset.id; }).join(',');

    new bootstrap.Modal(document.getElementById('batchProcessModal')).show();
}
</script>
