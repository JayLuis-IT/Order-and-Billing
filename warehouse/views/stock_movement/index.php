<?php
/** Stock In/Out page — receiving personnel (whse_receiving) + whse_admin. */
$movements = $movements ?? [];
$poOptions = $poOptions ?? [];
$stockOutItems = $stockOutItems ?? [];
?>
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light"><strong><i class="bi bi-box-arrow-in-down me-2"></i>Stock In (from Purchasing PO)</strong></div>
            <div class="card-body">
                <form method="POST" action="?controller=warehouse&action=saveStockMovement">
                    <input type="hidden" name="movement_type" value="IN">
                    <div class="mb-3">
                        <label class="form-label">Purchasing PO Reference <span class="text-danger">*</span></label>
                        <select name="supplier_order_id" id="stockInPo" class="form-select" required>
                            <option value="">— Select receiving PO —</option>
                            <?php foreach ($poOptions as $po): ?>
                            <option value="<?= (int) $po['supplier_order_id'] ?>"
                                data-item-id="<?= (int) $po['item_id'] ?>"
                                data-item-code="<?= htmlspecialchars($po['item_code']) ?>"
                                data-item-desc="<?= htmlspecialchars($po['item_description']) ?>"
                                data-uom="<?= htmlspecialchars($po['item_uom'] ?? '') ?>">
                                <?= htmlspecialchars(trim(($po['po_ref'] ?: 'PO#' . $po['supplier_order_id']) . ' — ' . $po['supplier_name'] . ' — ' . $po['item_code'])) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <input type="text" id="stockInItemLabel" class="form-control" placeholder="Selected from PO above" readonly>
                        <input type="hidden" name="item_id" id="stockInItemId" value="">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" step="0.0001" min="0.0001" name="qty" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Site Code</label>
                            <input type="text" name="site_code" class="form-control" value="001" required pattern="[A-Za-z0-9_\-]{1,20}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-box-arrow-in-down me-1"></i>Record Stock IN
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light"><strong><i class="bi bi-box-arrow-up me-2"></i>Stock Out (issue / removal)</strong></div>
            <div class="card-body">
                <form method="POST" action="?controller=warehouse&action=saveStockMovement">
                    <input type="hidden" name="movement_type" value="OUT">
                    <div class="mb-3">
                        <label class="form-label">Item <span class="text-danger">*</span></label>
                        <select name="item_id" class="form-select" required>
                            <option value="">— Select item —</option>
                            <?php foreach ($stockOutItems as $item): ?>
                            <option value="<?= (int) $item['item_id'] ?>">
                                <?= htmlspecialchars(trim(($item['item_code'] ?? '') . ' — ' . ($item['item_description'] ?? ''))) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" step="0.0001" min="0.0001" name="qty" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Site Code</label>
                            <input type="text" name="site_code" class="form-control" value="001" required pattern="[A-Za-z0-9_\-]{1,20}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="remarks" class="form-control" rows="2" required placeholder="Why is stock being removed?"></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-box-arrow-up me-1"></i>Record Stock OUT
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-light"><strong><i class="bi bi-clock-history me-2"></i>Stock Movement History</strong></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Item</th>
                    <th class="text-end">Qty</th>
                    <th>Site</th>
                    <th>Reference</th>
                    <th>Remarks</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No stock movements recorded yet.</td></tr>
                <?php else: foreach ($movements as $m): ?>
                    <tr>
                        <td class="text-nowrap"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($m['date_created'] ?? 'now'))) ?></td>
                        <td>
                            <?php if (($m['movement_type'] ?? '') === 'IN'): ?>
                                <span class="badge bg-success">IN</span>
                            <?php else: ?>
                                <span class="badge bg-danger">OUT</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($m['item_code'] ?? '-') ?></strong><br><small class="text-muted"><?= htmlspecialchars($m['item_description'] ?? '') ?></small></td>
                        <td class="text-end"><?= htmlspecialchars(formatQty((float) ($m['qty'] ?? 0))) ?></td>
                        <td><?= htmlspecialchars($m['site_code'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(trim(($m['reference_type'] ?? '') . ' ' . ($m['reference_no'] ?? '')) ?: '-') ?></td>
                        <td><small><?= htmlspecialchars($m['remarks'] ?? '-') ?></small></td>
                        <td><small><?= htmlspecialchars($m['created_by_name'] ?? '-') ?></small></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var poSelect = document.getElementById('stockInPo');
    var itemIdInput = document.getElementById('stockInItemId');
    var itemLabel = document.getElementById('stockInItemLabel');
    if (poSelect) {
        poSelect.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];
            if (!this.value) {
                itemIdInput.value = '';
                itemLabel.value = '';
                return;
            }
            itemIdInput.value = opt.getAttribute('data-item-id') || '';
            itemLabel.value = (opt.getAttribute('data-item-code') || '') + ' — ' + (opt.getAttribute('data-item-desc') || '');
        });
    }
});
</script>
