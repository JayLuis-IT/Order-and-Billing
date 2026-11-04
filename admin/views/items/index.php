<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal">
            <i class="bi bi-plus-circle me-1"></i> Add Item
        </button>
        <a href="?controller=admin&action=itemsExport&search=<?= urlencode($search ?? '') ?>&customer_id=<?= urlencode($customerFilter ?? '') ?>" class="btn btn-success"><i class="bi bi-download me-1"></i>Export Excel</a>
        <a href="?controller=admin&action=itemsPrint&search=<?= urlencode($search ?? '') ?>&customer_id=<?= urlencode($customerFilter ?? '') ?>" class="btn btn-danger" target="_blank"><i class="bi bi-printer me-1"></i>Print PDF</a>
    </div>
    <div class="d-flex align-items-center gap-2">
        <select class="form-select form-select-sm filter-select" style="width:200px" id="filterCustomer">
            <option value="">All Customers</option>
            <?php foreach ($customers as $c): ?>
                <option value="<?= $c['customer_id'] ?>" <?= ($customerFilter ?? '') == $c['customer_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('hiddenCustomerFilter').value=''; window.location.href='?controller=admin&action=items'"><i class="bi bi-x-circle me-1"></i>Clear</button>
        <div class="search-box" style="width: 300px;">
            <form method="GET" class="d-flex align-items-center">
                <input type="hidden" name="controller" value="admin">
                <input type="hidden" name="action" value="items">
                <input type="hidden" name="customer_id" id="hiddenCustomerFilter" value="<?= htmlspecialchars($customerFilter ?? '') ?>">
                <i class="bi bi-search"></i>
                <input type="text" name="search" id="searchItem" class="form-control" placeholder="Search items..." value="<?= htmlspecialchars($search ?? '') ?>">
            </form>
        </div>
    </div>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Code</th><th>Description</th><th>Customer</th><th>UOM</th><th>PCS to CS Conversion</th>
                    <th>Status</th><th>Created</th><th>Updated</th><th>Actions</th>
                </tr>
            </thead>
            <tbody id="itemTableBody">
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><strong class="text-primary"><?= $item['item_code'] ?></strong></td>
                    <td><?= $item['item_description'] ?></td>
                    <td><?= !empty($item['customer_name']) ? htmlspecialchars($item['customer_name']) : '—' ?></td>
                    <td><span class="badge bg-info"><?= $item['item_uom'] ?></span></td>
                    <td><?= $item['uom_conversion'] ? $item['uom_conversion'] : '—' ?></td>
                    <td><span class="badge bg-<?= $item['status'] ? 'success' : 'secondary' ?>"><?= $item['status'] ? 'Active' : 'Inactive' ?></span></td>
                    <td><?= date('Y-m-d', strtotime($item['date_created'])) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($item['last_update'])) ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#itemEditModal" data-id="<?= $item['item_id'] ?>" data-code="<?= htmlspecialchars($item['item_code']) ?>" data-desc="<?= htmlspecialchars($item['item_description']) ?>" data-uom="<?= $item['item_uom'] ?>" data-conversion="<?= $item['uom_conversion'] ?? '' ?>" data-customer="<?= $item['customer_id'] ?? '' ?>"><i class="bi bi-pencil"></i></button>
                        <a href="?controller=admin&action=itemToggleStatus&id=<?= $item['item_id'] ?>&search=<?= urlencode($search ?? '') ?>&customer_id=<?= urlencode($customerFilter ?? '') ?>" class="btn btn-sm btn-warning" data-confirm="Toggle item status?"><i class="bi bi-toggle-on"></i></a>
                        <a href="?controller=admin&action=itemDelete&id=<?= $item['item_id'] ?>&search=<?= urlencode($search ?? '') ?>&customer_id=<?= urlencode($customerFilter ?? '') ?>" class="btn btn-sm btn-danger" data-confirm="Delete?"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<?php $pages = \App\Helpers\Pagination::getPageRange($page, $totalPages); ?>
<nav>
    <ul class="pagination justify-content-center mt-4">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=admin&action=items&page=<?= $page - 1 ?>&search=<?= urlencode($search ?? '') ?>&customer_id=<?= urlencode($customerFilter ?? '') ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="?controller=admin&action=items&page=<?= $p ?>&search=<?= urlencode($search ?? '') ?>&customer_id=<?= urlencode($customerFilter ?? '') ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=admin&action=items&page=<?= $page + 1 ?>&search=<?= urlencode($search ?? '') ?>&customer_id=<?= urlencode($customerFilter ?? '') ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<div class="modal fade" id="itemModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="?controller=admin&action=itemCreate">
                <div class="modal-body">
                    <input type="hidden" name="filter_search" value="<?= htmlspecialchars($search ?? '') ?>">
                    <input type="hidden" name="filter_customer_id" value="<?= htmlspecialchars($customerFilter ?? '') ?>">
                    <div class="mb-3"><label class="form-label">Item Code *</label><input type="text" name="item_code" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description *</label><input type="text" name="item_description" class="form-control" required></div>
                    <div class="mb-3">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-select filter-select" required>
                            <option value="">Select Customer</option>
                            <?php foreach ($allCustomers as $c): ?>
                                <option value="<?= $c['customer_id'] ?>"><?= htmlspecialchars($c['customer_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">UOM</label><input type="text" class="form-control" name="item_uom" value="PCS"></div>
                    <div class="mb-3"><label class="form-label">Cases Conversion</label><input type="number" name="uom_conversion" id="add_uom_conversion" class="form-control" min="1" placeholder="e.g. 10 means 10 PCS = 1 CS"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="itemEditModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Edit Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="?controller=admin&action=itemUpdate">
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="edit_item_id">
                    <input type="hidden" name="filter_search" id="edit_filter_search" value="<?= htmlspecialchars($search ?? '') ?>">
                    <input type="hidden" name="filter_customer_id" id="edit_filter_customer_id" value="<?= htmlspecialchars($customerFilter ?? '') ?>">
                    <div class="mb-3"><label class="form-label">Item Code *</label><input type="text" name="item_code" id="edit_item_code" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description *</label><input type="text" name="item_description" id="edit_item_description" class="form-control" required></div>
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <select name="customer_id" id="edit_customer_id" class="form-select filter-select">
                            <option value="">All Customers</option>
                            <?php foreach ($allCustomers as $c): ?>
                                <option value="<?= $c['customer_id'] ?>"><?= htmlspecialchars($c['customer_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">UOM</label><input type="text" id="edit_item_uom" class="form-control" name="item_uom" value="PCS"></div>
                    <div class="mb-3"><label class="form-label">Cases Conversion</label><input type="number" name="uom_conversion" id="edit_uom_conversion" class="form-control" min="1" placeholder="e.g. 10 means 10 PCS = 1 CS"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var _searchTimer;
document.getElementById('searchItem').addEventListener('input', function() {
    clearTimeout(_searchTimer);
    var form = this.closest('form');
    _searchTimer = setTimeout(function() { form.submit(); }, 500);
});

(function() {
    var s = document.getElementById('searchItem');
    if (s && s.value) { s.focus(); s.setSelectionRange(s.value.length, s.value.length); }
})();

document.getElementById('itemEditModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    document.getElementById('edit_item_id').value = button.getAttribute('data-id');
    document.getElementById('edit_item_code').value = button.getAttribute('data-code');
    document.getElementById('edit_item_description').value = button.getAttribute('data-desc');
    document.getElementById('edit_item_uom').value = button.getAttribute('data-uom') || 'PCS';
    document.getElementById('edit_uom_conversion').value = button.getAttribute('data-conversion') || '';
    document.getElementById('edit_customer_id').value = button.getAttribute('data-customer') || '';
});

document.getElementById('filterCustomer').addEventListener('change', function() {
    document.getElementById('hiddenCustomerFilter').value = this.value;
    document.getElementById('searchItem').closest('form').submit();
});
</script>
