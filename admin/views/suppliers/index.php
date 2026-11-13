<?php $sup = htmlspecialchars($supController ?? 'admin'); ?>
<div class="d-flex justify-content-between mb-4">
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal"><i class="bi bi-plus-lg me-1"></i>Add Supplier</button>
    </div>
    <form method="GET" class="d-flex" style="width:25%">
        <input type="hidden" name="controller" value="<?= $sup ?>">
        <input type="hidden" name="action" value="suppliers">
        <input type="text" name="search" id="searchSupplier" class="form-control" placeholder="Search code, name, contact..." value="<?= htmlspecialchars($search ?? '') ?>">
    </form>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Code</th><th>Name</th><th>Contact Person</th><th>Phone</th><th>Email</th>
                    <th>Status</th><th>Created</th><th>Updated</th><th>Actions</th>
                </tr>
            </thead>
            <tbody id="supplierTableBody">
                <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td><strong class="text-primary"><?= htmlspecialchars($s['supplier_code']) ?></strong></td>
                    <td><?= htmlspecialchars($s['supplier_name']) ?></td>
                    <td><small><?= htmlspecialchars($s['contact_person'] ?? '-') ?></small></td>
                    <td><small><?= htmlspecialchars($s['phone'] ?? '-') ?></small></td>
                    <td><small><?= htmlspecialchars($s['email'] ?? '-') ?></small></td>
                    <td><span class="badge bg-<?= $s['status'] ? 'success' : 'secondary' ?>"><?= $s['status'] ? 'Active' : 'Inactive' ?></span></td>
                    <td><?= date('Y-m-d', strtotime($s['date_created'])) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($s['last_update'])) ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#supplierEditModal"
                                data-id="<?= (int)$s['supplier_id'] ?>"
                                data-code="<?= htmlspecialchars($s['supplier_code']) ?>"
                                data-name="<?= htmlspecialchars($s['supplier_name']) ?>"
                                data-contact="<?= htmlspecialchars($s['contact_person'] ?? '') ?>"
                                data-phone="<?= htmlspecialchars($s['phone'] ?? '') ?>"
                                data-email="<?= htmlspecialchars($s['email'] ?? '') ?>"
                                data-address="<?= htmlspecialchars($s['address'] ?? '') ?>"><i class="bi bi-pencil"></i></button>
                        <a href="?controller=<?= $sup ?>&action=supplierToggleStatus&id=<?= (int)$s['supplier_id'] ?>&search=<?= urlencode($search ?? '') ?>&page=<?= (int)($page ?? 1) ?>"
                           class="btn btn-sm btn-warning" title="Toggle status"><i class="bi bi-toggle-on"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($suppliers)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No suppliers found</td></tr>
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
            <a class="page-link" href="?controller=<?= $sup ?>&action=suppliers&page=<?= $page - 1 ?>&search=<?= urlencode($search ?? '') ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="?controller=<?= $sup ?>&action=suppliers&page=<?= $p ?>&search=<?= urlencode($search ?? '') ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?controller=<?= $sup ?>&action=suppliers&page=<?= $page + 1 ?>&search=<?= urlencode($search ?? '') ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<!-- ADD SUPPLIER MODAL -->
<div class="modal fade" id="supplierModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="?controller=<?= $sup ?>&action=supplierCreate&search=<?= urlencode($search ?? '') ?>&page=<?= (int)($page ?? 1) ?>">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Supplier Code *</label><input type="text" name="supplier_code" class="form-control" maxlength="50" placeholder="e.g. SUPPLIER-006" required></div>
                    <div class="mb-3"><label class="form-label">Supplier Name *</label><input type="text" name="supplier_name" class="form-control" maxlength="255" required></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Contact Person</label><input type="text" name="contact_person" class="form-control" maxlength="100"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" maxlength="50"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="100"></div>
                    <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT SUPPLIER MODAL -->
<div class="modal fade" id="supplierEditModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Edit Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="?controller=<?= $sup ?>&action=supplierUpdate&search=<?= urlencode($search ?? '') ?>&page=<?= (int)($page ?? 1) ?>">
                <div class="modal-body">
                    <input type="hidden" name="supplier_id" id="edit_supplier_id">
                    <div class="mb-3"><label class="form-label">Supplier Code *</label><input type="text" name="supplier_code" id="edit_supplier_code" class="form-control" maxlength="50" required></div>
                    <div class="mb-3"><label class="form-label">Supplier Name *</label><input type="text" name="supplier_name" id="edit_supplier_name" class="form-control" maxlength="255" required></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Contact Person</label><input type="text" name="contact_person" id="edit_supplier_contact" class="form-control" maxlength="100"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" id="edit_supplier_phone" class="form-control" maxlength="50"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" id="edit_supplier_email" class="form-control" maxlength="100"></div>
                    <div class="mb-3"><label class="form-label">Address</label><textarea name="address" id="edit_supplier_address" class="form-control" rows="2"></textarea></div>
                    <small class="text-muted">Use the toggle button in the table to activate/deactivate this supplier.</small>
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
document.getElementById('searchSupplier').addEventListener('input', function() {
    clearTimeout(_searchTimer);
    var form = this.closest('form');
    _searchTimer = setTimeout(function() { form.submit(); }, 500);
});

(function() {
    var s = document.getElementById('searchSupplier');
    if (s && s.value) { s.focus(); s.setSelectionRange(s.value.length, s.value.length); }
})();

document.getElementById('supplierEditModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    document.getElementById('edit_supplier_id').value = button.getAttribute('data-id');
    document.getElementById('edit_supplier_code').value = button.getAttribute('data-code');
    document.getElementById('edit_supplier_name').value = button.getAttribute('data-name');
    document.getElementById('edit_supplier_contact').value = button.getAttribute('data-contact') || '';
    document.getElementById('edit_supplier_phone').value = button.getAttribute('data-phone') || '';
    document.getElementById('edit_supplier_email').value = button.getAttribute('data-email') || '';
    document.getElementById('edit_supplier_address').value = button.getAttribute('data-address') || '';
});
</script>
