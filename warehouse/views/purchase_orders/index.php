<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal">
            <i class="bi bi-plus-circle me-1"></i> Create New PO
        </button>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <select id="filterCustomer" class="form-select form-select-sm filter-select" style="width:200px">
            <option value="">All Customers</option>
            <?php foreach (($allCustomers ?? []) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= ($filterCustomer ?? '') === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filterItem" class="form-select form-select-sm filter-select" style="width:200px">
            <option value="">All Items</option>
        </select>
        <input type="date" id="filterDate" class="form-control form-control-sm" style="width:160px" title="Filter by Date Created" value="<?= htmlspecialchars($filterDate ?? '') ?>">
        <select class="form-select form-select-sm filter-select" style="width:180px" onchange="location.href='?controller=warehouse&action=purchaseOrders&delivery_status=' + encodeURIComponent(this.value)">
            <option value="">All POs</option>
            <option value="open" <?= ($filterDeliveryStatus ?? '') === 'open' ? 'selected' : '' ?>>Open POs</option>
            <option value="closed" <?= ($filterDeliveryStatus ?? '') === 'closed' ? 'selected' : '' ?>>Closed POs</option>
        </select>
        <a href="?controller=warehouse&action=purchaseOrders" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Clear</a>
    </div>
    <div class="search-box" style="width: 300px;">
        <form method="GET" class="d-flex align-items-center">
            <input type="hidden" name="controller" value="warehouse">
            <input type="hidden" name="action" value="purchaseOrders">
            <input type="hidden" name="filter_customer" value="<?= htmlspecialchars($filterCustomer ?? '') ?>">
            <input type="hidden" name="filter_item" value="<?= htmlspecialchars($filterItem ?? '') ?>">
            <input type="hidden" name="filter_date" value="<?= htmlspecialchars($filterDate ?? '') ?>">
            <i class="bi bi-search"></i>
            <input type="text" name="search" id="searchPO" class="form-control" placeholder="Search PO..." value="<?= htmlspecialchars($search ?? '') ?>">
        </form>
    </div>
</div>

<div class="card data-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="po_number">PO Number <i class="bi bi-chevron-expand"></i></th>
                        <th class="sortable" data-sort="customer">Customer <i class="bi bi-chevron-expand"></i></th>
                        <th>Item</th>
                        <th>Delivery Progress</th>
                        <th>Type</th>
                        <th class="sortable" data-sort="created_by">Created By <i class="bi bi-chevron-expand"></i></th>
                        <th class="sortable" data-sort="date">Date Created <i class="bi bi-chevron-expand"></i></th>
                        <th class="text-center action-col">Actions</th>
                    </tr>
                </thead>
                <tbody id="poTableBody">
                    <?php foreach ($purchase_orders as $po):
                        $items = $po_items_map[$po['po_id']] ?? [];
                        $itemCount = max(1, count($items));
                    ?>
                    <?php if (empty($items)): ?>
                    <tr>
                        <td><strong class="text-primary">
                        <?= $po['customer_po_number'] ?>
                        </strong></td>
                        <td><?= htmlspecialchars($po['customer_name'] ?? '-') ?></td>
                        <td><small class="text-muted">-</small></td>
                        <td><small class="text-muted">-</small></td>
                        <td>
                            <span class="badge bg-secondary">Normal</span>
                        </td>
                        <td><?= htmlspecialchars($po['requested_by_name'] ?? '-') ?></td>
                        <td><?= date('Y-m-d', strtotime($po['date_created'])) ?></td>
                        <td class="text-center action-col">
                            <button type="button" class="btn btn-sm btn-primary view-po-btn" data-po-id="<?= $po['po_id'] ?>"><i class="bi bi-eye"></i></button>
                            <button type="button" class="btn btn-sm btn-success edit-po-btn" data-po-id="<?= $po['po_id'] ?>"><i class="bi bi-pencil"></i></button>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($items as $idx => $item):
                        $qty = $item['quantity'] ?? 0;
                        $itemDelivered = intval($item['delivered_quantity'] ?? 0);
                        $itemPercent = $qty > 0 ? min(100, round(($itemDelivered / $qty) * 100)) : 0;
                        $pcsPerCase = intval($item['uom_conversion'] ?? 0);
                        $useCases = $pcsPerCase > 1;
                        $displayQty = $useCases ? (int) ceil($qty / $pcsPerCase) : $qty;
                        $displayDelivered = $useCases ? (int) ceil($itemDelivered / $pcsPerCase) : $itemDelivered;
                        $displayRemaining = max(0, $displayQty - $displayDelivered);
                        $displayUnit = $useCases ? 'cases' : 'pcs';
                        $statusLabel = $itemDelivered <= 0 ? 'Pending' : ($itemDelivered >= $qty ? 'Fully Delivered' : 'Partial (' . $displayRemaining . ' ' . $displayUnit . ' left)');
                        $statusClass = $itemDelivered >= $qty ? 'bg-success' : ($itemDelivered > 0 ? 'bg-warning text-dark' : 'bg-secondary');
                    ?>
                    <tr>
                        <?php if ($idx === 0): ?>
                        <td rowspan="<?= $itemCount ?>"><strong class="text-primary">
                        <?= $po['customer_po_number'] ?>
                        </strong></td>
                        <td rowspan="<?= $itemCount ?>"><?= htmlspecialchars($po['customer_name'] ?? '-') ?></td>
                        <?php endif; ?>
                        <td><small><?= htmlspecialchars($item['item_description'] ?? '-') ?></small></td>
                        <td>
                            <div class="d-flex align-items-center flex-wrap gap-1" style="min-height: 20px;">
                                <div class="progress flex-grow-1 me-2" style="height: 12px; width: 50px;">
                                    <div class="progress-bar <?= $itemDelivered >= $qty ? 'bg-success' : 'bg-warning' ?>" style="width: <?= $itemPercent ?>%"></div>
                                </div>
                                <small class="text-muted text-nowrap"><?= $displayDelivered ?>/<?= $displayQty ?> <?= $displayUnit ?></small>
                                <span class="badge <?= $statusClass ?> text-nowrap"><?= $statusLabel ?></span>
                            </div>
                        </td>
                        <?php if ($idx === 0): ?>
                        <td rowspan="<?= $itemCount ?>">
                            <span class="badge bg-secondary">Normal</span>
                        </td>
                        <td rowspan="<?= $itemCount ?>"><?= htmlspecialchars($po['requested_by_name'] ?? '-') ?></td>
                        <td rowspan="<?= $itemCount ?>"><?= date('Y-m-d', strtotime($po['date_created'])) ?></td>
                        <td rowspan="<?= $itemCount ?>" class="text-center action-col">
                            <button type="button" class="btn btn-sm btn-primary view-po-btn" data-po-id="<?= $po['po_id'] ?>"><i class="bi bi-eye"></i></button>
                            <button type="button" class="btn btn-sm btn-success edit-po-btn" data-po-id="<?= $po['po_id'] ?>"><i class="bi bi-pencil"></i></button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (empty($purchase_orders)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No purchase orders found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php if ($totalPages > 1): ?>
<?php $pages = \App\Helpers\Pagination::getPageRange($page, $totalPages); ?>
<?php $paginationParams = http_build_query(array_filter(['controller'=>'warehouse','action'=>'purchaseOrders','search'=>$search??'','filter_customer'=>$filterCustomer??'','filter_item'=>$filterItem??'','filter_date'=>$filterDate??''])); ?>
<?php $paginationBase = '?' . $paginationParams . (strpos($paginationParams, '&') !== false ? '&' : '') . 'page='; ?>
<nav>
    <ul class="pagination justify-content-center mt-4">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $paginationBase ?><?= $page - 1 ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= $paginationBase ?><?= $p ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $paginationBase ?><?= $page + 1 ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<div class="modal fade" id="createPOModal">
    <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cart3 me-2"></i>Create Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?controller=warehouse&action=createPO" id="createPOForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <select id="customerSelect" name="customer_id" class="form-select filter-select" required>
                            <option value="">Select Customer</option>
                            <?php 
                            $customers = (new \App\Models\WarehouseModel())->getCustomers();
                            foreach ($customers as $c): 
                            ?>
                                <option value="<?= $c['customer_id'] ?>"
                                    data-code="<?= htmlspecialchars($c['customer_code']) ?>"
                                    data-name="<?= htmlspecialchars($c['customer_name']) ?>"
                                    data-address="<?= htmlspecialchars($c['customer_address']) ?>"
                                    data-tin="<?= htmlspecialchars($c['customer_tin'] ?? '') ?>"
                                    data-terms="<?= $c['customer_terms'] ?? 0 ?>">
                                    <?= $c['customer_code'] ?> - <?= $c['customer_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="customerDetails" class="row g-2 mb-3 d-none">
                        <div class="col-md-2">
                            <label class="form-label">Customer Code</label>
                            <input type="text" id="customerCode" class="form-control" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Customer Name</label>
                            <input type="text" id="customerName" class="form-control" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Delivery Address</label>
                            <input type="text" id="customerAddress" class="form-control" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Customer TIN</label>
                            <input type="text" id="customerTin" class="form-control" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Terms (Days)</label>
                            <div class="input-group">
                                <input type="number" name="customer_terms" id="customerTerms" class="form-control" min="0" readonly>
                                <span class="input-group-text">days</span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Customer PO Number</label>
                            <input type="text" name="customer_po_number" class="form-control" placeholder="Enter PO Number" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Customer PO Date</label>
                            <input type="date" name="customer_po_date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Production Process</label>
                            <select name="production_type" id="productionType" class="form-select filter-select" required>
                                <option value="normal">Normal Production</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Items</label>
                        <div id="itemsContainer">
                            <div class="row g-2 mb-2 item-row align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Item</label>
                                    <select name="item_id[]" class="form-select item-select filter-select" required>
                                        <option value="">Select Customer first</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Code</label>
                                    <input type="text" class="form-control item-code" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control item-description" readonly>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">UOM</label>
                                    <input type="text" name="uom[]" class="form-control item-uom" readonly>
                                </div>
                                <div class="col-2">
                                    <label class="form-label">Qty</label>
                                    <input type="number" name="quantity[]" class="form-control" min="1" placeholder="Qty" required>
                                </div>
                                <div class="col-1 text-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-item mt-4"><i class="bi bi-trash"></i></button>
                                </div>
                                <input type="hidden" name="unit_price[]" class="unit-price">
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addItemBtn"><i class="bi bi-plus"></i> Add Item</button>
                    </div>
                    <input type="hidden" name="items_json" id="itemsJson">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Create PO</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create PO Preview Modal -->
<div class="modal fade" id="createPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Confirm Create PO</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Please review the PO details before saving.</p>
                <table class="table table-bordered mb-3">
                    <tr><th style="width:35%">Customer</th><td id="prevCCustomer"></td></tr>
                    <tr><th>Customer Code</th><td id="prevCCustomerCode"></td></tr>
                    <tr><th>Customer TIN</th><td id="prevCCustomerTin"></td></tr>
                    <tr><th>PO Number</th><td id="prevCPONumber"></td></tr>
                    <tr><th>PO Date</th><td id="prevCPODate"></td></tr>
                    <tr><th>Terms</th><td id="prevCTerms"></td></tr>
                    <tr><th>Production Type</th><td id="prevCProdType"></td></tr>
                </table>
                <strong>Items:</strong>
                <div id="prevCItems"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmCreateBtn"><i class="bi bi-check-lg me-1"></i>Confirm & Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewPOModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>PO Details - <span id="viewPONumber"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <p class="mb-1"><strong>Customer Code:</strong></p>
                        <p class="text-muted" id="viewCustomerCode">-</p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1"><strong>Customer Name:</strong></p>
                        <p class="text-muted" id="viewCustomerName">-</p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1"><strong>Customer TIN:</strong></p>
                        <p class="text-muted" id="viewCustomerTin">-</p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1"><strong>Terms:</strong></p>
                        <p class="text-muted" id="viewCustomerTerms">-</p>
                    </div>
                </div>
                <h5 class="mb-3">Items</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>UOM</th>
                                <th>Quantity</th>
                                <th>Cases</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody id="viewPOItems">
                        </tbody>
                    </table>
                </div>

                <hr>
                <h5 class="mb-3">Lot Tracker</h5>
                <div class="row mb-3 align-items-center">
                    <div class="col-md-4">
                        <label for="lotTrackerItem" class="form-label fw-bold">Select Item</label>
                        <select id="lotTrackerItem" class="form-select form-select-sm filter-select">
                            <option value="">-- Select Item --</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="lotTrackerTable">
                        <thead>
                            <tr>
                                <th>Lot No.</th>
                                <th class="text-end">Qty Delivered</th>
                            </tr>
                        </thead>
                        <tbody id="lotTrackerBody">
                            <tr><td colspan="2" class="text-center text-muted py-3">Select an item to view lots</td></tr>
                        </tbody>
                        <tfoot id="lotTrackerFoot" style="display:none;">
                            <tr class="table-light fw-bold">
                                <td>Total</td>
                                <td class="text-end" id="lotTrackerTotal">0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editPOModal">
    <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit PO - <span id="editPONumber"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?controller=warehouse&action=editPO" id="editPOForm">
                <input type="hidden" name="po_id" id="editPoId">
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Customer Code</label>
                            <input type="text" id="editCustomerCode" class="form-control" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Customer Name</label>
                            <input type="text" id="editCustomerName" class="form-control" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">PO Number</label>
                            <input type="text" name="customer_po_number" id="editPONumDisplay" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Customer TIN</label>
                            <input type="text" id="editCustomerTin" class="form-control" readonly>
                        </div>
                    </div>
                    <div class="row g-2 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Customer PO Date</label>
                            <input type="date" name="customer_po_date" id="editPODate" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Production Process</label>
                            <select name="production_type" id="editProductionType" class="form-select filter-select" required>
                                <option value="normal">Normal Production</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Terms</label>
                            <div class="input-group">
                                <input type="text" id="editCustomerTerms" class="form-control" readonly>
                                <span class="input-group-text">days</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Items</label>
                        <div id="editItemsContainer"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="editAddItemBtn"><i class="bi bi-plus"></i> Add Item</button>
                    </div>
                    <input type="hidden" name="items_json" id="editItemsJson">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit PO Preview Modal -->
<div class="modal fade" id="editPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Confirm Edit PO</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Please review the PO changes before saving.</p>
                <table class="table table-bordered mb-3">
                    <tr><th style="width:35%">Customer</th><td id="prevECustomer"></td></tr>
                    <tr><th>Customer Code</th><td id="prevECustomerCode"></td></tr>
                    <tr><th>Customer TIN</th><td id="prevECustomerTin"></td></tr>
                    <tr><th>PO Number</th><td id="prevEPONumber"></td></tr>
                    <tr><th>PO Date</th><td id="prevEPODate"></td></tr>
                    <tr><th>Terms</th><td id="prevETerms"></td></tr>
                    <tr><th>Production Type</th><td id="prevEProdType"></td></tr>
                </table>
                <strong>Items:</strong>
                <div id="prevEItems"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmEditBtn"><i class="bi bi-check-lg me-1"></i>Confirm & Save</button>
            </div>
        </div>
    </div>
</div>

<script>

/* ---- Searchable Select (backed by global app.js) ---- */
function makeSearchable(row) {
    var select = row.querySelector('.item-select');
    if (select && typeof initSearchableDropdown === 'function') initSearchableDropdown(select);
}

function refreshSearchables() {
    document.querySelectorAll('.item-select').forEach(function(sel) {
        if (typeof refreshSearchableDropdown === 'function') refreshSearchableDropdown(sel);
    });
}

/* ---- Modal events ---- */
document.getElementById('createPOModal').addEventListener('shown.bs.modal', function() {
    updateItemDropdowns();
    refreshSearchables();
});

document.getElementById('createPOModal').addEventListener('hidden.bs.modal', function() {
    const form = this.querySelector('form');
    form.reset();

    const customerDetails = document.getElementById('customerDetails');
    customerDetails.classList.add('d-none');

    document.getElementById('customerCode').value = '';
    document.getElementById('customerName').value = '';
    document.getElementById('customerAddress').value = '';
    document.getElementById('customerTin').value = '';
    document.getElementById('customerTerms').value = '';

    const itemsContainer = document.getElementById('itemsContainer');
    const firstRow = itemsContainer.querySelector('.item-row');
    itemsContainer.innerHTML = '';
    itemsContainer.appendChild(firstRow.cloneNode(true));
    const newFirstRow = itemsContainer.querySelector('.item-row');
    newFirstRow.querySelectorAll('input').forEach(function(el) { el.value = ''; });
    newFirstRow.querySelector('.item-select').value = '';
    var uomInput = newFirstRow.querySelector('.item-uom');
    if (uomInput) uomInput.value = 'PCS';
    setupItemRow(newFirstRow);

    updateRemoveButtons();
    updateItemDropdowns();
    document.getElementById('itemsJson').value = '';
    window._createFormConfirmed = false;
});

var _searchTimer;
document.getElementById('searchPO').addEventListener('input', function() {
    clearTimeout(_searchTimer);
    var form = this.closest('form');
    _searchTimer = setTimeout(function() { form.submit(); }, 500);
});

(function() {
    var s = document.getElementById('searchPO');
    if (s && s.value) { s.focus(); s.setSelectionRange(s.value.length, s.value.length); }
})();

function applyServerFilters() {
    var params = new URLSearchParams();
    params.set('controller', 'warehouse');
    params.set('action', 'purchaseOrders');
    var s = document.getElementById('searchPO');
    if (s && s.value) params.set('search', s.value);
    var c = document.getElementById('filterCustomer');
    if (c && c.value) params.set('filter_customer', c.value);
    var i = document.getElementById('filterItem');
    if (i && i.value) params.set('filter_item', i.value);
    var d = document.getElementById('filterDate');
    if (d && d.value) params.set('filter_date', d.value);
    window.location.href = '?' + params.toString();
}

function populateItemFilter() {
    const items = new Set();
    document.querySelectorAll('#poTableBody tr').forEach(row => {
        if (row.querySelector('td[colspan]')) return;
        const itemCell = row.cells[2];
        if (itemCell) {
            itemCell.querySelectorAll('small').forEach(s => {
                const t = s.textContent.trim();
                if (t && t !== '-') items.add(t);
            });
        }
    });
    const itemSel = document.getElementById('filterItem');
    items.forEach(i => { const o = document.createElement('option'); o.value = i; o.textContent = i; itemSel.appendChild(o); });
    var currentFilterItem = '<?= addslashes($filterItem ?? '') ?>';
    if (currentFilterItem && itemSel) { for (var j = 0; j < itemSel.options.length; j++) { if (itemSel.options[j].value === currentFilterItem) { itemSel.selectedIndex = j; break; } } }
}

document.getElementById('filterCustomer').addEventListener('change', applyServerFilters);
document.getElementById('filterItem').addEventListener('change', applyServerFilters);
document.getElementById('filterDate').addEventListener('change', applyServerFilters);

document.addEventListener('DOMContentLoaded', populateItemFilter);

const customerSelect = document.getElementById('customerSelect');
const customerDetails = document.getElementById('customerDetails');
const customerCode = document.getElementById('customerCode');
const customerName = document.getElementById('customerName');
const customerAddress = document.getElementById('customerAddress');
const customerTin = document.getElementById('customerTin');
const customerTerms = document.getElementById('customerTerms');

customerSelect.addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    if (!this.value) {
        customerDetails.classList.add('d-none');
        customerCode.value = customerName.value = customerAddress.value = customerTin.value = '';
        customerTerms.value = '';
        document.querySelectorAll('.item-select').forEach(function(sel) {
            sel.innerHTML = '<option value="">Select Customer first</option>';
            if (typeof refreshSearchableDropdown === 'function') refreshSearchableDropdown(sel);
        });
        document.querySelectorAll('.item-row').forEach(function(row, idx) {
            if (idx > 0) row.remove();
        });
        updateRemoveButtons();
        return;
    }
    customerCode.value = option.dataset.code || '';
    customerName.value = option.dataset.name || '';
    customerAddress.value = option.dataset.address || '';
    customerTin.value = option.dataset.tin || '';
    customerTerms.value = option.dataset.terms || '0';
    customerDetails.classList.remove('d-none');

    var itemsContainer = document.getElementById('itemsContainer');
    var firstRow = itemsContainer.querySelector('.item-row');
    var freshRow = firstRow.cloneNode(true);
    freshRow.querySelectorAll('input').forEach(function(el) { el.value = ''; });
    freshRow.querySelector('.item-select').value = '';
    if (typeof refreshSearchableDropdown === 'function') refreshSearchableDropdown(freshRow.querySelector('.item-select'));
    var fUom = freshRow.querySelector('.item-uom');
    if (fUom) fUom.value = 'PCS';
    itemsContainer.innerHTML = '';
    itemsContainer.appendChild(freshRow);
    setupItemRow(freshRow);
    updateRemoveButtons();

    var itemsPromise = fetch('?controller=warehouse&action=getItemsByCustomer&customer_id=' + this.value).then(function(r) { return r.json(); });

    itemsPromise.then(function(items) {
        var html = '<option value="">Select Item</option>';
        items.forEach(function(item) {
            html += '<option value="' + item.item_id + '"'
                + ' data-code="' + (item.item_code || '') + '"'
                + ' data-description="' + (item.item_description || '') + '"'
                + ' data-uom="' + (item.item_uom || '') + '"'
                + ' data-price="' + (item.item_amount || 0) + '">'
                + item.item_code + ' - ' + item.item_description
                + '</option>';
        });
        document.querySelectorAll('.item-select').forEach(function(sel) {
            sel.innerHTML = html;
        });
        refreshSearchables();
    });
});

function updateRemoveButtons() {
    const rows = document.querySelectorAll('.item-row');
    rows.forEach((row) => {
        const removeButton = row.querySelector('.remove-item');
        if (removeButton) {
            removeButton.style.display = rows.length > 1 ? 'inline-flex' : 'none';
            removeButton.disabled = rows.length <= 1;
        }
    });
}

function updateItemDropdowns() {
    const selects = document.querySelectorAll('.item-select');
    const selectedValues = [];
    selects.forEach(function(sel) {
        if (sel.value) selectedValues.push(sel.value);
    });
    selects.forEach(function(sel) {
        Array.from(sel.options).forEach(function(opt) {
            if (!opt.value) return;
            const isSelectedElsewhere = selectedValues.includes(opt.value) && sel.value !== opt.value;
            opt.disabled = isSelectedElsewhere;
        });
    });
    refreshSearchables();
}

function setupItemRow(row) {
    const select = row.querySelector('.item-select');
    const codeInput = row.querySelector('.item-code');
    const descInput = row.querySelector('.item-description');
    const uomInput = row.querySelector('.item-uom');
    const priceInput = row.querySelector('.unit-price');

    if (uomInput && !uomInput.value) uomInput.value = 'PCS';

    select.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        codeInput.value = selected.dataset.code || '';
        descInput.value = selected.dataset.description || '';
        uomInput.value = selected.dataset.uom || 'PCS';
        priceInput.value = selected.dataset.price || '';
        updateItemDropdowns();
    });

    const removeButton = row.querySelector('.remove-item');
    removeButton.addEventListener('click', function() {
        const rows = document.querySelectorAll('.item-row');
        if (rows.length > 1) {
            row.remove();
            updateRemoveButtons();
            updateItemDropdowns();
        }
    });

    makeSearchable(row);
}

const initialRow = document.querySelector('.item-row');
if (initialRow) {
    setupItemRow(initialRow);
}
updateRemoveButtons();
updateItemDropdowns();

document.getElementById('addItemBtn').addEventListener('click', function() {
    const container = document.getElementById('itemsContainer');
    const itemTemplate = container.querySelector('.item-row').cloneNode(true);
    itemTemplate.querySelectorAll('input').forEach(function(el) { el.value = ''; });
    itemTemplate.querySelector('.item-select').value = '';
    var uom = itemTemplate.querySelector('.item-uom');
    if (uom) uom.value = 'PCS';
    setupItemRow(itemTemplate);
    container.appendChild(itemTemplate);
    updateRemoveButtons();
    updateItemDropdowns();
});

document.getElementById('createPOForm').addEventListener('submit', function(e) {
    if (window._createFormConfirmed) return;
    e.preventDefault();

    var customerId = document.getElementById('customerSelect').value;
    var poNumber = document.querySelector('#createPOForm input[name="customer_po_number"]').value.trim();
    var poDate = document.querySelector('#createPOForm input[name="customer_po_date"]').value;
    var prodType = document.querySelector('#createPOForm select[name="production_type"]').value;

    if (!customerId) { Dlg.alert('Please select a customer.'); return; }
    if (!poNumber) { Dlg.alert('Please enter a PO number.'); return; }
    if (!poDate) { Dlg.alert('Please enter a PO date.'); return; }

    var items = [];
    document.querySelectorAll('#itemsContainer .item-row').forEach(function(row) {
        var itemId = row.querySelector('[name="item_id[]"]').value;
        var quantity = row.querySelector('[name="quantity[]"]').value;
        var unitPrice = row.querySelector('.unit-price').value;
        var uom = row.querySelector('[name="uom[]"]').value;
        var itemCode = row.querySelector('.item-code').value;
        var itemDescription = row.querySelector('.item-description').value;
        if (itemId && quantity) {
            items.push({
                item_id: itemId, quantity: quantity, unit_price: unitPrice,
                uom: uom, item_code: itemCode, item_description: itemDescription
            });
        }
    });

    if (items.length === 0) { Dlg.alert('Please add at least one item.'); return; }

    document.getElementById('itemsJson').value = JSON.stringify(items);

    var customerName = document.getElementById('customerName').value || '-';
    var customerCode = document.getElementById('customerCode').value || '-';
    var customerTin = document.getElementById('customerTin').value || '-';
    var terms = document.getElementById('customerTerms').value || '0';

    document.getElementById('prevCCustomer').textContent = customerName;
    document.getElementById('prevCCustomerCode').textContent = customerCode;
    document.getElementById('prevCCustomerTin').textContent = customerTin;
    document.getElementById('prevCPONumber').textContent = poNumber;
    document.getElementById('prevCPODate').textContent = poDate;
    document.getElementById('prevCTerms').textContent = terms + ' days';
    document.getElementById('prevCProdType').textContent = 'Normal';

    var itemsHtml = '<table class="table table-sm table-bordered mb-0"><thead><tr><th>Item Code</th><th>Description</th><th>UOM</th><th>Qty</th></tr></thead><tbody>';
    items.forEach(function(item) {
        var qty = parseInt(item.quantity) || 0;
        var qtyCell = '<td>' + qty + '</td>';
        itemsHtml += '<tr><td>' + (item.item_code || '-') + '</td><td>' + (item.item_description || '-') + '</td><td>' + (item.uom || 'PCS') + '</td>' + qtyCell + '</tr>';
    });
    itemsHtml += '</tbody></table>';
    document.getElementById('prevCItems').innerHTML = itemsHtml;

    new bootstrap.Modal(document.getElementById('createPreviewModal')).show();
});

window._createFormConfirmed = false;

document.getElementById('confirmCreateBtn').addEventListener('click', function() {
    bootstrap.Modal.getInstance(document.getElementById('createPreviewModal')).hide();
    window._createFormConfirmed = true;
    document.getElementById('createPOForm').submit();
});

document.querySelectorAll('.view-po-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const poId = this.dataset.poId || this.getAttribute('data-po-id');
        console.log('View clicked, poId:', poId);
        fetch('?controller=warehouse&action=getPODetails&id=' + poId)
            .then(function(response) {
                console.log('Response:', response.status);
                return response.json();
            })
            .then(function(data) {
                const po = data.po;
                const items = data.po_items;
                
                document.getElementById('viewPONumber').textContent = po.customer_po_number || '-';
                document.getElementById('viewCustomerCode').textContent = po.customer_code || '-';
                document.getElementById('viewCustomerName').textContent = po.customer_name || '-';
                document.getElementById('viewCustomerTin').textContent = po.customer_tin || '-';
                document.getElementById('viewCustomerTerms').textContent = (po.customer_terms || 0) + ' days';
                
                const total = po.total_quantity || 0;
                const produced = po.produced_quantity || 0;
                
                const tbody = document.getElementById('viewPOItems');
                tbody.innerHTML = '';
                if (items && items.length > 0) {
                    items.forEach(function(item, idx) {
                        const qty = item.quantity || 0;
                        const itemProduced = item.produced_quantity || 0;
                        const itemPercent = qty > 0 ? Math.round((itemProduced / qty) * 100) : 0;
                        const isExcess = itemProduced > qty;
                        const barClass = isExcess ? 'bg-danger' : (itemPercent >= 100 ? 'bg-success' : 'bg-warning');
                        const barWidth = Math.min(itemPercent, 100);
                        const excessBadge = '';
                        const lineTotal = item.quantity * item.unit_price;
                        const conv = item.uom_conversion || null;
                        let casesHtml = '—';
                        if (conv && item.item_uom !== 'CS') {
                            casesHtml = Math.floor(qty / conv) + ' CS';
                        }
                        const row = '<tr>' +
                            '<td>' + (item.item_code || '-') + '</td>' +
                            '<td>' + (item.item_description || '-') + '</td>' +
                            '<td>' + (item.item_uom || '-') + '</td>' +
                            '<td>' + qty + '</td>' +
                            '<td>' + casesHtml + '</td>' +
                            '<td>' +
                                '<div class="d-flex align-items-center flex-wrap gap-1">' +
                                    '<div class="progress flex-grow-1 me-2" style="height: 14px; width: 80px;">' +
                                        '<div class="progress-bar ' + barClass + '" style="width: ' + barWidth + '%"></div>' +
                                    '</div>' +
                                    '<small class="text-muted">' + itemProduced + '/' + qty + ' pcs</small>' +
                                    excessBadge +
                                '</div>' +
                            '</td>' +
                            '</tr>';
                        tbody.innerHTML += row;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No items found</td></tr>';
                }

                var lotSelect = document.getElementById('lotTrackerItem');
                lotSelect.innerHTML = '<option value="">-- Select Item --</option>';
                if (items && items.length > 0) {
                    items.forEach(function(item) {
                        lotSelect.innerHTML += '<option value="' + item.poi_id + '">' +
                            (item.item_code || '') + ' - ' + (item.item_description || '-') +
                            '</option>';
                    });
                }
                document.getElementById('lotTrackerBody').innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">Select an item to view lots</td></tr>';
                document.getElementById('lotTrackerFoot').style.display = 'none';
                
                const modal = new bootstrap.Modal(document.getElementById('viewPOModal'));
                modal.show();
            })
            .catch(function(error) {
                console.error('Error:', error);
                Dlg.alert('Failed to load PO details');
            });
    });
});

function loadLots(poiId) {
    var tbody = document.getElementById('lotTrackerBody');
    var foot = document.getElementById('lotTrackerFoot');
    var totalEl = document.getElementById('lotTrackerTotal');
    if (!poiId) {
        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">Select an item to view lots</td></tr>';
        foot.style.display = 'none';
        return;
    }
    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">Loading...</td></tr>';
    foot.style.display = 'none';
    fetch('?controller=warehouse&action=getLotsByPOItem&poi_id=' + poiId + '&_t=' + Date.now())
        .then(function(r) { return r.json(); })
        .then(function(lots) {
            tbody.innerHTML = '';
            if (lots && lots.length > 0) {
                var total = 0;
                lots.forEach(function(lot) {
                    var qty = parseInt(lot.quantity_delivered || lot.quantity_produced) || 0;
                    total += qty;
                    tbody.innerHTML += '<tr>' +
                        '<td><strong>' + (lot.lot_number || '-') + '</strong></td>' +
                        '<td class="text-end">' + qty.toLocaleString() + '</td>' +
                        '</tr>';
                });
                totalEl.textContent = total.toLocaleString();
                foot.style.display = '';
            } else {
                tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">No lots created</td></tr>';
                foot.style.display = 'none';
            }
        })
        .catch(function() {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-3">Failed to load lots</td></tr>';
            foot.style.display = 'none';
        });
}

document.getElementById('lotTrackerItem').addEventListener('change', function() {
    loadLots(this.value);
});

document.querySelectorAll('.sortable').forEach(th => {
    th.addEventListener('click', function() {
        const table = this.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const col = this.cellIndex;
        const asc = !this.classList.contains('asc');
        
        table.querySelectorAll('.sortable').forEach(h => {
            h.classList.remove('asc', 'desc');
            h.querySelector('i').className = 'bi bi-chevron-expand';
        });
        
        this.classList.add(asc ? 'asc' : 'desc');
        this.querySelector('i').className = asc ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        
        rows.sort((a, b) => {
            let aVal = a.cells[col].textContent.trim();
            let bVal = b.cells[col].textContent.trim();
            if (!isNaN(aVal) && !isNaN(bVal)) {
                aVal = parseFloat(aVal);
                bVal = parseFloat(bVal);
            }
            return asc ? aVal.localeCompare(bVal, undefined, {numeric: true}) : bVal.localeCompare(aVal, undefined, {numeric: true});
        });
        
        rows.forEach(row => tbody.appendChild(row));
    });
});

/* ============================================================
   EDIT PO
   ============================================================ */
var editCustomerId = null;

function buildEditItemRow(poiId, itemId, itemCode, itemDesc, itemUom, qty, unitPrice, allItems, usedIds) {
    var row = document.createElement('div');
    row.className = 'row g-2 mb-2 item-row align-items-end';
    row.dataset.poiId = poiId || '';
    row.dataset.itemId = itemId || '';
    row.dataset.originalQty = qty || '';

    var isExisting = !!poiId;
    usedIds = usedIds || [];

    var selectHtml = '<select name="item_id[]" class="form-select item-select filter-select" required>';
    selectHtml += '<option value="">Select Item</option>';
    if (allItems) {
        allItems.forEach(function(it) {
            var sel = it.item_id == itemId ? ' selected' : '';
            var dis = (!isExisting && it.item_id != itemId && usedIds.indexOf(String(it.item_id)) > -1) ? ' disabled' : '';
            selectHtml += '<option value="' + it.item_id + '" data-code="' + (it.item_code || '') + '" data-description="' + (it.item_description || '') + '" data-uom="' + (it.item_uom || '') + '" data-price="' + (it.item_amount || 0) + '"' + sel + dis + '>' + it.item_code + ' - ' + it.item_description + '</option>';
        });
    }
    selectHtml += '</select>';

    var itemFieldHtml =
        '<label class="form-label">Item</label>' +
        selectHtml;

    row.innerHTML =
        '<input type="hidden" name="poi_id[]" class="edit-poi-id" value="' + (poiId || '') + '">' +
        '<div class="col-md-3">' + itemFieldHtml + '</div>' +
        '<div class="col-md-2">' +
            '<label class="form-label">Code</label>' +
            '<input type="text" class="form-control item-code" readonly value="' + (itemCode || '') + '">' +
        '</div>' +
        '<div class="col-md-3">' +
            '<label class="form-label">Description</label>' +
            '<input type="text" class="form-control item-description" readonly value="' + (itemDesc || '') + '">' +
        '</div>' +
        '<div class="col-md-1">' +
            '<label class="form-label">UOM</label>' +
            '<input type="text" name="uom[]" class="form-control item-uom" readonly value="' + (itemUom || 'PCS') + '">' +
        '</div>' +
        '<div class="col-md-2">' +
            '<label class="form-label">Qty</label>' +
            '<input type="number" name="quantity[]" class="form-control" min="1" placeholder="Qty" value="' + (qty || '') + '" required>' +
        '</div>' +
        (isExisting
            ? '<div class="col-md-1"></div>'
            : '<div class="col-md-1 text-end"><button type="button" class="btn btn-outline-danger btn-sm remove-item mt-4"><i class="bi bi-trash"></i></button></div>'
        ) +
        '<input type="hidden" name="unit_price[]" class="unit-price" value="' + (unitPrice || 0) + '">';

    return row;
}

function setupEditItemRow(row) {
    var select = row.querySelector('.item-select');
    var codeInput = row.querySelector('.item-code');
    var descInput = row.querySelector('.item-description');
    var uomInput = row.querySelector('.item-uom');
    var priceInput = row.querySelector('.unit-price');

    select.addEventListener('change', function() {
        var selected = this.options[this.selectedIndex];
        codeInput.value = selected.dataset.code || '';
        descInput.value = selected.dataset.description || '';
        uomInput.value = selected.dataset.uom || 'PCS';
        priceInput.value = selected.dataset.price || '';
    });

    var removeButton = row.querySelector('.remove-item');
    if (removeButton) {
        removeButton.addEventListener('click', function() {
            var rows = document.querySelectorAll('#editItemsContainer .item-row');
            if (rows.length > 1) {
                row.remove();
                updateEditRemoveButtons();
            }
        });
    }

    makeSearchable(row);
}

function updateEditRemoveButtons() {
    var rows = document.querySelectorAll('#editItemsContainer .item-row');
    var newRows = [];
    rows.forEach(function(row) {
        var btn = row.querySelector('.remove-item');
        if (btn) newRows.push(row);
    });
    newRows.forEach(function(row) {
        var btn = row.querySelector('.remove-item');
        if (btn) {
            btn.style.display = newRows.length > 1 ? 'inline-flex' : 'none';
            btn.disabled = newRows.length <= 1;
        }
    });
}

document.querySelectorAll('.edit-po-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var poId = this.dataset.poId;

        fetch('?controller=warehouse&action=getPODetails&id=' + poId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var po = data.po;
                var poItems = data.po_items;

                document.getElementById('editPoId').value = po.po_id;
                document.getElementById('editPONumber').textContent = po.customer_po_number || '';
                document.getElementById('editPONumDisplay').value = po.customer_po_number || '';
                document.getElementById('editCustomerCode').value = po.customer_code || '';
                document.getElementById('editCustomerName').value = po.customer_name || '';
                document.getElementById('editCustomerTin').value = po.customer_tin || '';
                document.getElementById('editPODate').value = po.customer_po_date || '';
                document.getElementById('editProductionType').value = po.production_type || 'normal';
                document.getElementById('editCustomerTerms').value = (po.customer_terms || 0) + ' days';
                editCustomerId = po.customer_id;

                fetch('?controller=warehouse&action=getItemsByCustomer&customer_id=' + po.customer_id)
                    .then(function(r2) { return r2.json(); })
                    .then(function(allItems) {
                        var container = document.getElementById('editItemsContainer');
                        container.innerHTML = '';

                        if (poItems && poItems.length > 0) {
                            poItems.forEach(function(item) {
                                var row = buildEditItemRow(
                                    item.poi_id, item.item_id,
                                    item.item_code, item.item_description,
                                    item.item_uom, item.quantity, item.unit_price,
                                    allItems
                                );
                                container.appendChild(row);
                                setupEditItemRow(row);
                            });
                        }

                        updateEditRemoveButtons();

                        var modal = new bootstrap.Modal(document.getElementById('editPOModal'));
                        modal.show();
                    });
            })
            .catch(function() {
                Dlg.alert('Failed to load PO details');
            });
    });
});

document.getElementById('editAddItemBtn').addEventListener('click', function() {
    fetch('?controller=warehouse&action=getItemsByCustomer&customer_id=' + editCustomerId)
        .then(function(r) { return r.json(); })
        .then(function(allItems) {
            var usedIds = [];
            document.querySelectorAll('#editItemsContainer .item-row').forEach(function(r) {
                var v = r.querySelector('.item-select').value;
                if (v) usedIds.push(v);
            });
            var container = document.getElementById('editItemsContainer');
            var row = buildEditItemRow('', '', '', '', '', '', 0, allItems, usedIds);
            container.appendChild(row);
            setupEditItemRow(row);
            updateEditRemoveButtons();
        });
});

document.getElementById('editPOModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('editItemsContainer').innerHTML = '';
    document.getElementById('editItemsJson').value = '';
    window._editFormConfirmed = false;
});

document.getElementById('editPOForm').addEventListener('submit', function(e) {
    if (window._editFormConfirmed) return;
    e.preventDefault();

    var poDate = document.getElementById('editPODate').value;
    if (!poDate) { Dlg.alert('Please enter a PO date.'); return; }

    var items = [];
    document.querySelectorAll('#editItemsContainer .item-row').forEach(function(row) {
        var itemId = row.querySelector('.item-select').value;
        var quantity = row.querySelector('[name="quantity[]"]').value;
        var unitPrice = row.querySelector('.unit-price').value;
        var uom = row.querySelector('[name="uom[]"]').value;
        var poiId = row.querySelector('.edit-poi-id').value;
        if (itemId && quantity) {
            items.push({
                poi_id: poiId || null,
                item_id: itemId,
                quantity: quantity,
                unit_price: unitPrice,
                uom: uom
            });
        }
    });

    if (items.length === 0) { Dlg.alert('Please add at least one item.'); return; }

    document.getElementById('editItemsJson').value = JSON.stringify(items);

    var customerName = document.getElementById('editCustomerName').value || '-';
    var customerCode = document.getElementById('editCustomerCode').value || '-';
    var customerTin = document.getElementById('editCustomerTin').value || '-';
    var poNumber = document.getElementById('editPONumDisplay').value || '-';
    var terms = document.getElementById('editCustomerTerms').value || '-';
    var prodType = document.getElementById('editProductionType').value;

    document.getElementById('prevECustomer').textContent = customerName;
    document.getElementById('prevECustomerCode').textContent = customerCode;
    document.getElementById('prevECustomerTin').textContent = customerTin;
    document.getElementById('prevEPONumber').textContent = poNumber;
    document.getElementById('prevEPODate').textContent = poDate;
    document.getElementById('prevETerms').textContent = terms;
    document.getElementById('prevEProdType').textContent = 'Normal';

    var itemsHtml = '<table class="table table-sm table-bordered mb-0"><thead><tr><th>Item Code</th><th>Description</th><th>UOM</th><th>Qty</th><th>Status</th></tr></thead><tbody>';
    document.querySelectorAll('#editItemsContainer .item-row').forEach(function(row) {
        var itemId = row.querySelector('.item-select').value;
        var quantity = row.querySelector('[name="quantity[]"]').value;
        var poiId = row.querySelector('.edit-poi-id').value;
        var code = row.querySelector('.item-code') ? row.querySelector('.item-code').value : '';
        var desc = row.querySelector('.item-description') ? row.querySelector('.item-description').value : '';
        var uom = row.querySelector('[name="uom[]"]') ? row.querySelector('[name="uom[]"]').value : 'PCS';
        if (!itemId || !quantity) return;
        var origQty = row.dataset.originalQty || '';
        var isNew = !poiId;
        var isChanged = poiId && origQty !== '' && String(quantity) !== String(origQty);
        var badge;
        if (isNew) {
            badge = '<span class="badge bg-success">NEW</span>';
        } else if (isChanged) {
            badge = '<span class="badge bg-warning text-dark">UPDATE</span>';
        } else {
            badge = '<span class="badge bg-secondary">EXISTING</span>';
        }
        itemsHtml += '<tr><td>' + (code || '-') + '</td><td>' + (desc || '-') + '</td><td>' + (uom || 'PCS') + '</td><td>' + quantity + '</td><td>' + badge + '</td></tr>';
    });
    itemsHtml += '</tbody></table>';
    document.getElementById('prevEItems').innerHTML = itemsHtml;

    new bootstrap.Modal(document.getElementById('editPreviewModal')).show();
});

window._editFormConfirmed = false;

document.getElementById('confirmEditBtn').addEventListener('click', function() {
    bootstrap.Modal.getInstance(document.getElementById('editPreviewModal')).hide();
    window._editFormConfirmed = true;
    document.getElementById('editPOForm').submit();
});
</script>