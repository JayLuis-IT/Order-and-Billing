<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPriceModal">
            <i class="bi bi-plus-circle me-1"></i>Add Price
        </button>
        <a href="?controller=finance&action=priceListExport&search=<?= urlencode($search ?? '') ?>&status=<?= urlencode($filterStatus ?? '') ?>&customer=<?= urlencode($filterCustomer ?? '') ?>" class="btn btn-success"><i class="bi bi-download me-1"></i>Export Excel</a>
        <a href="?controller=finance&action=priceListPrint&search=<?= urlencode($search ?? '') ?>&status=<?= urlencode($filterStatus ?? '') ?>&customer=<?= urlencode($filterCustomer ?? '') ?>" class="btn btn-danger" target="_blank"><i class="bi bi-printer me-1"></i>Print PDF</a>
        <span class="text-muted ms-2">Showing <?= empty($price_items) ? 0 : ((($page - 1) * $perPage) + 1) ?> to <?= (($page - 1) * $perPage) + count($price_items) ?> of <?= $total ?> items</span>
    </div>
    <form method="GET" class="d-flex align-items-center gap-2" style="width:60%">
        <input type="hidden" name="controller" value="finance">
        <input type="hidden" name="action" value="priceList">
        <input type="text" name="search" id="searchItem" class="form-control" placeholder="Search items..." value="<?= htmlspecialchars($search ?? '') ?>" style="width:25%">
        <select name="status" id="filterStatus" class="form-select filter-select" style="width:18%">
            <option value="">All Status</option>
            <option value="1" <?= ($filterStatus ?? '') === '1' ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= ($filterStatus ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <select name="customer" id="filterCustomer" class="form-select filter-select" style="width:25%">
            <option value="">All Customers</option>
            <?php foreach ($customers as $cust): ?>
            <option value="<?= htmlspecialchars($cust['customer_name']) ?>" <?= ($filterCustomer ?? '') === $cust['customer_name'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cust['customer_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
        <?php if (($search ?? '') || ($filterStatus ?? '') !== '' || ($filterCustomer ?? '')): ?>
        <a href="?controller=finance&action=priceList" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-circle"></i> Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product Name</th>
                    <th>Customer</th>
                    <th>Item Code</th>
                    <th>Net/Size</th>
                    <th class="text-end">Price per Piece</th>
                    <th class="text-end">Price per Pack</th>
                    <th class="text-end">Price per Case</th>
                    <th class="text-center">VAT Type</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Date Created</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="itemTableBody">
                <?php if (empty($price_items)): ?>
                <tr>
                    <td colspan="11" class="text-center text-muted py-4">No price list items found</td>
                </tr>
                <?php else: ?>
                <?php $i = ($page - 1) * $perPage + 1; ?>
                <?php foreach ($price_items as $item): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($item['product_name']) ?></strong></td>
                    <td><?= htmlspecialchars($item['customer_name'] ?? '—') ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($item['item_code'] ?? '—') ?></span></td>
                    <td><?= htmlspecialchars($item['net_size'] ?? '—') ?></td>
                    <td class="text-end">₱<?= number_format($item['price_per_piece'] ?? 0, 2) ?></td>
                    <td class="text-end">₱<?= number_format($item['price_per_pack'], 2) ?></td>
                    <td class="text-end">₱<?= number_format($item['price_per_case'], 2) ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $item['vat_type'] === 'vat' ? 'warning' : 'info' ?>">
                            <?= $item['vat_type'] === 'vat' ? 'VAT' : 'Non-VAT' ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-<?= $item['status'] ? 'success' : 'secondary' ?>">
                            <?= $item['status'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="text-center"><?= date('Y-m-d', strtotime($item['date_created'])) ?></td>
                    <td class="text-center">
                        <a href="?controller=finance&action=priceListToggle&id=<?= $item['price_list_id'] ?>" 
                           class="btn btn-sm btn-<?= $item['status'] ? 'success' : 'outline-secondary' ?>"
                           data-confirm="Are you sure you want to change the status?"
                           title="<?= $item['status'] ? 'Deactivate' : 'Activate' ?>">
                            <i class="bi bi-toggle-<?= $item['status'] ? 'on' : 'off' ?>"></i>
                        </a>
                        <button class="btn btn-sm btn-primary edit-btn"
                            data-id="<?= $item['price_list_id'] ?>"
                            data-item_id="<?= $item['item_id'] ?? '' ?>"
                            data-product_name="<?= htmlspecialchars($item['product_name']) ?>"
                            data-net_size="<?= htmlspecialchars($item['net_size'] ?? '') ?>"
                            data-price_per_pack="<?= $item['price_per_pack'] ?>"
                            data-price_per_case="<?= $item['price_per_case'] ?>"
                            data-price_per_piece="<?= $item['price_per_piece'] ?? 0 ?>"
                            data-vat_type="<?= $item['vat_type'] ?>"
                            data-bs-toggle="modal" data-bs-target="#editPriceModal">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<?php $pages = \App\Helpers\Pagination::getPageRange($page, $totalPages); ?>
    <?php $filterParams = http_build_query(array_filter(['search' => $search ?? '', 'status' => $filterStatus ?? '', 'customer' => $filterCustomer ?? ''], function($v) { return $v !== ''; })); ?>
    <nav>
        <ul class="pagination justify-content-center mt-4">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?controller=finance&action=priceList&page=<?= $page - 1 ?>&<?= $filterParams ?>">&laquo; Prev</a>
            </li>
            <?php foreach ($pages as $p): ?>
                <?php if ($p === '...'): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php else: ?>
                <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?controller=finance&action=priceList&page=<?= $p ?>&<?= $filterParams ?>"><?= $p ?></a>
                </li>
                <?php endif; ?>
            <?php endforeach; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?controller=finance&action=priceList&page=<?= $page + 1 ?>&<?= $filterParams ?>">Next &raquo;</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- Add Price Modal -->
<div class="modal fade" id="addPriceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Price List Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?controller=finance&action=priceListCreate" id="addPriceForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Customer *</label>
                        <select id="add_customer_id" class="form-select filter-select" required>
                            <option value="">-- Select a customer --</option>
                            <?php foreach ($customers as $cust): ?>
                            <option value="<?= $cust['customer_id'] ?>">
                                <?= htmlspecialchars($cust['customer_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Product *</label>
                        <select name="item_id" id="add_item_id" class="form-select filter-select" required>
                            <option value="">-- Select a product --</option>
                        </select>
                        <small class="text-muted d-none" id="addProductPlaceholder">Please select a customer first</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="product_name" id="add_product_name" class="form-control" required readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Net/Size</label>
                        <input type="text" name="net_size" id="add_net_size" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price per Piece *</label>
                            <input type="number" name="price_per_piece" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price per Pack *</label>
                            <input type="number" name="price_per_pack" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price per Case *</label>
                            <input type="number" name="price_per_case" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">VAT Type *</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="vat_type" id="addVat" value="vat" checked>
                                <label class="form-check-label" for="addVat">VAT</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="vat_type" id="addNonVat" value="non_vat">
                                <label class="form-check-label" for="addNonVat">Non-VAT</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Price Modal -->
<div class="modal fade" id="editPriceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Price List Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?controller=finance&action=priceListUpdate">
                <div class="modal-body">
                    <input type="hidden" name="price_list_id" id="edit_price_list_id">
                    <input type="hidden" name="item_id" id="edit_item_id">
                    <div class="mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Net/Size</label>
                        <input type="text" name="net_size" id="edit_net_size" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price per Pack *</label>
                            <input type="number" name="price_per_pack" id="edit_price_per_pack" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price per Case *</label>
                            <input type="number" name="price_per_case" id="edit_price_per_case" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price per Piece *</label>
                            <input type="number" name="price_per_piece" id="edit_price_per_piece" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">VAT Type *</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="vat_type" id="editVat" value="vat">
                                <label class="form-check-label" for="editVat">VAT</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="vat_type" id="editNonVat" value="non_vat">
                                <label class="form-check-label" for="editNonVat">Non-VAT</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
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

document.getElementById('filterStatus').addEventListener('change', function() {
    this.closest('form').submit();
});

document.getElementById('filterCustomer').addEventListener('change', function() {
    this.closest('form').submit();
});

(function() {
    var s = document.getElementById('searchItem');
    if (s && s.value) { s.focus(); s.setSelectionRange(s.value.length, s.value.length); }
})();

/* ---- Add Price: Customer → Items AJAX ---- */
document.getElementById('add_customer_id').addEventListener('change', function() {
    var customerId = this.value;
    var itemSelect = document.getElementById('add_item_id');
    var placeholder = document.getElementById('addProductPlaceholder');

    itemSelect.innerHTML = '<option value="">-- Select a product --</option>';
    refreshSearchableDropdown(itemSelect);

    if (!customerId) {
        placeholder.classList.remove('d-none');
        return;
    }

    fetch('?controller=finance&action=getUnpricedItemsByCustomer&customer_id=' + customerId)
        .then(function(r) { return r.json(); })
        .then(function(items) {
            itemSelect.innerHTML = '<option value="">-- Select a product --</option>';
            items.forEach(function(it) {
                var opt = document.createElement('option');
                opt.value = it.item_id;
                opt.dataset.name = it.item_description || '';
                opt.dataset.size = it.item_size || '';
                opt.textContent = it.item_code + ' - ' + it.item_description;
                itemSelect.appendChild(opt);
            });
            placeholder.classList.add('d-none');
            refreshSearchableDropdown(itemSelect);
        });
});

document.getElementById('add_item_id').addEventListener('change', function() {
    var selected = this.options[this.selectedIndex];
    document.getElementById('add_product_name').value = selected && selected.dataset ? (selected.dataset.name || '') : '';
    document.getElementById('add_net_size').value = selected && selected.dataset ? (selected.dataset.size || '') : '';
});

document.getElementById('addPriceModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('add_customer_id').value = '';
    document.getElementById('add_item_id').innerHTML = '<option value="">-- Select a product --</option>';
    document.getElementById('add_product_name').value = '';
    document.getElementById('add_net_size').value = '';
    refreshSearchableDropdown(document.getElementById('add_item_id'));
    document.getElementById('addProductPlaceholder').classList.remove('d-none');
});

/* ---- Edit Price ---- */
document.querySelectorAll('.edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_price_list_id').value = this.dataset.id;
        document.getElementById('edit_item_id').value = this.dataset.item_id;
        document.getElementById('edit_product_name').value = this.dataset.product_name;
        document.getElementById('edit_net_size').value = this.dataset.net_size;
        document.getElementById('edit_price_per_pack').value = this.dataset.price_per_pack;
        document.getElementById('edit_price_per_case').value = this.dataset.price_per_case;
        document.getElementById('edit_price_per_piece').value = this.dataset.price_per_piece;
        
        var vatType = this.dataset.vat_type;
        if (vatType === 'vat') {
            document.getElementById('editVat').checked = true;
        } else {
            document.getElementById('editNonVat').checked = true;
        }
    });
});
</script>
