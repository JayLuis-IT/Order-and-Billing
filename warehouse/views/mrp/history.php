<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">
            <i class="bi bi-clock-history me-2"></i>MRP Snapshot History
            <?php
            // Tracking badge: runs still awaiting a signature (any of the three gates).
            $pendingApprovalRuns = [];
            foreach (($runs ?? []) as $r) {
                $st = $r['approval_statuses'] ?? [];
                if (in_array('pending_whse_approval', $st, true) || in_array('pending_sysadmin_approval', $st, true) || in_array('pending_qc_approval', $st, true)) {
                    $pendingApprovalRuns[$r['run_id']] = true;
                }
            }
            if ($pendingApprovalRuns):
            ?>
            <span class="badge bg-warning text-dark ms-2" title="Runs still awaiting a signature — see the Status column">
                <i class="bi bi-hourglass-split me-1"></i><?= count($pendingApprovalRuns) ?> awaiting approval
            </span>
            <?php endif; ?>
        </h4>
        <?php if (!empty($poHeader)): ?>
        <small class="text-muted">
            PO: <?= htmlspecialchars($poHeader['customer_po_number'] ?? '') ?>
            — <?= htmlspecialchars($poHeader['customer_name'] ?? '') ?>
            — <?= number_format($poHeader['total_quantity'] ?? 0) ?> cases
        </small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($selectedPO)): ?>
        <a href="?controller=warehouse&action=mrp&po_id=<?= $selectedPO ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-calculator me-1"></i>Back to MRP
        </a>
        <?php else: ?>
        <a href="?controller=warehouse&action=mrp" class="btn btn-primary btn-sm">
            <i class="bi bi-calculator me-1"></i>Back to MRP
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Run #</th>
                    <th>MRP REF</th>
                    <?php if (empty($selectedPO)): ?>
                    <th>FG</th>
                    <th>Target Qty</th>
                    <?php endif; ?>
                    <th>Date Saved</th>
                    <th>Saved By</th>
                    <th>Status</th>
                    <th>Items Captured</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($runs)): ?>
                <tr>
                    <td colspan="<?= empty($selectedPO) ? 9 : 7 ?>" class="text-center text-muted py-4">No snapshots saved yet.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($runs as $r): ?>
                <tr>
                    <td><strong>#<?= $r['run_id'] ?></strong></td>
                    <td><code><?= htmlspecialchars($r['mrp_ref'] ?? ('MRP-#' . $r['run_id'])) ?></code></td>
                    <?php if (empty($selectedPO)): ?>
                    <td><code><?= htmlspecialchars($r['fg_code'] ?? '-') ?></code></td>
                    <td class="text-end"><?= $r['target_qty'] !== null && $r['target_qty'] !== '' ? formatQty($r['target_qty']) : '-' ?></td>
                    <?php endif; ?>
                    <td><?= date('m/d/Y h:i A', strtotime($r['date_created'])) ?></td>
                    <td><?= htmlspecialchars($r['user_name'] ?? '') ?></td>
                    <td>
                        <?php $runStatusLabel = $r['approval_label'] ?? 'No Requisitions'; ?>
                        <span class="badge <?= $r['approval_badge'] ?? 'bg-secondary' ?>" title="Linked requisition states: <?= htmlspecialchars(implode(', ', $r['approval_statuses'] ?? [])) ?: 'none' ?>">
                            <?= htmlspecialchars($runStatusLabel) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-info" id="itemCount<?= $r['run_id'] ?>">Loading...</span>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary view-snapshot-btn" data-run-id="<?= $r['run_id'] ?>" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <a href="?controller=warehouse&action=mrpSnapshotPrint&run_id=<?= $r['run_id'] ?>" class="btn btn-outline-danger" title="Print" target="_blank">
                                <i class="bi bi-printer"></i>
                            </a>
                            <a href="?controller=warehouse&action=deleteMrpRun&run_id=<?= $r['run_id'] ?>&po_id=<?= $selectedPO ?? '' ?>" class="btn btn-outline-danger" title="Delete" data-confirm="Delete this snapshot? Its unfulfilled purchase requests will be removed too.">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Snapshot Detail Modal -->
<div class="modal fade" id="snapshotDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calculator me-2"></i>MRP Snapshot Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="snapshotDetailContent">
                    <p class="text-muted">Loading...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Quantities arrive pre-formatted by formatQty() in mrpRunDetail — render them
// as-is; only the sign of the frozen excess value is parsed, for colouring.
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($runs as $r): ?>
    fetch('?controller=warehouse&action=mrpRunDetail&run_id=<?= $r['run_id'] ?>')
        .then(function(r) { return r.json(); })
        .then(function(items) {
            var el = document.getElementById('itemCount<?= $r['run_id'] ?>');
            if (el) el.textContent = items.length + ' rows';
        });
    <?php endforeach; ?>

    document.querySelectorAll('.view-snapshot-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var runId = this.getAttribute('data-run-id');
            var content = document.getElementById('snapshotDetailContent');
            content.innerHTML = '<p class="text-muted">Loading...</p>';
            fetch('?controller=warehouse&action=mrpRunDetail&run_id=' + runId)
                .then(function(r) { return r.json(); })
                .then(function(items) {
                    if (!items || items.length === 0) {
                        content.innerHTML = '<p class="text-muted">No items in this snapshot.</p>';
                        return;
                    }
                    var html = '<table class="table table-sm table-bordered"><thead><tr>' +
                        '<th>Item Code</th><th>DESCRIPTION</th><th>UoM</th>' +
                        '<th class="text-end">Total Reqt</th><th class="text-end">SOH</th>' +
                        '<th class="text-end">Open Customer PO / Allocated</th><th class="text-end">Pending PO/RR</th>' +
                        '<th class="text-end">Excess</th><th>Remarks</th></tr></thead><tbody>';
                    items.forEach(function(row) {
                        // excess is a formatted string ("-2,500"); a leading '-' marks a shortage.
                        var isLacking = String(row.excess || '').trim().charAt(0) === '-';
                        var excessClass = isLacking ? 'text-danger fw-bold' : 'text-success fw-bold';
                        html += '<tr>' +
                            '<td><code>' + (row.component_code || '') + '</code></td>' +
                            '<td>' + (row.component_name || '') + '</td>' +
                            '<td>' + (row.component_uom || '') + '</td>' +
                            '<td class="text-end">' + (row.total_reqt || '0') + '</td>' +
                            '<td class="text-end">' + (row.soh || '0') + '</td>' +
                            '<td class="text-end">' + (row.allocated || '0') + '</td>' +
                            '<td class="text-end">' + (row.pending || '0') + '</td>' +
                            '<td class="text-end ' + excessClass + '">' + (row.excess || '0') + '</td>' +
                            '<td>' + (row.remarks || '') + '</td></tr>';
                    });
                    html += '</tbody></table>';
                    content.innerHTML = html;
                });
            var modal = new bootstrap.Modal(document.getElementById('snapshotDetailModal'));
            modal.show();
        });
    });
});
</script>
