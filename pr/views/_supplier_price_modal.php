<?php
/**
 * Process Purchase Request (procurement) — triggered by the status-driven
 * ⚙ button on queue rows with status "pending".
 *
 * The body is a READ-ONLY replica of the New Purchase Request (PRF) modal:
 * header fields, full line-item table, signatories and notes — prefetched
 * from ?controller=pr&action=prfData. Supplier + price quotes are recorded
 * PER REQUESTED ITEM: each item's rows merge via rowspan (multiple suppliers
 * collapse into one item block) with inline Supplier/Price columns.
 *
 * Submitting (PrController::supplierSubmit) saves the quotes and flips the
 * request pending -> submitted (back to the requestor to print/sign/approve).
 * Submitted requests are locked.
 *
 * Included by procurement.php — needs $suppliers (active masters) in scope.
 */
$supplierOptionsJs = json_encode(array_map(static fn ($s) => [
    'id' => (int) $s['supplier_id'],
    'name' => (string) $s['supplier_name'],
], $suppliers ?? []));
?>
<div class="modal fade" id="supplierPriceModal" tabindex="-1" aria-labelledby="supplierPriceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-sm-down modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="?controller=pr&action=supplierSubmit" id="supplierPriceForm">
                <input type="hidden" name="prf_id" id="spPrfId" value="">
                <input type="hidden" name="back" id="spBack" value="queue">
                <div class="modal-header">
                    <h5 class="modal-title" id="supplierPriceModalLabel"><i class="bi bi-gear me-2"></i>Process Purchase Request <span class="text-muted fw-normal" id="spTitleNumber"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- PRF identity strip (instant, from the trigger's data-*) -->
                    <div class="row g-2 mb-3 border rounded p-2 bg-light">
                        <div class="col-md-3"><span class="text-muted small">PRF Number</span><div class="fw-semibold" id="spPrfNumber">&mdash;</div></div>
                        <div class="col-md-3"><span class="text-muted small">Date</span><div class="fw-semibold" id="spDate">&mdash;</div></div>
                        <div class="col-md-3"><span class="text-muted small">Status</span><div><span class="badge bg-secondary">Pending</span></div></div>
                        <div class="col-md-3"><span class="text-muted small">Requested by</span><div class="fw-semibold" id="spRequestor">&mdash;</div></div>
                    </div>

                    <!-- Header fields — read-only replica of the PRF form -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Department <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="spDepartment" readonly>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Purpose / Project Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="spPurpose" readonly>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" id="spDateInput" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Classification</label>
                            <input type="text" class="form-control" id="spClassification" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority Level</label>
                            <input type="text" class="form-control" id="spPriority" readonly>
                        </div>
                    </div>

                    <!-- Line items — read-only, same columns as the PRF form -->
                    <div class="d-flex justify-content-between align-items-center border-top pt-3 mb-2">
                        <h6 class="mb-0"><i class="bi bi-list-task me-1"></i>Line Items</h6>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:130px">Item Code<br><small class="text-muted fw-normal">optional</small></th>
                                    <th style="min-width:220px">Item Name / Description</th>
                                    <th style="min-width:120px">Item Specs</th>
                                    <th style="min-width:100px">Dimension</th>
                                    <th style="width:80px">Qty</th>
                                    <th style="width:70px">U/M</th>
                                    <th style="min-width:160px">Preferred Supplier</th>
                                    <th style="min-width:180px">Supplier <span class="text-danger">*</span></th>
                                    <th style="width:110px">Price <span class="text-danger">*</span></th>
                                    <th style="width:120px"></th>
                                </tr>
                            </thead>
                            <tbody id="spItemsBody">
                                <tr><td colspan="10" class="text-center text-muted small py-2">Loading&hellip;</td></tr>
                            </tbody>
                        </table>
                        <div class="form-text">Use <strong>＋</strong> on an item to add another supplier for it &mdash; extra rows merge into the same item. Every requested item needs at least one supplier + price before submitting.</div>
                    </div>

                    <!-- Signatories — read-only -->
                    <div class="row g-3 mb-3 border-top pt-3">
                        <div class="col-md-3">
                            <label class="form-label">Prepared by</label>
                            <input type="text" class="form-control" id="spPreparedBy" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Checked by</label>
                            <input type="text" class="form-control" id="spCheckedBy" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Approved by</label>
                            <input type="text" class="form-control" id="spApprovedBy" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Received by</label>
                            <input type="text" class="form-control" id="spReceivedBy" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes (supporting documents: sample photo, preferred supplier details, quotations, inventory report, etc.)</label>
                            <textarea class="form-control" id="spNotes" rows="2" readonly></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="spSubmitBtn"><i class="bi bi-send me-1"></i> Submit to Requestor</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('supplierPriceModal');
    if (!modalEl) return;
    const SUPPLIERS = <?= $supplierOptionsJs ?>;
    const itemsBody = document.getElementById('spItemsBody');
    // One state array per requested item; each holds its supplier/price rows.
    let itemsState = [];

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function supplierOptionsHtml(selected) {
        return '<option value="">Select supplier&hellip;</option>' + SUPPLIERS.map(function(s) {
            const isSel = selected !== undefined && selected !== '' && String(selected) === String(s.id);
            return '<option value="' + s.id + '"' + (isSel ? ' selected' : '') + '>' + esc(s.name) + '</option>';
        }).join('');
    }

    // Pull current input values back into itemsState (DOM order = state order).
    function harvest() {
        itemsBody.querySelectorAll('tr[data-ii]').forEach(function(tr) {
            const ii = parseInt(tr.getAttribute('data-ii'), 10);
            const qi = parseInt(tr.getAttribute('data-qi'), 10);
            if (isNaN(ii) || isNaN(qi) || !itemsState[ii] || !itemsState[ii].quotes[qi]) return;
            itemsState[ii].quotes[qi].supplier = tr.querySelector('select[name="supplier_id[]"]').value;
            itemsState[ii].quotes[qi].price = tr.querySelector('input[name="price[]"]').value;
        });
    }

    // Merged rendering: item cells rowspan across that item's supplier rows.
    function renderItems() {
        itemsBody.innerHTML = itemsState.map(function(it, ii) {
            const rows = it.quotes.length ? it.quotes : [{ supplier: '', price: '' }];
            const n = rows.length;
            const itemCells =
                '<td rowspan="' + n + '"><small>' + esc(it.code) + '</small></td>' +
                '<td rowspan="' + n + '">' + esc(it.name) + '</td>' +
                '<td rowspan="' + n + '"><small>' + esc(it.specs) + '</small></td>' +
                '<td rowspan="' + n + '"><small>' + esc(it.dim) + '</small></td>' +
                '<td rowspan="' + n + '" class="text-end">' + esc(it.qty) + '</td>' +
                '<td rowspan="' + n + '">' + esc(it.uom) + '</td>' +
                '<td rowspan="' + n + '"><small>' + esc(it.pref) + '</small></td>';
            const quoteCells = function(q, qi) {
                return '<td>' +
                        '<input type="hidden" name="pri_id[]" value="' + esc(it.pri_id) + '">' +
                        '<select class="form-select form-select-sm" name="supplier_id[]">' + supplierOptionsHtml(q.supplier) + '</select>' +
                    '</td>' +
                    '<td><input type="number" class="form-control form-control-sm" name="price[]" step="0.01" min="0" placeholder="0.00" value="' + esc(q.price) + '"></td>' +
                    '<td class="text-end text-nowrap">' +
                        '<button type="button" class="btn btn-sm btn-outline-primary sp-add-for-item" data-ii="' + ii + '" title="Add supplier for this item"><i class="bi bi-plus-lg"></i></button> ' +
                        '<button type="button" class="btn btn-sm btn-outline-danger sp-remove-row" data-ii="' + ii + '" data-qi="' + qi + '" title="Remove row"><i class="bi bi-x"></i></button>' +
                    '</td>';
            };
            return rows.map(function(q, qi) {
                if (qi === 0) {
                    return '<tr data-ii="' + ii + '" data-qi="0">' + itemCells + quoteCells(q, 0) + '</tr>';
                }
                return '<tr data-ii="' + ii + '" data-qi="' + qi + '">' + quoteCells(q, qi) + '</tr>';
            }).join('');
        }).join('');
    }

    // ＋ / ✕ live inside each item's merged block.
    itemsBody.addEventListener('click', function(e) {
        const addBtn = e.target.closest('.sp-add-for-item');
        if (addBtn) {
            harvest();
            const ii = parseInt(addBtn.getAttribute('data-ii'), 10);
            if (itemsState[ii]) {
                itemsState[ii].quotes.push({ supplier: '', price: '' });
                renderItems();
                const sel = itemsBody.querySelector(
                    'tr[data-ii="' + ii + '"][data-qi="' + (itemsState[ii].quotes.length - 1) + '"] select'
                );
                if (sel) {
                    sel.scrollIntoView({ block: 'nearest' });
                    sel.focus();
                }
            }
            return;
        }
        const delBtn = e.target.closest('.sp-remove-row');
        if (delBtn) {
            harvest();
            const ii = parseInt(delBtn.getAttribute('data-ii'), 10);
            const qi = parseInt(delBtn.getAttribute('data-qi'), 10);
            if (!itemsState[ii]) return;
            if (itemsState[ii].quotes.length > 1) {
                itemsState[ii].quotes.splice(qi, 1);
            } else {
                // Removing the only row leaves a fresh empty draft for the item.
                itemsState[ii].quotes[0] = { supplier: '', price: '' };
            }
            renderItems();
        }
    });

    function resetFields() {
        ['spDepartment', 'spPurpose', 'spDateInput', 'spClassification', 'spPriority',
         'spPreparedBy', 'spCheckedBy', 'spApprovedBy', 'spReceivedBy', 'spNotes'].forEach(function(id) {
            document.getElementById(id).value = '';
        });
        itemsState = [];
        itemsBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted small py-2">Loading&hellip;</td></tr>';
    }

    function fillFromData(prf) {
        document.getElementById('spPrfNumber').textContent = prf.pr_number || '—';
        document.getElementById('spTitleNumber').textContent = prf.pr_number ? '— ' + prf.pr_number : '';
        document.getElementById('spDate').textContent = prf.date_created || '—';
        document.getElementById('spDepartment').value = prf.department || '';
        document.getElementById('spPurpose').value = prf.purpose || '';
        document.getElementById('spDateInput').value = prf.date_created || '';
        document.getElementById('spClassification').value =
            prf.classification === 'repeat_order' ? 'Repeat Order' : 'New';
        document.getElementById('spPriority').value =
            prf.priority === 'immediate' ? 'Immediate' : 'Standard';
        document.getElementById('spPreparedBy').value = prf.prepared_by || '';
        document.getElementById('spCheckedBy').value = prf.checked_by || '';
        document.getElementById('spApprovedBy').value = prf.approved_by_name || '';
        document.getElementById('spReceivedBy').value = prf.received_by || '';
        document.getElementById('spNotes').value = prf.notes || '';
    }

    function fillItems(items) {
        if (!items || !items.length) {
            itemsState = [];
            itemsBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted small py-2">No line items</td></tr>';
            return;
        }
        itemsState = items.map(function(it) {
            return {
                pri_id: it.pri_id,
                code: it.item_code || '',
                name: it.item_name || '',
                specs: it.specs || '',
                dim: it.dimension || '',
                qty: it.qty ?? '',
                uom: it.uom || '',
                pref: it.preferred_supplier || '',
                // one blank draft row per requested item
                quotes: [{ supplier: '', price: '' }]
            };
        });
        renderItems();
    }

    modalEl.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        if (!btn) return;
        const prfId = btn.getAttribute('data-pr-id') || '';
        document.getElementById('spPrfId').value = prfId;
        document.getElementById('spBack').value = btn.getAttribute('data-back') || 'queue';

        // instant strip from data-*
        document.getElementById('spPrfNumber').textContent = btn.getAttribute('data-pr-number') || '—';
        document.getElementById('spTitleNumber').textContent = btn.getAttribute('data-pr-number') ? '— ' + btn.getAttribute('data-pr-number') : '';
        document.getElementById('spDate').textContent = btn.getAttribute('data-date') || '—';
        document.getElementById('spRequestor').textContent = btn.getAttribute('data-requestor') || '—';

        resetFields();

        fetch('?controller=pr&action=prfData&id=' + encodeURIComponent(prfId))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    Swal.fire({ icon: 'error', title: 'Cannot Process', text: data.error });
                    return;
                }
                fillFromData(data.prf || {});
                fillItems(data.items || []);
            })
            .catch(function() {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load the purchase request.' });
            });
    });

    document.getElementById('supplierPriceForm').addEventListener('submit', function(e) {
        harvest();
        // Every requested item needs at least one filled supplier + price row.
        const unquoted = itemsState.filter(function(it) {
            return !it.quotes.some(function(q) {
                return String(q.supplier) !== '' && String(q.price).trim() !== '';
            });
        });
        if (unquoted.length) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Quote every item',
                text: 'Add at least one supplier with a price for every requested item (' + unquoted.length + ' item(s) still unquoted).'
            });
        }
    });
});
</script>
