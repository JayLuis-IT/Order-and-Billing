<div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card p-3 h-100">
                <h6 class="text-muted">Total Customers</h6>
                <h3><?= count($customers ?? []) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-3 h-100">
                <h6 class="text-muted">Available Items</h6>
                <h3><?= count($items ?? []) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-3 h-100">
                <h6 class="text-muted">Total PO</h6>
                <h3><?= $allPOCount ?? count($purchase_orders ?? []) ?></h3>
            </div>
        </div>
    </div>
    
    <div class="card data-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-cart3 me-2"></i>Open Purchase Order</span>
            <a href="?controller=warehouse&action=purchaseOrders" class="btn btn-primary btn-sm">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>PO Date</th>
                        <th>Customer</th>
                        <th>Item</th>
<th>Delivery Progress</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchase_orders ?? [] as $po):
                        $items = $po_items_map[$po['po_id']] ?? [];
                    ?>
                    <tr>
                        <td><strong><?= $po['customer_po_number'] ?></strong></td>
                        <td><?= date('Y-m-d', strtotime($po['customer_po_date'])) ?></td>
                        <td><?= htmlspecialchars($po['customer_name'] ?? '-') ?></td>
                        <td>
                            <?php if (!empty($items)): ?>
                                <?php foreach ($items as $idx => $item): ?>
                                    <?= $idx > 0 ? '<hr class="my-1 border-secondary">' : '' ?>
                                    <div class="d-flex align-items-center" style="min-height: 20px;">
                                        <small><?= htmlspecialchars($item['item_description'] ?? '-') ?></small>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($items)): ?>
                                <?php foreach ($items as $idx => $item):
                                    $qty = intval($item['quantity'] ?? 0);
                                    $itemDelivered = intval($item['delivered_quantity'] ?? 0);
                                    $itemPercent = $qty > 0 ? min(100, round(($itemDelivered / $qty) * 100)) : 0;
                                    $remaining = max(0, $qty - $itemDelivered);
                                ?>
                                    <?= $idx > 0 ? '<hr class="my-1 border-secondary">' : '' ?>
                                    <div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:10px"><div class="progress-bar <?= $itemDelivered >= $qty ? 'bg-success' : 'bg-warning' ?>" style="width:<?= $itemPercent ?>%"></div></div><small class="text-nowrap"><?= $itemDelivered ?>/<?= $qty ?> pcs</small><span class="badge <?= $itemDelivered >= $qty ? 'bg-success' : ($itemDelivered > 0 ? 'bg-warning text-dark' : 'bg-secondary') ?> text-nowrap"><?= $itemDelivered <= 0 ? 'Pending' : ($itemDelivered >= $qty ? 'Fully Delivered' : 'Partial (' . $remaining . ' left)') ?></span></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary">Normal</span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($purchase_orders)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">No purchase orders yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<div class="card data-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-truck me-2"></i>Recent Deliveries</span>
        <a href="?controller=warehouse&action=deliveries" class="btn btn-primary btn-sm">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Customer</th>
                    <th>Item / Lot</th>
                    <th>PO Qty</th>
                    <th>Delivered</th>
                    <th>Type</th>
                    <th>Delivery Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($deliveries ?? [], 0, 5) as $d):
                    $lotItems = json_decode($d['lot_items'] ?? '[]', true);
                    $hasLotItems = is_array($lotItems) && count($lotItems) > 0;
                    $poItems = $po_items_map[$d['po_id']] ?? [];
                    $poItemLookup = [];
                    foreach ($poItems as $pi) { $poItemLookup[$pi['item_code']] = $pi; }

                    $itemLines = [];
                    $poQtyLines = [];
                    $deliveredLines = [];

                    if ($hasLotItems) {
                        $idx = 0;
                        foreach ($lotItems as $li) {
                            $liCode = $li['item_code'] ?? '';
                            $liQty = $li['qty'] ?? 0;
                            $liDesc = $li['item_description'] ?? $liCode;
                            $liLot = $li['lot_number'] ?? '';
                            $poItem = $poItemLookup[$liCode] ?? null;
                            $poQty = $poItem ? $poItem['quantity'] : 0;
                            $sep = $idx < count($lotItems) - 1 ? ' border-bottom pb-2 mb-2' : '';

                            $itemLines[] = '<div class="' . $sep . '"><small>' . htmlspecialchars($liDesc) . '</small><br><small class="text-muted">' . htmlspecialchars($liLot) . '</small></div>';
                            $poQtyLines[] = '<div class="' . $sep . '">' . $poQty . '</div>';
                            $deliveredLines[] = '<div class="' . $sep . '">' . $liQty . '</div>';
                            $idx++;
                        }
                    } else {
                        $dItemQty = $d['item_quantity'] ?? 0;
                        $dDelivered = $d['delivery_quantity'] ?? 0;
                        $itemLines[] = '<small>' . htmlspecialchars(($d['item_code'] ?? '-') . ' - ' . ($d['item_description'] ?? '')) . '</small>';
                        $poQtyLines[] = $dItemQty;
                        $deliveredLines[] = $dDelivered;
                    }
                ?>
                <tr>
                    <td><strong class="text-primary"><?= $d['customer_po_number'] ?></strong></td>
                    <td><?= htmlspecialchars($d['customer_name'] ?? '-') ?></td>
                    <td><?= implode('<br>', $itemLines) ?></td>
                    <td><?= implode('<br>', $poQtyLines) ?></td>
                    <td><?= implode('<br>', $deliveredLines) ?></td>
                    <td><span class="badge bg-secondary">Normal</span></td>
                    <td><?= date('Y-m-d', strtotime($d['delivery_date'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($deliveries)): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">No deliveries yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

    <div class="modal fade" id="viewPOModal" tabindex="-1">
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
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?controller=warehouse&action=createPO" id="createPOFormDash">
                <div class="modal-body">
                    <div class="row">
                                                $itemPercent = $itemQty > 0 ? min(100, round(($itemDelivered / $itemQty) * 100)) : 0;
                                                $statusLabel = $itemDelivered <= 0 ? 'Pending' : ($itemDelivered >= $itemQty ? 'Fully Delivered' : 'Partial (' . ($itemQty - $itemDelivered) . ' left)');
                                                $statusClass = $itemDelivered >= $itemQty ? 'bg-success' : ($itemDelivered > 0 ? 'bg-warning text-dark' : 'bg-secondary');
                        <div class="col-md-4 mb-3">
                            <label class="form-label">PO Number</label>
                                                <div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:10px"><div class="progress-bar <?= $itemDelivered >= $itemQty ? 'bg-success' : 'bg-warning' ?>" style="width:<?= $itemPercent ?>%"></div></div><small class="text-nowrap"><?= $itemDelivered ?>/<?= $itemQty ?> pcs</small><span class="badge <?= $statusClass ?> text-nowrap"><?= $statusLabel ?></span></div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">PO Date</label>
                            <input type="date" name="customer_po_date" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Production Process</label>
                            <select name="production_type" class="form-select filter-select" required>
                                <option value="normal">Normal Production</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <select id="dashCustomerSelect" name="customer_id" class="form-select filter-select" required>
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $c): ?>
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
                    <div class="mb-3">
                        <label class="form-label">Items</label>
                        <div id="itemsContainer">
                            <div class="row g-2 mb-2 item-row">
                                <div class="col-5">
                                    <select name="item_id[]" class="form-select item-select filter-select" required>
                                        <option value="">Select Customer first</option>
                                    </select>
                                </div>
                                <div class="col-3">
                                    <input type="number" name="quantity[]" class="form-control" placeholder="Quantity" required>
                                </div>
                                <div class="col-3">
                                    <input type="number" name="unit_price[]" class="form-control unit-price" placeholder="Unit Price" step="0.01" required>
                                </div>
                                <div class="col-1">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-item"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addItemBtn"><i class="bi bi-plus"></i> Add Item</button>
                    </div>
                    <input type="hidden" name="items_json" id="itemsJson">
                    <input type="hidden" name="customer_terms" id="dashCustomerTerms" value="0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Create PO</button>
                </div>
            </form>
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

document.getElementById('addItemBtn').addEventListener('click', function() {
    const container = document.getElementById('itemsContainer');
    const itemTemplate = container.querySelector('.item-row').cloneNode(true);
    itemTemplate.querySelectorAll('input').forEach(function(el) { el.value = ''; });
    itemTemplate.querySelector('.item-select').value = '';
    container.appendChild(itemTemplate);
    makeSearchable(itemTemplate);
});

document.getElementById('dashCustomerSelect').addEventListener('change', function() {
    if (!this.value) {
        document.getElementById('dashCustomerTerms').value = '0';
        document.querySelectorAll('.item-select').forEach(function(sel) {
            sel.innerHTML = '<option value="">Select Customer first</option>';
        });
        refreshSearchables();
        return;
    }
    const option = this.options[this.selectedIndex];
    document.getElementById('dashCustomerTerms').value = option.dataset.terms || '0';
    fetch('?controller=warehouse&action=getItemsByCustomer&customer_id=' + this.value)
        .then(function(r) { return r.json(); })
        .then(function(items) {
            var html = '<option value="">Select Item</option>';
            items.forEach(function(item) {
                html += '<option value="' + item.item_id + '" data-price="' + (item.item_amount || 0) + '">' + item.item_code + ' - ' + item.item_description + '</option>';
            });
            document.querySelectorAll('.item-select').forEach(function(sel) { sel.innerHTML = html; });
            refreshSearchables();
        });
});

document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-item')) {
        const rows = document.querySelectorAll('.item-row');
        if (rows.length > 1) e.target.closest('.item-row').remove();
    }
});

document.querySelectorAll('.item-select').forEach(select => {
    select.addEventListener('change', function() {
        const price = this.options[this.selectedIndex].dataset.price || 0;
        this.closest('.item-row').querySelector('.unit-price').value = price;
    });
});

document.querySelectorAll('.item-row').forEach(function(row) { makeSearchable(row); });

document.getElementById('createPOFormDash').addEventListener('submit', function() {
    const items = [];
    document.querySelectorAll('.item-row').forEach(row => {
        items.push({
            item_id: row.querySelector('[name="item_id[]"]').value,
            quantity: row.querySelector('[name="quantity[]"]').value,
            unit_price: row.querySelector('.unit-price').value
        });
    });
    document.getElementById('itemsJson').value = JSON.stringify(items);
});

document.querySelectorAll('.view-po-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const poId = this.dataset.poId || this.getAttribute('data-po-id');
        fetch('?controller=warehouse&action=getPODetails&id=' + poId)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                const po = data.po;
                const items = data.po_items;

                document.getElementById('viewPONumber').textContent = po.customer_po_number || '-';
                document.getElementById('viewCustomerCode').textContent = po.customer_code || '-';
                document.getElementById('viewCustomerName').textContent = po.customer_name || '-';
                document.getElementById('viewCustomerTin').textContent = po.customer_tin || '-';
                document.getElementById('viewCustomerTerms').textContent = (po.customer_terms || 0) + ' days';

                const tbody = document.getElementById('viewPOItems');
                tbody.innerHTML = '';
                if (items && items.length > 0) {
                    items.forEach(function(item) {
                        const qty = item.quantity || 0;
                        const itemProduced = item.produced_quantity || 0;
                        const itemPercent = qty > 0 ? Math.round((itemProduced / qty) * 100) : 0;
                        const isExcess = itemProduced > qty;
                        const barClass = isExcess ? 'bg-danger' : (itemPercent >= 100 ? 'bg-success' : 'bg-warning');
                        const barWidth = Math.min(itemPercent, 100);
                        const lineTotal = item.quantity * item.unit_price;
                        const row = '<tr>' +
                            '<td>' + (item.item_code || '-') + '</td>' +
                            '<td>' + (item.item_description || '-') + '</td>' +
                            '<td>' + (item.item_uom || '-') + '</td>' +
                            '<td>' + qty + '</td>' +
                            '<td>' +
                                '<div class="d-flex align-items-center">' +
                                    '<div class="progress flex-grow-1 me-2" style="height: 14px; width: 80px;">' +
                                        '<div class="progress-bar ' + barClass + '" style="width: ' + barWidth + '%"></div>' +
                                    '</div>' +
                                    '<small class=\"text-muted\">' + itemProduced + '/' + qty + ' pcs</small>' +
                                '</div>' +
                            '</td>' +
                            '</tr>';
                        tbody.innerHTML += row;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No items found</td></tr>';
                }

                const modal = new bootstrap.Modal(document.getElementById('viewPOModal'));
                modal.show();
            })
            .catch(function(error) {
                Dlg.alert('Failed to load PO details');
            });
    });
});
</script>