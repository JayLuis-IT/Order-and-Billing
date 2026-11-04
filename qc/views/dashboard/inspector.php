<?php
/** QC Inspector landing page — delivery inspection queue summary. */
$counts = $counts ?? [];
$recentHistory = $recentHistory ?? [];
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Total Delivery Lots</div>
                        <h3 class="mb-0"><?= (int) ($counts['total'] ?? 0) ?></h3>
                    </div>
                    <i class="bi bi-list-check fs-3 text-primary"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Remaining (to inspect)</div>
                        <h3 class="mb-0"><?= (int) ($counts['remaining'] ?? 0) ?></h3>
                    </div>
                    <i class="bi bi-hourglass-split fs-3 text-warning"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Inspected</div>
                        <h3 class="mb-0"><?= (int) ($counts['inspected'] ?? 0) ?></h3>
                    </div>
                    <i class="bi bi-check-circle fs-3 text-success"></i>
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
                <a href="?controller=qc&action=deliveryInspection" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-truck me-3 text-primary"></i> Open Delivery Inspection
                </a>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-truck me-2"></i>Recent Delivery Lots</strong>
                <a href="?controller=qc&action=deliveryInspection" class="btn btn-sm btn-outline-primary">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Lot</th><th>Customer</th><th>Item</th><th>QC Remark</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentHistory)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No delivery lots yet.</td></tr>
                        <?php else: foreach ($recentHistory as $h): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($h['lot_number'] ?? '-') ?></strong></td>
                                <td><?= htmlspecialchars($h['customer_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($h['item_description'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($h['qc_remark'])): ?>
                                        <span class="badge bg-success">Inspected</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
