<?php
/**
 * Shared Process Purchase Request modal — used by the requestor list
 * (procurement viewers) and the procurement queue. Submitting creates the
 * formal PR###### and flips the PRF to In Process in one action
 * (PrController::process). Trigger buttons carry data-* row attributes;
 * the form body is prefetched from ?controller=pr&action=prfData.
 */
?>
<div class="modal fade" id="processPrModal" tabindex="-1" aria-labelledby="processPrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-sm-down modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="?controller=pr&action=process" id="processPrForm">
                <input type="hidden" name="prf_id" id="processPrfId" value="">
                <input type="hidden" name="back" id="processPrBack" value="list">
                <div class="modal-header">
                    <h5 class="modal-title" id="processPrModalLabel"><i class="bi bi-gear me-2"></i>Process Purchase Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- A. PRF summary (from the trigger's data-* attributes) -->
                    <div class="row g-2 mb-3 border rounded p-2 bg-light">
                        <div class="col-md-3"><span class="text-muted small">PRF Number</span><div class="fw-semibold" id="pprPrfNumber">&mdash;</div></div>
                        <div class="col-md-3"><span class="text-muted small">Date</span><div class="fw-semibold" id="pprPrfDate">&mdash;</div></div>
                        <div class="col-md-3"><span class="text-muted small">Department</span><div class="fw-semibold" id="pprPrfDepartment">&mdash;</div></div>
                        <div class="col-md-3"><span class="text-muted small">Priority</span><div class="fw-semibold" id="pprPrfPriority">&mdash;</div></div>
                        <div class="col-md-6"><span class="text-muted small">Purpose / Project</span><div class="fw-semibold" id="pprPrfPurpose">&mdash;</div></div>
                        <div class="col-md-3"><span class="text-muted small">Items</span><div class="fw-semibold" id="pprPrfItems">&mdash;</div></div>
                        <div class="col-md-3"><span class="text-muted small">Status</span><div><span class="badge bg-success" id="pprPrfStatus">Approved</span></div></div>
                    </div>

                    <!-- B. PR header -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">PR Number</label>
                            <input type="text" class="form-control" name="pr_number" id="pprNumber" readonly pattern="PR\d{1,6}" title="System generated">
                            <div class="form-text mt-0"><i class="bi bi-lock-fill me-1"></i>System generated</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">PR Date</label>
                            <input type="date" class="form-control" name="pr_date" value="<?= htmlspecialchars(date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">PO Site</label>
                            <select class="form-select" name="po_site" id="pprPoSite"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ship To</label>
                            <select class="form-select" name="ship_to" id="pprShipTo"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" id="pprDepartment" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Requisitioner</label>
                            <input type="text" class="form-control" id="pprRequisitioner" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Purpose</label>
                            <input type="text" class="form-control" id="pprPurpose" readonly>
                        </div>
                    </div>

                    <!-- C. Line items (prefilled from the PRF) -->
                    <div class="d-flex justify-content-between align-items-center border-top pt-3 mb-2">
                        <h6 class="mb-0"><i class="bi bi-list-task me-1"></i>Line Items</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="pprAddLineBtn">
                            <i class="bi bi-plus-lg me-1"></i> Add Line Item
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle" id="pprItemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:220px">Description <span class="text-danger">*</span></th>
                                    <th style="min-width:170px">Item Number</th>
                                    <th style="width:90px">Qty <span class="text-danger">*</span></th>
                                    <th style="width:80px">Unit</th>
                                    <th style="min-width:160px">Account Description</th>
                                    <th style="min-width:140px">Account Charged</th>
                                    <th style="width:140px">Date Req'd</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="pprItemsBody"></tbody>
                        </table>
                    </div>
                    <div class="form-text">Type an Item Number to auto-fill its Description from the Item Master List; leave it blank to auto-assign {PR Number}-{n}.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="pprSubmitBtn"><i class="bi bi-gear me-1"></i> Process</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('processPrModal');
    if (!modalEl) return;
    const form = document.getElementById('processPrForm');
    const body = document.getElementById('pprItemsBody');
    const addBtn = document.getElementById('pprAddLineBtn');
    const prNumberInput = document.getElementById('pprNumber');
    const deptMap = { admin: 'System Admin', warehouse: 'Warehouse', production: 'Production', finance: 'Finance', qc: 'QC', qa: 'QA', rnd: 'R&D' };
    let currentPrNumber = '';
    let currentPrfDate = '';

    function deptLabelJs(d) {
        if (!d) return '—';
        return deptMap[d] || (d.charAt(0).toUpperCase() + d.slice(1));
    }

    function addRow(item) {
        item = item || {};
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td style="position:relative;">' +
                '<input type="text" class="form-control form-control-sm ppr-item-desc" name="description[]" autocomplete="off" required>' +
                '<div class="desc-ac-menu list-group position-absolute d-none" style="z-index:1060; max-height:200px; overflow-y:auto;"></div>' +
            '</td>' +
            '<td style="position:relative;">' +
                '<input type="text" class="form-control form-control-sm ppr-item-code" name="item_code[]" autocomplete="off" placeholder="Item number/code">' +
                '<div class="item-ac-menu list-group position-absolute d-none" style="z-index:1060; max-height:200px; overflow-y:auto;"></div>' +
            '</td>' +
            '<td><input type="number" step="any" min="0" class="form-control form-control-sm" name="qty[]" required></td>' +
            '<td><input type="text" class="form-control form-control-sm" name="unit[]"></td>' +
            '<td style="position:relative;">' +
                '<input type="text" class="form-control form-control-sm ppr-account-desc" name="account_desc[]" autocomplete="off" placeholder="Account description">' +
                '<div class="gl-desc-ac-menu list-group position-absolute d-none" style="z-index:1060; max-height:200px; overflow-y:auto;"></div>' +
            '</td>' +
            '<td style="position:relative;">' +
                '<input type="text" class="form-control form-control-sm ppr-account-code" name="account_charged[]" autocomplete="off" placeholder="e.g., 101101000000">' +
                '<div class="gl-ac-menu list-group position-absolute d-none" style="z-index:1060; max-height:200px; overflow-y:auto;"></div>' +
            '</td>' +
            '<td><input type="date" class="form-control form-control-sm" name="date_required[]"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger ppr-remove-row" title="Remove"><i class="bi bi-x"></i></button></td>';
        body.appendChild(tr);

        const codeInput = tr.querySelector('.ppr-item-code');
        const menu = tr.querySelector('.item-ac-menu');
        const descInput = tr.querySelector('input[name="description[]"]');
        const unitInput = tr.querySelector('input[name="unit[]"]');

        if (item.item_name !== undefined) {
            codeInput.value = item.item_code || '';
            descInput.value = item.item_name || '';
            if (item.qty !== null && item.qty !== undefined && item.qty !== '') {
                tr.querySelector('input[name="qty[]"]').value = parseFloat(item.qty);
            }
            unitInput.value = item.uom || '';
        }
        tr.querySelector('input[name="date_required[]"]').value = item.date_required || currentPrfDate || '';

        // ── Item Number autocomplete + description autofill ──
        function hideMenu() { menu.classList.add('d-none'); menu.innerHTML = ''; }
        function fillFromItem(it) {
            codeInput.value = it.item_code || codeInput.value;
            if (it.item_description) descInput.value = it.item_description;
            if (!unitInput.value && it.item_uom) unitInput.value = it.item_uom;
        }
        function search(q) {
            fetch('?controller=pr&action=searchItems&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(items) {
                    menu.innerHTML = '';
                    // Exact typed match -> autofill description (and unit when empty).
                    const exact = (items || []).find(function(i) {
                        return (i.item_code || '').toLowerCase() === q.toLowerCase();
                    });
                    if (exact) fillFromItem(exact);
                    if (!items || !items.length) { hideMenu(); return; }
                    items.forEach(function(it) {
                        const a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action py-1';
                        a.style.fontSize = '0.8rem';
                        a.innerHTML = '<strong>' + (it.item_code || '—') + '</strong> — ' + it.item_description;
                        a.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            fillFromItem(it);
                            hideMenu();
                        });
                        menu.appendChild(a);
                    });
                    menu.classList.remove('d-none');
                })
                .catch(hideMenu);
        }
        let acTimer = null;
        codeInput.addEventListener('input', function() {
            clearTimeout(acTimer);
            const q = codeInput.value.trim();
            if (q === '') { hideMenu(); return; }
            acTimer = setTimeout(function() { search(q); }, 250);
        });
        codeInput.addEventListener('blur', function() { setTimeout(hideMenu, 150); });

        // ── Account Charged: GL Account lookup + account description autofill ──
        const acctInput = tr.querySelector('.ppr-account-code');
        const acctDescInput = tr.querySelector('.ppr-account-desc');
        const glMenu = tr.querySelector('.gl-ac-menu');
        function hideGlMenu() { glMenu.classList.add('d-none'); glMenu.innerHTML = ''; }
        function fillFromGl(it) {
            acctInput.value = it.gl_account_code || acctInput.value;
            if (it.gl_account_description) acctDescInput.value = it.gl_account_description;
        }
        function searchGl(q) {
            fetch('?controller=pr&action=searchGlAccounts&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(items) {
                    if (!items || items.error) { hideGlMenu(); return; }
                    glMenu.innerHTML = '';
                    // Exact typed match -> autofill the account description.
                    const exact = items.find(function(i) {
                        return (i.gl_account_code || '').toLowerCase() === q.toLowerCase();
                    });
                    if (exact) fillFromGl(exact);
                    if (!items.length) { hideGlMenu(); return; }
                    items.forEach(function(it) {
                        const a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action py-1';
                        a.style.fontSize = '0.8rem';
                        a.innerHTML = '<strong>' + (it.gl_account_code || '—') + '</strong> — ' + (it.gl_account_description || '');
                        a.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            fillFromGl(it);
                            hideGlMenu();
                        });
                        glMenu.appendChild(a);
                    });
                    glMenu.classList.remove('d-none');
                })
                .catch(hideGlMenu);
        }
        let glTimer = null;
        acctInput.addEventListener('input', function() {
            clearTimeout(glTimer);
            const q = acctInput.value.trim();
            if (q === '') { hideGlMenu(); return; }
            glTimer = setTimeout(function() { searchGl(q); }, 250);
        });
        acctInput.addEventListener('blur', function() { setTimeout(hideGlMenu, 150); });

        // ── Item Description -> Item Code reverse autofill ──
        const descMenu = tr.querySelector('.desc-ac-menu');
        function hideDescMenu() { descMenu.classList.add('d-none'); descMenu.innerHTML = ''; }
        function searchByDescription(q) {
            fetch('?controller=pr&action=searchItems&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(items) {
                    if (!items || items.error) { hideDescMenu(); return; }
                    descMenu.innerHTML = '';
                    // Exact description match -> autofill the item code (+ unit).
                    const exact = items.find(function(i) {
                        return (i.item_description || '').toLowerCase() === q.toLowerCase();
                    });
                    if (exact) fillFromItem(exact);
                    if (!items.length) { hideDescMenu(); return; }
                    items.forEach(function(it) {
                        const a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action py-1';
                        a.style.fontSize = '0.8rem';
                        a.innerHTML = '<strong>' + (it.item_code || '—') + '</strong> — ' + it.item_description;
                        a.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            fillFromItem(it);
                            hideDescMenu();
                        });
                        descMenu.appendChild(a);
                    });
                    descMenu.classList.remove('d-none');
                })
                .catch(hideDescMenu);
        }
        let descTimer = null;
        descInput.addEventListener('input', function() {
            clearTimeout(descTimer);
            const q = descInput.value.trim();
            if (q === '') { hideDescMenu(); return; }
            descTimer = setTimeout(function() { searchByDescription(q); }, 250);
        });
        descInput.addEventListener('blur', function() { setTimeout(hideDescMenu, 150); });

        // ── Account Description -> Account Charged reverse autofill ──
        const glDescMenu = tr.querySelector('.gl-desc-ac-menu');
        function hideGlDescMenu() { glDescMenu.classList.add('d-none'); glDescMenu.innerHTML = ''; }
        function searchGlByDescription(q) {
            fetch('?controller=pr&action=searchGlAccounts&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(items) {
                    if (!items || items.error) { hideGlDescMenu(); return; }
                    glDescMenu.innerHTML = '';
                    // Exact account description match -> autofill the account code.
                    const exact = items.find(function(i) {
                        return (i.gl_account_description || '').toLowerCase() === q.toLowerCase();
                    });
                    if (exact) acctInput.value = exact.gl_account_code || acctInput.value;
                    if (!items.length) { hideGlDescMenu(); return; }
                    items.forEach(function(it) {
                        const a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action py-1';
                        a.style.fontSize = '0.8rem';
                        a.innerHTML = '<strong>' + (it.gl_account_code || '—') + '</strong> — ' + (it.gl_account_description || '');
                        a.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            acctInput.value = it.gl_account_code || acctInput.value;
                            if (it.gl_account_description) acctDescInput.value = it.gl_account_description;
                            hideGlDescMenu();
                        });
                        glDescMenu.appendChild(a);
                    });
                    glDescMenu.classList.remove('d-none');
                })
                .catch(hideGlDescMenu);
        }
        let glDescTimer = null;
        acctDescInput.addEventListener('input', function() {
            clearTimeout(glDescTimer);
            const q = acctDescInput.value.trim();
            if (q === '') { hideGlDescMenu(); return; }
            glDescTimer = setTimeout(function() { searchGlByDescription(q); }, 250);
        });
        acctDescInput.addEventListener('blur', function() { setTimeout(hideGlDescMenu, 150); });

        tr.querySelector('.ppr-remove-row').addEventListener('click', function() {
            if (body.querySelectorAll('tr').length > 1) {
                tr.remove();
            }
        });
    }

    addBtn.addEventListener('click', function() { addRow(); });

    // ── Open: instant summary from data-*, then fetch full PRF data ──
    modalEl.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        if (!btn) return;
        const prfId = btn.getAttribute('data-pr-id') || '';
        document.getElementById('processPrfId').value = prfId;
        document.getElementById('processPrBack').value = btn.getAttribute('data-back') || 'list';
        document.getElementById('pprPrfNumber').textContent = btn.getAttribute('data-pr-number') || '—';
        document.getElementById('pprPrfDate').textContent = btn.getAttribute('data-date') || '—';
        document.getElementById('pprPrfDepartment').textContent = btn.getAttribute('data-department') || '—';
        document.getElementById('pprPrfPurpose').textContent = btn.getAttribute('data-purpose') || '—';
        document.getElementById('pprPrfPriority').textContent = btn.getAttribute('data-priority') || '—';
        document.getElementById('pprPrfItems').textContent = btn.getAttribute('data-items') || '—';
        const statusBadge = document.getElementById('pprPrfStatus');
        statusBadge.textContent = 'Approved';
        statusBadge.className = 'badge bg-success';

        // reset form pieces before loading
        prNumberInput.value = '';
        currentPrNumber = '';
        currentPrfDate = '';
        document.getElementById('pprDepartment').value = '';
        document.getElementById('pprRequisitioner').value = '';
        document.getElementById('pprPurpose').value = '';
        document.getElementById('pprPoSite').innerHTML = '<option value="">Loading...</option>';
        document.getElementById('pprShipTo').innerHTML = '<option value="">Loading...</option>';
        body.innerHTML = '';
        addRow();

        fetch('?controller=pr&action=prfData&id=' + encodeURIComponent(prfId))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    Swal.fire({ icon: 'error', title: 'Cannot Process', text: data.error });
                    return;
                }
                currentPrNumber = data.pr_number || '';
                prNumberInput.value = currentPrNumber;
                currentPrfDate = data.prf.date_created || '';
                document.getElementById('pprDepartment').value = deptLabelJs(data.prf.department || '');
                document.getElementById('pprRequisitioner').value = data.prf.prepared_by || '';
                document.getElementById('pprPurpose').value = data.prf.purpose || '';

                const sites = data.sites || {};
                const siteOptions = Object.keys(sites).map(function(code) {
                    return '<option value="' + code + '">' + sites[code].name + '</option>';
                }).join('');
                document.getElementById('pprPoSite').innerHTML = siteOptions;
                document.getElementById('pprShipTo').innerHTML = siteOptions;

                body.innerHTML = '';
                if (data.items && data.items.length) {
                    data.items.forEach(function(it) {
                        if ((it.item_name || '').trim() !== '' || (it.item_description || '').trim() !== '') {
                            addRow({
                                item_code: it.item_code || '',
                                item_name: it.item_name || it.item_description || '',
                                qty: it.qty,
                                uom: it.uom || it.item_uom || ''
                            });
                        }
                    });
                }
                if (!body.querySelectorAll('tr').length) addRow();
            })
            .catch(function() {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load the purchase request.' });
            });
    });
});
</script>
