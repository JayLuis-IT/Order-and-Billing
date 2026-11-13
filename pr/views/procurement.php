<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="alert alert-info py-2 small mb-0 flex-grow-1">
        <i class="bi bi-info-circle me-1"></i>
        Approved Purchase Request Forms (PRF) forwarded by requestors after supervisor and President sign-off. Create the official Purchase Requisition (PR) from each PRF, then print it.
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
                    <tr><td colspan="9" class="text-center text-muted py-4">No approved purchase request forms yet.</td></tr>
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
                        <td><?= htmlspecialchars(ucfirst($r['department'])) ?></td>
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
                                <?php else: ?>
                                <button type="button" class="btn btn-sm btn-primary create-pr-btn tt" data-bs-placement="bottom"
                                        data-prf-id="<?= (int) $r['pr_id'] ?>"
                                        title="Create PR from <?= htmlspecialchars($r['pr_number']) ?>" aria-label="Create PR">
                                    <i class="bi bi-file-earmark-plus"></i> Create PR
                                </button>
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

<!-- ═══ Create PR Modal ═══ -->
<div class="modal fade" id="createPrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-sm-down modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="?controller=pr&action=createPr" id="prCreateForm">
                <input type="hidden" name="prf_id" id="createPrPrfId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPrTitle"><i class="bi bi-file-earmark-plus me-2"></i>Create Purchase Requisition</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">PR Number</label>
                            <input type="text" class="form-control" name="pr_number" id="createPrNumber" pattern="PR\d{1,6}" title="PR number, e.g. PR008126">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="pr_date" value="<?= htmlspecialchars(date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">PO Site</label>
                            <select class="form-select" name="po_site">
                                <?php foreach (App\Models\ProcurementPrModel::PO_SITES as $code => $site): ?>
                                <option value="<?= htmlspecialchars($code) ?>" selected><?= htmlspecialchars($site['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ship To</label>
                            <select class="form-select" name="ship_to">
                                <?php foreach (App\Models\ProcurementPrModel::PO_SITES as $code => $site): ?>
                                <option value="<?= htmlspecialchars($code) ?>" selected><?= htmlspecialchars($site['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" id="createPrDepartment" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Requisitioner</label>
                            <input type="text" class="form-control" id="createPrRequisitioner" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Purpose</label>
                            <input type="text" class="form-control" id="createPrPurpose" readonly>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top pt-3 mb-2">
                        <h6 class="mb-0"><i class="bi bi-list-task me-1"></i>Line Items</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="createPrAddLineBtn">
                            <i class="bi bi-plus-lg me-1"></i> Add Line Item
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle" id="createPrItemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:150px">Item Number</th>
                                    <th style="min-width:260px">Description <span class="text-danger">*</span></th>
                                    <th style="width:100px">Qty <span class="text-danger">*</span></th>
                                    <th style="width:90px">Unit</th>
                                    <th style="min-width:150px">Account Charged</th>
                                    <th style="width:140px">Date Req'd</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="createPrItemsBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Create PR</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // (Tooltips and the shared Add Request PRF form are wired by _prf_form.php)

    // ── Create PR modal wiring ──
    const modalEl = document.getElementById('createPrModal');
    const body = document.getElementById('createPrItemsBody');
    const addBtn = document.getElementById('createPrAddLineBtn');
    const prNumberInput = document.getElementById('createPrNumber');
    let currentPrNumber = '';
    let currentPrfDate = '';

    // Item numbers follow the ERP rule: a row with a PRF Item Code keeps that
    // code verbatim; uncoded rows get {PR_NUMBER}-{n} where n counts only the
    // uncoded rows on the PR (1, 2, 3, ...).
    function renumberItemNumbers() {
        let seq = 0;
        body.querySelectorAll('tr').forEach(function(tr) {
            const desc = tr.querySelector('input[name="description[]"]').value.trim();
            const code = tr.querySelector('input[name="item_code[]"]').value.trim();
            const cell = tr.querySelector('.item-number');
            if (code !== '') {
                cell.textContent = code;
                return;
            }
            if (desc !== '' && currentPrNumber) {
                seq++;
                cell.textContent = currentPrNumber + '-' + seq;
            } else {
                cell.textContent = '';
            }
        });
    }

    function addRow(item) {
        item = item || {};
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="text-center align-middle item-number" style="font-size:0.75rem; white-space:nowrap;"></td>' +
            '<input type="hidden" name="item_code[]" value="">' +
            '<td><input type="text" class="form-control form-control-sm" name="description[]" required></td>' +
            '<td><input type="number" step="any" min="0" class="form-control form-control-sm" name="qty[]" required></td>' +
            '<td><input type="text" class="form-control form-control-sm" name="unit[]"></td>' +
            '<td><input type="text" class="form-control form-control-sm" name="account_charged[]" placeholder="e.g., 701144-000-000"></td>' +
            '<td><input type="date" class="form-control form-control-sm" name="date_required[]"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove"><i class="bi bi-x"></i></button></td>';
        body.appendChild(tr);
        const descInput = tr.querySelector('input[name="description[]"]');
        if (item.item_name !== undefined) {
            descInput.value = item.item_name || '';
            tr.querySelector('input[name="item_code[]"]').value = item.item_code || '';
            if (item.qty !== null && item.qty !== undefined && item.qty !== '') {
                tr.querySelector('input[name="qty[]"]').value = parseFloat(item.qty);
            }
            tr.querySelector('input[name="unit[]"]').value = item.uom || '';
        }
        tr.querySelector('input[name="date_required[]"]').value = item.date_required || currentPrfDate || '';
        descInput.addEventListener('input', renumberItemNumbers);
        tr.querySelector('.remove-row').addEventListener('click', function() {
            if (body.querySelectorAll('tr').length > 1) tr.remove();
            renumberItemNumbers();
        });
    }

    addBtn.addEventListener('click', function() { addRow(); });
    prNumberInput.addEventListener('input', renumberItemNumbers);

    document.querySelectorAll('.create-pr-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            fetch('?controller=pr&action=prfData&id=' + encodeURIComponent(btn.getAttribute('data-prf-id')))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.error) {
                        Swal.fire({ icon: 'error', title: 'Cannot Create PR', text: data.error });
                        return;
                    }
                    document.getElementById('createPrTitle').innerHTML =
                        '<i class="bi bi-file-earmark-plus me-2"></i>Create Purchase Requisition from ' + data.prf.pr_number;
                    document.getElementById('createPrPrfId').value = data.prf.pr_id;
                    document.getElementById('createPrNumber').value = data.pr_number;
                    currentPrNumber = data.pr_number;
                    currentPrfDate = data.prf.date_created || '';
                    document.getElementById('createPrDepartment').value = data.prf.department || '';
                    document.getElementById('createPrRequisitioner').value = data.prf.prepared_by || '';
                    document.getElementById('createPrPurpose').value = data.prf.purpose || '';
                    body.innerHTML = '';
                    if (data.items.length) {
                        data.items.forEach(function(it) { addRow(it); });
                    } else {
                        addRow();
                    }
                    renumberItemNumbers();
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                })
                .catch(function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load the purchase request form.' });
                });
        });
    });
});
</script>
