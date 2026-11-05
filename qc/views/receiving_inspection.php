<script>
function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<!-- STRUCTURAL PLACEHOLDER: custom checklist-based QC evaluation UI mounts
     here when the checklist engine lands (see QcModel::evaluateChecklist and
     ##qcChecklistPanel). Hidden until data-checklist-hook is wired. -->
<div id="qcChecklistPanel" class="d-none" data-checklist-hook="pending"></div>

<div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Receiving Inspection Queue</h5>
        <span class="badge bg-warning text-dark"><?= count($pendingQcItems ?? []) ?> pending</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>PO Ref</th>
                        <th>Supplier</th>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th class="text-end">Qty Received</th>
                        <th>Date Received</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendingQcItems)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No incoming PO receipts are waiting for QC approval.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pendingQcItems as $item): ?>
                            <?php $itemId = (int) ($item['receiving_item_id'] ?? 0); ?>
                            <?php $receivedQty = (float) ($item['received_qty'] ?? 0); ?>
                            <?php $currentStatus = strtoupper((string) ($item['qc_status'] ?? 'PENDING_QC')); ?>
                            <?php $latestDisp = (string) ($item['latest_disposition'] ?? ''); ?>
                            <tr>
                                <td>
                        <?= htmlspecialchars($item['po_ref'] ?? '-') ?>
                        <?php if (!empty($item['mrp_ref'])): ?>
                            <div class="mt-1"><span class="badge bg-secondary"><?= htmlspecialchars($item['mrp_ref']) ?></span></div>
                        <?php endif; ?>
                    </td>
                                <td><?= htmlspecialchars($item['supplier_name'] ?? '-') ?></td>
                                <td><strong><?= htmlspecialchars($item['item_code'] ?? '-') ?></strong></td>
                                <td><?= htmlspecialchars($item['item_description'] ?? '-') ?></td>
                                <td class="text-end"><?= formatQty($receivedQty) ?> <?= htmlspecialchars($item['item_uom'] ?? $item['uom'] ?? '') ?></td>
                                <td><?= !empty($item['received_date']) ? date('Y-m-d', strtotime($item['received_date'])) : '-' ?></td>
                                <td>
                                    <?php if ($currentStatus === 'HOLD'): ?>
                                        <span class="badge bg-secondary">On Hold</span>
                                    <?php else: ?>
                                        <span class="badge text-white" style="background:#6f42c1">Pending Inspection</span>
                                    <?php endif; ?>
                                    <?php if ($latestDisp !== ''): ?>
                                        <div class="small mt-1">
                                            <span class="badge <?= $latestDisp === 'APPROVED' ? 'bg-success' : ($latestDisp === 'REJECTED' ? 'bg-danger' : ($latestDisp === 'WAIVED' ? 'bg-info text-dark' : 'bg-secondary')) ?>">
                                                <?= htmlspecialchars($latestDisp) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php $prevReportId = (int) ($item['latest_report_id'] ?? 0); ?>
                                    <?php $isPm = strtoupper((string) ($item['item_type'] ?? '')) === 'PM'; ?>
                                    <?php $latestIsPm = !empty($item['latest_is_pm']); ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary rc-inspect-btn"
                                            data-receiving-id="<?= $itemId ?>"
                                            data-rmname="<?= htmlspecialchars($item['item_description'] ?? '') ?>"
                                            data-supplier="<?= htmlspecialchars($item['supplier_name'] ?? '') ?>"
                                            data-lot="<?= htmlspecialchars($item['lot_number'] ?? '') ?>"
                                            data-delivered="<?= htmlspecialchars($item['received_date'] ?? '') ?>"
                                            data-report="<?= $prevReportId ?>"
                                            data-itemtype="<?= htmlspecialchars($item['item_type'] ?? '') ?>"
                                            data-ispm="<?= $isPm ? '1' : '0' ?>"
                                            data-latestisp="<?= $latestIsPm ? '1' : '0' ?>"
                                            data-received-qty="<?= htmlspecialchars($item['received_qty'] ?? 0) ?>"
                                            title="<?= $isPm ? 'Inspect (Packaging Material Inspection Report)' : 'Inspect (Raw Material Analysis Report)' ?>">
                                        <i class="bi bi-clipboard2-pulse me-1"></i>Inspect
                                    </button>
                                    <?php if ($prevReportId > 0): ?>
                                    <a href="?controller=qc&action=<?= $latestIsPm ? 'printPmReport' : 'printAnalysisReport' ?>&id=<?= $prevReportId ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print latest report">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>QC Inspection Record</h5>
        <span class="badge bg-secondary"><?= count($completedQcItems ?? []) ?> recorded</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>PO Ref</th>
                        <th>Supplier</th>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th class="text-end">Received Qty</th>
                        <th>Date Received</th>
                        <th>Decision</th>
                        <th>Inspector</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($completedQcItems)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No approved or rejected inspections have been recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($completedQcItems as $item): ?>
                            <tr>
                                <td>
                        <?= htmlspecialchars($item['po_ref'] ?? '-') ?>
                        <?php if (!empty($item['mrp_ref'])): ?>
                            <div class="mt-1"><span class="badge bg-secondary"><?= htmlspecialchars($item['mrp_ref']) ?></span></div>
                        <?php endif; ?>
                    </td>
                                <td><?= htmlspecialchars($item['supplier_name'] ?? '-') ?></td>
                                <td><strong><?= htmlspecialchars($item['item_code'] ?? '-') ?></strong></td>
                                <td><?= htmlspecialchars($item['item_description'] ?? '-') ?></td>
                                <td class="text-end"><?= formatQty($item['received_qty'] ?? 0) ?> <?= htmlspecialchars($item['item_uom'] ?? $item['uom'] ?? '') ?></td>
                                <td><?= !empty($item['received_date']) ? date('Y-m-d', strtotime($item['received_date'])) : '-' ?></td>
                                <td>
                                    <?php $decision = strtoupper((string) ($item['qc_status'] ?? '')); ?>
                                    <span class="badge <?= $decision === 'PASSED' ? 'bg-success' : 'bg-danger' ?>"><?= htmlspecialchars($decision === 'PASSED' ? 'Approved' : 'Rejected') ?></span>
                                </td>
                                <td><?= htmlspecialchars($item['inspected_by'] ?? 'QC') ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= (int) ($item['receiving_item_id'] ?? 0) ?>" title="View Details">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary btn-edit-po" data-id="<?= (int) ($item['supplier_order_id'] ?? 0) ?>" title="Edit Record">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <?php if (!empty($item['latest_report_id'])): ?>
                                    <?php $compIsPm = !empty($item['latest_is_pm']); ?>
                                    <a href="?controller=qc&action=<?= $compIsPm ? 'printPmReport' : 'printAnalysisReport' ?>&id=<?= (int) $item['latest_report_id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Print Inspection Report">
                                        <i class="bi bi-printer"></i> Report
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require BASE_PATH . 'app/views/shared/po_view_modal.php'; ?>
<?php $poEditContext = 'qc'; require BASE_PATH . 'app/views/shared/po_edit_modal.php'; ?>
<?php $analysisFormAction = '?controller=qc&action=saveReceivingInspection'; ?>
<?php require BASE_PATH . 'qc/views/analysis_report_form.php'; ?>
<?php require BASE_PATH . 'qc/views/pm_report_form.php'; ?>
<?php require BASE_PATH . 'app/views/shared/table_enhancer.php'; ?>

<script>
(function() {
    // Inspect → open the appropriate report prefilled from the receipt.
    // PM items get the Packaging Material Inspection Report (QCF-007);
    // everything else gets the Raw Material Analysis Report (QCF-06).
    document.querySelectorAll('.rc-inspect-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var reportId = parseInt(this.dataset.report, 10) || 0;
            var isPm = this.dataset.ispm === '1';
            var latestIsPm = this.dataset.latestisp === '1';
            var opts = {
                runId: '',
                itemId: '',
                receivingId: this.dataset.receivingId,
                rmName: this.dataset.rmname || '',
                supplier: this.dataset.supplier || '',
                lot: this.dataset.lot || '',
                rr: '',
                delivered: this.dataset.delivered || '',
                oldExpiry: '',
                receivedQty: this.dataset.receivedQty || ''
            };

            if (isPm) {
                // PM item → QCF-007 form
                if (!reportId || !latestIsPm) {
                    openPmReport(opts, null);
                    return;
                }
                fetch('?controller=qc&action=getPmReport&id=' + reportId)
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        openPmReport(opts, res && res.success ? res.data : null);
                    })
                    .catch(function() {
                        openPmReport(opts, null);
                    });
                return;
            }

            // Non-PM item → QCF-06 form
            if (!reportId) {
                openAnalysisReport(opts, null);
                return;
            }
            fetch('?controller=qc&action=getAnalysisReport&id=' + reportId)
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    openAnalysisReport(opts, res && res.success ? res.data : null);
                })
                .catch(function() {
                    openAnalysisReport(opts, null);
                });
        });
    });
})();
</script>
