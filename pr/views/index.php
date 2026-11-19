<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary btn-sm <?= $statusFilter === '' ? 'active' : '' ?>" href="?controller=pr">All</a>
        <a class="btn btn-outline-secondary btn-sm <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="?controller=pr&status=pending">Pending</a>
        <a class="btn btn-outline-secondary btn-sm <?= $statusFilter === 'submitted' ? 'active' : '' ?>" href="?controller=pr&status=submitted">Submitted</a>
        <a class="btn btn-outline-secondary btn-sm <?= $statusFilter === 'approved' ? 'active' : '' ?>" href="?controller=pr&status=approved">Approved</a>
        <a class="btn btn-outline-secondary btn-sm <?= $statusFilter === 'in_process' ? 'active' : '' ?>" href="?controller=pr&status=in_process">In Process</a>
        <?php if (!($seeAll ?? false)): ?>
        <span class="text-muted small align-self-center ms-1"><i class="bi bi-info-circle me-1"></i>Showing only your requests</span>
        <?php endif; ?>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPrModal">
        <i class="bi bi-plus-circle me-1"></i> Add Request
    </button>
</div>

<div class="card data-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>PR Number</th>
                        <th>Date</th>
                        <th>Department</th>
                        <th>Purpose / Project</th>
                        <th>Priority</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$requests): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No purchase requests yet. Click "Add Request" to submit one.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($requests as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($r['pr_number']) ?></td>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($r['date_created']))) ?></td>
                        <td><?= htmlspecialchars(deptLabel($r['department'])) ?></td>
                        <td><?= htmlspecialchars($r['purpose']) ?></td>
                        <td>
                            <?php if ($r['priority'] === 'immediate'): ?>
                            <span class="badge bg-danger">Immediate</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Standard</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $r['item_count'] ?></td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                            <span class="badge bg-secondary">Pending</span>
                            <?php elseif ($r['status'] === 'submitted'): ?>
                            <span class="badge bg-warning text-dark">Submitted</span>
                            <?php elseif ($r['status'] === 'approved'): ?>
                            <span class="badge bg-success">Approved</span>
                            <?php elseif ($r['status'] === 'in_process'): ?>
                            <span class="badge bg-info text-dark">In Process</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <?php $formalPr = ($r['status'] === 'in_process') ? ($prsByPrf[(int) $r['pr_id']] ?? null) : null; ?>
                                <a class="btn btn-sm btn-outline-primary tt" data-bs-placement="bottom"
                                   target="_blank" href="?controller=pr&action=<?= $formalPr ? 'printPr&id=' . (int) $formalPr['pr_id'] : 'print&id=' . (int) $r['pr_id'] ?>"
                                   title="<?= $formalPr ? 'Print PR ' . htmlspecialchars($formalPr['pr_number']) : 'Print PR' ?>" aria-label="Print PR">
                                    <i class="bi bi-printer"></i>
                                </a>
                                <?php if (($canProcess ?? false) && $r['status'] === 'pending'): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning tt" data-bs-placement="bottom"
                                        data-bs-toggle="modal" data-bs-target="#supplierPriceModal"
                                        data-pr-id="<?= (int) $r['pr_id'] ?>"
                                        data-pr-number="<?= htmlspecialchars($r['pr_number']) ?>"
                                        data-date="<?= htmlspecialchars(date('M d, Y', strtotime($r['date_created']))) ?>"
                                        data-department="<?= htmlspecialchars(deptLabel($r['department'])) ?>"
                                        data-purpose="<?= htmlspecialchars($r['purpose']) ?>"
                                        data-priority="<?= $r['priority'] === 'immediate' ? 'Immediate' : 'Standard' ?>"
                                        data-requestor="<?= htmlspecialchars($r['creator_full_name'] ?? ($r['prepared_by'] ?? '')) ?>"
                                        data-back="list"
                                        title="Process" aria-label="Process">
                                    <i class="bi bi-gear"></i>
                                </button>
                                <?php endif; ?>
                                <?php if (($canProcess ?? false) && $r['status'] === 'approved'): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning tt" data-bs-placement="bottom"
                                        data-bs-toggle="modal" data-bs-target="#processPrModal"
                                        data-pr-id="<?= (int) $r['pr_id'] ?>"
                                        data-pr-number="<?= htmlspecialchars($r['pr_number']) ?>"
                                        data-date="<?= htmlspecialchars(date('M d, Y', strtotime($r['date_created']))) ?>"
                                        data-department="<?= htmlspecialchars(deptLabel($r['department'])) ?>"
                                        data-purpose="<?= htmlspecialchars($r['purpose']) ?>"
                                        data-priority="<?= $r['priority'] === 'immediate' ? 'Immediate' : 'Standard' ?>"
                                        data-items="<?= (int) $r['item_count'] ?>"
                                        data-back="list"
                                        title="Process" aria-label="Process">
                                    <i class="bi bi-gear"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($r['status'] === 'pending' && (int) $r['created_by'] === (int) $_SESSION['user_id']): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary edit-pr-btn tt" data-bs-placement="bottom"
                                        data-edit-id="<?= (int) $r['pr_id'] ?>" title="Edit" aria-label="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a class="btn btn-sm btn-outline-danger tt" data-bs-placement="bottom"
                                   href="?controller=pr&action=delete&id=<?= (int) $r['pr_id'] ?>"
                                   data-confirm="Delete <?= htmlspecialchars($r['pr_number']) ?>?" title="Delete" aria-label="Delete">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php elseif ($r['status'] === 'submitted' && (int) $r['created_by'] === (int) $_SESSION['user_id']): ?>
                                <button type="button" class="btn btn-sm btn-success tt" data-bs-toggle="modal" data-bs-target="#approvePrModal" data-bs-placement="bottom"
                                        data-pr-id="<?= (int) $r['pr_id'] ?>" data-pr-number="<?= htmlspecialchars($r['pr_number']) ?>"
                                        title="Approve" aria-label="Approve">
                                    <i class="bi bi-check2-circle"></i>
                                </button>
                                <?php elseif (in_array($r['status'], ['approved', 'in_process'], true) && !empty($r['signed_file_path'])): ?>
                                <a class="btn btn-sm btn-outline-success tt" data-bs-placement="bottom"
                                   target="_blank" href="<?= URL_ROOT . htmlspecialchars($r['signed_file_path']) ?>" title="View signed PRF" aria-label="View signed PRF">
                                    <i class="bi bi-paperclip"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/_prf_form.php'; ?>
<?php if ($canProcess ?? false): ?>
<?php include __DIR__ . '/_supplier_price_modal.php'; ?>
<?php include __DIR__ . '/_process_modal.php'; ?>
<?php endif; ?>

<!-- Approve (attach signed PRF) Modal -->
<div class="modal fade" id="approvePrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?controller=pr&action=approve" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-check2-circle me-2"></i>Approve Purchase Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="pr_id" id="approvePrId">
                    <p class="mb-2">
                        Approving <strong id="approvePrNumber"></strong>.
                        This forwards the request to <strong>Procurement</strong>.
                    </p>
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Print the updated PRF (with the supplier and price added by Procurement), have it signed by your supervisor and the President, then attach the signed hardcopy (scan or photo) here.
                    </div>
                    <label class="form-label">Signed PRF attachment <span class="text-danger">*</span></label>
                    <input type="file" name="signed_file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" required>
                    <div class="form-text">Allowed: JPG, PNG, GIF, WebP, PDF - max 10MB.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check2 me-1"></i> Approve with Attachment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Approve modal wiring
    const approveModal = document.getElementById('approvePrModal');
    approveModal.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        document.getElementById('approvePrId').value = btn.getAttribute('data-pr-id');
        document.getElementById('approvePrNumber').textContent = btn.getAttribute('data-pr-number');
    });
});
</script>
