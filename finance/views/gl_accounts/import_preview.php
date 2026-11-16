<div class="d-flex justify-content-between mb-4">
    <div class="d-flex gap-2">
        <a href="?controller=finance&action=glAccountImportForm" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Import</a>
    </div>
</div>

<div class="card data-card mb-4">
    <div class="card-header"><i class="bi bi-info-circle me-2"></i>Import Summary</div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <h4 class="mb-0 text-primary"><?= $newCount ?></h4>
                    <small class="text-muted">New</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <h4 class="mb-0 text-secondary"><?= $skippedCount ?></h4>
                    <small class="text-muted">Skipped (existing)</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <h4 class="mb-0 text-danger"><?= $errorCount ?></h4>
                    <small class="text-muted">Errors</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <h4 class="mb-0"><?= count($preview) ?></h4>
                    <small class="text-muted">Total Rows</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card data-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2"></i>Preview (first 200 rows)</span>
        <?php if ($errorCount === 0 && ($newCount + $skippedCount) > 0): ?>
        <form method="POST" action="?controller=finance&action=glAccountImportConfirm" class="d-inline">
            <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Confirm Import</button>
        </form>
        <?php elseif ($errorCount > 0): ?>
        <span class="badge bg-danger">Fix errors before importing</span>
        <?php endif; ?>
    </div>
    <div class="table-responsive" style="max-height:500px; overflow-y:auto;">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Row</th>
                    <th>Status</th>
                    <th>GL Account Code</th>
                    <th>GL Account Description</th>
                    <th>Report Included</th>
                    <th>Grouping in Financial Report</th>
                    <th>Validation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($preview, 0, 200) as $r): ?>
                <tr class="<?= !empty($r['errors']) ? 'table-danger' : '' ?>">
                    <td><?= $r['row'] ?></td>
                    <td>
                        <?php if (!empty($r['errors'])): ?>
                            <span class="badge bg-danger">Error</span>
                        <?php elseif ($r['status'] === 'skipped'): ?>
                            <span class="badge bg-secondary">Skipped</span>
                        <?php else: ?>
                            <span class="badge bg-success">New</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($r['code']) ?></strong></td>
                    <td><?= htmlspecialchars($r['description']) ?></td>
                    <td><small><?= htmlspecialchars($r['report_included'] ?: '—') ?></small></td>
                    <td><small><?= htmlspecialchars($r['grouping_financial_report'] ?: '—') ?></small></td>
                    <td>
                        <?php if (!empty($r['errors'])): ?>
                            <small class="text-danger"><?= implode('; ', $r['errors']) ?></small>
                        <?php elseif ($r['status'] === 'skipped'): ?>
                            <small class="text-muted">Code already exists — will be skipped</small>
                        <?php else: ?>
                            <small class="text-success">OK</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($preview) > 200): ?>
    <div class="card-footer text-muted">
        Showing 200 of <?= count($preview) ?> rows. All rows will be processed.
    </div>
    <?php endif; ?>
</div>

<?php if ($errorCount === 0 && ($newCount + $skippedCount) > 0): ?>
<div class="text-center mb-4">
    <form method="POST" action="?controller=finance&action=glAccountImportConfirm">
        <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-circle me-1"></i>Confirm Import — <?= $newCount ?> new<?= $skippedCount ? ', ' . $skippedCount . ' skipped' : '' ?></button>
    </form>
</div>
<?php endif; ?>
