<?php
/*
    Print Delivery Receipt – UI for selecting a PO, entering a DR number, and picking lots.
    Updated to include a visible DR number input (top) and to send PO ID via POST.
*/
if (function_exists('opcache_reset')) { opcache_reset(); }
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
        <a href="?controller=warehouse&action=deliveries" class="btn btn-secondary me-3"><i class="bi bi-arrow-left me-1"></i> Back</a>
        <h4 class="mb-0"><i class="bi bi-printer me-2"></i>Print Delivery Receipt</h4>
    </div>
</div>

<div class="card data-card">
    <div class="card-body">
        <!-- DR Number Input (always visible) -->
        <div class="mb-4">
            <label class="form-label">Delivery Receipt (DR) Number</label>
            <input type="text" id="drNumberInput" class="form-control" placeholder="Enter DR number" value="<?= htmlspecialchars($dr_number ?? '') ?>">
            <div id="drNumberFeedback" class="mt-1"></div>
        </div>

        <div class="mb-4">
            <label class="form-label">Select Purchase Order</label>
            <select id="printDRPoSelect" class="form-select filter-select" style="max-width: 500px;">
                <option value="">-- Select PO --</option>
                <?php foreach ($purchase_orders as $po): ?>
                    <option value="<?= $po['po_id'] ?>" <?= ($selected_po_id == $po['po_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($po['customer_po_number']) ?> - <?= htmlspecialchars($po['customer_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($dr_number): ?>
        <div class="mb-3">
            <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>DR Number: <?= htmlspecialchars($dr_number) ?></span>
        </div>
        <?php endif; ?>
        
        <!-- Hidden Po ID – used when posting the receipt -->
        <input type="hidden" id="hiddenPoId" value="<?= htmlspecialchars($selected_po_id ?? '') ?>">

        <div id="lotSelectionArea" style="display: <?= $selected_po_id ? 'block' : 'none' ?>;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Select Lots to Print</h5>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllLots">Select All Available</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllLots">Deselect All</button>
                </div>
            </div>

            <div id="lotsContainer">
                <?php if (!empty($lots_by_item)): ?>
                    <?php foreach ($lots_by_item as $item): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($item['item_code']) ?></strong> - <?= htmlspecialchars($item['item_description']) ?>
                                        <span class="badge bg-info ms-2"><?= $item['item_uom'] ?></span>
                                        <?php if ($item['uom_conversion'] && $item['item_uom'] !== 'CS'): ?>
                                            <small class="text-muted ms-1">(<?= $item['uom_conversion'] ?> <?= $item['item_uom'] ?> = 1 CS)</small>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">Price: <?= number_format($item['unit_price'], 2) ?></small>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th width="50"><input type="checkbox" class="form-check-input item-select-all" data-item="<?= $item['item_id'] ?>"></th>
                                            <th>Lot Number</th>
                                            <th class="text-right">Produced</th>
                                            <th class="text-right">Delivered</th>
                                            <th class="text-right">Available</th>
                                            <?php if ($item['uom_conversion'] && $item['item_uom'] !== 'CS'): ?>
                                                <th class="text-right">Cases</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($item['lots'] as $lot):
                                            $remaining = $lot['available_quantity'];
                                            $lotConv = $lot['actual_uom_conversion'] ?? $item['uom_conversion'] ?? null;
                                            $cases = ($lotConv && $item['item_uom'] !== 'CS') ? floor($remaining / $lotConv) : null;
                                            $isExisting = in_array($lot['lot_id'], $existing_lot_ids);
                                        ?>
                                            <tr class="<?= $remaining <= 0 && !$isExisting ? 'table-secondary' : '' ?>" style="<?= $remaining <= 0 && !$isExisting ? 'opacity:0.6' : '' ?>">
                                                <td>
                                                    <input type="checkbox" class="form-check-input lot-checkbox"
                                                           name="selected_lots[]"
                                                           value="<?= $lot['lot_id'] ?>"
                                                           data-remaining="<?= $remaining ?>"
                                                           data-item="<?= $item['item_id'] ?>"
                                                           <?= ($remaining <= 0 && !$isExisting) ? 'disabled' : '' ?>
                                                           <?= $isExisting ? 'checked' : '' ?>>
                                                </td>
                                                <td><strong><?= htmlspecialchars($lot['lot_number']) ?></strong>
                                                    <?php if ($remaining <= 0 && !$isExisting): ?>
                                                        <i class="bi bi-lock-fill text-muted ms-1" title="Fully delivered"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-right"><?= number_format($lot['quantity_produced']) ?></td>
                                                <td class="text-right"><?= number_format($lot['quantity_produced'] - $remaining) ?></td>
                                                <td class="text-right">
                                                    <?php if ($remaining > 0): ?>
                                                        <span class="text-success fw-bold"><?= number_format($remaining) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Fully Delivered</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($item['uom_conversion'] && $item['item_uom'] !== 'CS'): ?>
                                                    <td class="text-right">
                                                        <?php if ($remaining > 0): ?>
                                                            <?= $cases ?> CS
                                                        <?php else: ?>
                                                            —
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-4" id="noLotsMsg">
                        <?php if ($selected_po_id): ?>
                            No production lots found for this PO.
                        <?php else: ?>
                            Select a PO to view available lots.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    <span class="text-muted">Selected: <strong id="selectedCount">0</strong> lot(s)</span>
                </div>
                <button type="button" class="btn btn-primary btn-lg" id="generateReceiptBtn" disabled>
                    <i class="bi bi-printer me-2"></i>Generate Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('drNumberInput').addEventListener('blur', function() {
    var drVal = this.value.trim();
    var feedback = document.getElementById('drNumberFeedback');
    if (!drVal) { feedback.innerHTML = ''; return; }
    fetch('?controller=warehouse&action=checkDRNumber&dr_number=' + encodeURIComponent(drVal))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.exists) {
                feedback.innerHTML = '<small class="text-success"><i class="bi bi-check-circle me-1"></i>DR exists. <a href="?controller=warehouse&action=printDRPreview&dr_number=' + encodeURIComponent(drVal) + (data.po_ids && data.po_ids[0] ? '&po_id=' + data.po_ids[0] : '') + '" target="_blank">View/Print</a></small>';
            } else {
                feedback.innerHTML = '<small class="text-muted">New DR number - proceed to select lots.</small>';
            }
        })
        .catch(function() { feedback.innerHTML = ''; });
});

// Update PO selection – keep the DR number typed
document.getElementById('printDRPoSelect').addEventListener('change', function() {
    const poId = this.value;
    const drNum = document.getElementById('drNumberInput').value.trim();
    // Preserve PO ID in hidden field for later POST
    document.getElementById('hiddenPoId').value = poId;
    if (poId) {
        window.location.href = '?controller=warehouse&action=printDR&po_id=' + poId + (drNum ? '&dr_number=' + encodeURIComponent(drNum) : '');
    } else {
        window.location.href = '?controller=warehouse&action=printDR' + (drNum ? '&dr_number=' + encodeURIComponent(drNum) : '');
    }
});

function updateSelectedCount() {
    const checked = document.querySelectorAll('.lot-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = checked;
    document.getElementById('generateReceiptBtn').disabled = checked === 0;
}

document.querySelectorAll('.lot-checkbox').forEach(function(cb) {
    cb.addEventListener('change', updateSelectedCount);
});

document.getElementById('selectAllLots').addEventListener('click', function() {
    document.querySelectorAll('.lot-checkbox:not(:disabled)').forEach(function(cb) {
        cb.checked = true;
    });
    updateSelectedCount();
});

document.getElementById('deselectAllLots').addEventListener('click', function() {
    document.querySelectorAll('.lot-checkbox').forEach(function(cb) {
        cb.checked = false;
    });
    updateSelectedCount();
});

document.querySelectorAll('.item-select-all').forEach(function(cb) {
    cb.addEventListener('change', function() {
        const itemId = this.dataset.item;
        document.querySelectorAll('.lot-checkbox[data-item="' + itemId + '"]:not(:disabled)').forEach(function(lotCb) {
            lotCb.checked = cb.checked;
        });
        updateSelectedCount();
    });
});

document.getElementById('generateReceiptBtn').addEventListener('click', function() {
    const checked = document.querySelectorAll('.lot-checkbox:checked');
    if (checked.length === 0) return;

    const lotIds = [];
    checked.forEach(function(cb) {
        lotIds.push(cb.value);
    });

    const poId = document.getElementById('printDRPoSelect').value;
    const drNum = document.getElementById('drNumberInput').value.trim();

    if (!drNum) {
        Dlg.alert('Please enter a DR number first');
        return;
    }
    if (!poId) {
        Dlg.alert('Please select a Purchase Order first');
        return;
    }

    var formData = new FormData();
    formData.append('lot_ids', lotIds.join(','));
    formData.append('dr_number', drNum);
    formData.append('po_id', poId);
    // Use today's date for delivery_date (server can also set it)
    formData.append('delivery_date', new Date().toISOString().split('T')[0]);

    fetch('?controller=warehouse&action=saveDRNumberForLots', {
        method: 'POST',
        body: formData
    })
    .then(function() {
        var url = '?controller=warehouse&action=printDRPreview&po_id=' + poId + '&dr_number=' + encodeURIComponent(drNum);
        window.open(url, '_blank');
    })
    .catch(function() {
        var url = '?controller=warehouse&action=printDRPreview&po_id=' + poId + '&dr_number=' + encodeURIComponent(drNum);
        window.open(url, '_blank');
    });
});
</script>
