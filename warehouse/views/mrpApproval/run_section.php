<?php
/**
 * Shared MRP run-grouped approval table (driven by WarehouseModel::getMrpApprovalRuns).
 * Expectations set by the requiring page:
 *   $runs            — grouped runs (run header + items; zero_lacking flag on header-only runs)
 *   $isApprover      — whether the current user may act at this page's stage
 *   $isWarehouseContext — warehouse module (WHSE gate) vs admin module (SysAdmin gate)
 *   $pageStage       — 'whse' | 'sysadmin' | 'qc' (which stage this screen decides on)
 * Optional overrides:
 *   $approvalController — controller for the approve/reject POSTs (default: warehouse/admin)
 *   $approveAction / $rejectAction — action names (default: approveMrpRunDraft / rejectMrpRunDraft)
 *   $decisionInModal    — (admin Approvals hub) row shows View only; Approve All
 *                         + Reject live in the run review modal footer instead.
 *                         Default false keeps the WHSE gate's inline buttons.
 */
$isWarehouseContext = $isWarehouseContext ?? false;
$isApprover = $isApprover ?? false;
$pageStage = $pageStage ?? 'whse';
$decisionInModal = $decisionInModal ?? false;
$actionBase = '?controller=' . ($approvalController ?? ($isWarehouseContext ? 'warehouse' : 'admin')) . '&action=';
$approveActionName = $approveAction ?? 'approveMrpRunDraft';
$rejectActionName = $rejectAction ?? 'rejectMrpRunDraft';
$approveConfirm = $pageStage === 'whse'
    ? 'Approve this MRP run and forward it to System Admin approval?'
    : ($pageStage === 'qc'
        ? 'Approve this MRP run? Requisitions are released to Procurement and stock is reserved.'
        : 'Approve this MRP run and release requisitions to Procurement?');
?>
<div class="card data-card mb-4">
    <div class="table-responsive">
        <table class="table table-hover table-vcenter no-source-grouping mb-0">
            <thead>
                <tr>
                    <th>MRP Run</th>
                    <th>BOM Code</th>
                    <th>FG / Target</th>
                    <th>Date Saved</th>
                    <th>Prepared By</th>
                    <th>Components</th>
                    <th style="width:280px">Decision</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($runs)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <?php if ($pageStage === 'whse'): ?>
                            Nothing is waiting for Warehouse Admin approval.
                        <?php elseif ($pageStage === 'qc'): ?>
                            No MRP runs are waiting for QC Admin approval.
                        <?php else: ?>
                            No MRP requisitions are waiting for System Admin approval.
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($runs as $run): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><code><?= htmlspecialchars($run['mrp_ref']) ?></code></div>
                        <div class="small text-muted">Run #<?= (int) $run['run_id'] ?></div>
                    </td>
                    <td>
                        <?php if (!empty($run['bom_code'])): ?>
                            <code><?= htmlspecialchars($run['bom_code']) ?></code>
                        <?php else: ?>
                            <span class="text-muted" title="No explicit BOM was pinned to this run — the server resolved the default formulation at calculate time.">(default BOM)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code><?= htmlspecialchars($run['fg_code'] ?? '-') ?></code>
                        <?php if ($run['target_qty'] !== null && $run['target_qty'] !== ''): ?>
                            <div class="small text-muted">Target: <?= formatQty($run['target_qty']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($run['customer_name'])): ?>
                            <div class="small text-muted"><?= htmlspecialchars($run['customer_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= date('m/d/Y h:i A', strtotime($run['run_date'])) ?></td>
                    <td><?= htmlspecialchars($run['run_user_name'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($run['zero_lacking'])): ?>
                            <span class="badge bg-secondary">No purchase requests</span>
                            <div class="small text-muted mt-1">Nothing lacking — approval is sign-off only.</div>
                        <?php else: ?>
                            <span class="badge bg-info"><?= count($run['items']) ?> requisition(s)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex flex-nowrap gap-1 align-items-center">
                            <?php if ($decisionInModal && $isApprover): ?>
                            <?php // Admin Approvals hub: decisions live in the modal footer. ?>
                            <?php elseif ($isApprover): ?>
                            <button type="button" class="btn btn-success btn-sm text-nowrap" data-id="<?= $run['run_id'] ?>"
                                    title="<?= $pageStage === 'whse' ? 'Approve this run and forward to System Admin' : ($pageStage === 'qc' ? 'Approve this run — release requisitions to Procurement and reserve stock' : 'Approve this run and release requisitions to Procurement') ?>"
                                    onclick="submitRunApproval(this.dataset.id, true)">
                                <i class="bi bi-check-lg"></i> Approve All
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-id="<?= $run['run_id'] ?>"
                                    title="Reject ALL requisitions of this run"
                                    onclick="submitRunApproval(this.dataset.id, false)">
                                <i class="bi bi-x-lg"></i> Reject
                            </button>
                            <?php else: ?>
                            <span class="text-muted small text-nowrap">Awaiting signature</span>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-info text-nowrap run-detail-btn" data-id="<?= $run['run_id'] ?>"
                                    data-code="<?= htmlspecialchars($run['mrp_ref']) ?>"
                                    data-bom-id="<?= (int) ($run['bom_id'] ?? 0) ?>"
                                    data-bom-code="<?= htmlspecialchars($run['bom_code'] ?? '') ?>"
                                    data-target-qty="<?= $run['target_qty'] !== null && $run['target_qty'] !== '' ? formatQty($run['target_qty']) : '0' ?>"
                                    data-fg-code="<?= htmlspecialchars($run['fg_code'] ?? '') ?>"
                                    title="Review the MRP run (component break-down + BOM)">
                                <i class="bi bi-eye"></i> View
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="mrpRunApprovalForm" method="POST" style="display:none">
    <input type="hidden" name="mrp_run_id" id="mrpRunApprovalId">
    <input type="hidden" name="reject_reason" id="mrpRunRejectReason">
</form>

<!-- Run review modal: requisitions + component break-down -->
<div class="modal fade" id="mrpRunDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calculator me-2"></i>MRP Run Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between flex-wrap mb-3">
                    <div>
                        <div><span class="text-muted small">MRP Ref:</span> <code id="mrpRunDetailRef">-</code></div>
                        <div><span class="text-muted small">BOM:</span> <code id="mrpRunDetailBom">-</code></div>
                    </div>
                    <div>
                        <div><span class="text-muted small">FG:</span> <code id="mrpRunDetailFg">-</code></div>
                        <div class="fw-bold"><span class="text-muted small">Target Qty:</span> <span id="mrpRunDetailTarget">-</span>
                            <span class="text-muted small" style="font-weight:400">(demand this run was calculated for)</span></div>
                    </div>
                </div>
                <p class="fw-bold text-uppercase small text-muted mb-1">MRP component break-down (frozen at save time)</p>
                <div id="mrpRunDetailContent"></div>
            </div>
            <div class="modal-footer">
                <?php if ($decisionInModal && $isApprover): ?>
                <div class="me-auto d-flex gap-1" id="mrpModalActions">
                    <button type="button" class="btn btn-success btn-sm text-nowrap" id="mrpModalApproveBtn"
                            title="<?= $pageStage === 'whse' ? 'Approve this run and forward to System Admin approval' : ($pageStage === 'qc' ? 'Approve this run — release requisitions to Procurement and reserve stock' : 'Approve this run and release requisitions to Procurement') ?>">
                        <i class="bi bi-check-lg"></i> Approve All
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm text-nowrap" id="mrpModalRejectBtn"
                            title="Reject ALL requisitions of this run">
                        <i class="bi bi-x-lg"></i> Reject
                    </button>
                </div>
                <?php endif; ?>
                <a href="?controller=warehouse&action=mrpSnapshotPrint&run_id=" id="mrpModalPrintBtn"
                   target="_blank" class="btn btn-outline-secondary btn-sm text-nowrap"
                   title="Print the MRP snapshot report for this run">
                    <i class="bi bi-printer"></i> Print
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
var mrpRunDetailModal = null;
// Run shown in the review modal — used by the modal-footer decision buttons
// when $decisionInModal is on (admin Approvals hub).
var mrpModalCurrentRunId = null;

function buildComponentTable(rows) {
    var html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr>' +
        '<th>Item Code</th><th>Description</th><th>UoM</th>' +
        '<th class="text-end">Total Reqt</th><th class="text-end">SOH</th>' +
        '<th class="text-end">Allocated</th><th class="text-end">Pending PO/RR</th>' +
        '<th class="text-end">Excess</th><th>Remarks</th></tr></thead><tbody>';
    rows.forEach(function(row) {
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
            '<td><span class="badge bg-light text-dark border">' + (row.remarks || '') + '</span></td></tr>';
    });
    return html + '</tbody></table></div>';
}

function loadMrpRunDetail(runId, mrpRef, bomId, bomCode, items, targetQty, fgCode) {
    mrpRunDetailModal = mrpRunDetailModal || new bootstrap.Modal(document.getElementById('mrpRunDetailModal'));
    mrpModalCurrentRunId = runId;
    var printBtn = document.getElementById('mrpModalPrintBtn');
    if (printBtn) printBtn.href = '?controller=warehouse&action=mrpSnapshotPrint&run_id=' + encodeURIComponent(runId);
    document.getElementById('mrpRunDetailRef').textContent = mrpRef || '-';
    document.getElementById('mrpRunDetailBom').textContent = bomId ? (bomCode || 'BOM #' + bomId) : '(default BOM)';
    document.getElementById('mrpRunDetailFg').textContent = fgCode || '-';
    document.getElementById('mrpRunDetailTarget').textContent = targetQty || '0';

    var box = document.getElementById('mrpRunDetailContent');
    box.innerHTML = '<p class="text-muted">Loading component break-down…</p>';
    mrpRunDetailModal.show();

    fetch('?controller=warehouse&action=mrpRunDetail&run_id=' + encodeURIComponent(runId))
        .then(function(r) { return r.json(); })
        .then(function(rows) {
            if (!rows || rows.length === 0) {
                box.innerHTML = '<p class="text-muted">No items in this snapshot.</p>';
                return;
            }
            box.innerHTML = buildComponentTable(rows);
        })
        .catch(function() {
            box.innerHTML = '<p class="text-danger">Failed to load the run detail.</p>';
        });
}

function submitRunApproval(runId, approve) {
    var form = document.getElementById('mrpRunApprovalForm');
    document.getElementById('mrpRunApprovalId').value = runId;
    if (approve) {
        Dlg.confirm(<?= json_encode($approveConfirm) ?>).then(function(ok) {
            if (!ok) return;
            form.action = '<?= $actionBase . $approveActionName ?>';
            form.submit();
        });
        return;
    }
    Dlg.prompt('Reason for rejecting this MRP run (required):', { requiredMessage: 'A rejection reason is required.' }).then(function(reason) {
        if (reason === null || reason === '') return;
        document.getElementById('mrpRunRejectReason').value = reason;
        form.action = '<?= $actionBase . $rejectActionName ?>';
        form.submit();
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.run-detail-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            loadMrpRunDetail(this.dataset.id, this.dataset.code, this.dataset.bomId, this.dataset.bomCode, [], this.dataset.targetQty, this.dataset.fgCode);
            // Modal decisions only apply to pending runs — tracking rows
            // (data-decided) open the same modal read-only.
            var actions = document.getElementById('mrpModalActions');
            if (actions) actions.classList.toggle('d-none', this.dataset.decided === '1');
        });
    });

    // Modal-footer decisions (Approvals hubs, $decisionInModal):
    // same hidden-form POSTs as the inline row buttons — approve = Dlg.confirm,
    // reject = Dlg.prompt small input box for the reason.
    var modalApprove = document.getElementById('mrpModalApproveBtn');
    var modalReject = document.getElementById('mrpModalRejectBtn');
    if (modalApprove) {
        modalApprove.addEventListener('click', function() {
            if (!mrpModalCurrentRunId) return;
            submitRunApproval(mrpModalCurrentRunId, true);
        });
    }
    if (modalReject) {
        modalReject.addEventListener('click', function() {
            if (!mrpModalCurrentRunId) return;
            submitRunApproval(mrpModalCurrentRunId, false);
        });
    }
});
</script>
