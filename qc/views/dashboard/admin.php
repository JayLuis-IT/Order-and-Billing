<?php
/** QC Admin landing page — received RM from warehouse receipts + delivery monitoring. */
$pendingQcItems = $pendingQcItems ?? [];
$completedQcItems = $completedQcItems ?? [];
$counts = $counts ?? [];
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <a href="?controller=qc&action=receivingInspection" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Awaiting Receiving Inspection</div>
                            <h3 class="mb-0"><?= count($pendingQcItems) ?></h3>
                        </div>
                        <i class="bi bi-box-arrow-in-down fs-3 text-warning"></i>
                    </div>
                    <small class="text-primary">Open queue &rarr;</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Completed Receiving Inspections</div>
                        <h3 class="mb-0"><?= count($completedQcItems) ?></h3>
                    </div>
                    <i class="bi bi-check-circle fs-3 text-success"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <a href="?controller=qc&action=deliveryInspection" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Delivery Lots Remaining</div>
                            <h3 class="mb-0"><?= (int) ($counts['remaining'] ?? 0) ?></h3>
                        </div>
                        <i class="bi bi-truck fs-3 text-primary"></i>
                    </div>
                    <small class="text-primary">Monitor &rarr;</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Delivery Lots Inspected</div>
                        <h3 class="mb-0"><?= (int) ($counts['inspected'] ?? 0) ?></h3>
                    </div>
                    <i class="bi bi-clipboard-check fs-3 text-info"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-box-arrow-in-down me-2"></i>Received RM from Warehouse — Awaiting Inspection</strong>
        <a href="?controller=qc&action=receivingInspection" class="btn btn-sm btn-outline-primary">Open Receiving Inspection</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>PO Ref</th>
                    <th>Supplier</th>
                    <th>Item</th>
                    <th>Lot</th>
                    <th class="text-end">Received</th>
                    <th>Received Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingQcItems)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No goods currently awaiting QC approval.</td></tr>
                <?php else: foreach (array_slice($pendingQcItems, 0, 10) as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['po_ref'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($item['supplier_name'] ?? '-') ?></td>
                        <td><strong><?= htmlspecialchars($item['item_code'] ?? '-') ?></strong><br><small><?= htmlspecialchars($item['item_description'] ?? '-') ?></small></td>
                        <td><?= htmlspecialchars($item['lot_number'] ?? '-') ?></td>
                        <td class="text-end"><?= formatQty((float) ($item['received_qty'] ?? 0)) ?></td>
                        <td><?= !empty($item['received_date']) ? date('Y-m-d', strtotime($item['received_date'])) : '-' ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
