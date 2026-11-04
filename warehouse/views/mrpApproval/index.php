<h4 class="mb-0"><i class="bi bi-shield-check me-2"></i>MRP Approval</h4>
<span class="text-muted small d-block mb-3">
    Requisition drafts saved from the MRP Sheet, awaiting the <b>Warehouse Admin</b> signature (first of two gates: WHSE Admin → System Admin — after System Admin the requisitions release to Procurement; raw material inspection happens at Receiving). Decisions are made per MRP run — runs with nothing lacking appear too (approval sign-off only, no purchase requests).
</span>

<?php
$runs = isset($runs) ? $runs : [];
$isWarehouseContext = true;
$pageStage = 'whse';
// WHSE gate: rows show View only — Approve All / Reject live in the run
// review modal footer (same pattern as the admin Approvals hub).
$decisionInModal = true;
require __DIR__ . '/run_section.php';
?>

<?php $decisionRuns = isset($decisionRuns) ? $decisionRuns : []; ?>
<p class="fw-bold text-uppercase text-muted mt-4 mb-2">
    <i class="bi bi-clock-history me-1"></i>Runs I Signed — Tracking (<?= count($decisionRuns) ?>)
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
                    <th>Current Stage</th>
                    <th style="width:120px">Review</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($decisionRuns)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Runs you approve appear here so you can track where they are next.</td></tr>
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
                    <td><span class="badge bg-info"><?= (int) $dr['item_count'] ?> requisition(s)</span></td>
                    <td>
                        <span class="badge <?= $dr['tracking_badge'] ?>"><?= htmlspecialchars($dr['tracking_label']) ?></span>
                        <?php if ((int) $dr['st_awaiting_sysadmin'] > 0): ?>
                            <div class="small text-muted"><?= (int) $dr['st_awaiting_sysadmin'] ?> awaiting System Admin</div>
                        <?php endif; ?>
                        <?php if ((int) $dr['st_released'] > 0): ?>
                            <div class="small text-muted"><?= (int) $dr['st_released'] ?> released</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-info text-nowrap run-detail-btn"
                                data-decided="1"
                                data-id="<?= (int) $dr['run_id'] ?>"
                                data-code="<?= htmlspecialchars($dr['mrp_ref'] ?: ('MRP-#' . $dr['run_id'])) ?>"
                                data-bom-id="<?= (int) ($dr['bom_id'] ?? 0) ?>"
                                data-bom-code="<?= htmlspecialchars($dr['bom_code'] ?? '') ?>"
                                data-target-qty="<?= $dr['target_qty'] !== null && $dr['target_qty'] !== '' ? formatQty($dr['target_qty']) : '0' ?>"
                                data-fg-code="<?= htmlspecialchars($dr['fg_code'] ?? '') ?>"
                                title="Review the run component break-down">
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
