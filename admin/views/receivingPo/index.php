<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-box-arrow-in-down me-2"></i>Receiving Purchasing PO (View Only)</h4>
    <div class="d-flex gap-2 flex-wrap">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="controller" value="admin">
            <input type="hidden" name="action" value="receivingPo">
            <select name="status" class="form-select form-select-sm filter-select" style="width:180px">
                <option value="">Any status (per tab)</option>
                <option value="all" <?= ($filters['status'] ?? '') === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="processed" <?= ($filters['status'] ?? '') === 'processed' ? 'selected' : '' ?>>Processed</option>
                <option value="partially_received" <?= ($filters['status'] ?? '') === 'partially_received' ? 'selected' : '' ?>>Partially Received</option>
                <option value="For Inspection" <?= ($filters['status'] ?? '') === 'For Inspection' ? 'selected' : '' ?>>For Inspection</option>
                <option value="received" <?= ($filters['status'] ?? '') === 'received' ? 'selected' : '' ?>>Received</option>
                <option value="rejected" <?= ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <select name="supplier" class="form-select form-select-sm filter-select" style="width:200px">
                <option value="">All Suppliers</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= ($filters['supplier'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search PO Ref, Item..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="width:220px">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
            <a href="?controller=admin&action=receivingPo" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
        </form>
    </div>
</div>

<div class="card data-card">
    <?php
    $tabCounts = $tabCounts ?? ['pending' => 0, 'inspection' => 0, 'completed' => 0];
    // Receiving has no "All Shipments" view — only actionable pipeline tabs,
    // with In-Transit as the landing tab. Overage Approvals appear for System
    // Admins only (the gate for receipts beyond the ordered quantity).
    $activeTab = $filters['tab'] ?: 'pending';
    $recvTabs = [
        'pending'    => 'In-Transit',
        'inspection' => 'For QC Inspection',
        'completed'  => 'Completed / Approved',
        'overage'    => 'Overage Approvals',
    ];
    ?>
    <div class="card-body pb-0">
        <ul class="nav nav-pills flex-wrap gap-2" id="receivingTabs">
            <?php foreach ($recvTabs as $tabKey => $tabLabel):
                $tabHref = '?controller=admin&action=receivingPo&tab=' . rawurlencode($tabKey);
                if (!empty($filters['search'])) {
                    $tabHref .= '&search=' . rawurlencode($filters['search']);
                }
                if (!empty($filters['supplier'])) {
                    $tabHref .= '&supplier=' . rawurlencode($filters['supplier']);
                }
                $tabCount = $tabCounts[$tabKey] ?? 0;
            ?>
            <li class="nav-item">
                <a class="nav-link px-3 py-1 border rounded-pill <?= $activeTab === $tabKey ? 'active' : '' ?>"
                   href="<?= $tabHref ?>">
                    <?= $tabLabel ?> <span class="badge <?= $activeTab === $tabKey ? 'bg-white text-primary' : 'bg-secondary' ?>"><?= (int) $tabCount ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php if ($activeTab === 'overage'): ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>PO / MRP REF</th>
                    <th>Supplier</th>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th class="text-end">Qty Ordered</th>
                    <th class="text-end">Received So Far</th>
                    <th class="text-end">Requested Overage</th>
                    <th>Requested By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($overageRequests)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No pending over-receipt approval requests.</td></tr>
                <?php else: ?>
                <?php foreach ($overageRequests as $index => $ov): ?>
                <?php
                    $hasPoRef = !empty($ov['po_ref']) && trim((string) $ov['po_ref']) !== '';
                    $mrpRef = (string) ($ov['mrp_ref'] ?? '');
                    if ($mrpRef === '' && !empty($ov['mrp_run_id'])) {
                        $mrpRef = 'MRP-#' . $ov['mrp_run_id'];
                    }
                ?>
                <tr>
                    <td title="Order #<?= $ov['supplier_order_id'] ?>"><?= $index + 1 ?></td>
                    <td>
                        <?= htmlspecialchars($hasPoRef ? $ov['po_ref'] : '-') ?>
                        <?php if ($mrpRef !== ''): ?>
                            <div class="mt-1"><span class="badge bg-secondary"><?= htmlspecialchars($mrpRef) ?></span></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($ov['supplier_name']) ?></td>
                    <td><code><?= htmlspecialchars($ov['item_code']) ?></code></td>
                    <td><?= htmlspecialchars($ov['item_description']) ?></td>
                    <td class="text-end"><?= formatQty($ov['quantity']) ?> <?= htmlspecialchars($ov['item_uom'] ?? '') ?> <span class="text-muted small">(ordered)</span></td>
                    <td class="text-end"><?= formatQty($ov['received_qty']) ?> <?= htmlspecialchars($ov['item_uom'] ?? '') ?></td>
                    <td class="text-end"><span class="badge bg-danger">+<?= formatQty($ov['overage_request_qty']) ?> <?= htmlspecialchars($ov['item_uom'] ?? '') ?></span></td>
                    <td>Warehouse receipt attempt</td>
                    <td>
                        <button type="button" class="btn btn-success btn-sm" title="Approve the requested overage amount"
                                onclick="submitOverageApproval(<?= $ov['supplier_order_id'] ?>, true)">
                            <i class="bi bi-check-lg"></i> Approve
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" title="Reject the overage request"
                                onclick="submitOverageApproval(<?= $ov['supplier_order_id'] ?>, false)">
                            <i class="bi bi-x-lg"></i> Reject
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>PO / MRP REF</th>
                    <th>Supplier</th>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th class="text-end">Qty Ordered</th>
                    <th class="text-end">Received Qty</th>
                    <th>Received Date</th>
                    <th>Received By</th>
                    <th>Status</th>
                    <th>Access</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="11" class="text-center text-muted py-4">No purchasing POs ready for receiving.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($orders as $index => $o): ?>
                <?php
                    $hasPoRef = !empty($o['po_ref_display']) && $o['po_ref_display'] !== '-';
                    $mrpRef = (string) ($o['mrp_ref'] ?? '');
                    if ($mrpRef === '' && !empty($o['mrp_run_id'])) {
                        $mrpRef = 'MRP-#' . $o['mrp_run_id'];
                    }
                ?>
                <tr>
                    <td title="Order #<?= $o['supplier_order_id'] ?>"><?= $index + 1 ?></td>
                    <td>
                        <?php if ($hasPoRef): ?>
                            <?= htmlspecialchars($o['po_ref_display']) ?>
                        <?php endif; ?>
                        <?php if ($mrpRef !== ''): ?>
                            <div class="<?= $hasPoRef ? 'mt-1' : '' ?>"><span class="badge bg-secondary"><?= htmlspecialchars($mrpRef) ?></span></div>
                        <?php elseif (!$hasPoRef): ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($o['supplier_name']) ?></td>
                    <td><code><?= htmlspecialchars($o['item_code']) ?></code></td>
                    <td><?= htmlspecialchars($o['item_description']) ?></td>
                    <td class="text-end"><?= formatQty($o['quantity']) ?> <?= htmlspecialchars($o['item_uom'] ?? '') ?></td>
                    <td class="text-end"><?= $o['received_qty'] > 0
                        ? formatQty($o['received_qty']) . ' ' . htmlspecialchars($o['item_uom'] ?? '')
                        : '<span class="text-muted">-</span>' ?></td>
                    <td><?php
                        $hasDate = !empty($o['received_date']) && $o['received_date'] !== '0000-00-00' && $o['received_date'] !== '0000-00-00 00:00:00';
                        echo $hasDate ? date('m/d/Y', strtotime($o['received_date'])) : '-';
                    ?></td>
                    <td><?= !empty($o['received_by_name']) ? htmlspecialchars($o['received_by_name']) : '<span class="text-muted">-</span>' ?></td>
                    <td>
                        <?php if ($o['status'] === 'requested'): ?>
                            <span class="badge bg-info text-dark">Requested</span>
                        <?php elseif ($o['status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php elseif ($o['status'] === 'processed'): ?>
                            <span class="badge bg-primary">Processed</span>
                        <?php elseif ($o['status'] === 'partially_received'): ?>
                            <span class="badge bg-secondary">Partially Received</span>
                        <?php elseif ($o['status'] === 'For Inspection'): ?>
                            <span class="badge text-white" style="background:#6f42c1">For Inspection</span>
                        <?php elseif ($o['status'] === 'received'): ?>
                            <span class="badge bg-success">Received</span>
                        <?php elseif ($o['status'] === 'rejected'): ?>
                            <span class="badge bg-danger">Rejected</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars(ucfirst($o['status'])) ?: 'Unknown' ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-secondary-subtle text-secondary">Read-only</span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Overage approval actions: hidden POST form driven by row buttons -->
<form id="overageApprovalForm" method="POST" style="display:none">
    <input type="hidden" name="supplier_order_id" id="overageRequestId">
    <input type="hidden" name="reject_reason" id="overageRejectReason">
</form>
<script>
function submitOverageApproval(id, approve) {
    var form = document.getElementById('overageApprovalForm');
    document.getElementById('overageRequestId').value = id;
    if (approve) {
        Dlg.confirm('Approve this over-receipt? The warehouse may then receive the approved excess beyond the ordered quantity.').then(function(ok) {
            if (!ok) return;
            form.action = '?controller=admin&action=approveOverageRequest';
            form.submit();
        });
        return;
    }
    Dlg.prompt('Reason for rejecting this over-receipt request (required):', { requiredMessage: 'A rejection reason is required.' }).then(function(reason) {
        if (reason === null || reason === '') return;
        document.getElementById('overageRejectReason').value = reason;
        form.action = '?controller=admin&action=rejectOverageRequest';
        form.submit();
    });
}
</script>
