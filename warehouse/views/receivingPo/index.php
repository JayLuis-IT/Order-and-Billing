<?php $isReadOnly = !empty($readOnly) || (($_SESSION['department'] ?? '') !== 'warehouse'); ?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-box-arrow-in-down me-2"></i>Receiving Purchasing PO</h4>
    <?php if (!$isReadOnly): ?>
    <div class="d-flex gap-2 flex-wrap">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="controller" value="warehouse">
            <input type="hidden" name="action" value="receivingPo">
            <select name="status" class="form-select form-select-sm filter-select" style="width:180px">
                <option value="">Any status (per tab)</option>
                <option value="all" <?= ($filters['status'] ?? '') === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="processed" <?= ($filters['status'] ?? '') === 'processed' ? 'selected' : '' ?>>Processed</option>
                <option value="partially_received" <?= ($filters['status'] ?? '') === 'partially_received' ? 'selected' : '' ?>>Partially Received</option>
                <option value="For Inspection" <?= ($filters['status'] ?? '') === 'For Inspection' ? 'selected' : '' ?>>For Inspection</option>
                <option value="received" <?= ($filters['status'] ?? '') === 'received' ? 'selected' : '' ?>>Received</option>
                <option value="rejected" <?= ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <select name="supplier" class="form-select form-select-sm filter-select" style="width:200px">
                <option value="">All Suppliers</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= ($filters['supplier'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search PO Ref, Item..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="width:220px">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
            <a href="?controller=warehouse&action=receivingPo" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class="card data-card">
    <?php
    // Status tabs: in-transit vs quarantine (For QC) vs approved receipts.
    $tabCounts = $tabCounts ?? ['pending' => 0, 'inspection' => 0, 'completed' => 0];
    // Receiving has no "All Shipments" view â€” only actionable pipeline tabs,
    // with In-Transit as the landing tab.
    $activeTab = $filters['tab'] ?: 'pending';
    $recvTabs = [
        'pending'    => 'In-Transit',
        'inspection' => 'For QC Inspection',
        'completed'  => 'Completed / Approved',
    ];
    ?>
    <div class="card-body pb-0">
        <ul class="nav nav-pills flex-wrap gap-2" id="receivingTabs">
            <?php foreach ($recvTabs as $tabKey => $tabLabel):
                $tabHref = '?controller=warehouse&action=receivingPo&tab=' . rawurlencode($tabKey);
                if (!empty($filters['search'])) {
                    $tabHref .= '&search=' . rawurlencode($filters['search']);
                }
                if (!empty($filters['supplier'])) {
                    $tabHref .= '&supplier=' . rawurlencode($filters['supplier']);
                }
                $tabCount = $tabCounts[$tabKey] ?? 0;
            ?>
            <li class="nav-item">
                <a class="nav-link px-3 py-1 border rounded-pill <?= $activeTab === $tabKey ? 'active' : '' ?>"
                   href="<?= $tabHref ?>">
                    <?= $tabLabel ?> <span class="badge <?= $activeTab === $tabKey ? 'bg-white text-primary' : 'bg-secondary' ?>"><?= (int) $tabCount ?></span>
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
                    <th>PO / MRP REF</th>
                    <th>Supplier</th>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th class="text-end">Qty Ordered</th>
                    <th class="text-end">Received Qty</th>
                    <th>Received Date</th>
                    <th>Received By</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="11" class="text-center text-muted py-4">No purchasing POs ready for receiving.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($orders as $index => $o): ?>
                <?php
                    $status = strtolower(trim((string) ($o['status'] ?? '')));
                    $remainingQty = floatval($o['quantity']) - floatval($o['received_qty'] ?? 0);
                    $hasPoRef = !empty($o['po_ref_display']) && $o['po_ref_display'] !== '-';
                    $mrpRef = (string) ($o['mrp_ref'] ?? '');
                    if ($mrpRef === '' && !empty($o['mrp_run_id'])) {
                        $mrpRef = 'MRP-#' . $o['mrp_run_id'];
                    }
                ?>
                <tr>
                    <td title="Order #<?= $o['supplier_order_id'] ?>"><?= $index + 1 ?></td>
                    <td>
                        <?php if ($hasPoRef): ?>
                            <?= htmlspecialchars($o['po_ref_display']) ?>
                        <?php endif; ?>
                        <?php if ($mrpRef !== ''): ?>
                            <div class="<?= $hasPoRef ? 'mt-1' : '' ?>"><span class="badge bg-secondary"><?= htmlspecialchars($mrpRef) ?></span></div>
                        <?php elseif (!$hasPoRef): ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($o['supplier_name']) ?></td>
                    <td><code><?= htmlspecialchars($o['item_code']) ?></code></td>
                    <td><?= htmlspecialchars($o['item_description']) ?></td>
                    <td class="text-end"><?= formatQty($o['quantity']) ?> <?= htmlspecialchars($o['item_uom'] ?? '') ?></td>
                    <td class="text-end"><?= $o['received_qty'] > 0
                        ? formatQty($o['received_qty']) . ' ' . htmlspecialchars($o['item_uom'] ?? '')
                        : '<span class="text-muted">-</span>' ?></td>
                    <td><?php
                        $hasDate = !empty($o['received_date']) && $o['received_date'] !== '0000-00-00' && $o['received_date'] !== '0000-00-00 00:00:00';
                        echo $hasDate ? date('m/d/Y', strtotime($o['received_date'])) : '-';
                    ?></td>
                    <td><?= !empty($o['received_by_name']) ? htmlspecialchars($o['received_by_name']) : '<span class="text-muted">-</span>' ?></td>
                    <td>
                        <?php if ($status === 'requested'): ?>
<span class="badge bg-info text-dark">Requested</span>
                        <?php elseif ($status === 'pending'): ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php elseif ($status === 'processed'): ?>
                            <span class="badge bg-primary">Processed</span>
                        <?php elseif ($status === 'partially_received'): ?>
                            <span class="badge bg-secondary">Partially Received</span>
                        <?php elseif ($status === 'for inspection'): ?>
                            <span class="badge text-white" style="background:#6f42c1">For Inspection</span>
                        <?php elseif (($o['overage_request_status'] ?? '') === 'approved'): ?>
                            <span class="badge bg-success">In-Transit (+<?= formatQty($o['overage_approved_qty'] ?? 0) ?> overage approved)</span>
                        <?php elseif (($o['overage_request_status'] ?? '') === 'pending'): ?>
                            <span class="badge bg-warning text-dark">Overage Awaiting Admin Approval</span>
                        <?php elseif ($status === 'received'): ?>
                            <span class="badge bg-success">Received & Approved</span>
                        <?php elseif ($status === 'rejected'): ?>
                            <span class="badge bg-danger">Rejected</span>
                        <?php elseif ($status === 'cancelled'): ?>
                            <span class="badge bg-dark">Cancelled</span>
                        <?php elseif ($status === 'po_created'): ?>
                            <span class="badge bg-light text-dark border">PO Created (consolidated)</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst((string) ($o['status'] ?? 'Unknown'))) ?: 'Unknown' ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $isQcUser = (($_SESSION['department'] ?? '') === 'qc'); ?>
                        <?php
                        // Completed tab rows (received / approved / rejected / cancelled)
                        // get a read-only View Details modal.
                        $isCompletedShipment = in_array($status, ['received', 'approved', 'rejected', 'cancelled']);
                        ?>
                        <?php if ($isReadOnly): ?>
                            <?php if ($status === 'for inspection' && $isQcUser): ?>
                                <a class="btn btn-sm btn-primary" href="?controller=qc&action=receivingInspection" title="Open the QC inspection queue">
                                    <i class="bi bi-clipboard-check"></i> Process Inspection
                                </a>
                            <?php elseif ($isCompletedShipment): ?>
                                <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        <?php elseif (in_array($status, ['pending', 'processed', 'partially_received', 'for inspection']) && $remainingQty > 0.0001): ?>
                            <?php
                            // The same order row can render in both tabs (In-Transit for the
                            // receivable balance, For QC Inspection for the staged batch).
                            // The "In QC Queue" hint only adds information on the In-Transit
                            // tab; on For QC Inspection it duplicates the status badge.
                            $isInspectionTab = (($filters['tab'] ?? '') === 'inspection');
                            $showQcQueueUi = !$isInspectionTab && !empty($o['has_pending_qc']);
                            $showEditStaging = $isInspectionTab && !empty($o['has_pending_qc']);
                            ?>
                            <?php if ($showQcQueueUi): ?>
                                <span class="badge text-white" style="background:#6f42c1">In QC Queue</span>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-info btn-view-po <?= $showQcQueueUi ? 'd-block mt-1' : '' ?>" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <?php if ($showEditStaging): ?>
                                <button class="btn btn-sm btn-outline-secondary btn-edit-staging d-block mt-1" data-id="<?= $o['supplier_order_id'] ?>" title="Edit staged receipt (qty, lot, expiry, remarks)">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                            <?php endif; ?>
                            <?php if (!$isInspectionTab): ?>
                            <button class="btn btn-sm btn-success <?= $showQcQueueUi ? 'd-block mt-1' : '' ?> receive-shipment-btn"
                                    data-id="<?= $o['supplier_order_id'] ?>"
                                    data-supplier="<?= htmlspecialchars($o['supplier_name']) ?>"
                                    data-item-code="<?= htmlspecialchars($o['item_code']) ?>"
                                    data-item-desc="<?= htmlspecialchars($o['item_description']) ?>"
                                    data-item-type="<?= htmlspecialchars($o['item_type'] ?? '') ?>"
                                    data-ordered-qty="<?= $o['quantity'] ?>"
                                    data-received-qty="<?= $o['received_qty'] ?? 0 ?>"
                                    data-uom="<?= htmlspecialchars($o['item_uom']) ?>"
                                    data-po-ref="<?= htmlspecialchars($o['po_ref_display']) ?>"
                                    title="Receive and stage for QC inspection">
                                <i class="bi bi-box-arrow-in-down"></i> Receive &amp; Send to QC
                            </button>
                            <?php endif; ?>
                        <?php elseif ($status === 'requested'): ?>
                            <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <span class="badge bg-secondary">Awaiting Purchasing</span>
                        <?php elseif ($status === 'for inspection'): ?>
                            <?php if ($isQcUser): ?>
                                <a class="btn btn-sm btn-primary" href="?controller=qc&action=receivingInspection" title="Open the QC inspection queue">
                                    <i class="bi bi-clipboard-check"></i> Process Inspection
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <button class="btn btn-sm btn-outline-secondary btn-edit-staging d-block mt-1" data-id="<?= $o['supplier_order_id'] ?>" title="Edit staged receipt (qty, lot, expiry, remarks)">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                            <?php endif; ?>
                        <?php elseif (in_array($status, ['approved', 'received'])): ?>
                            <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                <i class="bi bi-eye"></i> View
                            </button>
                        <?php elseif ($status === 'rejected'): ?>
                            <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                <i class="bi bi-eye"></i> View
                            </button>
                        <?php else: ?>
                            <?php if ($isCompletedShipment): ?>
                                <button class="btn btn-sm btn-outline-info btn-view-po" data-id="<?= $o['supplier_order_id'] ?>" title="View Details">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            <?php else: ?>
                                <span class="text-muted">-</span>
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
<!-- Receive Shipment Modal -->
<div class="modal fade" id="receiveShipmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?controller=warehouse&action=receivePurchasingPo" id="receiveShipmentForm"
                  onsubmit="return submitReceiveFormOnce(this);">
                <input type="hidden" name="supplier_order_id" id="recvOrderId">
                <input type="hidden" name="receipt_token" id="recvReceiptToken">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-arrow-in-down me-2"></i>Receive Shipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>PO Ref:</strong> <span id="recvPoRef"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Supplier:</strong> <span id="recvSupplier"></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Item:</strong> <code id="recvItemCode"></code> - <span id="recvItemDesc"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>UOM:</strong> <span id="recvUom"></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Ordered Qty:</strong> <span id="recvOrderedQty"></span>
                        </div>
                        <div class="col-md-4">
                            <strong>Already Received:</strong> <span id="recvAlreadyReceived"></span>
                        </div>
                        <div class="col-md-4">
                            <strong>Remaining:</strong> <span id="recvRemaining" class="fw-bold text-primary"></span>
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Received Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="received_qty" id="recvReceivedQty" class="form-control" min="0.0001" step="0.0001" required>
                        <small class="text-muted" id="recvQtyHint">Enter the quantity actually received (may differ from ordered).</small>
                    </div>
                    <div class="row mb-3" id="recvLotBatchRow">
                        <div class="col-md-6" id="recvLotBatchContainer">
                            <label class="form-label fw-bold">Lot / Batch Number <span class="text-danger" id="recvLotRequiredMark">*</span></label>
                            <input type="text" name="lot_number" id="recvLotNumber" class="form-control" placeholder="Required">
                        </div>
                        <div class="col-md-6" id="recvExpiryContainer">
                            <label class="form-label fw-bold">Expiry Date</label>
                            <input type="date" name="expiry_date" id="recvExpiryDate" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Received Date <span class="text-danger">*</span></label>
                        <input type="date" name="received_date" id="recvReceivedDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Received By</label>
                        <input type="text" id="recvReceivedBy" class="form-control" readonly
                               value="<?= htmlspecialchars($_SESSION['full_name'] ?? ($_SESSION['username'] ?? '')) ?>">
                        <small class="text-muted">The logged-in account recorded for this receipt.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Remarks</label>
                        <textarea name="remarks" id="recvRemarks" class="form-control" rows="2" placeholder="Any notes about this receipt..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Confirm Receipt</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function submitReceiveFormOnce(form) {
    // Prevent accidental double-clicks from submitting the form twice.
    var submitBtn = form.querySelector('button[type=submit]');
    if (submitBtn) {
        if (submitBtn.disabled) return false;
        submitBtn.disabled = true;
    }
    return true;
}

function fmtQty(n) {
    // floatval()-equivalent: no trailing zeros (750.0000 -> 750, 2.5050 -> 2.505)
    return String(parseFloat(Number(n).toFixed(4)));
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.receive-shipment-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var ordered = parseFloat(this.dataset.orderedQty) || 0;
            var received = parseFloat(this.dataset.receivedQty) || 0;
            var remaining = ordered - received;

            document.getElementById('recvOrderId').value = this.dataset.id;
            // Fresh one-shot token per modal open: the backend uses it to tell a
            // genuine new batch apart from a double-click / back-resubmit.
            document.getElementById('recvReceiptToken').value =
                (window.crypto && window.crypto.randomUUID)
                    ? window.crypto.randomUUID()
                    : Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
            document.getElementById('recvPoRef').textContent = this.dataset.poRef;
            document.getElementById('recvSupplier').textContent = this.dataset.supplier;
            document.getElementById('recvItemCode').textContent = this.dataset.itemCode;
            document.getElementById('recvItemDesc').textContent = this.dataset.itemDesc;
            document.getElementById('recvUom').textContent = this.dataset.uom;
            document.getElementById('recvOrderedQty').textContent = fmtQty(ordered);
            document.getElementById('recvAlreadyReceived').textContent = fmtQty(received);
            document.getElementById('recvRemaining').textContent = fmtQty(remaining);
            document.getElementById('recvReceivedQty').value = fmtQty(remaining);
            // The remaining balance pre-fills but is NOT clamped: an over-receipt
            // attempt is blocked server-side and becomes an approval request for
            // the System Admin (see the In-Transit overage badges).
            document.getElementById('recvReceivedQty').removeAttribute('max');
            document.getElementById('recvQtyHint').innerHTML =
                'Prefill = remaining balance (' + fmtQty(remaining) + '). Exact / partial receipts go straight to QC. '
                + 'Quantities ABOVE the ordered total require System Admin overage approval before the shipment can reach QC.';

            // Lot / Batch + Expiry are required for RM, FG and SFG; hidden for PM / SUPPLIES.
            var itemType = (this.dataset.itemType || '').toUpperCase();
            var requiresLot = ['RM', 'FG', 'SFG'].indexOf(itemType) !== -1;
            document.getElementById('recvLotBatchContainer').style.display = requiresLot ? '' : 'none';
            document.getElementById('recvExpiryContainer').style.display = requiresLot ? '' : 'none';
            var lotInput = document.getElementById('recvLotNumber');
            var lotMark = document.getElementById('recvLotRequiredMark');
            lotInput.required = requiresLot;
            lotMark.style.display = requiresLot ? '' : 'none';
            lotInput.value = '';
            document.getElementById('recvExpiryDate').value = '';
            if (!requiresLot) {
                lotInput.placeholder = '';
            } else {
                lotInput.placeholder = 'Required';
            }

            document.getElementById('recvReceivedDate').value = '<?= date('Y-m-d') ?>';
            document.getElementById('recvRemarks').value = '';

            new bootstrap.Modal(document.getElementById('receiveShipmentModal')).show();
        });
    });

    // Reset form on modal close
    document.getElementById('receiveShipmentModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('receiveShipmentForm').reset();
    });
});
</script>
<?php endif; ?>
<?php $poEditContext = 'receiving'; require BASE_PATH . 'app/views/shared/po_view_modal.php'; ?>
<?php $poEditContext = 'receiving'; require BASE_PATH . 'app/views/shared/po_edit_modal.php'; ?>
<?php require BASE_PATH . 'app/views/shared/staging_edit_modal.php'; ?><?php require BASE_PATH . 'app/views/shared/table_enhancer.php'; ?>
