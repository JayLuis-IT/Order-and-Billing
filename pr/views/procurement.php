<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="alert alert-info py-2 small mb-0 flex-grow-1">
        <i class="bi bi-info-circle me-1"></i>
        Approved Purchase Request Forms (PRF) forwarded by requestors after supervisor and President sign-off. Use the Process button to create the official Purchase Requisition (PR) and mark the PRF In Process, then print it.
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
                        <th>PRF Number</th>
                        <th>PR Number</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Department</th>
                        <th>Purpose / Project</th>
                        <th>Priority</th>
                        <th>Items</th>
                        <th>Approved</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$requests): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No approved purchase request forms yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($requests as $r): $pr = $prsByPrf[(int) $r['pr_id']] ?? null; ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($r['pr_number']) ?></td>
                        <td>
                            <?php if ($pr): ?>
                            <span class="fw-semibold text-primary"><?= htmlspecialchars($pr['pr_number']) ?></span>
                            <span class="badge bg-success ms-1"><?= htmlspecialchars($pr['pr_status']) ?></span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($r['date_created']))) ?></td>
                        <td>
                            <?php if ($r['status'] === 'in_process'): ?>
                            <span class="badge bg-info text-dark">In Process</span>
                            <?php else: ?>
                            <span class="badge bg-success">Approved</span>
                            <?php endif; ?>
                        </td>
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
                        <td class="small text-muted">
                            <?= htmlspecialchars(date('M d, Y', strtotime($r['approved_at']))) ?><br>
                            <?= htmlspecialchars($r['creator_full_name'] ?? '') ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <?php if ($r['status'] === 'approved'): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning tt" data-bs-placement="bottom"
                                        data-bs-toggle="modal" data-bs-target="#processPrModal"
                                        data-pr-id="<?= (int) $r['pr_id'] ?>"
                                        data-pr-number="<?= htmlspecialchars($r['pr_number']) ?>"
                                        data-date="<?= htmlspecialchars(date('M d, Y', strtotime($r['date_created']))) ?>"
                                        data-department="<?= htmlspecialchars(deptLabel($r['department'])) ?>"
                                        data-purpose="<?= htmlspecialchars($r['purpose']) ?>"
                                        data-priority="<?= $r['priority'] === 'immediate' ? 'Immediate' : 'Standard' ?>"
                                        data-items="<?= (int) $r['item_count'] ?>"
                                        data-back="queue"
                                        title="Process" aria-label="Process">
                                    <i class="bi bi-gear"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($pr): ?>
                                <a class="btn btn-sm btn-outline-primary tt" data-bs-placement="bottom" target="_blank"
                                   href="?controller=pr&action=printPr&id=<?= (int) $pr['pr_id'] ?>"
                                   title="Print PR <?= htmlspecialchars($pr['pr_number']) ?>" aria-label="Print PR">
                                    <i class="bi bi-printer"></i> Print PR
                                </a>
                                <a class="btn btn-sm btn-outline-secondary tt" data-bs-placement="bottom" target="_blank"
                                   href="?controller=pr&action=printPr&id=<?= (int) $pr['pr_id'] ?>"
                                   title="View PR <?= htmlspecialchars($pr['pr_number']) ?>" aria-label="View PR">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($r['signed_file_path'])): ?>
                                <a class="btn btn-sm btn-outline-success tt" data-bs-placement="bottom" target="_blank" href="<?= URL_ROOT . htmlspecialchars($r['signed_file_path']) ?>" title="Signed PRF" aria-label="Signed PRF">
                                    <i class="bi bi-paperclip"></i> Signed PRF
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
<?php include __DIR__ . '/_process_modal.php'; ?>
