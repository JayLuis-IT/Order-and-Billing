<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-cart3 me-2"></i>Purchasing PO (View Only)</h4>
    <div class="d-flex gap-2 flex-wrap">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="controller" value="admin">
            <input type="hidden" name="action" value="purchasingPo">
            <select name="status" class="form-select form-select-sm filter-select" style="width:170px">
                <option value="">Any status (per tab)</option>
                <option value="all" <?= ($filters['status'] ?? '') === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="requested" <?= ($filters['status'] ?? '') === 'requested' ? 'selected' : '' ?>>Requested</option>
                <option value="pending_whse_approval" <?= ($filters['status'] ?? '') === 'pending_whse_approval' ? 'selected' : '' ?>>Pending WHSE Approval</option>
                <option value="pending_admin_approval" <?= ($filters['status'] ?? '') === 'pending_admin_approval' ? 'selected' : '' ?>>Pending Admin Approval (PO Draft)</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="processed" <?= ($filters['status'] ?? '') === 'processed' ? 'selected' : '' ?>>Processed</option>
                <option value="partially_received" <?= ($filters['status'] ?? '') === 'partially_received' ? 'selected' : '' ?>>Partially Received</option>
                <option value="For Inspection" <?= ($filters['status'] ?? '') === 'For Inspection' ? 'selected' : '' ?>>For Inspection</option>
                <option value="received" <?= ($filters['status'] ?? '') === 'received' ? 'selected' : '' ?>>Received</option>
                <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search supplier/item..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="width:220px">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
            <a href="?controller=admin&action=purchasingPo" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
        </form>
    </div>
</div>

<?php
// ─── Decidable Source blocks: ONE View (+ Print) per Source ──────────────
// Placement mirrors the warehouse page's blue "Source:" separator row: the
// button HTML is registered in window.TABLE_GROUP_ACTIONS and the shared
// table_enhancer appends it (float-end, right side) to each separator.
// Registry keys MUST equal the enhancer's groupKeyOf(cell text):
//   MRP rows  -> the MRP# badge (its regex prefers MRP over PO REF)
//   non-MRP   -> the PO REF cell text ('-' blank renders NO separator)
// Fallbacks keep a FIRST-ROW button when no separator can exist — blank
// PO REF sources, a table filtered to a single row, or a source whose
// lines sit at two decidable stages (never two buttons for one source).
// Decidable on THIS page: processed PO drafts only (pending_admin_approval).
// Born MRP run-gate rows are hidden from this table (getPurchasingPoFiltered)
// — MRP run decisions live on the Approvals hub's MRP Requisitions section.
$decidableStatuses = ['pending_admin_approval'];
$totalOrders = count($orders);
$sourceKeyOf = function (array $o): string {
    if (!empty($o['mrp_ref'])) return trim($o['mrp_ref']);
    $ref = trim(preg_replace('/\s+/', ' ', (string) ($o['po_ref_display'] ?? '')));
    if ($ref !== '' && $ref !== '-') return $ref;
    return '';
};
$sourceBlocks = [];   // block key => payload
foreach ($orders as $o) {
    if (!in_array($o['status'], $decidableStatuses, true)) continue;
    $srcKey = $sourceKeyOf($o);
    if ($srcKey !== '') {
        $blockKey = $o['status'] . '|' . $srcKey;
        $sepKey = $srcKey;
    } else {
        // Blank PO REF: no separator is drawn — group by po_group_id (or the lone row).
        $blockKey = $o['status'] . '|blank|'
            . (!empty($o['po_group_id']) ? 'g' . (int) $o['po_group_id'] : 'r' . (int) $o['supplier_order_id']);
        $sepKey = '';
    }
    if (!isset($sourceBlocks[$blockKey])) {
        if (!empty($o['mrp_ref'])) {
            $blockRef = trim($o['mrp_ref']);
        } elseif ($sepKey !== '') {
            $blockRef = $sepKey;
        } elseif (!empty($o['po_group_id'])) {
            $blockRef = 'Group #' . (int) $o['po_group_id'];
        } else {
            $blockRef = 'Order #' . (int) $o['supplier_order_id'];
        }
        $sourceBlocks[$blockKey] = [
            'sep_key'  => $sepKey,
            'mode'     => $o['status'] === 'pending_sysadmin_approval' ? 'mrp' : 'po',
            'run_id'   => (int) ($o['mrp_run_id'] ?? 0),
            'ref'      => $blockRef,
            'first_id' => (int) $o['supplier_order_id'],
            'ids'      => [],
            'items'    => [],
        ];
    }
    $sourceBlocks[$blockKey]['ids'][] = (int) $o['supplier_order_id'];
    $sourceBlocks[$blockKey]['items'][] = [
        'id'     => (int) $o['supplier_order_id'],
        'code'   => $o['item_code'] ?? '',
        'desc'   => $o['item_description'] ?? '',
        'qty'    => formatQty($o['quantity']),
        'uom'    => $o['item_uom'] ?? '',
        'sup'    => $o['supplier_name'] ?? '',
        'cost'   => number_format((float) ($o['unit_cost'] ?? 0), 2, '.', ','),
        'date'   => !empty($o['order_date']) ? date('m/d/Y', strtotime($o['order_date'])) : '-',
        'by'     => $o['created_by_name'] ?? '',
    ];
}

// One button pair per block: View (opens the Source Review modal) + Print
// (whole source via printPurchasingPo — group/run resolution in the model).
$buildSourceButtons = function (array $b): string {
    $html = '<button type="button" class="btn btn-sm btn-outline-info src-view-btn"'
        . ' data-mode="' . htmlspecialchars($b['mode'], ENT_QUOTES) . '"'
        . ' data-run="' . (int) $b['run_id'] . '"'
        . ' data-ids="' . htmlspecialchars(implode(',', $b['ids']), ENT_QUOTES) . '"'
        . ' data-ref="' . htmlspecialchars($b['ref'], ENT_QUOTES) . '"'
        . ' data-count="' . count($b['ids']) . '"'
        . " data-items='" . htmlspecialchars(json_encode($b['items'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES) . "'"
        . ' title="Review all ' . count($b['ids']) . ' line(s) of this source, then approve or reject">'
        . '<i class="bi bi-eye"></i> View</button>';
    $html .= ' <a href="?controller=warehouse&action=printPurchasingPo&id=' . (int) $b['first_id'] . '"'
        . ' target="_blank" class="btn btn-sm btn-outline-secondary" title="Print the whole source">'
        . '<i class="bi bi-printer"></i> Print</a>';
    return $html;
};

// Placement: separator via registry vs first-row fallback — mutually exclusive.
$blocksPerSepKey = [];
foreach ($sourceBlocks as $b) {
    if ($b['sep_key'] !== '') {
        $blocksPerSepKey[$b['sep_key']] = ($blocksPerSepKey[$b['sep_key']] ?? 0) + 1;
    }
}
$tableGroupActions = [];
$blockFirst = [];   // first-row fallback: supplier_order_id => block key
foreach ($sourceBlocks as $blockKey => $b) {
    $separatorOk = $b['sep_key'] !== ''
        && ($blocksPerSepKey[$b['sep_key']] ?? 0) === 1   // never split one separator between two decisions
        && $totalOrders > 1;                              // enhancer draws no separators on a 1-row table
    if ($separatorOk) {
        $tableGroupActions[$b['sep_key']] = $buildSourceButtons($b);
    } else {
        $blockFirst[$b['first_id']] = $blockKey;
    }
}
?>

<div class="card data-card">
    <?php
    $activeTab = $filters['tab'] ?: 'all';
    $tabCounts = $tabCounts ?? [];
    $poTabs = [
        'all'       => 'All Orders',
        'requested' => 'Requisitions (Requested)',
        'approval'  => 'Awaiting Approval (MRP / PO Draft)',
        'active'    => 'Active POs (Pending)',
        'history'   => 'History (Received / Cancelled)',
    ];
    ?>
    <div class="card-body pb-0">
        <ul class="nav nav-pills flex-wrap gap-2" id="purchasingTabs">
            <?php foreach ($poTabs as $tabKey => $tabLabel):
                $tabHref = '?controller=admin&action=purchasingPo&tab=' . rawurlencode($tabKey);
                if (!empty($filters['search'])) {
                    $tabHref .= '&search=' . rawurlencode($filters['search']);
                }
            ?>
            <li class="nav-item">
                <a class="nav-link px-3 py-1 border rounded-pill <?= $activeTab === $tabKey ? 'active' : '' ?>"
                   href="<?= $tabHref ?>">
                    <?= $tabLabel ?> <span class="badge <?= $activeTab === $tabKey ? 'bg-white text-primary' : 'bg-secondary' ?>"><?= (int) ($tabCounts[$tabKey] ?? 0) ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Supplier</th>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th class="text-end">Quantity</th>
                    <th>Order Date</th>
                    <th>Expected</th>
                    <th>PO REF / SOURCE</th>
                    <th>Status</th>
                    <th class="text-end">Received Qty</th>
                    <th>Received Date</th>
                    <th>Created By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="13" class="text-center text-muted py-4">No purchasing POs found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($orders as $index => $o): $qty = floatval($o['quantity']); ?>
                <tr>
                    <td title="Order #<?= $o['supplier_order_id'] ?>"><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($o['supplier_name']) ?></td>
                    <td><code><?= htmlspecialchars($o['item_code']) ?></code></td>
                    <td><?= htmlspecialchars($o['item_description']) ?></td>
                    <td class="text-end"><?= formatQty($qty) ?> <?= htmlspecialchars($o['item_uom'] ?? '') ?></td>
                    <td><?= $o['order_date'] ? date('m/d/Y', strtotime($o['order_date'])) : '-' ?></td>
                    <td><?= $o['expected_date'] ? date('m/d/Y', strtotime($o['expected_date'])) : '-' ?></td>
                    <td>
                        <?= htmlspecialchars($o['po_ref_display']) ?>
                        <?php if (!empty($o['mrp_ref'])): ?>
                            <div class="mt-1"><span class="badge bg-secondary"><?= htmlspecialchars($o['mrp_ref']) ?></span></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($o['status'] === 'requested'): ?>
                            <span class="badge bg-info text-dark">Requested</span>
                        <?php elseif ($o['status'] === 'pending_whse_approval'): ?>
                            <span class="badge bg-warning text-dark">Pending WHSE Approval</span>
                        <?php elseif ($o['status'] === 'pending_sysadmin_approval'): ?>
                            <span class="badge bg-info text-dark">Pending SysAdmin Approval</span>
                        <?php elseif ($o['status'] === 'pending_qc_approval'): ?>
                            <span class="badge bg-primary">Pending QC Approval</span>
                        <?php elseif ($o['status'] === 'pending_admin_approval'): ?>
                            <span class="badge bg-warning text-dark">Pending Admin Approval (PO Draft)</span>
                        <?php elseif ($o['status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php elseif ($o['status'] === 'processed'): ?>
                            <span class="badge bg-primary">Processed</span>
                        <?php elseif ($o['status'] === 'partially_received'): ?>
                            <span class="badge bg-secondary">Partially Received</span>
                        <?php elseif ($o['status'] === 'For Inspection'): ?>
                            <span class="badge text-white" style="background:#6f42c1">For Inspection</span>
                        <?php elseif ($o['status'] === 'received'): ?>
                            <span class="badge bg-success">Received</span>
                        <?php elseif ($o['status'] === 'cancelled'): ?>
                            <span class="badge bg-dark">Cancelled</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars(ucfirst($o['status'])) ?: 'Unknown' ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?php
                        $recvQty = floatval($o['received_qty'] ?? 0);
                        $remainQty = floatval($o['quantity']) - $recvQty;
                        if ($recvQty > 0) {
                            echo formatQty($recvQty) . ' ' . htmlspecialchars($o['item_uom'] ?? '');
                            if ($o['status'] === 'partially_received' && $remainQty > 0.0001) {
                                echo '<div class="small text-muted">' . formatQty($remainQty) . ' ' . htmlspecialchars($o['item_uom'] ?? '') . ' remaining</div>';
                            } elseif ($o['status'] === 'For Inspection') {
                                echo '<div class="small text-muted">awaiting QC</div>';
                            }
                        } else {
                            echo '-';
                        }
                    ?></td>
                    <td><?= $o['received_date'] ? date('m/d/Y', strtotime($o['received_date'])) : '-' ?></td>
                    <td><?= htmlspecialchars($o['created_by_name'] ?? '') ?></td>
                    <td>
                        <?php if (isset($blockFirst[(int) $o['supplier_order_id']])):
                            // First-row fallback (blank PO REF / single-row table /
                            // mixed-stage source) — keyed blocks live on the
                            // Source separator instead (TABLE_GROUP_ACTIONS).
                            $blk = $sourceBlocks[$blockFirst[(int) $o['supplier_order_id']]];
                        ?>
                            <?= $buildSourceButtons($blk) ?>
                        <?php elseif (in_array($o['status'], $decidableStatuses, true)): ?>
                            <span class="text-muted">-</span>
                        <?php elseif ($o['status'] === 'pending_whse_approval'): ?>
                            <span class="text-muted small">Awaiting Warehouse Admin</span>
                        <?php elseif ($o['status'] === 'pending_qc_approval'): ?>
                            <span class="badge bg-primary">Awaiting QC Admin</span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Source decision form: driven by the Source Review modal buttons -->
<form id="mrpApprovalForm" method="POST" style="display:none">
    <input type="hidden" name="mrp_run_id" id="srcRunId">
    <input type="hidden" name="supplier_order_ids" id="srcGroupIds">
    <input type="hidden" name="group_ref" id="srcGroupRef">
    <input type="hidden" name="reject_reason" id="mrpRejectReason">
</form>

<!-- Source Review modal: every decidable line of one source, then Approve / Reject -->
<div class="modal fade" id="sourceReviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-diagram-3 me-2"></i>Source Review — <span id="srcReviewRef" class="text-danger"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body table-responsive">
                <div class="mb-2 small text-muted" id="srcReviewSummary"></div>
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr class="small text-muted">
                            <th>#</th>
                            <th>Item Code</th>
                            <th>Description</th>
                            <th class="text-end">Qty</th>
                            <th>UOM</th>
                            <th>Supplier</th>
                            <th class="text-end">Unit Cost</th>
                            <th>Order Date</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tbody id="srcReviewBody">
                        <tr><td colspan="9" class="text-muted">No lines.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success btn-sm text-nowrap" id="srcApproveBtn">
                    <i class="bi bi-check-lg"></i> Approve All
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm text-nowrap" id="srcRejectBtn">
                    <i class="bi bi-x-lg"></i> Reject
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var srcDecision = null;   // {mode, runId, ids, ref, count}
    var modal = null;

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }
    function fillForm(fields) {
        document.getElementById('srcRunId').value = fields.runId || '';
        document.getElementById('srcGroupIds').value = fields.ids || '';
        document.getElementById('srcGroupRef').value = fields.ref || '';
        document.getElementById('mrpRejectReason').value = '';
    }

    // Delegated: separator buttons are created — and RE-created on every
    // pagination page switch — by the shared table_enhancer AFTER this
    // script runs, so a one-time querySelectorAll binding would miss them.
    document.addEventListener('click', function(e) {
        var btn = e.target.closest ? e.target.closest('.src-view-btn') : null;
        if (!btn) return;
        modal = modal || new bootstrap.Modal(document.getElementById('sourceReviewModal'));
        var items = [];
        try { items = JSON.parse(btn.dataset.items || '[]'); } catch (e) { items = []; }
        srcDecision = {
            mode: btn.dataset.mode,
            runId: parseInt(btn.dataset.run, 10) || 0,
            ids: btn.dataset.ids || '',
            ref: btn.dataset.ref || '-',
            count: parseInt(btn.dataset.count, 10) || items.length
        };
        document.getElementById('srcReviewRef').textContent = srcDecision.ref;
        document.getElementById('srcReviewSummary').textContent = srcDecision.mode === 'mrp'
            ? srcDecision.count + ' requisition line(s) awaiting System Admin approval — one decision covers all of them (released to Procurement / cancelled with a reason).'
            : srcDecision.count + ' PO draft line(s) awaiting admin approval — one decision covers all of them (activated for Receiving / cancelled with a reason).';
        var body = document.getElementById('srcReviewBody');
        if (!items.length) {
            body.innerHTML = '<tr><td colspan="9" class="text-muted">No lines.</td></tr>';
        } else {
            body.innerHTML = '';
            items.forEach(function(it, i) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td></td><td><code></code></td><td></td><td class="text-end"></td>' +
                    '<td></td><td></td><td class="text-end"></td><td></td><td></td>';
                tr.cells[0].textContent = String(i + 1);
                tr.cells[1].textContent = it.code || '';
                tr.cells[2].textContent = it.desc || '';
                tr.cells[3].textContent = it.qty || '';
                tr.cells[4].textContent = it.uom || '';
                tr.cells[5].textContent = it.sup || '';
                tr.cells[6].textContent = it.cost || '';
                tr.cells[7].textContent = it.date || '-';
                tr.cells[8].textContent = it.by || '';
                body.appendChild(tr);
            });
        }
        modal.show();
    });

    var form = document.getElementById('mrpApprovalForm');
    document.getElementById('srcApproveBtn').addEventListener('click', function() {
        if (!srcDecision) return;
        var d = srcDecision;
        if (d.mode === 'mrp') {
            Dlg.confirm('Approve ALL ' + d.count + ' requisition(s) of ' + d.ref + ' and release them to Procurement?').then(function(ok) {
                if (!ok) return;
                fillForm({ runId: d.runId, ref: d.ref });
                form.action = '?controller=admin&action=approveMrpRunDraft';
                form.submit();
            });
        } else {
            Dlg.confirm('Approve ALL ' + d.count + ' PO line(s) of ' + d.ref + ' and activate them for Receiving?').then(function(ok) {
                if (!ok) return;
                fillForm({ ids: d.ids, ref: d.ref });
                form.action = '?controller=admin&action=approvePoGroup';
                form.submit();
            });
        }
    });

    document.getElementById('srcRejectBtn').addEventListener('click', function() {
        if (!srcDecision) return;
        var d = srcDecision;
        var subject = d.mode === 'mrp'
            ? 'requisition(s) of ' + d.ref
            : 'PO line(s) of ' + d.ref;
        Dlg.prompt('Reason for rejecting ALL ' + d.count + ' ' + subject + ' (required):', {
            requiredMessage: 'A rejection reason is required.'
        }).then(function(reason) {
            if (reason === null || reason === '') return;
            if (d.mode === 'mrp') {
                fillForm({ runId: d.runId, ref: d.ref });
                form.action = '?controller=admin&action=rejectMrpRunDraft';
            } else {
                fillForm({ ids: d.ids, ref: d.ref });
                form.action = '?controller=admin&action=rejectPoGroup';
            }
            document.getElementById('mrpRejectReason').value = reason;
            form.submit();
        });
    });
})();
</script>
<?php
// Registered separator buttons — the shared table_enhancer looks each
// separator's key up here in makeSep() and appends the HTML (float-end).
// Emitted before the enhancer include so the registry exists by the time
// DOMContentLoaded builds the separators.
?>
<script>
window.TABLE_GROUP_ACTIONS = <?= json_encode($tableGroupActions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php require BASE_PATH . 'app/views/shared/table_enhancer.php'; ?>
