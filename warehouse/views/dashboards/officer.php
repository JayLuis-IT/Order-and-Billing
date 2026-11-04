<?php
/** Warehouse Officer landing page — DR creation, receiving, items, reports. */
$tabCounts = $tabCounts ?? [];
$deliveries = $deliveries ?? [];
$recentDeliveries = array_slice($deliveries, 0, 5);
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">In-Transit (awaiting receipt)</div>
                        <h3 class="mb-0"><?= (int) ($tabCounts['pending'] ?? 0) ?></h3>
                    </div>
                    <i class="bi bi-box-arrow-in-down fs-3 text-warning"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">For QC Inspection</div>
                        <h3 class="mb-0"><?= (int) ($tabCounts['inspection'] ?? 0) ?></h3>
                    </div>
                    <i class="bi bi-hourglass-split fs-3 text-info"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Completed Receipts</div>
                        <h3 class="mb-0"><?= (int) ($tabCounts['completed'] ?? 0) ?></h3>
                    </div>
                    <i class="bi bi-check-circle fs-3 text-success"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Delivery Reports</div>
                        <h3 class="mb-0"><?= (int) ($deliveryReportsCount ?? 0) ?></h3>
                    </div>
                    <i class="bi bi-flag fs-3 text-danger"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light"><strong><i class="bi bi-lightning me-2"></i>Quick Actions</strong></div>
            <div class="list-group list-group-flush">
                <a href="?controller=warehouse&action=deliveries" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-truck me-3 text-primary"></i> Create Delivery Receipt
                </a>
                <a href="?controller=warehouse&action=receivingPo" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-box-arrow-in-down me-3 text-warning"></i> Receive Purchasing PO
                </a>
                <a href="?controller=warehouse&action=items" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-box-seam me-3 text-success"></i> Item Master List
                </a>
                <a href="?controller=warehouse&action=deliveryReport" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-graph-up me-3 text-info"></i> Delivery Reports
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
                <strong><i class="bi bi-truck me-2"></i>Recent Deliveries</strong>
                <a href="?controller=warehouse&action=deliveries" class="btn btn-sm btn-outline-primary">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>DR Number</th><th>Customer</th><th>Qty</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentDeliveries)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No deliveries yet.</td></tr>
                        <?php else: foreach ($recentDeliveries as $d): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($d['dr_number'] ?? '-') ?></strong></td>
                                <td><?= htmlspecialchars($d['customer_name'] ?? '-') ?></td>
                                <td><?= (int) ($d['delivery_quantity'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($d['delivery_date'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
