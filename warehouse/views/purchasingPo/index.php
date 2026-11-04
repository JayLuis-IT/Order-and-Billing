<style>
    /* Actions column: keep View/Edit/Cancel/Delete buttons on one line */
    .purchasing-po-table td:last-child, .purchasing-po-table th:last-child { white-space: nowrap; }
</style>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <?php $isReadOnly = !empty($readOnly) || (($_SESSION['department'] ?? '') !== 'warehouse'); ?>
    <?php if (!$isReadOnly): ?>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPurchasingPoModal">
            <i class="bi bi-plus-circle me-1"></i> New Purchasing PO
        </button>
        <button class="btn btn-success d-none" id="batchProcessBtn" onclick="openBatchProcessModal()">
            <i class="bi bi-lightning me-1"></i>Process Selected Items
            <span class="badge bg-white text-success ms-1" id="selectedCount">0</span>
        </button>
    </div>
    <?php endif; ?>
    <div class="d-flex gap-2 flex-wrap">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="controller" value="warehouse">
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
                <option value="PO_Created" <?= ($filters['status'] ?? '') === 'PO_Created' ? 'selected' : '' ?>>PO Created (consolidated)</option>
            </select>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search supplier, item, PO Ref..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="width:220px">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
            <a href="?controller=warehouse&action=purchasingPo" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
        </form>
    </div>
</div>

<div class="card data-card">
    <?php
    // Status tabs: separate unassigned requisitions from active supplier orders.
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
                $tabHref = '?controller=warehouse&action=purchasingPo&tab=' . rawurlencode($tabKey);
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
        <table class="table table-hover mb-0 purchasing-po-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAllOrders" class="form-check-input"></th>
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="14" class="text-center text-muted py-4">No purchasing POs found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($orders as $index => $o): $qty = floatval($o['quantity']); ?>
                <tr>
                    <td>
                        <?php if (in_array($o['status'], ['requested', 'pending'])): ?>
                        <input type="checkbox" class="form-check-input order-checkbox"
                               data-id="<?= $o['supplier_order_id'] ?>"
                               data-req-id="<?= $o['supplier_order_id'] ?>"
                               data-item-code="<?= htmlspecialchars($o['item_code']) ?>"
                               data-item-desc="<?= htmlspecialchars($o['item_description']) ?>"
                               data-item-name="<?= htmlspecialchars($o['item_description']) ?>"
                               data-qty="<?= $o['quantity'] ?>"
                               data-po-ref="<?= htmlspecialchars($o['po_ref_display']) ?>"
                               data-mrp-ref="<?= htmlspecialchars($o['mrp_ref'] ?? '') ?>">
                        <?php endif; ?>
                    </td>
                    <td title="Order #<?= $o['supplier_order_id'] ?>"><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($o['supplier_name']) ?></td>
                    <td><code><?= htmlspecialchars($o['item_code']) ?></code></td>
                    <td><?= htmlspecialchars($o['item_description']) ?></td>
                    <td class="text-end"><?= formatQty($qty) ?> <?= htmlspecialchars($o['item_uom'] ?? '') ?></td>
                    <td><?= $o['order_date'] ? date('m/d/Y', strtotime($o['order_date'])) : '-' ?></td>
                    <td><?= $o['expected_date'] ? date('m/d/Y', strtotime($o['expected_date'])) : '-' ?></td>
                    <td>
                        <?= htmlspecialchars($o['po_ref_display']) ?>
                        <?php if (!empty($o['po_group_id'])): ?>
                            <div class="mt-1"><span class="badge bg-light text-dark border">Group #<?= (int) $o['po_group_id'] ?></span></div>
                        <?php endif; ?>
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
                        <?php elseif ($o['status'] === 'PO_Created'): ?>
                            <span class="badge bg-light text-dark border">PO Created</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($o['status'])) ?: 'Unknown' ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?php
                        $recvQty = floatval($o['received_qty'] ?? 0);
                        $remainQty = $qty - $recvQty;
                        if ($recvQty > 0) {
                            echo formatQty($recvQty) . ' ' . htmlspecialchars($o['item_uom'] ?? '');
                            // Split-state hints: never inflate the numbers themselves —
                            // the balance shows as a muted sub-line only.
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
                        <?php
                        // Completed / terminal rows get a read-only View Details modal.
                        $isCompletedPo = in_array($o['status'], ['received', 'cancelled', 'rejected', 'completed', 'PO_Created']);
                        // MRP-sourced rows are managed from the Source group row
                        // (View / Process / Print live on the separator) — the
                        // per-row button cluster is suppressed for lifecycle
                        // statuses. Approval statuses keep their workflow buttons.
                        $isMrpRow = !empty($o['mrp_run_id']);
                        $isMrpLifecycle = $isMrpRow && in_array($o['status'], [
                            'pending', 'processed', 'partially_received', 'For Inspection',
                            'received', 'cancelled', 'completed', 'PO_Created', 'rejected',
                        ]);
                        ?>
                        <?php if ($isMrpLifecycle): ?>
                            <span class="text-muted small">Source group</span>
                        <?php else: ?>
                        <?php if (!$isMrpRow): ?>
                        <a href="?controller=warehouse&action=printPurchasingPo&id=<?= $o['supplier_order_id'] ?>"
                           target="_blank" class="btn btn-sm btn-outline-secondary" title="Print PO">
                            <i class="bi bi-printer"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($isReadOnly): ?>
                            <?php if ($isCompletedPo): ?>
                                <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        <?php elseif ($o['status'] === 'pending_whse_approval'): ?>
                            <?php if (!empty($o['mrp_run_id'])): ?>
                                <?php // MRP-source PO drafts: the Source separator carries
                                      // View/Print and Approve/Reject lives inside the
                                      // source View modal — no per-row buttons. ?>
                                <span class="text-muted small">Use Source group buttons</span>
                            <?php else: ?>
                                <?php
                                // Manual cart POs have no source View/Print (a blank PO REF
                                // renders no separator) — they keep their row buttons.
                                ?>
                                <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <?php if (!empty($isWarehouseApprover)): ?>
                                    <button type="button" class="btn btn-success btn-sm" title="Approve the whole PO group and forward to System Admin"
                                            onclick="submitManualPoDecision(<?= $o['supplier_order_id'] ?>, true)">
                                        <i class="bi bi-check-lg"></i> Approve
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" title="Reject and cancel the whole PO group"
                                            onclick="submitManualPoDecision(<?= $o['supplier_order_id'] ?>, false)">
                                        <i class="bi bi-x-lg"></i> Reject
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">Awaiting WHSE admin approval</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php elseif ($o['status'] === 'pending_admin_approval'): ?>
                            <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <span class="badge bg-warning text-dark">Awaiting System Admin</span>
                        <?php elseif ($o['status'] === 'requested'): ?>
                            <?php if ($isMrpRow): ?>
                                <?php // MRP-sourced lines are handled by the Source group's
                                      // View/Process/Print buttons on the separator row. ?>
                                <span class="text-muted small">Use Source group buttons</span>
                            <?php else: ?>
                            <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button class="btn btn-outline-primary btn-sm process-order-btn"
                                    data-id="<?= $o['supplier_order_id'] ?>"
                                    data-supplier="<?= htmlspecialchars($o['supplier_name']) ?>"
                                    data-qty="<?= $o['quantity'] ?>"
                                    data-uom="<?= htmlspecialchars($o['item_uom'] ?? '') ?>"
                                    data-order-date="<?= $o['order_date'] ?>"
                                    data-expected="<?= $o['expected_date'] ?>"
                                    data-po-id="<?= $o['po_id'] ?? '' ?>"
                                    title="Process Order">
                                <i class="bi bi-gear"></i> Process
                            </button>
                            <?php endif; ?>
                        <?php elseif ($o['status'] === 'pending'): ?>
                            <?php $receivedAnything = floatval($o['received_qty'] ?? 0) > 0.0001; ?>
                            <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button class="btn btn-sm btn-outline-secondary btn-edit-po" data-id="<?= $o['supplier_order_id'] ?>" title="Edit Record">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <?php if (!$receivedAnything): ?>
                            <a href="?controller=warehouse&action=cancelPurchasingPo&id=<?= $o['supplier_order_id'] ?>" class="btn btn-outline-warning btn-sm" title="Cancel" data-confirm="Cancel this order? Any pending QC inspection entries will be cleared from the queue.">
                                <i class="bi bi-x-circle"></i>
                            </a>
                            <a href="?controller=warehouse&action=deletePurchasingPo&id=<?= $o['supplier_order_id'] ?>" class="btn btn-outline-danger btn-sm" title="Delete" data-confirm="Delete this order?">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php endif; ?>
                        <?php elseif (in_array($o['status'], ['processed', 'partially_received', 'For Inspection'])): ?>
                            <?php
                            // Once goods have been receipted (staged into the QC
                            // queue or partially received) the order can no
                            // longer be cancelled or edited — it is "received".
                            $hasReceipts = floatval($o['received_qty'] ?? 0) > 0.0001;
                            $canCancel = $o['status'] === 'processed' && !$hasReceipts;
                            ?>
                            <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <?php if (!$hasReceipts): ?>
                            <button class="btn btn-sm btn-outline-secondary btn-edit-po" data-id="<?= $o['supplier_order_id'] ?>" title="Edit Record">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <?php endif; ?>
                            <?php if ($canCancel): ?>
                            <a href="?controller=warehouse&action=cancelPurchasingPo&id=<?= $o['supplier_order_id'] ?>" class="btn btn-outline-warning btn-sm" title="Cancel" data-confirm="Cancel this order? Any pending QC inspection entries will be cleared from the queue.">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($isCompletedPo): ?>
                                <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!$isReadOnly): ?>
<!-- Create Purchasing PO Modal -->
<div class="modal fade" id="createPurchasingPoModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="?controller=warehouse&action=createPurchasingPo" id="createPoForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>New Purchasing PO</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        Add one or more lines — the same item may appear under different suppliers.
                        The whole PO is saved together and routed to WHSE Admin approval.
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">PO REF</label>
                            <input type="text" name="po_ref" class="form-control" placeholder="e.g. PO-2026-001">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Order Date</label>
                            <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Expected Date</label>
                            <input type="date" name="expected_date" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>
                    <label class="form-label fw-bold">Order Lines <span class="text-danger">*</span></label>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="poLinesTable">
                            <thead>
                                <tr class="small text-muted">
                                    <th style="width:18%">Supplier <span class="text-danger">*</span></th>
                                    <th style="width:22%">Item Code <span class="text-danger">*</span></th>
                                    <th>Item Description</th>
                                    <th style="width:12%">Qty <span class="text-danger">*</span></th>
                                    <th style="width:12%">Unit Cost</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="poLinesBody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addPoLineBtn">
                        <i class="bi bi-plus-lg me-1"></i>Add Line
                    </button>
                    <input type="hidden" name="lines" id="poLinesJson" value="[]">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create Order</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!$isReadOnly): ?>
<!-- Process Order Modal -->
<div class="modal fade" id="processOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?controller=warehouse&action=processPurchasingPo">
                <input type="hidden" name="supplier_order_id" id="procOrderId">
                <input type="hidden" name="po_id" id="procPoId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-gear me-2"></i>Process Purchasing PO</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3">
                        <i class="bi bi-info-circle me-1"></i>Convert this requested item into a processed procurement PO by selecting a supplier and confirming quantities.
                    </div>
                    <div class="mb-3">
                        <label for="procSupplierSelect" class="form-label fw-bold">Supplier Name <span class="text-danger">*</span></label>
                        <select class="form-select supplier-select" id="procSupplierSelect" name="supplier_name" required>
                            <option value="" selected disabled>-- Select Supplier --</option>
                            <?php foreach (($suppliers ?? []) as $s): ?>
                            <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="text-danger small mt-1 d-none supplier-error">Please select a supplier from the list before generating the Purchase Order.</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Quantity <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="quantity" id="procQuantity" class="form-control" min="0.01" step="0.0001" required>
                                <span class="input-group-text" id="procQuantityUom">&nbsp;</span>
                            </div>
                            <small class="text-muted">Adjust to supplier MOQ/packaging size.</small>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Order Date</label>
                            <input type="date" name="order_date" id="procOrderDate" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Expected Date</label>
                            <input type="date" name="expected_date" id="procExpectedDate" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">PO REF</label>
                            <input type="text" name="po_ref" id="procPoRef" class="form-control" placeholder="e.g. PO-2026-001">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Remarks</label>
                        <textarea name="remarks" id="procRemarks" class="form-control" rows="2" placeholder="e.g. expected delivery, notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Confirm & Process</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!$isReadOnly): ?>
<!-- Batch Process Modal -->
<div class="modal fade" id="batchProcessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?controller=warehouse&action=batchProcessPurchasingPo">
                <input type="hidden" name="order_ids" id="batchOrderIds">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-lightning me-2"></i>Batch Process Purchasing POs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3">
                        <i class="bi bi-info-circle me-1"></i>Consolidate multiple requested items of the same product into a single supplier transaction.
                    </div>
                    <div class="card bg-light mb-3">
                        <div class="card-body py-2">
                            <div class="row mb-1">
                                <div class="col-4 fw-bold">Item:</div>
                                <div class="col-8"><code id="batchItemCode"></code> - <span id="batchItemDesc"></span></div>
                            </div>
                            <div class="row mb-1">
                                <div class="col-4 fw-bold">Total Consolidated Qty:</div>
                                <div class="col-8" id="batchTotalQty"></div>
                            </div>
                            <div class="row mb-1">
                                <div class="col-4 fw-bold">Linked PO Refs:</div>
                                <div class="col-8" id="batchPoRefs"></div>
                            </div>
                            <div class="row">
                                <div class="col-4 fw-bold">MRP Refs:</div>
                                <div class="col-8" id="batchMrpRefs">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="batchSupplierSelect" class="form-label fw-bold">Supplier Name <span class="text-danger">*</span></label>
                        <select class="form-select supplier-select" id="batchSupplierSelect" name="supplier_name" required>
                            <option value="" selected disabled>-- Select Supplier --</option>
                            <?php foreach (($suppliers ?? []) as $s): ?>
                            <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="text-danger small mt-1 d-none supplier-error">Please select a supplier from the list before generating the Purchase Order.</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Unit Cost</label>
                            <input type="number" name="unit_cost" class="form-control" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Expected Delivery Date</label>
                            <input type="date" name="expected_date" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Confirm & Process All</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Selection Error Modal (different item codes selected together) -->
<div class="modal fade" id="bulkItemErrorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Invalid Selection</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                You can only bulk process requisitions for the <b>SAME item code</b>.<br><br>
                Selected items contain: <span id="bulkErrorCodes"></span>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ─── Supplier dropdown (process / batch forms) ─────────────────────
    // The supplier field is a select of fixed options; guard submission so an
    // unselected dropdown can never generate a Purchase Order (native
    // `required` blocks it too — these listeners also surface our own message).
    function supplierErrorFor(sel) {
        var form = sel.closest('form');
        return form ? form.querySelector('.supplier-error') : null;
    }
    function hideSupplierError(sel) {
        var err = supplierErrorFor(sel);
        if (err) err.classList.add('d-none');
    }
    // `invalid` does not bubble — capture it to show the inline message.
    document.addEventListener('invalid', function(e) {
        if (e.target.classList && e.target.classList.contains('supplier-select')) {
            var err = supplierErrorFor(e.target);
            if (err) err.classList.remove('d-none');
        }
    }, true);
    document.addEventListener('submit', function(e) {
        var sel = e.target.querySelector ? e.target.querySelector('select.supplier-select') : null;
        if (!sel) return;
        if (!sel.value) {
            e.preventDefault();
            e.stopPropagation();
            var err = supplierErrorFor(sel);
            if (err) err.classList.remove('d-none');
            sel.focus();
            return;
        }
        hideSupplierError(sel);
    });
    document.addEventListener('change', function(e) {
        if (e.target.classList && e.target.classList.contains('supplier-select') && e.target.value) {
            hideSupplierError(e.target);
        }
    });
    document.addEventListener('reset', function(e) {
        var sel = e.target.querySelector ? e.target.querySelector('select.supplier-select') : null;
        if (sel) hideSupplierError(sel);
    });

    // ─── Create PO cart: dynamic lines (supplier + item per line) ──────
    var poSupplierOptions = <?= json_encode(array_map(function ($s) { return $s['name']; }, $suppliers ?? [])) ?>;
    var poLinesBody = document.getElementById('poLinesBody');
    var poLinesJson = document.getElementById('poLinesJson');
    var poCreateForm = document.getElementById('createPoForm');
    var poLineTimers = {};

    function poLineSupplierOptions() {
        var html = '<option value="" selected disabled>-- Select Supplier --</option>';
        poSupplierOptions.forEach(function(name) {
            var safe = name.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            html += '<option value="' + safe + '">' + safe + '</option>';
        });
        return html;
    }

    function addPoLine() {
        var row = document.createElement('tr');
        row.className = 'po-line-row';
        row.innerHTML =
            '<td>' +
                '<select class="form-select form-select-sm po-line-supplier" required>' + poLineSupplierOptions() + '</select>' +
            '</td>' +
            '<td style="position:relative">' +
                '<input type="text" class="form-control form-control-sm po-item-code" placeholder="Type or select code..." autocomplete="off">' +
                '<input type="hidden" class="po-item-id">' +
                '<div class="list-group po-item-dropdown d-none mt-1" style="position:absolute;z-index:1060;width:100%;min-width:340px;max-height:220px;overflow-y:auto;"></div>' +
            '</td>' +
            '<td><input type="text" class="form-control form-control-sm po-item-desc" placeholder="Auto-filled from item code" readonly></td>' +
            '<td><input type="number" class="form-control form-control-sm po-line-qty" min="0.01" step="0.0001" required></td>' +
            '<td><input type="number" class="form-control form-control-sm po-line-cost" min="0" step="0.01" value="0"></td>' +
            '<td class="text-center">' +
                '<button type="button" class="btn btn-sm btn-outline-danger po-remove-line" title="Remove line"><i class="bi bi-x-lg"></i></button>' +
            '</td>';
        poLinesBody.appendChild(row);
    }

    if (poLinesBody) {
        addPoLine(); // start with one line
        document.getElementById('addPoLineBtn').addEventListener('click', addPoLine);

        // Remove a line (keep at least one).
        poLinesBody.addEventListener('click', function(e) {
            var rm = e.target.closest('.po-remove-line');
            if (rm) {
                if (poLinesBody.querySelectorAll('.po-line-row').length > 1) {
                    rm.closest('tr').remove();
                } else {
                    Dlg.alert('A PO needs at least one line.');
                }
                return;
            }
            // Hide item dropdowns when clicking elsewhere.
            if (!e.target.closest('.po-item-code') && !e.target.closest('.po-item-dropdown')) {
                poLinesBody.querySelectorAll('.po-item-dropdown').forEach(function(d) { d.classList.add('d-none'); });
            }
        });

        // Fill a line from a chosen item (dropdown click or exact-match auto-fill).
        function poFillItemRow(row, item) {
            row.querySelector('.po-item-id').value = item.item_id;
            row.querySelector('.po-item-code').value = item.item_code || '';
            row.querySelector('.po-item-desc').value = item.item_description || '';
            row.querySelector('.po-item-code').classList.remove('is-invalid');
            row.querySelector('.po-item-dropdown').classList.add('d-none');
        }

        // Fetch + render the item dropdown for a code field. Empty text on
        // focus = browse mode (first codes); typed text = code/description
        // search. An exact code match auto-fills the line without a click.
        function poLoadItems(input) {
            var row = input.closest('tr');
            var dropdown = row.querySelector('.po-item-dropdown');
            var q = input.value.trim();
            fetch('?controller=warehouse&action=searchItems&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(items) {
                    dropdown.innerHTML = '';
                    if (!items || items.length === 0) {
                        dropdown.innerHTML = '<div class="list-group-item text-muted">No items found</div>';
                        dropdown.classList.remove('d-none');
                        return;
                    }
                    var seen = new Set();
                    var unique = [];
                    items.forEach(function(item) {
                        if (seen.has(item.item_id)) return;
                        seen.add(item.item_id);
                        unique.push(item);
                    });
                    // Exact code match (case-insensitive) while typing → auto-fill.
                    if (q !== '') {
                        var qLower = q.toLowerCase();
                        var exact = unique.find(function(item) {
                            return (item.item_code || '').trim().toLowerCase() === qLower;
                        });
                        if (exact) {
                            poFillItemRow(row, exact);
                            return;
                        }
                    }
                    unique.forEach(function(item) {
                        var a = document.createElement('a');
                        a.className = 'list-group-item list-group-item-action';
                        a.href = '#';
                        a.innerHTML = '<strong>' + (item.item_code || '') + '</strong> - ' + (item.item_description || '') + '<br><small class="text-muted">UOM: ' + (item.item_uom || '') + '</small>';
                        a.addEventListener('click', function(ev) {
                            ev.preventDefault();
                            poFillItemRow(row, item);
                        });
                        dropdown.appendChild(a);
                    });
                    dropdown.classList.remove('d-none');
                });
        }

        // Item Code field: browse-all on focus, search as you type.
        poLinesBody.addEventListener('focusin', function(e) {
            if (!e.target.classList.contains('po-item-code')) return;
            clearTimeout(poLineTimers[e.target.closest('tr').rowIndex]);
            poLoadItems(e.target);
        });

        poLinesBody.addEventListener('input', function(e) {
            if (!e.target.classList.contains('po-item-code')) return;
            var input = e.target;
            var row = input.closest('tr');
            var dropdown = row.querySelector('.po-item-dropdown');
            // Editing the code invalidates the previous selection on this line.
            row.querySelector('.po-item-id').value = '';
            row.querySelector('.po-item-desc').value = '';
            clearTimeout(poLineTimers[row.rowIndex]);
            var delay = input.value.trim() === '' ? 0 : 250;
            poLineTimers[row.rowIndex] = setTimeout(function() {
                poLoadItems(input);
            }, delay);
        });

        // Serialize lines to JSON on submit; validate every line first.
        poCreateForm.addEventListener('submit', function(e) {
            var rows = poLinesBody.querySelectorAll('.po-line-row');
            var lines = [];
            var firstBad = null;
            rows.forEach(function(row) {
                var supplier = row.querySelector('.po-line-supplier');
                var itemId = row.querySelector('.po-item-id');
                var qty = row.querySelector('.po-line-qty');
                var cost = row.querySelector('.po-line-cost');
                var code = row.querySelector('.po-item-code');
                var ok = supplier.value && itemId.value && parseFloat(qty.value) > 0;
                [supplier, code, qty].forEach(function(el) { el.classList.remove('is-invalid'); });
                if (!ok) {
                    if (!supplier.value) supplier.classList.add('is-invalid');
                    if (!itemId.value) code.classList.add('is-invalid');
                    if (!(parseFloat(qty.value) > 0)) qty.classList.add('is-invalid');
                    if (!firstBad) firstBad = row;
                    return;
                }
                lines.push({
                    supplier_name: supplier.value,
                    item_id: parseInt(itemId.value, 10),
                    quantity: parseFloat(qty.value),
                    unit_cost: parseFloat(cost.value) || 0
                });
            });
            if (firstBad || lines.length === 0) {
                e.preventDefault();
                e.stopPropagation();
                Dlg.alert('Each line needs a supplier, an item, and a quantity greater than zero.');
                return;
            }
            poLinesJson.value = JSON.stringify(lines);
        });

        // Reset the cart to a single empty line each time the modal opens.
        document.getElementById('createPurchasingPoModal').addEventListener('show.bs.modal', function() {
            poLinesBody.innerHTML = '';
            addPoLine();
        });
    }

    document.querySelectorAll('.process-order-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('procOrderId').value = this.dataset.id;
            document.getElementById('procPoId').value = this.dataset.poId || '';
            // Supplier is a dropdown now: prefill only when the row's supplier
            // matches one of the listed options, otherwise reset to the placeholder.
            var procSupplier = document.getElementById('procSupplierSelect');
            var supplier = this.dataset.supplier === 'Pending Selection' ? '' : (this.dataset.supplier || '');
            procSupplier.value = supplier;
            if (procSupplier.selectedIndex < 0) procSupplier.selectedIndex = 0;
            hideSupplierError(procSupplier);
            var qty = parseFloat(this.dataset.qty);
            document.getElementById('procQuantity').value = isNaN(qty) ? '' : qty;
            document.getElementById('procQuantityUom').textContent = this.dataset.uom || '-';
            document.getElementById('procOrderDate').value = this.dataset.orderDate || '<?= date('Y-m-d') ?>';
            document.getElementById('procExpectedDate').value = this.dataset.expected || '';
            // PO Reference input is intentionally left blank for manual entry.
            new bootstrap.Modal(document.getElementById('processOrderModal')).show();
        });
    });

    // ─── Batch Process ─────────────────────────────────────────────────
    var selectAll = document.getElementById('selectAllOrders');
    var checkboxes = document.querySelectorAll('.order-checkbox');
    var batchBtn = document.getElementById('batchProcessBtn');
    var countBadge = document.getElementById('selectedCount');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
            updateBatchButton();
        });
    }

    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', updateBatchButton);
    });

    function updateBatchButton() {
        var checked = document.querySelectorAll('.order-checkbox:checked');
        if (checked.length > 0) {
            batchBtn.classList.remove('d-none');
            countBadge.textContent = checked.length;
        } else {
            batchBtn.classList.add('d-none');
        }
    }
});

function openBatchProcessModal() {
    var checked = document.querySelectorAll('.order-checkbox:checked');
    if (checked.length === 0) {
        Dlg.alert('Please select at least one item to process.');
        return;
    }

    var groups = {};
    checked.forEach(function(cb) {
        var code = cb.dataset.itemCode;
        if (!groups[code]) {
            groups[code] = {
                code: code,
                desc: cb.dataset.itemName || cb.dataset.itemDesc || '',
                qty: 0,
                poRefs: [],
                mrpRefs: []
            };
        }
        groups[code].qty += parseFloat(cb.dataset.qty);
        if (cb.dataset.poRef) groups[code].poRefs.push(cb.dataset.poRef);
        var mrp = (cb.dataset.mrpRef || '').trim();
        if (mrp && groups[code].mrpRefs.indexOf(mrp) === -1) {
            groups[code].mrpRefs.push(mrp);
        }
    });

    // 1. Validation: ALL selected rows must be the same item code — show the
    //    offending codes in an error dialog instead of ignoring the selection.
    var keys = Object.keys(groups);
    if (keys.length > 1) {
        var codeList = document.getElementById('bulkErrorCodes');
        codeList.innerHTML = '';
        keys.forEach(function(k) {
            var code = document.createElement('code');
            code.className = 'me-1';
            code.textContent = k;
            codeList.appendChild(code);
        });
        new bootstrap.Modal(document.getElementById('bulkItemErrorModal')).show();
        return;
    }

    // 2. Open the consolidation modal with the summed quantity and linked refs.
    var g = groups[keys[0]];
    document.getElementById('batchItemCode').textContent = g.code;
    document.getElementById('batchItemDesc').textContent = g.desc;
    document.getElementById('batchTotalQty').textContent = g.qty.toFixed(4);
    document.getElementById('batchPoRefs').textContent = g.poRefs.join(', ') || '-';
    document.getElementById('batchMrpRefs').textContent = g.mrpRefs.join(', ') || '-';
    document.getElementById('batchOrderIds').value = Array.from(checked).map(function(cb) {
        return cb.dataset.reqId || cb.dataset.id;
    }).join(',');

    new bootstrap.Modal(document.getElementById('batchProcessModal')).show();
}
</script>
<?php endif; ?>

<!-- MRP Source group: View modal — every line of the run -->
<div class="modal fade" id="mrpGroupViewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-diagram-3 me-2"></i>MRP Source — <span id="mrpGroupViewRef" class="text-danger"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr class="small text-muted">
                            <th>#</th>
                            <th>Item Code</th>
                            <th>Description</th>
                            <th class="text-end">Qty</th>
                            <th>UOM</th>
                            <th>Supplier</th>
                            <th class="text-end">Unit Cost</th>
                            <th>Expected</th>
                            <th>Status</th>
                            <?php if (empty($isReadOnly) && empty($isWarehouseApprover)): ?><th style="width:110px">Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="mrpGroupViewBody"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <div class="me-auto d-flex gap-1 d-none" id="mrpGroupDecisionActions">
                    <button type="button" class="btn btn-success btn-sm text-nowrap" id="mrpGroupApproveBtn"
                            title="Approve the whole PO group and forward to System Admin">
                        <i class="bi bi-check-lg"></i> Approve All
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm text-nowrap" id="mrpGroupRejectBtn"
                            title="Reject and cancel the whole PO group">
                        <i class="bi bi-x-lg"></i> Reject
                    </button>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- MRP Source group: Process modal — every requested line in one save -->
<div class="modal fade" id="mrpGroupProcessModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <form method="POST" action="?controller=warehouse&action=processMrpGroupPo" id="mrpGroupProcessForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-gear me-2"></i>Process MRP Source — <span id="mrpGroupProcRef" class="text-danger"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        Every requested line of this source is saved together as PO drafts and routed to WHSE Admin approval, then System Admin approval.
                    </div>
                    <input type="hidden" name="mrp_run_id" id="mrpGroupProcRunId" value="">
                    <input type="hidden" name="lines" id="mrpGroupProcLines" value="[]">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Order Date</label>
                            <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Expected Date</label>
                            <input type="date" name="expected_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">PO REF (whole source)</label>
                            <input type="text" name="po_ref" class="form-control" placeholder="e.g. PO-2026-001">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-muted">
                                    <th>Item</th>
                                    <th style="width:22%">Supplier <span class="text-danger">*</span></th>
                                    <th style="width:14%">Qty <span class="text-danger">*</span></th>
                                    <th style="width:8%">UOM</th>
                                    <th style="width:14%">Unit Cost</th>
                                </tr>
                            </thead>
                            <tbody id="mrpGroupProcBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Process All Lines</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
// ─── Per-MRP-source registry: separator buttons + modal data ───────────────
// table_enhancer builds the "Source: ..." rows and looks up
// window.TABLE_GROUP_ACTIONS[sourceKey] for buttons; clicks are handled by
// delegated listeners below (survive separator rebuilds on pagination).
$mrpGroupActions = [];
$mrpSourceGroups = [];
foreach (($mrpRunLines ?? []) as $runId => $runLines) {
    if (!count($runLines)) continue;
    $mrpRef = '';
    $requestedCount = 0;
    foreach ($runLines as $l) {
        if (!empty($l['mrp_ref'])) $mrpRef = trim($l['mrp_ref']);
        if (($l['status'] ?? '') === 'requested') $requestedCount++;
    }
    if ($mrpRef === '') $mrpRef = 'MRP run #' . (int) $runId;
    $key = $mrpRef; // must match table_enhancer's groupKeyOf() extraction
    $firstLineId = (int) ($runLines[0]['supplier_order_id'] ?? 0);
    $btns = '<button type="button" class="btn btn-sm btn-outline-info mrp-group-view-btn" data-run="' . (int) $runId
        . '" title="View all items in this source"><i class="bi bi-eye"></i> View</button>';
    if (empty($isReadOnly) && $requestedCount > 0) {
        $btns .= ' <button type="button" class="btn btn-sm btn-outline-primary mrp-group-process-btn" data-run="' . (int) $runId
            . '" title="Process every requested line of this source"><i class="bi bi-gear"></i> Process (' . $requestedCount . ')</button>';
    }
    if ($firstLineId > 0) {
        $btns .= ' <a href="?controller=warehouse&action=printPurchasingPo&id=' . $firstLineId
            . '" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print the whole source"><i class="bi bi-printer"></i> Print</a>';
    }
    $mrpGroupActions[$key] = $btns;
    $mrpSourceGroups[$key] = [
        'run_id' => (int) $runId,
        'mrp_ref' => $mrpRef,
        'requested' => $requestedCount,
        'lines' => array_map(function ($l) {
            return [
                'id' => (int) $l['supplier_order_id'],
                'code' => $l['item_code'] ?? '',
                'desc' => $l['item_description'] ?? '',
                'qty' => (float) $l['quantity'],
                'uom' => $l['item_uom'] ?? '',
                'supplier' => $l['supplier_name'] ?? '',
                'cost' => (float) $l['unit_cost'],
                'expected' => $l['expected_date'] ?? '',
                'status' => $l['status'] ?? '',
            ];
        }, $runLines),
    ];
}
$mrpProcSupplierOptionsHtml = '<option value="" selected disabled>-- Select Supplier --</option>';
foreach (($suppliers ?? []) as $s) {
    $mrpProcSupplierOptionsHtml .= '<option value="' . htmlspecialchars($s['name']) . '">' . htmlspecialchars($s['name']) . '</option>';
}
?>
<script>
window.TABLE_GROUP_ACTIONS = <?= json_encode($mrpGroupActions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.MRP_SOURCE_GROUPS = <?= json_encode($mrpSourceGroups, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.MRP_PROC_SUPPLIER_OPTIONS = <?= json_encode($mrpProcSupplierOptionsHtml, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
// Modal row actions: Edit (status-gated, never Delete) is for procurement
// only — WHSE admins approve/reject via the footer, readonly viewers get a
// read-only modal.
window.WH_PO_ROW_ACTIONS = <?= (empty($isReadOnly) && empty($isWarehouseApprover)) ? 'true' : 'false' ?>;
// Approve/Reject inside the source View modal: WHSE approvers only
// (whse_procurement may Edit but must not approve).
window.WH_PO_CAN_DECIDE = <?= (!empty($isWarehouseApprover) && empty($isReadOnly)) ? 'true' : 'false' ?>;

(function() {
    var STATUS_LABELS = {
        'requested': 'Requested',
        'pending_whse_approval': 'Pending WHSE Approval',
        'pending_sysadmin_approval': 'Pending SysAdmin Approval',
        'pending_qc_approval': 'Pending QC Approval',
        'pending_admin_approval': 'Pending Admin Approval',
        'pending': 'Active',
        'processed': 'Processed',
        'partially_received': 'Partially Received',
        'For Inspection': 'For Inspection',
        'received': 'Received',
        'cancelled': 'Cancelled',
        'rejected': 'Rejected',
        'completed': 'Completed'
    };

    function groupByRun(runId) {
        var groups = window.MRP_SOURCE_GROUPS || {};
        var keys = Object.keys(groups);
        for (var i = 0; i < keys.length; i++) {
            if (groups[keys[i]].run_id === runId) return groups[keys[i]];
        }
        return null;
    }

    function fmtDate(d) {
        if (!d) return '-';
        var p = d.split('-');
        return p.length === 3 ? (p[1] + '/' + p[2] + '/' + p[0]) : d;
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    // Delegated so clicks work on separator rows rebuilt by pagination.
    // mrpGroupDecision: context for the modal's Approve/Reject buttons
    // (first line of the source still at the WHSE PO gate, if any).
    var mrpGroupDecision = null;
    document.addEventListener('click', function(e) {
        var v = e.target.closest('.mrp-group-view-btn');
        if (v) {
            var g = groupByRun(parseInt(v.dataset.run, 10));
            if (!g) return;
            document.getElementById('mrpGroupViewRef').textContent = g.mrp_ref;
            var body = document.getElementById('mrpGroupViewBody');
            body.innerHTML = '';
            g.lines.forEach(function(l, i) {
                var tr = document.createElement('tr');
                var actions = '';
                if (window.WH_PO_ROW_ACTIONS) {
                    // Procurement-only cell: Edit (no Delete — removed) and
                    // only while the PO is NOT yet approved by BOTH gates:
                    // editable through pending_admin_approval (System Admin
                    // still pending); locked from 'pending' (Active) onward.
                    var editableWhileApproving = l.status === 'requested'
                        || l.status === 'pending_whse_approval'
                        || l.status === 'pending_admin_approval';
                    actions = editableWhileApproving
                        ? '<td class="text-nowrap">' +
                            '<button type="button" class="btn btn-sm btn-outline-secondary btn-edit-po" data-id="' + l.id + '" title="Edit this line">' +
                                '<i class="bi bi-pencil"></i> Edit' +
                            '</button>' +
                          '</td>'
                        : '<td class="text-muted">-</td>';
                }
                tr.innerHTML = '<td>' + (i + 1) + '</td>' +
                    '<td><code>' + esc(l.code) + '</code></td>' +
                    '<td>' + esc(l.desc) + '</td>' +
                    '<td class="text-end">' + l.qty + '</td>' +
                    '<td>' + esc(l.uom) + '</td>' +
                    '<td>' + esc(l.supplier) + '</td>' +
                    '<td class="text-end">' + Number(l.cost).toFixed(2) + '</td>' +
                    '<td>' + fmtDate(l.expected) + '</td>' +
                    '<td><span class="badge bg-secondary">' + esc(STATUS_LABELS[l.status] || l.status) + '</span></td>' +
                    actions;
                body.appendChild(tr);
            });
            // Approve/Reject live in this modal (WHSE PO gate): shown only when
            // the source still has lines awaiting WHSE approval AND the viewer
            // is a WHSE approver — otherwise the modal is view-only.
            var pendingWhse = g.lines.filter(function(l) { return l.status === 'pending_whse_approval'; });
            mrpGroupDecision = (pendingWhse.length && window.WH_PO_CAN_DECIDE)
                ? { id: pendingWhse[0].id }
                : null;
            document.getElementById('mrpGroupDecisionActions').classList.toggle('d-none', !mrpGroupDecision);
            new bootstrap.Modal(document.getElementById('mrpGroupViewModal')).show();
            return;
        }
        var p = e.target.closest('.mrp-group-process-btn');
        if (p) {
            var gp = groupByRun(parseInt(p.dataset.run, 10));
            if (!gp) return;
            document.getElementById('mrpGroupProcRef').textContent = gp.mrp_ref;
            document.getElementById('mrpGroupProcRunId').value = gp.run_id;
            var pbody = document.getElementById('mrpGroupProcBody');
            pbody.innerHTML = '';
            gp.lines.filter(function(l) { return l.status === 'requested'; }).forEach(function(l) {
                var tr = document.createElement('tr');
                tr.className = 'mrp-proc-row';
                tr.innerHTML =
                    '<td>' +
                        '<input type="hidden" class="mrp-proc-id" value="' + l.id + '">' +
                        '<code>' + esc(l.code) + '</code> ' + esc(l.desc) +
                    '</td>' +
                    '<td><select class="form-select form-select-sm supplier-select mrp-proc-supplier" required>' + window.MRP_PROC_SUPPLIER_OPTIONS + '</select></td>' +
                    '<td><input type="number" class="form-control form-control-sm mrp-proc-qty" min="0.01" step="0.0001" value="' + l.qty + '" required></td>' +
                    '<td class="text-nowrap">' + esc(l.uom || '-') + '</td>' +
                    '<td><input type="number" class="form-control form-control-sm mrp-proc-cost" min="0" step="0.01" value="' + Number(l.cost).toFixed(2) + '"></td>';
                pbody.appendChild(tr);
            });
            new bootstrap.Modal(document.getElementById('mrpGroupProcessModal')).show();
            return;
        }
    });

    // Source View modal decisions (WHSE PO gate): reuse the row-era endpoints —
    // submitManualPoDecision (defined in the approver script block below,
    // rendered only for approvers, matching WH_PO_CAN_DECIDE) confirms/
    // prompts and POSTs approveManualPo / rejectManualPo for the whole group.
    document.getElementById('mrpGroupApproveBtn').addEventListener('click', function() {
        if (!mrpGroupDecision) return;
        submitManualPoDecision(mrpGroupDecision.id, true);
    });
    document.getElementById('mrpGroupRejectBtn').addEventListener('click', function() {
        if (!mrpGroupDecision) return;
        submitManualPoDecision(mrpGroupDecision.id, false);
    });

    // Stacked-modal safety: fully close the group View modal before the shared
    // edit modal opens (Bootstrap backdrops fight when two modals toggle at
    // once). Capture-phase guard blocks the shared document handler for the
    // original click, then re-dispatches via a proxy button after the group
    // modal has finished hiding.
    document.getElementById('mrpGroupViewModal').addEventListener('click', function(e) {
        var btn = e.target.closest ? e.target.closest('.btn-edit-po') : null;
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var recordId = btn.getAttribute('data-id');
        var groupModal = bootstrap.Modal.getInstance(this);
        var openEdit = function() {
            var proxy = document.createElement('button');
            proxy.className = 'btn-edit-po';
            proxy.setAttribute('data-id', recordId);
            proxy.style.display = 'none';
            document.body.appendChild(proxy);
            proxy.click();
            proxy.remove();
        };
        if (groupModal) {
            var modalEl = this;
            modalEl.addEventListener('hidden.bs.modal', function onH() {
                modalEl.removeEventListener('hidden.bs.modal', onH);
                openEdit();
            });
            groupModal.hide();
        } else {
            openEdit();
        }
    }, true);

    // Serialize process lines to JSON; validate every line first.
    document.getElementById('mrpGroupProcessForm').addEventListener('submit', function(e) {
        var rows = document.querySelectorAll('#mrpGroupProcBody .mrp-proc-row');
        var lines = [];
        var firstBad = null;
        rows.forEach(function(row) {
            var id = row.querySelector('.mrp-proc-id');
            var supplier = row.querySelector('.mrp-proc-supplier');
            var qty = row.querySelector('.mrp-proc-qty');
            var cost = row.querySelector('.mrp-proc-cost');
            var ok = supplier.value && parseFloat(qty.value) > 0;
            [supplier, qty].forEach(function(el) { el.classList.remove('is-invalid'); });
            if (!ok) {
                if (!supplier.value) supplier.classList.add('is-invalid');
                if (!(parseFloat(qty.value) > 0)) qty.classList.add('is-invalid');
                if (!firstBad) firstBad = row;
                return;
            }
            lines.push({
                supplier_order_id: parseInt(id.value, 10),
                supplier_name: supplier.value,
                quantity: parseFloat(qty.value),
                unit_cost: parseFloat(cost.value) || 0
            });
        });
        if (firstBad || lines.length === 0) {
            e.preventDefault();
            e.stopPropagation();
            Dlg.alert('Every line needs a supplier and a quantity greater than zero.');
            return;
        }
        document.getElementById('mrpGroupProcLines').value = JSON.stringify(lines);
    });
})();
</script>

<?php if (!$isReadOnly && !empty($isWarehouseApprover)): ?>
<!-- PO group decision (WHSE gate): hidden POST form driven by row buttons -->
<form id="mrpApprovalForm" method="POST" style="display:none">
    <input type="hidden" name="supplier_order_id" id="mrpApprovalId">
    <input type="hidden" name="reject_reason" id="mrpRejectReason">
</form>
<script>
// PO group: approve/reject acts on EVERY line of the group.
// (MRP run decisions live on the MRP Approval page — not here.)
function submitManualPoDecision(id, approve) {
    var form = document.getElementById('mrpApprovalForm');
    // Copy the target line id into the hidden form — without this the POST
    // went out with an empty supplier_order_id and the server rejected it
    // with "Invalid PO."
    document.getElementById('mrpApprovalId').value = id;
    if (approve) {
        Dlg.confirm('Approve this PO group (every line) and forward it to System Admin approval?').then(function(ok) {
            if (!ok) return;
            form.action = '?controller=warehouse&action=approveManualPo';
            form.submit();
        });
        return;
    }
    Dlg.prompt('Reason for rejecting this PO group (required):', { requiredMessage: 'A rejection reason is required.' }).then(function(reason) {
        if (reason === null || reason === '') return;
        document.getElementById('mrpRejectReason').value = reason;
        form.action = '?controller=warehouse&action=rejectManualPo';
        form.submit();
    });
}
</script>
<?php endif; ?>
<?php $poEditContext = 'purchasing'; require BASE_PATH . 'app/views/shared/po_view_modal.php'; ?>
<?php $poEditContext = 'purchasing'; require BASE_PATH . 'app/views/shared/po_edit_modal.php'; ?><?php require BASE_PATH . 'app/views/shared/table_enhancer.php'; ?>
