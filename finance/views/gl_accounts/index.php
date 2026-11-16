<div class="d-flex justify-content-between mb-4">
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#glAccountModal"><i class="bi bi-plus-lg me-1"></i>Add GL Account</button>
        <a href="?controller=finance&action=glAccountImportForm" class="btn btn-success"><i class="bi bi-upload me-1"></i>Import</a>
    </div>
    <form method="GET" class="d-flex" style="width:25%">
        <input type="hidden" name="controller" value="finance">
        <input type="hidden" name="action" value="glAccounts">
        <input type="text" name="search" id="searchGlAccount" class="form-control" placeholder="Search code, description..." value="<?= htmlspecialchars($search ?? '') ?>">
    </form>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>GL Account Code</th>
                    <th>GL Account Description</th>
                    <th>Report Included</th>
                    <th>Grouping in Financial Report</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="glAccountTableBody">
                <?php foreach (($gl_accounts ?? []) as $g): ?>
                <tr>
                    <td><strong class="text-primary"><?= htmlspecialchars($g['gl_account_code']) ?></strong></td>
                    <td><?= htmlspecialchars($g['gl_account_description']) ?></td>
                    <td><small><?= htmlspecialchars($g['report_included'] ?? '—') ?></small></td>
                    <td><small><?= htmlspecialchars($g['grouping_financial_report'] ?? '—') ?></small></td>
                    <td><span class="badge bg-<?= $g['status'] ? 'success' : 'secondary' ?>"><?= $g['status'] ? 'Active' : 'Inactive' ?></span></td>
                    <td><?= date('Y-m-d', strtotime($g['date_created'])) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($g['last_update'])) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#glAccountEditModal"
                                data-id="<?= (int)$g['gl_account_id'] ?>"
                                data-code="<?= htmlspecialchars($g['gl_account_code']) ?>"
                                data-description="<?= htmlspecialchars($g['gl_account_description']) ?>"
                                data-report="<?= htmlspecialchars($g['report_included'] ?? '') ?>"
                                data-grouping="<?= htmlspecialchars($g['grouping_financial_report'] ?? '') ?>"><i class="bi bi-pencil"></i></button>
                        <a href="?controller=finance&action=glAccountToggleStatus&id=<?= (int)$g['gl_account_id'] ?>&search=<?= urlencode($search ?? '') ?>&page=<?= (int)($page ?? 1) ?>"
                           class="btn btn-sm btn-warning" data-confirm="Toggle status of <?= htmlspecialchars($g['gl_account_code']) ?>?" title="Toggle status"><i class="bi bi-toggle-on"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($gl_accounts)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No GL accounts found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (($totalPages ?? 1) > 1): ?>
<?php $pages = \App\Helpers\Pagination::getPageRange($page, $totalPages); ?>
<nav>
    <ul class="pagination justify-content-center mt-4">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=finance&action=glAccounts&page=<?= $page - 1 ?>&search=<?= urlencode($search ?? '') ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="?controller=finance&action=glAccounts&page=<?= $p ?>&search=<?= urlencode($search ?? '') ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=finance&action=glAccounts&page=<?= $page + 1 ?>&search=<?= urlencode($search ?? '') ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<!-- ADD GL ACCOUNT MODAL -->
<div class="modal fade" id="glAccountModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-journal-text me-2"></i>Add GL Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="?controller=finance&action=glAccountCreate&search=<?= urlencode($search ?? '') ?>&page=<?= (int)($page ?? 1) ?>">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">GL Account Code <span class="text-danger">*</span></label><input type="text" name="gl_account_code" class="form-control" maxlength="30" placeholder="e.g. 101101000000" required></div>
                    <div class="mb-3"><label class="form-label">GL Account Description <span class="text-danger">*</span></label><input type="text" name="gl_account_description" class="form-control" maxlength="255" placeholder="e.g. CIB - BPI Collections 5774" required></div>
                    <div class="mb-3"><label class="form-label">Report Included</label><input type="text" name="report_included" class="form-control" maxlength="100" placeholder="e.g. Balance Sheet"></div>
                    <div class="mb-3"><label class="form-label">Grouping in Financial Report</label><input type="text" name="grouping_financial_report" class="form-control" maxlength="100" placeholder="e.g. Cash and Banks"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT GL ACCOUNT MODAL -->
<div class="modal fade" id="glAccountEditModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit GL Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="?controller=finance&action=glAccountUpdate&search=<?= urlencode($search ?? '') ?>&page=<?= (int)($page ?? 1) ?>">
                <div class="modal-body">
                    <input type="hidden" name="gl_account_id" id="edit_gl_account_id">
                    <div class="mb-3"><label class="form-label">GL Account Code <span class="text-danger">*</span></label><input type="text" name="gl_account_code" id="edit_gl_account_code" class="form-control" maxlength="30" required></div>
                    <div class="mb-3"><label class="form-label">GL Account Description <span class="text-danger">*</span></label><input type="text" name="gl_account_description" id="edit_gl_account_description" class="form-control" maxlength="255" required></div>
                    <div class="mb-3"><label class="form-label">Report Included</label><input type="text" name="report_included" id="edit_report_included" class="form-control" maxlength="100"></div>
                    <div class="mb-3"><label class="form-label">Grouping in Financial Report</label><input type="text" name="grouping_financial_report" id="edit_grouping_financial_report" class="form-control" maxlength="100"></div>
                    <small class="text-muted">Use the toggle button in the table to activate/deactivate this GL Account.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var _searchTimer;
document.getElementById('searchGlAccount').addEventListener('input', function() {
    clearTimeout(_searchTimer);
    var form = this.closest('form');
    _searchTimer = setTimeout(function() { form.submit(); }, 500);
});

(function() {
    var s = document.getElementById('searchGlAccount');
    if (s && s.value) { s.focus(); s.setSelectionRange(s.value.length, s.value.length); }
})();

document.getElementById('glAccountEditModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    document.getElementById('edit_gl_account_id').value = button.getAttribute('data-id');
    document.getElementById('edit_gl_account_code').value = button.getAttribute('data-code');
    document.getElementById('edit_gl_account_description').value = button.getAttribute('data-description');
    document.getElementById('edit_report_included').value = button.getAttribute('data-report') || '';
    document.getElementById('edit_grouping_financial_report').value = button.getAttribute('data-grouping') || '';
});
</script>
