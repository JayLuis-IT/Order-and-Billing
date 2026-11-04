<?php if (!$delivery): ?>
<div class="alert alert-danger">Delivery not found.</div>
<?php else: ?>

<div class="mb-3">
    <a href="?controller=finance&action=deliveries" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Sales Invoices
    </a>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card data-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-receipt me-2"></i>Sales Invoice Information</span>
                <?php if (!empty($delivery['si_number'])): ?>
                    <span class="badge bg-success fs-6 me-2"><?= htmlspecialchars($delivery['si_number']) ?></span>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="openEditSIModal()" title="Edit SI Number">
                        <i class="bi bi-pencil"></i>
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>PO Number:</strong> <?= htmlspecialchars($delivery['customer_po_number'] ?? '-') ?></p>
                        <p><strong>Customer:</strong> <?= htmlspecialchars($delivery['customer_name'] ?? '-') ?></p>
                        <p><strong>Customer Code:</strong> <?= htmlspecialchars($delivery['customer_code'] ?? '-') ?></p>
                        <p><strong>Delivery Address:</strong> <?= htmlspecialchars($delivery['customer_address'] ?? '-') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Delivery Date:</strong> <?= date('F d, Y', strtotime($delivery['delivery_date'])) ?></p>
                        <p><strong>DR No.:</strong> <?= htmlspecialchars($delivery['dr_number'] ?? '-') ?></p>
                        <p><strong>Delivered By:</strong> <?= htmlspecialchars($delivery['delivered_by_name'] ?? '-') ?></p>
                        <p><strong>Remarks:</strong> <?= htmlspecialchars($delivery['remarks'] ?? 'None') ?></p>
                        <p><strong>Report / Edit:</strong> <?= htmlspecialchars($delivery['report_remarks'] ?? 'None') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($delivery['si_number'])): ?>
        <div class="modal fade" id="siModal" tabindex="-1" aria-labelledby="siModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="siModalLabel"><i class="bi bi-pencil-square me-2"></i>Set Sales Invoice Number</h5>
                        <button type="button" class="btn-close btn-close-white" aria-label="Close" onclick="window.location.href='?controller=finance&action=deliveries'"></button>
                    </div>
                    <form method="POST" action="?controller=finance&action=saveSINumber">
                        <div class="modal-body">
                            <input type="hidden" name="delivery_id" value="<?= $delivery['delivery_id'] ?>">
                            <div class="mb-3">
                                <label class="form-label fw-bold">SI Number</label>
                                <input type="text" name="si_number" class="form-control form-control-lg" placeholder="e.g. SI-00001" required autofocus>
                                <div class="form-text">You must set an SI number before viewing this sales invoice.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check-circle me-1"></i>Save & Continue
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($delivery['si_number'])): ?>
        <div class="modal fade" id="editSIModal" tabindex="-1" aria-labelledby="editSIModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title" id="editSIModalLabel"><i class="bi bi-pencil-square me-2"></i>Edit Sales Invoice Number</h5>
                        <button type="button" class="btn-close" aria-label="Close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="?controller=finance&action=saveSINumber" id="editSIForm">
                        <div class="modal-body">
                            <input type="hidden" name="delivery_id" value="<?= $delivery['delivery_id'] ?>">
                            <div class="mb-3">
                                <label class="form-label fw-bold">SI Number</label>
                                <input type="text" name="si_number" id="editSINumberInput" class="form-control form-control-lg" placeholder="e.g. SI-00001" required>
                                <div class="form-text">Update the SI number for this delivery. This will reflect across all modules.</div>
                            </div>
                            <div class="alert alert-warning py-2 mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                <strong>Warning:</strong> Changing the SI number will update it everywhere (Finance, Admin, Warehouse reports).
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-check-circle me-1"></i>Update SI Number
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card data-card mb-4">
            <div class="card-header">
                <i class="bi bi-box me-2"></i>Delivered Items
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Description</th>
                            <th>Lot Number</th>
                            <th>UOM</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Cases</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $lotItems = json_decode($delivery['lot_items'] ?? '[]', true);
                        $hasLotItems = is_array($lotItems) && count($lotItems) > 0;
                        $totalQty = 0;
                        $totalCases = 0;
                        if ($hasLotItems):
                            foreach ($lotItems as $li):
                                $liQty = $li['qty'] ?? 0;
                                $conv = $li['actual_uom_conversion'] ?? $li['uom_conversion'] ?? null;
                                $uom = $li['item_uom'] ?? '';
                                $cases = ($conv && $uom !== 'CS') ? floor($liQty / $conv) : 0;
                                $totalQty += $liQty;
                                $totalCases += $cases;
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($li['item_code'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars($li['item_description'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($li['lot_number'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($uom) ?></td>
                            <td class="text-end"><?= number_format($liQty) ?></td>
                            <td class="text-end"><?= $cases > 0 ? $cases . ' CS' : '---' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="4" class="text-end">Total:</td>
                            <td class="text-end"><?= number_format($totalQty) ?></td>
                            <td class="text-end"><?= $totalCases > 0 ? $totalCases . ' CS' : '---' ?></td>
                        </tr>
                        <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No lot items found</td></tr>
<?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="col-md-4">
        <div class="card data-card mb-4">
            <div class="card-body">
                <a href="?controller=finance&action=printSalesInvoice&id=<?= $delivery['delivery_id'] ?>" target="_blank" class="btn btn-primary w-100 mb-2">
                    <i class="bi bi-printer me-2"></i>Print Sales Invoice (SB)
                </a>
                <a href="?controller=finance&action=printSalesInvoiceWD&id=<?= $delivery['delivery_id'] ?>" target="_blank" class="btn btn-success w-100">
                    <i class="bi bi-printer me-2"></i>Print Sales Invoice (WD)
                </a>
            </div>
        </div>

        <div class="card data-card mb-4">
            <div class="card-header">
                <i class="bi bi-paperclip me-2"></i>Delivery Receipt Attachments
            </div>
            <div class="card-body">
                <?php if (!empty($dr_receipts)): ?>
                    <?php foreach ($dr_receipts as $r): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <i class="bi bi-file-earmark me-2"></i>
                            <strong><?= htmlspecialchars($r['file_name']) ?></strong>
                            <br>
                            <small class="text-muted">
                                <?= number_format($r['file_size'] / 1024, 1) ?> KB &middot; 
                                <?= date('M d, Y', strtotime($r['date_created'])) ?>
                                <?php if (!empty($r['uploaded_by_name'])): ?>
                                    &middot; by <?= htmlspecialchars($r['uploaded_by_name']) ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <a href="<?= URL_ROOT . $r['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="View">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-muted py-3">No DR attachments uploaded by warehouse</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card data-card mb-4">
            <div class="card-header">
                <i class="bi bi-receipt me-2"></i>Sales Invoice Attachments
            </div>
            <div class="card-body">
                <?php if (!empty($si_receipts)): ?>
                    <?php foreach ($si_receipts as $r): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <i class="bi bi-file-earmark me-2"></i>
                            <strong><?= htmlspecialchars($r['file_name']) ?></strong>
                            <br>
                            <small class="text-muted">
                                <?= number_format($r['file_size'] / 1024, 1) ?> KB &middot; 
                                <?= date('M d, Y', strtotime($r['date_created'])) ?>
                            </small>
                        </div>
                        <div class="btn-group">
                            <a href="<?= URL_ROOT . $r['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="?controller=finance&action=deleteReceipt&id=<?= $r['receipt_id'] ?>&delivery_id=<?= $delivery['delivery_id'] ?>" 
                               class="btn btn-sm btn-outline-danger" title="Delete"
                               data-confirm="Are you sure you want to delete this SI attachment?">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-muted py-3">No SI attachments yet</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card data-card">
            <div class="card-header">
                <i class="bi bi-upload me-2"></i>Attach Sales Invoice
            </div>
            <div class="card-body">
                <form method="POST" action="?controller=finance&action=uploadReceipt" enctype="multipart/form-data">
                    <input type="hidden" name="delivery_id" value="<?= $delivery['delivery_id'] ?>">
                    <input type="hidden" name="po_id" value="<?= $delivery['po_id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Select File</label>
                        <input type="file" name="receipt_file" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" required>
                        <div class="form-text">Allowed: JPG, PNG, GIF, WebP, PDF (max 10MB)</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-upload me-2"></i>Upload SI Attachment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php if (empty($delivery['si_number'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('siModal'));
    modal.show();
});
</script>
<?php endif; ?>

<?php if (!empty($delivery['si_number'])): ?>
<script>
function openEditSIModal() {
    var currentSI = '<?= addslashes($delivery['si_number']) ?>';
    document.getElementById('editSINumberInput').value = currentSI;
    var modal = new bootstrap.Modal(document.getElementById('editSIModal'));
    modal.show();
}

// Async SI-change confirmation: confirm (SweetAlert) only when the number
// actually changed; unchanged submits go through directly.
var siForm = document.getElementById('editSIForm');
if (siForm) {
    var siConfirmed = false;
    siForm.addEventListener('submit', function(e) {
        if (siConfirmed) return;                       // second pass after confirm
        var newSI = document.getElementById('editSINumberInput').value.trim();
        var currentSI = '<?= addslashes($delivery['si_number']) ?>';
        if (newSI === currentSI) return;               // unchanged — submit as-is
        e.preventDefault();
        Dlg.confirm('Are you sure you want to change the SI number from "' + currentSI + '" to "' + newSI + '"?\n\nThis change will reflect across Finance, Admin, and Warehouse modules.').then(function(ok) {
            if (!ok) return;
            siConfirmed = true;
            siForm.requestSubmit();
        });
    });
}
</script>
<?php endif; ?>
