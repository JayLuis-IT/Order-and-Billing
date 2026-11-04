<?php
/** Warehouse Receiving landing page — Stock In/Out + receiving references. */
$recentMovements = $recentMovements ?? [];
$tabCounts = $tabCounts ?? [];
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Awaiting Receipt</div>
                        <h3 class="mb-0"><?= (int) ($tabCounts['pending'] ?? 0) ?></h3>
                    </div>
                    <i class="bi bi-truck fs-3 text-primary"></i>
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
                    <i class="bi bi-hourglass-split fs-3 text-warning"></i>
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
                        <div class="text-muted small">Recent Stock Movements</div>
                        <h3 class="mb-0"><?= count($recentMovements) ?></h3>
                    </div>
                    <i class="bi bi-arrow-left-right fs-3 text-info"></i>
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
                <a href="?controller=warehouse&action=stockMovement" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-arrow-left-right me-3 text-primary"></i> Stock In / Stock Out
                </a>
                <a href="?controller=warehouse&action=items" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-box-seam me-3 text-success"></i> Item Master List (view)
                </a>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light"><strong><i class="bi bi-arrow-left-right me-2"></i>Recent Stock Movements</strong></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Date</th><th>Type</th><th>Item</th><th class="text-end">Qty</th><th>Reference</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentMovements)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No stock movements recorded yet.</td></tr>
                        <?php else: foreach ($recentMovements as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($m['date_created'] ?? 'now'))) ?></td>
                                <td>
                                    <?php if (($m['movement_type'] ?? '') === 'IN'): ?>
                                        <span class="badge bg-success">IN</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">OUT</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($m['item_code'] ?? '-') ?></strong><br><small class="text-muted"><?= htmlspecialchars($m['item_description'] ?? '') ?></small></td>
                                <td class="text-end"><?= htmlspecialchars(rtrim(rtrim(number_format((float) ($m['qty'] ?? 0), 4), '0'), '.')) ?></td>
                                <td><?= htmlspecialchars(trim(($m['reference_type'] ?? '') . ' ' . ($m['reference_no'] ?? '')) ?: '-') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
