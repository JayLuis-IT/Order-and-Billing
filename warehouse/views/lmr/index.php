<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">LMRs</h4>
    </div>
    <a href="?controller=lmr&action=create" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Create New LMR
    </a>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= ($status ?? 'all') === 'all' ? 'active' : '' ?>" href="?controller=lmr&action=index&status=all">All Orders</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($status ?? '') === 'Planned' ? 'active' : '' ?>" href="?controller=lmr&action=index&status=Planned">Planned Orders</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($status ?? '') === 'Released' ? 'active' : '' ?>" href="?controller=lmr&action=index&status=Released">Released Orders</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($status ?? '') === 'Completed' ? 'active' : '' ?>" href="?controller=lmr&action=index&status=Completed">Completed Orders</a>
            </li>
        </ul>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>LMR Number</th>
                        <th>Customer</th>
                        <th>Item Code</th>
                        <th>BOM</th>
                        <th>Qty Ordered</th>
                        <th>Batch / Lot No</th>
                        <th>Planned Start Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No manufacturing orders found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $lmr): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="?controller=lmr&action=view&id=<?= (int) ($lmr['lmr_id'] ?? 0) ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($lmr['lmr_number'] ?? '-') ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?= htmlspecialchars($lmr['customer_name'] ?? $lmr['customer_code'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($lmr['item_code'] ?? '-') ?></td>
                                <td>
                                    <?php
                                    $bomCode = null;
                                    if (!empty($lmr['items'])) {
                                        foreach ($lmr['items'] as $mi) {
                                            if (!empty($mi['bom_code'])) {
                                                $bomCode = $mi['bom_code'];
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                    <?php if ($bomCode): ?>
                                        <span class="text-muted"><?= htmlspecialchars($bomCode) ?></span>
                                        <button class="btn btn-sm btn-outline-info ms-1 view-bom-btn"
                                                data-lmr-id="<?= (int) ($lmr['lmr_id'] ?? 0) ?>"
                                                title="View BOM Breakdown">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    <?php else: ?>
                                        <small class="text-danger">No BOM</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($lmr['qty_ordered'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($lmr['batch_lot_no'] ?? (!empty($lmr['lot_series']) ? $lmr['lot_series'] : '-')) ?></td>
                                <td><?= !empty($lmr['planned_start_date']) ? date('Y-m-d', strtotime($lmr['planned_start_date'])) : '-' ?></td>
                                <td>
                                    <?php
                                        $statusClass = 'secondary';
                                        if (($lmr['lmr_status'] ?? 'Planned') === 'Planned') $statusClass = 'info';
                                        elseif (($lmr['lmr_status'] ?? '') === 'Released') $statusClass = 'primary';
                                        elseif (($lmr['lmr_status'] ?? '') === 'Completed') $statusClass = 'success';
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($lmr['lmr_status'] ?? 'Planned') ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="?controller=lmr&action=view&id=<?= (int) ($lmr['lmr_id'] ?? 0) ?>" class="btn btn-outline-info" title="View Details"><i class="bi bi-eye"></i></a>
                                        <a href="?controller=lmr&action=edit&id=<?= (int) ($lmr['lmr_id'] ?? 0) ?>" class="btn btn-outline-primary">Edit</a>
                                        <?php if (($lmr['lmr_status'] ?? '') === 'Planned'): ?>
                                            <a href="?controller=lmr&action=release&id=<?= (int) ($lmr['lmr_id'] ?? 0) ?>" class="btn btn-outline-success">Release LMR</a>
                                        <?php endif; ?>
                                        <?php if (($lmr['lmr_status'] ?? '') === 'Released'): ?>
                                            <a href="?controller=lmr&action=printLmr&id=<?= (int) ($lmr['lmr_id'] ?? 0) ?>" class="btn btn-outline-dark" target="_blank">Print LMR</a>
                                            <form method="POST" action="?controller=lmr&action=cancel" class="d-inline" data-confirm="Cancel this LMR and release all allocated materials?">
                                                <input type="hidden" name="lmr_id" value="<?= (int) ($lmr['lmr_id'] ?? 0) ?>">
                                                <button type="submit" class="btn btn-outline-danger">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary" type="button" disabled>Print LMR</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- BOM Breakdown Modal -->
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
                    html = '<div class="alert alert-danger mb-3"><i class="bi bi-exclamation-triangle me-1"></i><strong>Stock shortages detected.</strong> Some materials lack sufficient inventory.</div>' + html;
                } else {
                    html = '<div class="alert alert-success mb-3"><i class="bi bi-check-circle me-1"></i><strong>All materials available.</strong> This LMR can be safely released.</div>' + html;
                }

                body.innerHTML = html;
            })
            .catch(function(err) {
                body.innerHTML = '<div class="alert alert-danger">Failed to load BOM data.</div>';
            });
    });
});
</script>
