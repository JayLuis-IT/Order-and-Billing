<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle me-1"></i>
    Approved Purchase Requests forwarded by requestors after supervisor and President sign-off. Each entry carries the attached signed PRF as proof.
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
                        <th>Classification</th>
                        <th>Items</th>
                        <th>Approved</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$requests): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No approved purchase requests yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($requests as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($r['pr_number']) ?></td>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($r['date_created']))) ?></td>
                        <td><?= htmlspecialchars(ucfirst($r['department'])) ?></td>
                        <td><?= htmlspecialchars($r['purpose']) ?></td>
                        <td>
                            <?php if ($r['priority'] === 'immediate'): ?>
                            <span class="badge bg-danger">Immediate</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Standard</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $r['classification'] === 'repeat_order' ? 'Repeat Order' : 'New' ?></td>
                        <td><?= (int) $r['item_count'] ?></td>
                        <td class="small text-muted">
                            <?= htmlspecialchars(date('M d, Y', strtotime($r['approved_at']))) ?><br>
                            <?= htmlspecialchars($r['creator_full_name'] ?? '') ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a class="btn btn-sm btn-outline-primary" target="_blank"
                                   href="?controller=pr&action=print&id=<?= (int) $r['pr_id'] ?>">
                                    <i class="bi bi-printer"></i> PRF
                                </a>
                                <?php if (!empty($r['signed_file_path'])): ?>
                                <a class="btn btn-sm btn-outline-success" target="_blank" href="<?= URL_ROOT . htmlspecialchars($r['signed_file_path']) ?>">
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
