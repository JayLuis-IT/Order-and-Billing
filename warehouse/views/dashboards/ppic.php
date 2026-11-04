<?php
/** Warehouse PPIC landing page — Customer PO, LMR, MRP Sheet. */
$purchase_orders = $purchase_orders ?? [];
$po_items_map = $po_items_map ?? [];
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Active Customer POs</div>
                        <h3 class="mb-0"><?= count($purchase_orders) ?></h3>
                    </div>
                    <i class="bi bi-cart3 fs-3 text-primary"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">PO Items Tracked</div>
                        <h3 class="mb-0"><?= count($po_items_map) ?></h3>
                    </div>
                    <i class="bi bi-list-check fs-3 text-info"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <a href="?controller=lmr&action=create" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">New LMR Entry</div>
                            <h5 class="mb-0 text-primary">Create &rarr;</h5>
                        </div>
                        <i class="bi bi-clipboard-check fs-3 text-success"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light"><strong><i class="bi bi-lightning me-2"></i>Quick Actions</strong></div>
            <div class="list-group list-group-flush">
                <a href="?controller=warehouse&action=purchaseOrders" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-cart3 me-3 text-primary"></i> Customer PO
                </a>
                <a href="?controller=lmr&action=index" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-clipboard-check me-3 text-success"></i> LMR Entry
                </a>
                <a href="?controller=warehouse&action=mrp" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-calculator me-3 text-warning"></i> MRP Sheet
                </a>
                <a href="?controller=warehouse&action=activityLogs" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-clock-history me-3 text-secondary"></i> Activity Logs
                </a>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-cart3 me-2"></i>Active Customer POs</strong>
                <a href="?controller=warehouse&action=purchaseOrders" class="btn btn-sm btn-outline-primary">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>PO Number</th><th>Customer</th><th>Date</th><th>Items</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchase_orders)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No active customer POs.</td></tr>
                        <?php else: foreach ($purchase_orders as $po): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($po['customer_po_number'] ?? ('PO #' . ($po['po_id'] ?? ''))) ?></strong></td>
                                <td><?= htmlspecialchars($po['customer_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($po['customer_po_date'] ?? '-') ?></td>
                                <td><?= count($po_items_map[$po['po_id']] ?? []) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
