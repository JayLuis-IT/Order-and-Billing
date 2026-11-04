<style>
    .lmr-card {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
    }

    .lmr-section-title {
        font-size: 0.78rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
    }

    .lmr-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }

    .table-soft {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    .table-soft thead th {
        background: #f8fafc;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
    }

    .table-soft tbody td {
        vertical-align: middle;
        border-bottom: 1px solid #edf2f7;
        padding: 0.7rem 0.75rem;
        font-size: 0.9rem;
    }

    .distance-compact {
        gap: 0.9rem;
    }

    .sticky-toolbar {
        position: sticky;
        top: 0;
        z-index: 3;
        background: rgba(255,255,255,0.96);
        backdrop-filter: blur(8px);
    }
</style>

<div class="lmr-toolbar sticky-toolbar pb-2">
    <div>
        <h5 class="mb-1"><i class="bi bi-clipboard-check me-2 text-primary"></i>LMR Entry</h5>
        <small class="text-muted">Standardized LMR record creation and item line tracking</small>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-text me-1"></i>Draft
        </button>
        <button type="button" class="btn btn-primary btn-sm">
            <i class="bi bi-check2-circle me-1"></i>Save LMR
        </button>
    </div>
</div>

<form id="lmrEntryForm" class="lmr-card p-4">
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="lmr-section-title mb-3">Header Meta & Schedule</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">LMR Number <span class="text-danger">*</span></label>
                    <input type="text" name="lmr_number" class="form-control" placeholder="Enter LMR number" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">LMR Type</label>
                    <select name="lmr_type" class="form-select filter-select">
                        <option value="Standard" selected>Standard</option>
                        <option value="Rework">Rework</option>
                        <option value="Trial">Trial</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">LMR Site</label>
                    <select name="lmr_site" class="form-select filter-select">
                        <option value="001 - Sterling Technopark" selected>001 - Sterling Technopark</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Order Date</label>
                    <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Planned Start Date</label>
                    <input type="date" name="planned_start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Priority</label>
                    <input type="number" name="priority" class="form-control" value="3" min="1" max="9">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Reference No</label>
                    <input type="text" name="reference_no" class="form-control" placeholder="Optional">
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="lmr-section-title mb-3">Customer & Order Tracking</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">LMR Status</label>
                    <select name="lmr_status" class="form-select filter-select">
                        <option value="Planned" selected>Planned</option>
                        <option value="Released">Released</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Customer Code</label>
                    <select name="customer_code" id="customerCode" class="form-select filter-select">
                        <option value="">Select customer</option>
                        <?php foreach ($customers ?? [] as $customer): ?>
                            <option value="<?= htmlspecialchars($customer['customer_id']) ?>" data-name="<?= htmlspecialchars($customer['customer_name'] ?? '') ?>" data-code="<?= htmlspecialchars($customer['customer_code'] ?? '') ?>">
                                <?= htmlspecialchars($customer['customer_code'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-semibold">Customer Name</label>
                    <input type="text" id="customerName" class="form-control" readonly placeholder="Auto-fills after selecting customer">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Batch / Lot No</label>
                    <input type="text" name="batch_lot_no" class="form-control" placeholder="e.g. FB013-0826143">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">PO Number</label>
                    <select name="po_number" id="poNumber" class="form-select filter-select">
                        <option value="">Select open PO</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="lmr-section-title mb-0">Item / Material Lines</div>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#lmrItemModal">
            <i class="bi bi-plus-circle me-1"></i>Add Line Item
        </button>
    </div>

    <div class="table-responsive border rounded-3">
        <table class="table-soft mb-0">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Item Number</th>
                    <th>Description</th>
                    <th>UOM</th>
                    <th>Item Type</th>
                    <th>Site</th>
                    <th>Qty Ordered</th>
                    <th>SO Number</th>
                    <th>BOM Code</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="lmrLinesTableBody">
                <tr class="empty-state-row">
                    <td colspan="10" class="text-center text-muted py-4">
                        No line items added yet. Use “Add Line Item” to begin the LMR.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</form>

<div class="modal fade" id="lmrItemModal" tabindex="-1" aria-labelledby="lmrItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title" id="lmrItemModalLabel"><i class="bi bi-plus-circle me-2"></i>Add LMR Product Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Type</label>
                        <select id="lineType" class="form-select filter-select">
                            <option value="FG" selected>FG</option>
                            <option value="SFG">SFG - Semi-Finished Goods (Bulk)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Item Number</label>
                        <select id="lineItemSelect" class="form-select filter-select">
                            <option value="">Select item</option>
                            <?php foreach ($items ?? [] as $item): ?>
                                <option value="<?= htmlspecialchars($item['item_id']) ?>" data-code="<?= htmlspecialchars($item['item_code'] ?? '') ?>" data-description="<?= htmlspecialchars($item['item_description'] ?? '') ?>" data-uom="<?= htmlspecialchars($item['item_uom'] ?? '') ?>" data-type="<?= htmlspecialchars($item['item_type'] ?? '') ?>">
                                    <?= htmlspecialchars($item['item_code'] ?? '') ?> - <?= htmlspecialchars($item['item_description'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" id="lineDescription" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">UOM</label>
                        <input type="text" id="lineUom" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Item Type</label>
                        <input type="text" id="lineItemType" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Site</label>
                        <input type="text" id="lineSite" class="form-control" value="001 - Sterling Technopark" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Qty Ordered</label>
                        <input type="number" id="lineQtyOrdered" class="form-control" min="1" placeholder="0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">SO Number</label>
                        <input type="text" id="lineSoNumber" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">BOM Code</label>
                        <input type="text" id="lineBomCode" class="form-control" readonly placeholder="Auto-populated from active formulation">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveLineItemBtn">Save Line</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const customerSelect = document.getElementById('customerCode');
    const customerNameInput = document.getElementById('customerName');
    const poSelect = document.getElementById('poNumber');
    const itemSelect = document.getElementById('lineItemSelect');
    const lineDescription = document.getElementById('lineDescription');
    const lineUom = document.getElementById('lineUom');
    const lineItemType = document.getElementById('lineItemType');
    const lineBomCode = document.getElementById('lineBomCode');
    const lineQtyOrdered = document.getElementById('lineQtyOrdered');
    const lineType = document.getElementById('lineType');
    const linesTable = document.getElementById('lmrLinesTableBody');
    const emptyRow = linesTable.querySelector('.empty-state-row');

    function populateCustomerName() {
        const selectedOption = customerSelect.selectedOptions[0];
        customerNameInput.value = selectedOption ? selectedOption.dataset.name || '' : '';

        const customerId = selectedOption ? selectedOption.value : '';
        poSelect.innerHTML = '<option value="">Select open PO</option>';

        if (!customerId) {
            return;
        }

        fetch('?controller=production&action=getCustomerPOs&customer_id=' + encodeURIComponent(customerId))
            .then(response => response.json())
            .then(data => {
                data.forEach(function (po) {
                    const option = document.createElement('option');
                    option.value = po.po_number || '';
                    option.textContent = po.label || po.po_number || 'PO';
                    poSelect.appendChild(option);
                });
            })
            .catch(function () {
                poSelect.innerHTML = '<option value="">No open PO found</option>';
            });
    }

    function updateLineDetails() {
        const selectedOption = itemSelect.selectedOptions[0];
        if (!selectedOption || !selectedOption.value) {
            lineDescription.value = '';
            lineUom.value = '';
            lineItemType.value = '';
            lineBomCode.value = '';
            return;
        }

        const code = selectedOption.dataset.code || '';
        const description = selectedOption.dataset.description || '';
        const uom = selectedOption.dataset.uom || '';
        const itemType = selectedOption.dataset.type || lineType.value || 'FG';

        lineDescription.value = description;
        lineUom.value = uom;
        lineItemType.value = itemType;

        fetch('?controller=production&action=getItemBomInfo&item_id=' + encodeURIComponent(selectedOption.value))
            .then(response => response.json())
            .then(data => {
                lineBomCode.value = data && data.bom_code ? data.bom_code : '—';
            })
            .catch(function () {
                lineBomCode.value = '—';
            });
    }

    function addLineItem() {
        const selectedItem = itemSelect.selectedOptions[0];
        const qty = parseFloat(lineQtyOrdered.value || '0');

        if (!selectedItem || !selectedItem.value) {
            Dlg.alert('Please select an item before saving the line.');
            return;
        }

        if (!qty || qty <= 0) {
            Dlg.alert('Qty Ordered must be greater than zero.');
            return;
        }

        if (emptyRow) {
            emptyRow.remove();
        }

        const row = document.createElement('tr');
        row.innerHTML = `
            <td><span class="badge bg-light text-dark border">${lineType.value || 'FG'}</span></td>
            <td><strong>${selectedItem.dataset.code || ''}</strong></td>
            <td>${lineDescription.value || ''}</td>
            <td>${lineUom.value || ''}</td>
            <td>${lineItemType.value || 'FG'}</td>
            <td>001 - Sterling Technopark</td>
            <td>${qty}</td>
            <td>${(document.getElementById('lineSoNumber').value || '-')}</td>
            <td>${lineBomCode.value || '—'}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger remove-line-item">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        linesTable.appendChild(row);

        document.getElementById('lineSoNumber').value = '';
        lineQtyOrdered.value = '';
        itemSelect.value = '';
        lineDescription.value = '';
        lineUom.value = '';
        lineItemType.value = '';
        lineBomCode.value = '';
        lineType.value = 'FG';

        const modal = bootstrap.Modal.getInstance(document.getElementById('lmrItemModal'));
        if (modal) {
            modal.hide();
        }
    }

    customerSelect.addEventListener('change', populateCustomerName);
    itemSelect.addEventListener('change', updateLineDetails);
    lineType.addEventListener('change', function () {
        if (itemSelect.value) {
            updateLineDetails();
        }
    });

    document.getElementById('saveLineItemBtn').addEventListener('click', addLineItem);

    linesTable.addEventListener('click', function (event) {
        const removeButton = event.target.closest('.remove-line-item');
        if (!removeButton) {
            return;
        }
        const row = removeButton.closest('tr');
        if (row) {
            row.remove();
            if (!linesTable.querySelector('tr') && !document.querySelector('#lmrLinesTableBody .empty-state-row')) {
                linesTable.innerHTML = `
                    <tr class="empty-state-row">
                        <td colspan="10" class="text-center text-muted py-4">
                            No line items added yet. Use “Add Line Item” to begin the LMR.
                        </td>
                    </tr>
                `;
            }
        }
    });

    populateCustomerName();
    updateLineDetails();
});
</script>
