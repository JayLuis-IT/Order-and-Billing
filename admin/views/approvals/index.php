<?php if (($_SESSION['department'] ?? '') !== 'admin'): ?>
<div class="alert alert-danger py-2 mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>Only System Admins can act on these queues.
</div>
<?php endif; ?>

<div class="alert alert-info py-2 mb-0 d-none d-print-none">
    <i class="bi bi-clock-history me-1"></i><b>Decision history is kept below</b> — every approval/rejection recorded by the System Admin stays visible at the bottom of this page (and permanently in <b>Activity Logs</b>).
</div>

<!-- 1 · MRP requisitions (System Admin gate) -->
<p class="fw-bold text-uppercase text-muted mt-4 mb-2"><i class="bi bi-calculator me-1"></i>MRP Requisitions — System Admin Gate (<?= count($mrpRuns) ?> run(s))</p>
<?php
$isWarehouseContext = false;
$pageStage = 'sysadmin';
// Admin hub: rows show View only — Approve All / Reject live in the review
// modal footer. (Warehouse WHSE gate keeps its inline buttons.)
$decisionInModal = true;
$runs = $mrpRuns;
require BASE_PATH . 'warehouse/views/mrpApproval/run_section.php';
?>

<!-- 2 · Purchasing PO drafts — grouped per source/MRP, one decision for the whole group -->
<?php
$poDrafts = $poDrafts ?? [];
$poGroups = [];
foreach ($poDrafts as $o) {
    // MRP drafts group by MRP ref; manual cart POs group by po_group_id
    // (lines may span several suppliers and share only the group id);
    // anything else falls back to the PO ref display.
    if (!empty($o['mrp_ref'])) {
        $key = 'MRP ' . $o['mrp_ref'];
    } elseif (!empty($o['po_group_id'])) {
        $key = 'Group #' . (int) $o['po_group_id'] . (!empty($o['po_ref_display']) && $o['po_ref_display'] !== '-' ? ' / ' . $o['po_ref_display'] : '');
    } else {
        $key = 'PO ' . $o['po_ref_display'];
    }
    if (!isset($poGroups[$key])) {
        $poGroups[$key] = ['ref' => $key, 'items' => [], 'suppliers' => [], 'dates' => [], 'creators' => []];
    }
    $poGroups[$key]['items'][] = $o;
    if (!in_array($o['supplier_name'], $poGroups[$key]['suppliers'])) $poGroups[$key]['suppliers'][] = $o['supplier_name'];
    if (!in_array($o['created_by_name'] ?? '', $poGroups[$key]['creators'])) $poGroups[$key]['creators'][] = $o['created_by_name'] ?? '';
    if (!empty($o['order_date'])) $poGroups[$key]['dates'][] = date('m/d/Y', strtotime($o['order_date']));
}
?>
<div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <p class="fw-bold text-uppercase text-muted mb-0"><i class="bi bi-cart3 me-1"></i>Purchasing PO Drafts — Awaiting Admin Approval (<?= count($poDrafts) ?> line(s) in <?= count($poGroups) ?> group(s))</p>
</div>
<div class="card data-card mb-4">
    <div class="table-responsive">
        <table class="table table-hover no-source-grouping mb-0">
            <thead>
                <tr>
                    <th>Source (PO / MRP)</th>
                    <th>Supplier(s)</th>
                    <th>Items</th>
                    <th>Date</th>
                    <th>Created By</th>
                    <th style="width:300px">Decision</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($poDrafts)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No PO drafts awaiting admin approval.</td></tr>
                <?php else: ?>
                <?php foreach ($poGroups as $g): ?>
                <tr>
                    <td>
                        <?php
                        $mrpBadge = '';
                        foreach ($g['items'] as $it) { if (!empty($it['mrp_ref'])) { $mrpBadge = $it['mrp_ref']; break; } }
                        $srcLabel = trim($g['items'][0]['po_ref_display'] ?? '');
                        if ($srcLabel === '' || $srcLabel === '-') { $srcLabel = $g['ref']; }
                        ?>
                        <div class="fw-bold"><code><?= htmlspecialchars($srcLabel) ?></code></div>
                        <?php if ($mrpBadge): ?><div class="mt-1"><span class="badge bg-secondary"><?= htmlspecialchars($mrpBadge) ?></span></div><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(implode(', ', $g['suppliers'])) ?></td>
                    <td>
                        <span class="badge bg-info"><?= count($g['items']) ?> line(s)</span>
                        <?php foreach (array_slice($g['items'], 0, 3) as $it): ?>
                            <div class="small"><code><?= htmlspecialchars($it['item_code']) ?></code> <?= htmlspecialchars($it['item_description']) ?> — <?= formatQty($it['quantity']) ?> <?= htmlspecialchars($it['item_uom'] ?? '') ?></div>
                        <?php endforeach; ?>
                        <?php if (count($g['items']) > 3): ?><div class="small text-muted">+<?= count($g['items']) - 3 ?> more line(s) — click View</div><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(implode(', ', array_unique($g['dates'])) ?: '-') ?></td>
                    <td><?= htmlspecialchars(implode(', ', $g['creators'])) ?></td>
                    <td>
                        <?php if (!empty($isApprover)): ?>
                        <div class="d-flex flex-nowrap gap-1">
                            <button type="button" class="btn btn-sm btn-outline-info text-nowrap view-po-group-btn"
                                    data-type="po"
                                    data-ref="<?= htmlspecialchars($g['ref'], ENT_QUOTES) ?>"
                                    data-items='<?= htmlspecialchars(json_encode(array_map(function ($i) { return ['id' => $i['supplier_order_id'], 'po' => $i['po_ref_display'], 'mrp' => $i['mrp_ref'] ?? '', 'supplier' => $i['supplier_name'], 'item' => $i['item_code'], 'desc' => $i['item_description'], 'qty' => $i['quantity'], 'uom' => $i['item_uom'] ?? '', 'date' => $i['order_date'] ? date('m/d/Y', strtotime($i['order_date'])) : '-', 'by' => $i['created_by_name'] ?? '']; }, $g['items'])), ENT_QUOTES, "UTF-8", true) ?>'
                                    title="Review every PO line in this group, then approve or reject">
                                <i class="bi bi-eye"></i> View
                            </button>
                        </div>
                        <?php else: ?>
                        <span class="text-muted small">Awaiting approval</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 3 · Over-receipt requests — grouped per source/MRP, one decision for the whole group -->
<?php
$overageRequests = $overageRequests ?? [];
$overGroups = [];
foreach ($overageRequests as $ov) {
    $key = !empty($ov['mrp_ref']) ? 'MRP ' . $ov['mrp_ref'] : 'PO ' . trim((string) ($ov['po_ref'] ?? ''));
    if (!isset($overGroups[$key])) {
        $overGroups[$key] = ['ref' => $key, 'items' => [], 'suppliers' => []];
    }
    $overGroups[$key]['items'][] = $ov;
    if (!in_array($ov['supplier_name'], $overGroups[$key]['suppliers'])) $overGroups[$key]['suppliers'][] = $ov['supplier_name'];
}
?>
<div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <p class="fw-bold text-uppercase text-muted mb-0"><i class="bi bi-box-arrow-in-down me-1"></i>Over-Receipt Requests (<?= count($overageRequests) ?> line(s) in <?= count($overGroups) ?> group(s))</p>
</div>
<div class="card data-card mb-4">
    <div class="table-responsive">
        <table class="table table-hover no-source-grouping mb-0">
            <thead>
                <tr>
                    <th>Source (PO / MRP)</th>
                    <th>Supplier(s)</th>
                    <th>Items</th>
                    <th style="width:300px">Decision</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($overageRequests)): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No pending over-receipt approval requests.</td></tr>
                <?php else: ?>
                <?php foreach ($overGroups as $g): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><code><?= htmlspecialchars(count($g['items']) && trim((string) $g['items'][0]['po_ref']) !== '' ? $g['items'][0]['po_ref'] : '-') ?></code></div>
                        <?php if (!empty($g['items'][0]['mrp_ref'])): ?><div class="mt-1"><span class="badge bg-secondary"><?= htmlspecialchars($g['items'][0]['mrp_ref']) ?></span></div><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(implode(', ', $g['suppliers'])) ?></td>
                    <td>
                        <span class="badge bg-info"><?= count($g['items']) ?> line(s)</span>
                        <?php foreach (array_slice($g['items'], 0, 3) as $ov): ?>
                            <div class="small"><code><?= htmlspecialchars($ov['item_code']) ?></code> <?= htmlspecialchars($ov['item_description']) ?>
                                — ordered <?= formatQty($ov['quantity']) ?>, received <?= formatQty($ov['received_qty']) ?>,
                                <span class="badge bg-danger">+<?= formatQty($ov['overage_request_qty']) ?></span></div>
                        <?php endforeach; ?>
                        <?php if (count($g['items']) > 3): ?><div class="small text-muted">+<?= count($g['items']) - 3 ?> more line(s) — click View</div><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($isApprover)): ?>
                        <div class="d-flex flex-nowrap gap-1">
                            <button type="button" class="btn btn-sm btn-outline-info text-nowrap view-po-group-btn"
                                    data-type="overage"
                                    data-ref="<?= htmlspecialchars($g['ref'], ENT_QUOTES) ?>"
                                    data-items='<?= htmlspecialchars(json_encode(array_map(function ($i) { return ['id' => $i['supplier_order_id'], 'po' => $i['po_ref'] ?? '-', 'mrp' => $i['mrp_ref'] ?? '', 'supplier' => $i['supplier_name'], 'item' => $i['item_code'], 'desc' => $i['item_description'], 'qty' => $i['quantity'], 'uom' => $i['item_uom'] ?? '', 'recv' => $i['received_qty'] ?? 0, 'over' => $i['overage_request_qty'] ?? 0]; }, $g['items'])), ENT_QUOTES, "UTF-8", true) ?>'
                                    title="Review every over-receipt line in this group, then approve or reject">
                                <i class="bi bi-eye"></i> View
                            </button>
                        </div>
                        <?php else: ?>
                        <span class="text-muted small">Awaiting approval</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PO draft group decision form -->
<form id="poGroupDecisionForm" method="POST" style="display:none">
    <input type="hidden" name="supplier_order_ids" id="poGroupId">
    <input type="hidden" name="group_ref" id="poGroupRef">
    <input type="hidden" name="reject_reason" id="poGroupReason">
</form>
<!-- Over-receipt group decision form -->
<form id="overageGroupDecisionForm" method="POST" style="display:none">
    <input type="hidden" name="supplier_order_ids" id="overageGroupId">
    <input type="hidden" name="group_ref" id="overageGroupRef">
    <input type="hidden" name="reject_reason" id="overageGroupReason">
</form>
<script>
function submitPoGroupDecision(ids, groupRef, approve) {
    var form = document.getElementById('poGroupDecisionForm');
    document.getElementById('poGroupId').value = ids;
    document.getElementById('poGroupRef').value = groupRef;
    if (approve) {
        Dlg.confirm('Approve ALL ' + ids.split(',').length + ' PO line(s) of ' + groupRef + ' and activate them all for Receiving?').then(function(ok) {
            if (!ok) return;
            form.action = '?controller=admin&action=approvePoGroup';
            form.submit();
        });
        return;
    }
    Dlg.prompt('Reason for rejecting ALL ' + ids.split(',').length + ' PO line(s) of ' + groupRef + ' (required):', { requiredMessage: 'A rejection reason is required.' }).then(function(reason) {
        if (reason === null || reason === '') return;
        document.getElementById('poGroupReason').value = reason;
        form.action = '?controller=admin&action=rejectPoGroup';
        form.submit();
    });
}
function submitOverageGroupDecision(ids, groupRef, approve) {
    var form = document.getElementById('overageGroupDecisionForm');
    document.getElementById('overageGroupId').value = ids;
    document.getElementById('overageGroupRef').value = groupRef;
    if (approve) {
        Dlg.confirm('Approve the over-receipt for ALL ' + ids.split(',').length + ' line(s) of ' + groupRef + '?').then(function(ok) {
            if (!ok) return;
            form.action = '?controller=admin&action=approveOverageGroup';
            form.submit();
        });
        return;
    }
    Dlg.prompt('Reason for rejecting ALL ' + ids.split(',').length + ' line(s) of ' + groupRef + ' (required):', { requiredMessage: 'A rejection reason is required.' }).then(function(reason) {
        if (reason === null || reason === '') return;
        document.getElementById('overageGroupReason').value = reason;
        form.action = '?controller=admin&action=rejectOverageGroup';
        form.submit();
    });
}
</script>

<!-- Group review modal: every line inside one approval group -->
<div class="modal fade" id="poGroupReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cart3 me-2"></i>Approval Group Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><span class="text-muted small">Source:</span> <code id="poGroupReviewRef" class="fw-bold">-</code></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>PO Ref / MRP</th>
                                <th>Supplier</th>
                                <th>Item</th>
                                <th class="text-end">Qty</th>
                                <th>Date</th>
                                <th>Created By</th>
                            </tr>
                        </thead>
                        <tbody id="poGroupReviewBody">
                            <tr><td colspan="7" class="text-muted">No lines.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <?php if (!empty($isApprover)): ?>
                <div class="me-auto d-flex gap-1 d-none" id="poGroupReviewActions">
                    <button type="button" class="btn btn-success btn-sm text-nowrap" id="reviewApproveBtn">
                        <i class="bi bi-check-lg"></i> Approve All
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm text-nowrap" id="reviewRejectBtn">
                        <i class="bi bi-x-lg"></i> Reject
                    </button>
                </div>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = null;
    var reviewActions = document.getElementById('poGroupReviewActions');
    // Decision context for the review modal footer buttons: set when a
    // pending group's View opens the modal, cleared for history rows.
    var currentReview = null;

    function showReviewActions(show) {
        if (reviewActions) reviewActions.classList.toggle('d-none', !show);
    }

    document.querySelectorAll('.view-po-group-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            modal = modal || new bootstrap.Modal(document.getElementById('poGroupReviewModal'));
            document.getElementById('poGroupReviewRef').textContent = this.dataset.ref || '-';
            var items = [];
            try { items = JSON.parse(this.dataset.items || '[]'); } catch (e) { items = []; }
            currentReview = {
                type: this.dataset.type || 'po',
                ref: this.dataset.ref || '-',
                ids: items.map(function(it) { return it.id; }).join(',')
            };
            showReviewActions(!!currentReview.ids);
            var body = document.getElementById('poGroupReviewBody');
            if (!items.length) {
                body.innerHTML = '<tr><td colspan="7" class="text-muted">No lines.</td></tr>';
            } else {
                body.innerHTML = '';
                items.forEach(function(it) {
                    var tr = document.createElement('tr');
                    var src = [it.po && it.po !== '-' ? String(it.po) : '', it.mrp ? String(it.mrp) : ''].filter(Boolean).join(' / ');
                    var qty = it.over !== undefined
                        ? String(it.qty) + (it.uom ? ' ' + it.uom : '') + ' · recv ' + it.recv + ' · +' + it.over + ' over'
                        : String(it.qty) + (it.uom ? ' ' + it.uom : '');
                    tr.innerHTML = '<td>#' + it.id + '</td>' +
                        '<td></td><td></td><td></td>' +
                        '<td class="text-end"></td><td></td><td></td>';
                    // Assign via textContent to avoid HTML injection
                    tr.cells[0].textContent = '#' + it.id;
                    tr.cells[1].textContent = src;
                    tr.cells[2].textContent = it.supplier || '';
                    tr.cells[3].innerHTML = '<code></code> <span></span>';
                    tr.cells[3].children[0].textContent = it.item || '';
                    tr.cells[3].children[1].textContent = it.desc || '';
                    tr.cells[4].textContent = qty;
                    tr.cells[5].textContent = it.date || '';
                    tr.cells[6].textContent = it.by || '';
                    body.appendChild(tr);
                });
            }
            modal.show();
        });
    });

    // Footer decisions: same hidden-form POSTs as the old inline row buttons
    // (approve = Dlg.confirm, reject = Dlg.prompt small input box for reason).
    var approveBtn = document.getElementById('reviewApproveBtn');
    var rejectBtn = document.getElementById('reviewRejectBtn');
    if (approveBtn) {
        approveBtn.addEventListener('click', function() {
            if (!currentReview || !currentReview.ids) return;
            if (currentReview.type === 'overage') {
                submitOverageGroupDecision(currentReview.ids, currentReview.ref, true);
            } else {
                submitPoGroupDecision(currentReview.ids, currentReview.ref, true);
            }
        });
    }
    if (rejectBtn) {
        rejectBtn.addEventListener('click', function() {
            if (!currentReview || !currentReview.ids) return;
            if (currentReview.type === 'overage') {
                submitOverageGroupDecision(currentReview.ids, currentReview.ref, false);
            } else {
                submitPoGroupDecision(currentReview.ids, currentReview.ref, false);
            }
        });
    }

    // History rows: review the lines inside a decided group (fetch fresh details)
    document.querySelectorAll('.view-history-group-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            modal = modal || new bootstrap.Modal(document.getElementById('poGroupReviewModal'));
            currentReview = null;
            showReviewActions(false);
            var ids = this.dataset.ids || '';
            document.getElementById('poGroupReviewRef').textContent = 'Lines #' + ids.replace(/,/g, ', #');
            var body = document.getElementById('poGroupReviewBody');
            body.innerHTML = '<tr><td colspan="7" class="text-muted">Loading lines…</td></tr>';
            modal.show();

            // fill the modal title with the item status, then render
            fetch('?controller=admin&action=groupLineDetails&ids=' + encodeURIComponent(ids))
                .then(function(r) { return r.json(); })
                .then(function(items) {
                    if (!Array.isArray(items) || !items.length) {
                        body.innerHTML = '<tr><td colspan="7" class="text-muted">No lines found.</td></tr>';
                        return;
                    }
                    body.innerHTML = '';
                    items.forEach(function(it) {
                        var tr = document.createElement('tr');
                        var src = [it.po && it.po !== '-' ? it.po : '', it.mrp || ''].filter(Boolean).join(' / ');
                        tr.innerHTML = '<td></td><td></td><td></td><td><code></code> <span></span></td>' +
                            '<td class="text-end"></td><td></td><td><span class="badge"></span></td>';
                        tr.cells[0].textContent = '#' + it.id;
                        tr.cells[1].textContent = src;
                        tr.cells[2].textContent = it.supplier || '';
                        tr.cells[3].children[0].textContent = it.item || '';
                        tr.cells[3].children[1].textContent = it.desc || '';
                        tr.cells[4].textContent = String(it.qty) + (it.uom ? ' ' + it.uom : '');
                        tr.cells[5].textContent = it.date || '';
                        tr.cells[6].children[0].textContent = String(it.status || '');
                        tr.cells[6].children[0].className = 'badge ' + (String(it.status) === 'cancelled' ? 'bg-dark' : 'bg-primary');
                        body.appendChild(tr);
                    });
                })
                .catch(function() {
                    body.innerHTML = '<tr><td colspan="7" class="text-danger">Failed to load the line details.</td></tr>';
                });
        });
    });
});
</script>

<!-- 4 · Approval History — everything the System Admin decided, one table -->
<?php
$decisionRuns = $decisionRuns ?? [];
$decisionHistory = $decisionHistory ?? [];
$approvalHistory = [];
foreach ($decisionRuns as $dr) {
    $approvalHistory[] = [
        'type' => 'mrp_run', 'timestamp' => $dr['decided_at'],
        'run' => $dr,
    ];
}
foreach ($decisionHistory as $h) {
    $desc = (string) $h['description'];
    if (stripos($desc, 'over-receipt') !== false || stripos($desc, 'overage') !== false) {
        $label = 'Over-Receipt';
        $badge = 'bg-warning text-dark';
    } elseif (stripos($desc, 'mrp requisition') !== false) {
        $label = 'MRP';
        $badge = 'bg-info text-dark';
    } else {
        $label = 'Purchasing PO';
        $badge = 'bg-primary';
    }
    $approvalHistory[] = [
        'type' => 'other', 'timestamp' => $h['created_at'], 'log' => $h,
        'label' => $label, 'badge' => $badge,
    ];
}
usort($approvalHistory, function ($a, $b) {
    return strtotime($b['timestamp']) <=> strtotime($a['timestamp']);
});
?>
<div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <p class="fw-bold text-uppercase text-muted mb-0"><i class="bi bi-clock-history me-1"></i>Approval History (<?= count($approvalHistory) ?>)</p>
</div>
<div class="card data-card mb-4">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Type</th>
                    <th>Decision</th>
                    <th>Details</th>
                    <th>Decided By</th>
                    <th style="width:110px">Review</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($approvalHistory)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No approval decisions recorded yet — every approve/reject you make will appear here permanently (also in <b>Activity Logs</b>).</td></tr>
                <?php else: ?>
                <?php foreach ($approvalHistory as $entry): ?>
                <?php if ($entry['type'] === 'mrp_run'): $dr = $entry['run']; ?>
                <tr>
                    <td><small class="text-muted"><?= date('m/d/Y h:i A', strtotime($entry['timestamp'])) ?></small></td>
                    <td><span class="badge bg-info text-dark">MRP</span></td>
                    <td><span class="badge <?= $dr['tracking_badge'] ?>"><?= $dr['decision'] === 'REJECT' ? 'REJECT' : 'APPROVE' ?></span></td>
                    <td>
                        <div class="fw-bold"><code><?= htmlspecialchars($dr['mrp_ref'] ?: ('MRP-#' . $dr['run_id'])) ?></code>
                            <small class="text-muted">Run #<?= (int) $dr['run_id'] ?></small></div>
                        <small class="text-muted">
                           <?= htmlspecialchars($dr['bom_code'] ?: '(default BOM)') ?>
                            &middot; <?= htmlspecialchars($dr['fg_code'] ?? '-') ?> Target: <?= formatQty($dr['target_qty']) ?>
                            &middot; Prepared by <?= htmlspecialchars($dr['run_user_name'] ?? '') ?>
                            &middot; <?= (int) $dr['item_count'] ?> requisition(s)
                            <?php if ((int) $dr['st_released'] > 0): ?> &middot; <?= (int) $dr['st_released'] ?> released<?php endif; ?>
                            <?php if ((int) $dr['st_cancelled'] > 0): ?> &middot; <?= (int) $dr['st_cancelled'] ?> cancelled<?php endif; ?>
                            &middot; saved <?= date('m/d/Y h:i A', strtotime($dr['run_date'])) ?>
                        </small>
                    </td>
                    <td>admin (admin)</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-info text-nowrap run-detail-btn"
                                data-id="<?= (int) $dr['run_id'] ?>"
                                data-code="<?= htmlspecialchars($dr['mrp_ref'] ?: ('MRP-#' . $dr['run_id'])) ?>"
                                data-bom-id="<?= (int) ($dr['bom_id'] ?? 0) ?>"
                                data-bom-code="<?= htmlspecialchars($dr['bom_code'] ?? '') ?>"
                                data-target-qty="<?= $dr['target_qty'] !== null && $dr['target_qty'] !== '' ? formatQty($dr['target_qty']) : '0' ?>"
                                data-fg-code="<?= htmlspecialchars($dr['fg_code'] ?? '') ?>"
                                title="Review the full MRP run (component break-down + BOM)">
                            <i class="bi bi-eye"></i> View
                        </button>
                    </td>
                </tr>
                <?php else: $h = $entry['log']; $isApproveAction = strtoupper((string) $h['action']) === 'APPROVE';
                    $histLines = null;
                    if (in_array($h['target_type'], ['po_draft_group', 'overage_group']) && !empty($h['old_values'])) {
                        $decoded = json_decode((string) $h['old_values'], true);
                        if (!empty($decoded['lines'])) {
                            $histLines = implode(',', array_map(function ($l) {
                                return (int) ($l['supplier_order_id'] ?? 0);
                            }, $decoded['lines']));
                        }
                    }
                ?>
                <tr>
                    <td><small class="text-muted"><?= date('m/d/Y h:i A', strtotime($entry['timestamp'])) ?></small></td>
                    <td><span class="badge <?= $entry['badge'] ?>"><?= htmlspecialchars($entry['label']) ?></span></td>
                    <td><span class="badge <?= $isApproveAction ? 'bg-success' : 'bg-danger' ?>"><?= strtoupper((string) $h['action']) ?></span></td>
                    <td><small><?= preg_replace_callback(
                        '/\b(?:MRP\s*(?:RUN|REF|#|-)\s*[\w#-]*\d+|SO\s*#?\s*\d+|PO\s*(?:RUN|REF|#|-)\s*\d+|run\s*#?\s*\d+)\b/i',
                        function ($m) { return '<code>' . $m[0] . '</code>'; },
                        htmlspecialchars((string) $h['description'])
                    ) ?></small></td>
                    <td><?= htmlspecialchars(($h['username'] ?? '') . ' (' . ($h['department'] ?? '') . ')') ?></td>
                    <td>
                        <?php if ($histLines): ?>
                        <button type="button" class="btn btn-sm btn-outline-info text-nowrap view-history-group-btn"
                                data-ids="<?= htmlspecialchars($histLines) ?>"
                                title="View the PO lines inside this decided group">
                            <i class="bi bi-eye"></i> View
                        </button>
                        <?php else: ?>
                        <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require BASE_PATH . 'app/views/shared/table_enhancer.php'; ?>
