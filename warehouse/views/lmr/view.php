<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">LMR Details</h4>
        <small class="text-muted">LMR <?= htmlspecialchars($lmr['lmr_number'] ?? '') ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="?controller=lmr&action=index" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to LMR List
        </a>
        <?php if (($lmr['lmr_status'] ?? '') === 'Planned'): ?>
            <a href="?controller=lmr&action=release&id=<?= (int) ($lmr['lmr_id'] ?? 0) ?>" class="btn btn-success btn-sm">
                <i class="bi bi-send me-1"></i>Release LMR
            </a>
        <?php endif; ?>
        <?php if (($lmr['lmr_status'] ?? '') === 'Released'): ?>
            <a href="?controller=lmr&action=printLmr&id=<?= (int) ($lmr['lmr_id'] ?? 0) ?>" class="btn btn-outline-dark btn-sm" target="_blank">
                <i class="bi bi-printer me-1"></i>Print LMR
            </a>
            <form method="POST" action="?controller=lmr&action=cancel" class="d-inline" data-confirm="Cancel this LMR and release all allocated materials back to inventory?">
                <input type="hidden" name="lmr_id" value="<?= (int) ($lmr['lmr_id'] ?? 0) ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-x-circle me-1"></i>Cancel LMR
                </button>
            </form>
        <?php endif; ?>
        <a href="?controller=lmr&action=edit&id=<?= (int) ($lmr['lmr_id'] ?? 0) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    </div>
</div>

<!-- LMR Header Info -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <small class="text-muted d-block">LMR Number</small>
                <strong><?= htmlspecialchars($lmr['lmr_number'] ?? '-') ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Customer</small>
                <?= htmlspecialchars($lmr['customer_name'] ?? $lmr['customer_code'] ?? '-') ?>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">LMR Type</small>
                <?= htmlspecialchars($lmr['lmr_type'] ?? 'Standard') ?>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Status</small>
                <?php
                    $statusClass = 'secondary';
                    if (($lmr['lmr_status'] ?? 'Planned') === 'Planned') $statusClass = 'info';
                    elseif (($lmr['lmr_status'] ?? '') === 'Released') $statusClass = 'primary';
                    elseif (($lmr['lmr_status'] ?? '') === 'Completed') $statusClass = 'success';
                ?>
                <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($lmr['lmr_status'] ?? 'Planned') ?></span>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Priority</small>
                <?= (int) ($lmr['priority'] ?? 3) ?>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-3">
                <small class="text-muted d-block">Order Date</small>
                <?= !empty($lmr['order_date']) ? date('Y-m-d', strtotime($lmr['order_date'])) : '-' ?>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Due Date</small>
                <?= !empty($lmr['due_date']) ? date('Y-m-d', strtotime($lmr['due_date'])) : '-' ?>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Planned Start</small>
                <?= !empty($lmr['planned_start_date']) ? date('Y-m-d', strtotime($lmr['planned_start_date'])) : '-' ?>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Batch / Lot No</small>
                <?= htmlspecialchars($lmr['batch_lot_no'] ?? (!empty($lmr['lot_series']) ? $lmr['lot_series'] : '-')) ?>
            </div>
        </div>
        <?php if (!empty($lmr['reference_no']) || !empty($lmr['po_number'])): ?>
        <hr>
        <div class="row">
            <?php if (!empty($lmr['reference_no'])): ?>
            <div class="col-md-3">
                <small class="text-muted d-block">Reference No</small>
                <?= htmlspecialchars($lmr['reference_no']) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($lmr['po_number'])): ?>
            <div class="col-md-3">
                <small class="text-muted d-block">PO Number</small>
                <?= htmlspecialchars($lmr['po_number']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Line Items -->
<?php if (!empty($items)): ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Line Items (<?= count($items) ?>)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>UOM</th>
                        <th>Type</th>
                        <th>BOM Code</th>
                        <th class="text-end">Qty Ordered</th>
                        <th>SO Number</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['item_code'] ?? '-') ?></strong></td>
                        <td><?= htmlspecialchars($item['item_description'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($item['uom'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($item['item_type'] ?? '-') ?></td>
                        <td>
                            <?php if (!empty($item['bom_code'])): ?>
                                <span class="text-muted"><?= htmlspecialchars($item['bom_code']) ?></span>
                                <button class="btn btn-sm btn-outline-info ms-1 view-bom-btn"
                                        data-lmr-id="<?= (int) ($lmr['lmr_id'] ?? 0) ?>"
                                        title="View BOM Breakdown">
                                    <i class="bi bi-eye"></i>
                                </button>
                            <?php else: ?>
                                <small class="text-danger">No BOM</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= number_format($item['qty_ordered'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($item['so_number'] ?? '-') ?></td>
                        <td>
                            <a href="?controller=lmr&action=edit&id=<?= (int) ($lmr['lmr_id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- BOM Breakdown -->
<?php if (!empty($breakdown)): ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>BOM Component Breakdown</h6>
    </div>
    <div class="card-body">
        <?php foreach ($breakdown as $item): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= htmlspecialchars($item['item_code']) ?> &mdash; <?= htmlspecialchars($item['item_description']) ?></strong>
                <span class="badge bg-secondary">Qty: <?= number_format($item['qty_ordered']) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if ($item['bom_status'] === 'no_bom'): ?>
                    <div class="p-3 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>No BOM defined for this item.</div>
                <?php elseif ($item['bom_status'] === 'not_found'): ?>
                    <div class="p-3 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>BOM "<?= htmlspecialchars($item['bom_code']) ?>" not found in system.</div>
                <?php else: ?>
                    <div class="px-3 py-2 bg-light border-bottom small">
                        BOM: <strong><?= htmlspecialchars($item['bom_code']) ?></strong> &nbsp;|&nbsp;
                        Fill Volume: <strong><?= $item['fill_volume'] ?? $item['batch_qty'] ?></strong> <?= htmlspecialchars($item['uom'] ?? $item['batch_uom'] ?? '') ?>
                        <?php if (!empty($item['is_legacy_formula'])): ?>
                            &nbsp;|&nbsp; <span class="badge bg-warning text-dark">Legacy formula</span>
                            &nbsp;|&nbsp; Batches Needed: <strong><?= $item['batches_needed'] ?></strong>
                        <?php else: ?>
                            &nbsp;|&nbsp; UOM Divisor: <strong><?= number_format($item['batch_unit_divisor'] ?? 1000, 0) ?></strong>
                            &nbsp;|&nbsp; Bulk Batch: <strong><?= formatQty($item['bulk_batch'] ?? 0, 3) ?></strong>
                        <?php endif; ?>
                    </div>
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Phase</th>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>UOM</th>
                                <th class="text-end">Dosage Rate</th>
                                <th class="text-end">Wastage %</th>
                                <th class="text-end">Required Qty</th>
                                <th class="text-end">SOH</th>
                                <th class="text-end">Allocated</th>
                                <th class="text-end">Net Available</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($item['components'] as $c): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($c['phase_code'] ?? '101') ?></code></td>
                                <td><?= htmlspecialchars($c['item_code']) ?></td>
                                <td><?= htmlspecialchars($c['item_description']) ?></td>
                                <td><?= htmlspecialchars($c['item_uom'] ?? '-') ?></td>
                                <td class="text-end"><?= formatQty($c['dosage_rate']) ?><?= ($c['item_type'] ?? '') === 'RM' && empty($item['is_legacy_formula']) ? '%' : '' ?></td>
                                <td class="text-end"><?= formatQty($c['wastage_pct'], 2) ?>%</td>
                                <td class="text-end"><?= formatQty($c['required_qty']) ?></td>
                                <td class="text-end"><?= formatQty($c['soh']) ?></td>
                                <td class="text-end"><?= formatQty($c['allocated'] ?? 0) ?></td>
                                <td class="text-end"><?= formatQty($c['net_available']) ?></td>
                                <td>
                                    <?php if ($c['status'] === 'available'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-lg"></i> Available</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-lg"></i> Lacking <?= formatQty($c['shortage'], 2) ?> <?= htmlspecialchars($c['item_uom'] ?? '') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- BOM Breakdown Modal (for eye icon clicks) -->
<div class="modal fade" id="bomBreakdownModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>BOM Feasibility Breakdown</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bomBreakdownBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-info" role="status"></div>
                    <p class="mt-2 text-muted">Loading BOM data...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.view-bom-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var lmrId = this.getAttribute('data-lmr-id');
        var modal = new bootstrap.Modal(document.getElementById('bomBreakdownModal'));
        var body = document.getElementById('bomBreakdownBody');
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-info"></div><p class="mt-2 text-muted">Loading...</p></div>';
        modal.show();

        fetch('?controller=lmr&action=getBomBreakdown&lmr_id=' + lmrId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    body.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                    return;
                }
                var html = '';
                html += '<div class="row mb-3">';
                html += '<div class="col-md-3"><strong>LMR:</strong> ' + (data.lmr.lmr_number || '-') + '</div>';
                html += '<div class="col-md-3"><strong>Customer:</strong> ' + (data.lmr.customer_name || data.lmr.customer_code || '-') + '</div>';
                html += '<div class="col-md-3"><strong>Status:</strong> <span class="badge bg-info">' + (data.lmr.lmr_status || '-') + '</span></div>';
                html += '</div>';

                var anyShortage = false;
                data.breakdown.forEach(function(item) {
                    if (item.has_shortage) anyShortage = true;
                    html += '<div class="card mb-3">';
                    html += '<div class="card-header d-flex justify-content-between align-items-center">';
                    html += '<strong>' + item.item_code + ' &mdash; ' + item.item_description + '</strong>';
                    html += '<span class="badge bg-secondary">Qty: ' + item.qty_ordered.toLocaleString() + '</span>';
                    html += '</div>';
                    html += '<div class="card-body p-0">';

                    if (item.bom_status === 'no_bom') {
                        html += '<div class="p-3 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>No BOM defined for this item.</div>';
                    } else if (item.bom_status === 'not_found') {
                        html += '<div class="p-3 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>BOM "' + (item.bom_code || '') + '" not found in system.</div>';
                    } else {
                        html += '<div class="px-3 py-2 bg-light border-bottom small">';
                        html += 'BOM: <strong>' + item.bom_code + '</strong> &nbsp;|&nbsp; Fill Volume: <strong>' + (item.fill_volume ?? item.batch_qty) + '</strong> ' + (item.uom ?? item.batch_uom ?? '');
                        if (item.is_legacy_formula) {
                            html += ' &nbsp;|&nbsp; <span class="badge bg-warning text-dark">Legacy formula</span> &nbsp;|&nbsp; Batches Needed: <strong>' + item.batches_needed + '</strong>';
                        } else {
                            html += ' &nbsp;|&nbsp; UOM Divisor: <strong>' + (item.batch_unit_divisor ?? 1000) + '</strong> &nbsp;|&nbsp; Bulk Batch: <strong>' + (item.bulk_batch ?? 0) + '</strong>';
                        }
                        html += '</div>';
                        html += '<table class="table table-sm mb-0">';
                        html += '<thead class="table-light"><tr>';
                        html += '<th>Item Code</th><th>Description</th><th class="text-end">Required Qty</th><th class="text-end">SOH</th><th class="text-end">Allocated</th><th class="text-end">Net Available</th><th>Status</th>';
                        html += '</tr></thead><tbody>';
                        item.components.forEach(function(c) {
                            var statusBadge = c.status === 'available'
                                ? '<span class="badge bg-success"><i class="bi bi-check-lg"></i> Available</span>'
                                : '<span class="badge bg-danger"><i class="bi bi-x-lg"></i> Lacking ' + c.shortage.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:4}) + ' ' + (c.item_uom || '') + '</span>';
                            html += '<tr>';
                            html += '<td>' + c.item_code + '</td>';
                            html += '<td>' + c.item_description + '</td>';
                            html += '<td class="text-end">' + c.required_qty.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:4}) + '</td>';
                            html += '<td class="text-end">' + c.soh.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:4}) + '</td>';
                            html += '<td class="text-end">' + (c.allocated || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:4}) + '</td>';
                            html += '<td class="text-end">' + c.net_available.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:4}) + '</td>';
                            html += '<td>' + statusBadge + '</td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table>';
                    }
                    html += '</div></div>';
                });

                if (anyShortage) {
                    html = '<div class="alert alert-danger mb-3"><i class="bi bi-exclamation-triangle me-1"></i><strong>Stock shortages detected.</strong></div>' + html;
                } else {
                    html = '<div class="alert alert-success mb-3"><i class="bi bi-check-circle me-1"></i><strong>All materials available.</strong></div>' + html;
                }

                body.innerHTML = html;
            })
            .catch(function(err) {
                body.innerHTML = '<div class="alert alert-danger">Failed to load BOM data.</div>';
            });
    });
});
</script>
