<h4 class="mb-0"><i class="bi bi-clipboard2-check me-2"></i>MRP Check</h4>
<span class="text-muted small d-block mb-3">
    Third approval gate: every MRP run passes <b>Warehouse Admin → System Admin → QC Admin</b>.
    Inspect each item via the <b>Raw Material Analysis Report</b> — when every item has a final
    disposition (Approved / Waived / Rejected — Hold keeps the run open), the run auto-completes:
    approved items are released to Procurement and stock is reserved; rejected ones are cancelled.
    <b>No analysis report is needed when the item already passed Receiving Inspection
    ("Approved — Received"), or when it is not lacking ("No PO Needed") — there is no purchase
    request to decide on.</b> Only lacking items that were never QC-approved require inspection;
    newly received batches are always inspected at Receiving Inspection.
</span>

<div class="card data-card mb-4">
    <div class="table-responsive">
        <table class="table table-hover table-vcenter mb-0">
            <thead>
                <tr>
                    <th>MRP Run</th>
                    <th>BOM Code</th>
                    <th>FG / Target</th>
                    <th>Date Saved</th>
                    <th>Prepared By</th>
                    <th>Progress</th>
                    <th style="width:100px">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($runs)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No MRP runs are waiting for QC Admin approval.</td></tr>
                <?php else: ?>
                <?php foreach ($runs as $run): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><code><?= htmlspecialchars($run['mrp_ref']) ?></code></div>
                        <div class="small text-muted">Run #<?= (int) $run['run_id'] ?></div>
                    </td>
                    <td>
                        <?php if (!empty($run['bom_code'])): ?>
                            <code><?= htmlspecialchars($run['bom_code']) ?></code>
                        <?php else: ?>
                            <span class="text-muted">(default BOM)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code><?= htmlspecialchars($run['fg_code'] ?? '-') ?></code>
                        <?php if ($run['target_qty'] !== null && $run['target_qty'] !== ''): ?>
                            <div class="small text-muted">Target: <?= formatQty($run['target_qty']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($run['customer_name'])): ?>
                            <div class="small text-muted"><?= htmlspecialchars($run['customer_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= date('m/d/Y h:i A', strtotime($run['run_date'])) ?></td>
                    <td><?= htmlspecialchars($run['run_user_name'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($run['zero_lacking'])): ?>
                            <span class="badge bg-secondary">No purchase requests</span>
                        <?php else: ?>
                            <span class="badge bg-info"><?= count($run['items']) ?> requisition(s)</span>
                        <?php endif; ?>
                        <div class="small mt-1">
                            <span class="badge <?= $run['qc_final'] >= $run['qc_total'] && $run['qc_total'] > 0 ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= (int) $run['qc_final'] ?>/<?= (int) $run['qc_total'] ?> inspected
                            </span>
                            <?php if ((int) $run['qc_on_hold'] > 0): ?>
                                <span class="badge bg-secondary"><?= (int) $run['qc_on_hold'] ?> on hold</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-info qc-view-run-btn"
                                data-id="<?= (int) $run['run_id'] ?>"
                                data-code="<?= htmlspecialchars($run['mrp_ref']) ?>"
                                title="Review items and inspect">
                            <i class="bi bi-eye"></i> View
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $decisionRuns = isset($decisionRuns) ? $decisionRuns : []; ?>
<p class="fw-bold text-uppercase text-muted mt-4 mb-2">
    <i class="bi bi-clock-history me-1"></i>Runs I Checked — Tracking (<?= count($decisionRuns) ?>)
</p>
<div class="card data-card mb-4">
    <div class="table-responsive">
        <table class="table table-hover table-vcenter mb-0">
            <thead>
                <tr>
                    <th>MRP Run</th>
                    <th>BOM Code</th>
                    <th>FG / Target</th>
                    <th>Date Saved</th>
                    <th>Prepared By</th>
                    <th>Components</th>
                    <th>Outcome</th>
                    <th>Decided</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($decisionRuns)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Runs you check appear here so you can track their outcome.</td></tr>
                <?php else: ?>
                <?php foreach ($decisionRuns as $dr): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><code><?= htmlspecialchars($dr['mrp_ref'] ?: ('MRP-#' . $dr['run_id'])) ?></code></div>
                        <div class="small text-muted">Run #<?= (int) $dr['run_id'] ?></div>
                    </td>
                    <td>
                        <?php if ((int) ($dr['no_bom'] ?? 0) === 1 || empty($dr['bom_code'])): ?>
                            <span class="text-muted">(default BOM)</span>
                        <?php else: ?>
                            <code><?= htmlspecialchars($dr['bom_code']) ?></code>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code><?= htmlspecialchars($dr['fg_code'] ?? '-') ?></code>
                        <?php if ($dr['target_qty'] !== null && $dr['target_qty'] !== ''): ?>
                            <div class="small text-muted">Target: <?= formatQty($dr['target_qty']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= date('m/d/Y h:i A', strtotime($dr['run_date'])) ?></td>
                    <td><?= htmlspecialchars($dr['run_user_name'] ?? '') ?></td>
                    <td>
                        <?php if ((int) $dr['item_count'] > 0): ?>
                            <span class="badge bg-info"><?= (int) $dr['item_count'] ?> requisition(s)</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">No purchase requests</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $dr['tracking_badge'] ?>"><?= htmlspecialchars($dr['tracking_label']) ?></span></td>
                    <td class="small text-muted"><?= !empty($dr['decided_at']) ? date('m/d/Y h:i A', strtotime($dr['decided_at'])) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Run items modal: every component + latest report + Inspect -->
<div class="modal fade" id="qcRunItemsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title mb-0"><i class="bi bi-list-check me-2"></i>MRP Run Items — <span id="qcRunItemsRef">-</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>UoM</th>
                                <th class="text-end">Total Reqt</th>
                                <th class="text-end">SOH</th>
                                <th class="text-end">Excess</th>
                                <th>Disposition</th>
                                <th>Requisition</th>
                                <th style="width:110px">Action</th>
                            </tr>
                        </thead>
                        <tbody id="qcRunItemsBody">
                            <tr><td colspan="9" class="text-center text-muted py-3">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php $analysisFormAction = '?controller=qc&action=saveMrpInspection'; ?>
<?php require BASE_PATH . 'qc/views/analysis_report_form.php'; ?>

<script>
// DOMContentLoaded: bootstrap.bundle.min.js loads AFTER $content in the QC
// layout — touching `bootstrap` at parse time throws and kills the IIFE
// (the View/Inspect handlers would never attach).
document.addEventListener('DOMContentLoaded', function() {
    var itemsModalEl = document.getElementById('qcRunItemsModal');
    var itemsModal = bootstrap.Modal.getOrCreateInstance(itemsModalEl);
    var currentRunId = 0;

    var dispBadges = {
        'APPROVED': 'bg-success',
        'REJECTED': 'bg-danger',
        'HOLD': 'bg-secondary',
        'WAIVED': 'bg-info text-dark'
    };
    var reqBadges = {
        'pending_qc_approval': 'bg-primary|Awaiting QC',
        'requested': 'bg-success|Released',
        'cancelled': 'bg-dark|Cancelled'
    };
    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderItems(items) {
        var body = document.getElementById('qcRunItemsBody');
        if (!items || !items.length) {
            body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">No items in this run.</td></tr>';
            return;
        }
        var html = '';
        items.forEach(function(row) {
            var isLacking = String(row.excess || '').trim().charAt(0) === '-';
            var excessClass = isLacking ? 'text-danger fw-bold' : 'text-success fw-bold';
            var disp = row.disposition || '';
            // Auto-skip rules (no run report required):
            //   1. stock already passed Receiving Inspection
            //   2. not lacking → no purchase request → nothing to decide
            var autoApproved = !disp && !!row.already_qc_approved;
            var noPoNeeded = !disp && !autoApproved && !!row.no_po_needed;
            var dispHtml;
            if (disp) {
                dispHtml = '<span class="badge ' + (dispBadges[disp] || 'bg-secondary') + '">' + esc(disp) + '</span>';
            } else if (autoApproved) {
                dispHtml = '<span class="badge bg-success" title="' +
                    esc('Stock already passed Receiving Inspection' +
                        (row.last_qc_passed_date ? ' (' + row.last_qc_passed_date + ')' : '')) +
                    '">Approved — Received</span>';
            } else if (noPoNeeded) {
                dispHtml = '<span class="badge bg-success" title="' +
                    esc('Not lacking — no purchase request exists, so no inspection is required.') +
                    '">No PO Needed</span>';
            } else {
                dispHtml = '<span class="text-muted small">Not inspected</span>';
            }
            var req = row.requisition_status || '';
            var reqHtml = req
                ? '<span class="badge ' + (reqBadges[req] || 'bg-secondary').split('|')[0] + '">' + ((reqBadges[req] || '|' + req).split('|')[1] || esc(req)) + '</span>'
                : '<span class="text-muted small">—</span>';
            var latestId = row.latest_report_id || 0;
            // Auto-skipped items need no report — hide the Inspect action.
            var actionHtml = (autoApproved || noPoNeeded)
                ? '<span class="text-muted small">—</span>'
                : '<button type="button" class="btn btn-sm btn-outline-primary qc-inspect-btn"' +
                  ' data-run="' + esc(row.run_id) + '"' +
                  ' data-item="' + esc(row.component_item_id) + '"' +
                  ' data-rmname="' + esc(row.component_name) + '"' +
                  ' data-report="' + esc(latestId) + '">' +
                  '<i class="bi bi-clipboard2-pulse"></i> Inspect</button>';
            html += '<tr>' +
                '<td><code>' + esc(row.component_code) + '</code></td>' +
                '<td>' + esc(row.component_name) + '</td>' +
                '<td>' + esc(row.component_uom) + '</td>' +
                '<td class="text-end">' + esc(row.total_reqt) + '</td>' +
                '<td class="text-end">' + esc(row.soh) + '</td>' +
                '<td class="text-end ' + excessClass + '">' + esc(row.excess) + '</td>' +
                '<td>' + dispHtml + '</td>' +
                '<td>' + reqHtml + '</td>' +
                '<td>' + actionHtml + '</td>' +
                '</tr>';
        });
        body.innerHTML = html;
    }

    document.querySelectorAll('.qc-view-run-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            currentRunId = parseInt(this.dataset.id, 10) || 0;
            document.getElementById('qcRunItemsRef').textContent = this.dataset.code || '-';
            document.getElementById('qcRunItemsBody').innerHTML =
                '<tr><td colspan="9" class="text-center text-muted py-3">Loading…</td></tr>';
            itemsModal.show();
            fetch('?controller=qc&action=mrpRunQcItems&run_id=' + currentRunId)
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (!res || !res.success) {
                        document.getElementById('qcRunItemsBody').innerHTML =
                            '<tr><td colspan="9" class="text-danger py-3">Failed to load run items.</td></tr>';
                        return;
                    }
                    renderItems(res.items);
                })
                .catch(function() {
                    document.getElementById('qcRunItemsBody').innerHTML =
                        '<tr><td colspan="9" class="text-danger py-3">Failed to load run items.</td></tr>';
                });
        });
    });

    // Inspect → open the Raw Material Analysis Report (prefill latest report).
    document.getElementById('qcRunItemsBody').addEventListener('click', function(e) {
        var btn = e.target.closest('.qc-inspect-btn');
        if (!btn) return;
        var reportId = parseInt(btn.dataset.report, 10) || 0;
        var opts = {
            runId: btn.dataset.run,
            itemId: btn.dataset.item,
            receivingId: '',
            rmName: btn.dataset.rmname || '',
            supplier: '', lot: '', rr: '', delivered: '', oldExpiry: ''
        };
        if (!reportId) {
            openAnalysisReport(opts, null);
            return;
        }
        fetch('?controller=qc&action=getAnalysisReport&id=' + reportId)
            .then(function(r) { return r.json(); })
            .then(function(res) {
                openAnalysisReport(opts, res && res.success ? res.data : null);
            })
            .catch(function() {
                openAnalysisReport(opts, null);
            });
    });
});
</script>
