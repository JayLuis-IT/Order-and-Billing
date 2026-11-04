<?php
/** Warehouse Procurement landing page — Purchasing PO only. */
$procurementCounts = $procurementCounts ?? [];
?>
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['key' => 'requested', 'label' => 'Awaiting Processing', 'icon' => 'bi-pencil-square', 'color' => 'text-secondary'],
        ['key' => 'processed', 'label' => 'Processed (with supplier)', 'icon' => 'bi-send', 'color' => 'text-primary'],
        ['key' => 'partially_received', 'label' => 'Partially Received', 'icon' => 'bi-hourglass-split', 'color' => 'text-warning'],
        ['key' => 'received', 'label' => 'Fully Received', 'icon' => 'bi-check-circle', 'color' => 'text-success'],
    ];
    foreach ($cards as $c): ?>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small"><?= htmlspecialchars($c['label']) ?></div>
                        <h3 class="mb-0"><?= (int) ($procurementCounts[$c['key']] ?? 0) ?></h3>
                    </div>
                    <i class="bi <?= $c['icon'] ?> fs-3 <?= $c['color'] ?>"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-light"><strong><i class="bi bi-lightning me-2"></i>Quick Actions</strong></div>
    <div class="list-group list-group-flush">
        <a href="?controller=warehouse&action=purchasingPo" class="list-group-item list-group-item-action d-flex align-items-center">
            <i class="bi bi-cart3 me-3 text-primary"></i> Open Purchasing PO
        </a>
    </div>
</div>
