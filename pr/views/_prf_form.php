<?php
/** Shared "Add Request" PRF form — used by the requestor view and the
 *  Procurement queue so any department (including warehouse/procurement
 *  users) can submit a Purchase Request Form. */
$dept = $dept ?? ($_SESSION['department'] ?? '');
$today = $today ?? date('Y-m-d');
?>
<!-- ═══ Add Request Modal ═══ -->
<div class="modal fade" id="createPrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-sm-down modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="?controller=pr&action=create" id="prForm">
                <input type="hidden" name="pr_id" id="prFormPrId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="prModalTitle"><i class="bi bi-file-earmark-text me-2"></i>New Purchase Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Header fields -->
                    <div class="row g-3 mb-2">
                        <div class="col-md-4">
                            <label class="form-label">Department <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars(ucfirst($dept)) ?>" readonly>
                            <input type="hidden" name="department" value="<?= htmlspecialchars($dept) ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Purpose / Project Name <span class="text-danger">*</span></label>
                            <input type="text" name="purpose" class="form-control" placeholder="e.g., Production line maintenance supplies" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" name="date_created" class="form-control" value="<?= htmlspecialchars($today) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Classification</label>
                            <select name="classification" class="form-select">
                                <option value="new">New</option>
                                <option value="repeat_order">Repeat Order</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority Level</label>
                            <select name="priority" class="form-select">
                                <option value="standard">Standard</option>
                                <option value="immediate">Immediate</option>
                            </select>
                        </div>
                    </div>

                    <!-- Line items -->
                    <div class="d-flex justify-content-between align-items-center border-top pt-3 mb-2">
                        <h6 class="mb-0"><i class="bi bi-list-task me-1"></i>Line Items</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn">
                            <i class="bi bi-plus-lg me-1"></i> Add Line Item
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle" id="lineItemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:130px">Item Code<br><small class="text-muted fw-normal">optional</small></th>
                                    <th style="min-width:220px">Item Name / Description <span class="text-danger">*</span></th>
                                    <th style="min-width:120px">Item Specs</th>
                                    <th style="min-width:100px">Dimension</th>
                                    <th style="width:100px">Qty</th>
                                    <th style="width:90px">U/M</th>
                                    <th style="min-width:160px">Preferred Supplier</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="lineItemsBody"></tbody>
                        </table>
                    </div>

                    <!-- Signatories -->
                    <div class="row g-3 border-top pt-3">
                        <div class="col-md-3">
                            <label class="form-label">Prepared by</label>
                            <input type="text" class="form-control" name="prepared_by" value="<?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? '') ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Checked by</label>
                            <input type="text" class="form-control" name="checked_by" placeholder="Supervisor name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Approved by</label>
                            <input type="text" class="form-control" name="approved_by" value="Ferdinand Ginete" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Received by</label>
                            <input type="text" class="form-control" name="received_by" placeholder="Receiving name">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes (supporting documents: sample photo, preferred supplier details, quotations, inventory report, etc.)</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Dynamic line items ──
    const body = document.getElementById('lineItemsBody');
    const addBtn = document.getElementById('addLineBtn');
    const prForm = document.getElementById('prForm');
    const prModalEl = document.getElementById('createPrModal');
    const prModalTitle = document.getElementById('prModalTitle');
    const prFormPrId = document.getElementById('prFormPrId');
    const CREATE_ACTION = '?controller=pr&action=create';
    let rowIdx = 0;

    function addRow(item) {
        item = item || {};
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' +
                '<input type="hidden" name="item_id[]">' +
                '<input type="text" class="form-control form-control-sm item-code" name="item_code[]" autocomplete="off">' +
                '<div class="ac-menu list-group position-absolute d-none" style="z-index:1060; max-height:200px; overflow-y:auto;"></div>' +
            '</td>' +
            '<td><input type="text" class="form-control form-control-sm item-name" name="item_name[]" required autocomplete="off"></td>' +
            '<td><input type="text" class="form-control form-control-sm" name="specs[]" placeholder="e.g., 2.0mm"></td>' +
            '<td><input type="text" class="form-control form-control-sm" name="dimension[]" placeholder="e.g., n/a"></td>' +
            '<td><input type="number" step="any" min="0" class="form-control form-control-sm" name="qty[]" required></td>' +
            '<td><input type="text" class="form-control form-control-sm" name="uom[]" list="umList" placeholder="KG"></td>' +
            '<td><input type="text" class="form-control form-control-sm" name="preferred_supplier[]" autocomplete="off"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove"><i class="bi bi-x"></i></button></td>';
        body.appendChild(tr);

        if (item.item_name !== undefined) {
            tr.querySelector('input[name="item_id[]"]').value = item.item_id || '';
            tr.querySelector('.item-code').value = item.item_code || '';
            tr.querySelector('.item-name').value = item.item_name || '';
            tr.querySelector('input[name="specs[]"]').value = item.specs || '';
            tr.querySelector('input[name="dimension[]"]').value = item.dimension || '';
            if (item.qty !== null && item.qty !== undefined && item.qty !== '') {
                tr.querySelector('input[name="qty[]"]').value = parseFloat(item.qty);
            }
            tr.querySelector('input[name="uom[]"]').value = item.uom || '';
            tr.querySelector('input[name="preferred_supplier[]"]').value = item.preferred_supplier || '';
        }

        const codeInput = tr.querySelector('.item-code');
        const nameInput = tr.querySelector('.item-name');
        const menu = tr.querySelector('.ac-menu');
        let debounce = null;

        function hideMenu() { menu.classList.add('d-none'); menu.innerHTML = ''; }

        function search(q) {
            fetch('?controller=pr&action=searchItems&q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(items => {
                    menu.innerHTML = '';
                    if (!items.length) { hideMenu(); return; }
                    items.forEach(function(it) {
                        const a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action py-1';
                        a.style.fontSize = '0.8rem';
                        a.innerHTML = '<strong>' + (it.item_code || '—') + '</strong> — ' + it.item_description;
                        a.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            codeInput.value = it.item_code || '';
                            tr.querySelector('input[name="item_id[]"]').value = it.item_id || '';
                            nameInput.value = it.item_description || '';
                            const uom = tr.querySelector('input[name="uom[]"]');
                            if (!uom.value && it.item_uom) uom.value = it.item_uom;
                            hideMenu();
                        });
                        menu.appendChild(a);
                    });
                    menu.classList.remove('d-none');
                });
        }

        codeInput.addEventListener('input', function() {
            tr.querySelector('input[name="item_id[]"]').value = '';
            clearTimeout(debounce);
            const q = this.value.trim();
            if (q === '') { hideMenu(); return; }
            debounce = setTimeout(function() { search(q); }, 250);
        });
        codeInput.addEventListener('blur', function() { setTimeout(hideMenu, 150); });

        tr.querySelector('.remove-row').addEventListener('click', function() {
            if (body.querySelectorAll('tr').length > 1) {
                tr.remove();
            }
        });
    }

    addBtn.addEventListener('click', function() { addRow(); });
    addRow(); // start with one blank row

    // ── Edit mode: reuse the same modal, prefill from the stored PR ──
    const setCreateMode = function() {
        prModalTitle.innerHTML = '<i class="bi bi-file-earmark-text me-2"></i>New Purchase Request';
        prForm.action = CREATE_ACTION;
        prFormPrId.value = '';
    };
    prModalEl.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        const editId = (btn && btn.classList.contains('edit-pr-btn')) ? btn.getAttribute('data-edit-id') : null;
        if (!editId) { setCreateMode(); return; }
        fetch('?controller=pr&action=edit&id=' + encodeURIComponent(editId))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    bootstrap.Modal.getOrCreateInstance(prModalEl).hide();
                    Swal.fire({ icon: 'error', title: 'Cannot Edit', text: data.error });
                    return;
                }
                prModalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Purchase Request ' + data.pr.pr_number;
                prForm.action = '?controller=pr&action=update';
                prFormPrId.value = data.pr.pr_id;
                document.getElementById('createPrModal').querySelector('input[name="purpose"]').value = data.pr.purpose || '';
                prForm.querySelector('input[name="date_created"]').value = data.pr.date_created || '';
                prForm.querySelector('select[name="classification"]').value = data.pr.classification || 'new';
                prForm.querySelector('select[name="priority"]').value = data.pr.priority || 'standard';
                prForm.querySelector('input[name="checked_by"]').value = data.pr.checked_by || '';
                prForm.querySelector('input[name="approved_by"]').value = data.pr.approved_by_name || 'Ferdinand Ginete';
                prForm.querySelector('input[name="received_by"]').value = data.pr.received_by || '';
                prForm.querySelector('textarea[name="notes"]').value = data.pr.notes || '';
                body.innerHTML = '';
                if (data.items.length) {
                    data.items.forEach(function(it) { addRow(it); });
                } else {
                    addRow();
                }
            })
            .catch(function() {
                bootstrap.Modal.getOrCreateInstance(prModalEl).hide();
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load the purchase request.' });
            });
    });
    document.querySelectorAll('.edit-pr-btn').forEach(function(b) {
        b.addEventListener('click', function() {
            bootstrap.Modal.getOrCreateInstance(prModalEl).show(this);
        });
    });

    // Activate Bootstrap tooltips on the action icon buttons
    document.querySelectorAll('.tt').forEach(function(el) {
        new bootstrap.Tooltip(el);
    });

    // U/M suggestions datalist
    const dl = document.createElement('datalist');
    dl.id = 'umList';
    ['Pc', 'KG', 'Roll', 'Box', 'Set', 'Lot', 'Liter', 'Meter', 'Pack'].forEach(function(u) {
        const o = document.createElement('option');
        o.value = u;
        dl.appendChild(o);
    });
    document.body.appendChild(dl);
});
</script>
