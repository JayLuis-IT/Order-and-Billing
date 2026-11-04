<?php
// Reusable read-only "View Details" modal for Purchasing PO / Receiving PO / QC Record.
// Included at the bottom of the three views that render a .btn-view-po button.
// The JSON endpoint depends on the module the page belongs to.
$isQcController = (($_GET['controller'] ?? '') === 'qc');
$poViewEndpoint = $isQcController
    ? '?controller=qc&action=getInspectionDetails&id='
    : '?controller=warehouse&action=getPoFullDetails&id=';
?>
<div class="modal fade" id="modalViewPoDetails" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">PO &amp; Receiving Details — <span id="viewPoRef">-</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="viewSpinner" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="text-muted small mt-2">Loading record…</div>
                </div>
                <div id="viewError" class="alert alert-danger d-none" role="alert"></div>
                <div id="viewContent">
                    <h6 class="fw-bold text-primary">Purchasing Information</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4"><b>Supplier:</b> <span id="vSupplier"></span></div>
                        <div class="col-md-4"><b>Item Code:</b> <span id="vItemCode"></span></div>
                        <div class="col-md-4"><b>Description:</b> <span id="vDescription"></span></div>
                        <div class="col-md-4"><b>Order Qty:</b> <span id="vOrderQty"></span></div>
                        <div class="col-md-4"><b>Order Date:</b> <span id="vOrderDate"></span></div>
                        <div class="col-md-4"><b>Expected Date:</b> <span id="vExpectedDate"></span></div>
                        <div class="col-md-4"><b>Status:</b> <span id="vStatus"></span></div>
                        <div class="col-md-4"><b>Created By:</b> <span id="vCreatedBy"></span></div>
                        <div class="col-md-12"><b>Order Remarks:</b> <span id="vOrderRemarks"></span></div>
                    </div>
                    <hr>
                    <h6 class="fw-bold text-success">Receiving Information</h6>
                    <div class="row g-2 mb-2">
                        <div class="col-md-4"><b>Total Received Qty:</b> <span id="vRecQty"></span></div>
                        <div class="col-md-4"><b>Received Date:</b> <span id="vRecDate"></span></div>
                        <div class="col-md-4"><b>DR / Invoice No:</b> <span id="vDrNo"></span></div>
                        <div class="col-md-4"><b>Received By:</b> <span id="vRecBy"></span></div>
                        <div class="col-md-4"><b>Lot No:</b> <span id="vLotNo"></span></div>
                        <div class="col-md-4"><b>Expiry Date:</b> <span id="vExpiry"></span></div>
                        <div class="col-md-12"><b>Receiving Notes:</b> <span id="vRecNotes"></span></div>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th class="text-end">Received Qty</th>
                                    <th>Received Date</th>
                                    <th>Received By</th>
                                    <th>Lot No</th>
                                    <th>DR / Invoice No</th>
                                    <th>QC Decision</th>
                                </tr>
                            </thead>
                            <tbody id="viewBatchRows"></tbody>
                        </table>
                    </div>
                    <hr>
                    <h6 class="fw-bold text-info">QC Inspection Results</h6>
                    <div class="row g-2">
                        <div class="col-md-4"><b>QC Decision:</b> <span id="vQcDecision"></span></div>
                        <div class="col-md-4"><b>Inspector:</b> <span id="vQcInspector"></span></div>
                        <div class="col-md-4"><b>Inspection Date:</b> <span id="vQcDate"></span></div>
                        <div class="col-md-4"><b>Passed Qty:</b> <span id="vPassedQty"></span></div>
                        <div class="col-md-4"><b>Rejected Qty:</b> <span id="vRejectedQty"></span></div>
                        <div class="col-md-12"><b>Inspection Remarks:</b> <span id="vQcRemarks"></span></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var ENDPOINT = <?= json_encode($poViewEndpoint) ?>;
    var modalEl = document.getElementById('modalViewPoDetails');
    if (!modalEl) return;
    var modal = null;

    var STATUS_BADGES = {
        'requested': 'bg-info',
        'pending_whse_approval': 'bg-warning text-dark',
        'pending_sysadmin_approval': 'bg-info text-dark',
        'pending_qc_approval': 'bg-primary',
        'pending': 'bg-warning text-dark',
        'processed': 'bg-primary',
        'partially_received': 'bg-info text-dark',
        'for inspection': 'bg-warning text-dark',
        'received': 'bg-success',
        'approved': 'bg-success',
        'rejected': 'bg-danger',
        'cancelled': 'bg-secondary',
        'po_created': 'bg-dark'
    };

    function setText(id, value, fallback) {
        var el = document.getElementById(id);
        if (el) el.textContent = (value !== null && value !== undefined && value !== '') ? value : (fallback || '-');
    }

    function statusBadge(status) {
        var span = document.createElement('span');
        var key = String(status || '').toLowerCase();
        span.className = 'badge ' + (STATUS_BADGES[key] || 'bg-secondary');
        span.textContent = status ? status.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); }) : '-';
        return span;
    }

    function qcBadge(decision) {
        var span = document.createElement('span');
        var key = String(decision || '').toUpperCase();
        span.className = 'badge ' + (key === 'PASSED' ? 'bg-success' : (key === 'REJECTED' ? 'bg-danger' : 'bg-secondary'));
        span.textContent = key === 'PASSED' ? 'Approved' : (key === 'REJECTED' ? 'Rejected' : (decision || '-'));
        return span;
    }

    function cell(text) {
        var td = document.createElement('td');
        td.textContent = (text === null || text === undefined || text === '') ? '-' : text;
        return td;
    }

    function renderBatches(batches, uom) {
        var tbody = document.getElementById('viewBatchRows');
        if (!tbody) return;
        tbody.textContent = '';
        (batches || []).forEach(function(b, i) {
            var tr = document.createElement('tr');
            var qty = document.createElement('td');
            qty.className = 'text-end';
            qty.textContent = (b.received_qty || '0') + (uom ? ' ' + uom : '');

            tr.appendChild(cell(i + 1));
            tr.appendChild(qty);
            tr.appendChild(cell(b.received_date));
            tr.appendChild(cell(b.received_by_name));
            tr.appendChild(cell(b.lot_number));
            tr.appendChild(cell(b.dr_invoice_no));

            var qc = document.createElement('td');
            qc.appendChild(qcBadge(b.qc_decision));
            tr.appendChild(qc);
            tbody.appendChild(tr);
        });
    }

    function populate(data) {
        var uom = data.uom || '';
        setText('viewPoRef', data.po_ref);
        setText('vSupplier', data.supplier_name, 'N/A');
        setText('vItemCode', data.item_code);
        setText('vDescription', data.description);
        setText('vOrderQty', data.quantity ? data.quantity + (uom ? ' ' + uom : '') : null);
        setText('vOrderDate', data.order_date);
        setText('vExpectedDate', data.expected_date);
        setText('vCreatedBy', data.created_by);
        setText('vOrderRemarks', data.order_remarks, 'None');

        var statusEl = document.getElementById('vStatus');
        if (statusEl) {
            statusEl.textContent = '';
            statusEl.appendChild(statusBadge(data.status));
        }

        setText('vRecQty', data.received_qty ? data.received_qty + (uom ? ' ' + uom : '') : '0' + (uom ? ' ' + uom : ''));
        setText('vRecDate', data.received_date);
        setText('vDrNo', data.dr_number, 'N/A');
        setText('vRecBy', data.received_by, '-');
        setText('vLotNo', data.lot_number);
        setText('vExpiry', data.expiry_date);
        setText('vRecNotes', data.receiving_notes, 'None');

        renderBatches(data.batches, uom);

        var qcEl = document.getElementById('vQcDecision');
        if (qcEl) {
            qcEl.textContent = '';
            qcEl.appendChild(qcBadge(data.qc_decision));
        }
        setText('vQcInspector', data.inspector_name);
        setText('vQcDate', data.qc_date);
        setText('vPassedQty', data.passed_qty !== null && data.passed_qty !== undefined ? data.passed_qty + (uom ? ' ' + uom : '') : null);
        setText('vRejectedQty', data.rejected_qty !== null && data.rejected_qty !== undefined ? data.rejected_qty + (uom ? ' ' + uom : '') : null);
        setText('vQcRemarks', data.qc_remarks, 'None');
    }

    function showLoading(show) {
        document.getElementById('viewSpinner').classList.toggle('d-none', !show);
        document.getElementById('viewContent').classList.toggle('d-none', show);
    }

    function showError(message) {
        var err = document.getElementById('viewError');
        err.textContent = message;
        err.classList.remove('d-none');
    }

    document.addEventListener('click', function(e) {
        var btn = e.target.closest ? e.target.closest('.btn-view-po') : null;
        if (!btn) return;
        e.preventDefault();

        var recordId = btn.getAttribute('data-id');
        if (!recordId) return;

        document.getElementById('viewError').classList.add('d-none');
        document.getElementById('viewPoRef').textContent = '…';
        showLoading(true);

        if (!modal) modal = new bootstrap.Modal(modalEl);
        modal.show();

        fetch(ENDPOINT + encodeURIComponent(recordId), { credentials: 'same-origin' })
            .then(function(res) { return res.json(); })
            .then(function(res) {
                showLoading(false);
                if (!res.success) {
                    showError(res.error || 'Failed to load record details.');
                    return;
                }
                populate(res.data);
            })
            .catch(function() {
                showLoading(false);
                showError('Could not reach the server. Please try again.');
            });
    });
})();
</script>
