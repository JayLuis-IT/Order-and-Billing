<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDeliveryModal">
            <i class="bi bi-plus-circle me-1"></i> Create Delivery Receipt
        </button>
        <button type="button" class="btn btn-primary ms-2" id="printDRBtn">
            <i class="bi bi-printer me-1"></i> Print DR
        </button>
        <a href="?controller=warehouse&action=viewBackloads" class="btn btn-warning ms-2">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Backload Records
        </a>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <select id="filterCustomer" class="form-select form-select-sm filter-select" style="width:200px">
            <option value="">All Customers</option>
            <?php foreach (($allCustomers ?? []) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= ($filterCustomer ?? '') === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filterItem" class="form-select form-select-sm filter-select" style="width:200px">
            <option value="">All Items</option>
        </select>
        <input type="date" id="filterDate" class="form-control form-control-sm" style="width:160px" title="Filter by Delivery Date">
        <a href="?controller=warehouse&action=deliveries" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Clear</a>
    </div>
    <div class="search-box" style="width: 300px;">
        <form method="GET" class="d-flex align-items-center">
            <input type="hidden" name="controller" value="warehouse">
            <input type="hidden" name="action" value="deliveries">
            <input type="hidden" name="filter_customer" value="<?= htmlspecialchars($filterCustomer ?? '') ?>">
            <input type="hidden" name="filter_item" value="<?= htmlspecialchars($filterItem ?? '') ?>">
            <i class="bi bi-search"></i>
            <input type="text" name="search" id="searchDelivery" class="form-control" placeholder="Search PO number..." value="<?= htmlspecialchars($search ?? '') ?>">
        </form>
    </div>
</div>

<div class="mb-2">
    <small><i class="bi bi-info-circle me-1"></i><strong>Report/Edit:</strong> <span style="color:red;font-weight:bold;">Red</span> = Reported, <span style="color:#e6a800;font-weight:bold;">Yellow</span> = Edited by Admin</small>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Customer</th>
                    <th>Items / Lots</th>
                    <th>DR Number</th>
                    <th>Total Delivered</th>
                    <th>Cases</th>
                    <th>Type</th>
                    <th>Delivery Date</th>
                    <th>Remarks</th>
                    <th>Report / Edit</th>
                    <th class="action-col">Actions</th>
                </tr>
            </thead>
            <tbody id="deliveryTableBody">
                <?php foreach ($deliveries as $d): ?>
                <?php
                    $lotItems = json_decode($d['lot_items'] ?? '[]', true);
                    $hasLotItems = is_array($lotItems) && count($lotItems) > 0;
                    $itemSummary = '';
                    $casesSummary = '';
                    $deliveryBackloads = $backloads_map[$d['delivery_id']] ?? [];
                    $backloadByItem = [];
                    foreach ($deliveryBackloads as $bl) {
                        foreach ($lotItems as $li) {
                            if (intval($li['lot_id'] ?? 0) === intval($bl['lot_id'])) {
                                $blKey = $li['item_description'] ?? $li['item_code'] ?? 'Unknown';
                                $blConv = $li['actual_uom_conversion'] ?? $li['uom_conversion'] ?? 0;
                                $blUom = $li['item_uom'] ?? '';
                                if ($blConv && $blUom !== 'CS') {
                                    $backloadByItem[$blKey] = ($backloadByItem[$blKey] ?? 0) + floor(intval($bl['quantity']) / $blConv);
                                } else {
                                    $backloadByItem[$blKey] = ($backloadByItem[$blKey] ?? 0) + intval($bl['quantity']);
                                }
                                break;
                            }
                        }
                    }
                    if ($hasLotItems) {
                        $grouped = [];
                        foreach ($lotItems as $li) {
                            $key = $li['item_description'] ?? $li['item_code'] ?? 'Unknown';
                            if (!isset($grouped[$key])) $grouped[$key] = ['qty' => 0, 'lots' => [], 'conv' => null, 'uom' => '', 'cases' => 0];
                            $grouped[$key]['qty'] += $li['qty'] ?? 0;
                            $grouped[$key]['lots'][] = $li['lot_number'] ?? '?';
                            $lotConv = $li['actual_uom_conversion'] ?? $li['uom_conversion'] ?? null;
                            $lotUom = $li['item_uom'] ?? '';
                            if (!empty($lotUom)) $grouped[$key]['uom'] = $lotUom;
                            if ($lotConv && $lotUom !== 'CS') {
                                $grouped[$key]['conv'] = $lotConv;
                            }
                        }
                        foreach ($grouped as $key => &$info) {
                            if ($info['conv'] && $info['uom'] !== 'CS') {
                                $info['cases'] = floor($info['qty'] / $info['conv']);
                            }
                        }
                        unset($info);
                        $parts = [];
                        $caseParts = [];
                        foreach ($grouped as $desc => $info) {
                            $parts[] = htmlspecialchars($desc) . ' (' . $info['qty'] . ' - ' . implode(', ', $info['lots']) . ')';
                            if ($info['cases'] > 0) {
                                $caseText = htmlspecialchars($desc) . ': ' . $info['cases'] . ' CS';
                                $blQty = $backloadByItem[$desc] ?? 0;
                                if ($blQty > 0) {
                                    $caseText .= ' <span class="badge bg-danger ms-1">backload ' . $blQty . ' CS</span>';
                                }
                                $caseParts[] = $caseText;
                            }
                        }
                        $itemSummary = implode('<br>', $parts);
                        $casesSummary = implode('<br>', $caseParts);
                    } else {
                        $itemSummary = htmlspecialchars(($d['item_code'] ?? '-') . ' - ' . ($d['item_description'] ?? ''));
                        if (!empty($d['lot_number'])) $itemSummary .= '<br><small>' . htmlspecialchars($d['lot_number']) . '</small>';
                        $conv = $d['actual_uom_conversion'] ?? $d['uom_conversion'] ?? null;
                        $itemUom = $d['item_uom'] ?? '';
                        if ($conv && $itemUom !== 'CS') {
                            $deliveredCS = floor(($d['delivery_quantity'] ?? 0) / $conv);
                            $blCS = 0;
                            foreach ($deliveryBackloads as $bl) {
                                $blCS += intval($bl['quantity']);
                            }
                            $casesSummary = $deliveredCS . ' CS';
                            if ($blCS > 0) {
                                $casesSummary .= ' <span class="badge bg-danger ms-1">backload ' . $blCS . ' CS</span>';
                            }
                        }
                    }
                ?>
                <?php $isActive = ($d['active_status'] ?? 1) == 1; ?>
                <tr class="<?= $isActive ? '' : 'text-decoration-line-through opacity-50' ?>">
                    <td><strong class="text-primary">
                    <?= $d['customer_po_number'] ?>
                    </strong></td>
                    <td><?= htmlspecialchars($d['customer_name'] ?? '-') ?></td>
                    <td><small><?= $itemSummary ?></small></td>
                    <td><?= htmlspecialchars($d['dr_number'] ?? '') ?: '<span class="text-muted">-</span>' ?></td>
                    <td><?= $d['delivery_quantity'] ?? 0 ?></td>
                    <td>
                        <?php if (!empty($casesSummary)): ?>
                            <small><?= $casesSummary ?></small>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-secondary">Normal</span></td>
                    <td><?= date('Y-m-d', strtotime($d['delivery_date'])) ?></td>
                    <td>
                        <?php if (!empty($d['remarks'])): ?>
                            <span><?= htmlspecialchars($d['remarks']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($d['report_remarks'])): ?>
                            <?php
                            $rmType = $d['remarks_type'] ?? '';
                            if ($rmType === 'report') $rmStyle = 'color:red;font-weight:bold;';
                            elseif ($rmType === 'edited') $rmStyle = 'color:#e6a800;font-weight:bold;';
                            else $rmStyle = '';
                            ?>
                            <span style="<?= $rmStyle ?>"><?= htmlspecialchars($d['report_remarks']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="action-col">
                        <?php $disAttr = $isActive ? '' : 'disabled'; ?>
                        <?php $disClass = $isActive ? '' : 'disabled'; ?>
                        <?php if ($hasLotItems): ?>
                        <button type="button" class="btn btn-sm btn-primary viewDeliveryBtn <?= $disClass ?>" <?= $disAttr ?>
                            data-bs-toggle="modal" data-bs-target="#viewDeliveryModal"
                            data-dr="<?= htmlspecialchars($d['dr_number']) ?>"
                            data-po="<?= htmlspecialchars($d['customer_po_number']) ?>"
                            data-customer="<?= htmlspecialchars($d['customer_name'] ?? '') ?>"
                            data-date="<?= date('Y-m-d', strtotime($d['delivery_date'])) ?>"
                            data-remarks="<?= htmlspecialchars($d['remarks'] ?? '') ?>"
                            data-report-remarks="<?= htmlspecialchars($d['report_remarks'] ?? '') ?>"
                            data-remarks-type="<?= htmlspecialchars($d['remarks_type'] ?? '') ?>"
                            data-lot-items="<?= htmlspecialchars($d['lot_items'] ?? '[]') ?>"
                            data-delivered-by="<?= htmlspecialchars($d['delivered_by_name'] ?? '') ?>"
                            data-receipts="<?= htmlspecialchars(json_encode($receipts_map[$d['delivery_id']] ?? [])) ?>"
                            data-backloads="<?= htmlspecialchars(json_encode($backloads_map[$d['delivery_id']] ?? [])) ?>">
                            <i class="bi bi-eye"></i> View
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-danger reportDeliveryBtn <?= $disClass ?>" <?= $disAttr ?>
                            data-delivery-id="<?= $d['delivery_id'] ?>"
                            data-dr="<?= htmlspecialchars($d['dr_number'] ?? '') ?>"
                            data-po-id="<?= $d['po_id'] ?>"
                            data-poi-id="<?= $d['poi_id'] ?? '' ?>"
                            data-lot-items="<?= htmlspecialchars($d['lot_items'] ?? '[]') ?>">
                            <i class="bi bi-flag"></i> Edit
                        </button>
                        <button type="button" class="btn btn-sm btn-success attachDRBtn <?= $disClass ?>" <?= $disAttr ?>
                            data-delivery-id="<?= $d['delivery_id'] ?>"
                            data-po-id="<?= $d['po_id'] ?>"
                            data-dr="<?= htmlspecialchars($d['dr_number'] ?? '') ?>">
                            <i class="bi bi-paperclip"></i> Attach
                        </button>
                        <button type="button" class="btn btn-sm btn-warning backloadBtn <?= $disClass ?>" <?= $disAttr ?>
                            data-delivery-id="<?= $d['delivery_id'] ?>"
                            data-dr="<?= htmlspecialchars($d['dr_number'] ?? '') ?>"
                            data-po="<?= htmlspecialchars($d['customer_po_number'] ?? '') ?>">
                            <i class="bi bi-arrow-counterclockwise"></i> Backload
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($deliveries)): ?>
                <tr><td colspan="11" class="text-center text-muted py-4">No deliveries found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<?php $pages = \App\Helpers\Pagination::getPageRange($page, $totalPages); ?>
<?php $paginationParams = http_build_query(array_filter(['controller'=>'warehouse','action'=>'deliveries','search'=>$search??'','filter_customer'=>$filterCustomer??'','filter_item'=>$filterItem??''])); ?>
<?php $paginationBase = '?' . $paginationParams . (strpos($paginationParams, '&') !== false ? '&' : '') . 'page='; ?>
<nav>
    <ul class="pagination justify-content-center mt-4">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $paginationBase ?><?= $page - 1 ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= $paginationBase ?><?= $p ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $paginationBase ?><?= $page + 1 ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<!-- DR Number Input Modal -->
<div class="modal fade" id="drInputModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Enter DR Number</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="drNumberInput" class="form-control" placeholder="Enter DR Number" autofocus>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="drInputOkBtn">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- DR Number Confirm Modal -->
<div class="modal fade" id="drConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-question-circle me-2"></i>Confirm DR Number</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure for this DR number?</p>
                <p class="fw-bold text-primary mb-0" id="drConfirmNumber">-</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="drConfirmEditBtn"><i class="bi bi-pencil me-1"></i>Edit</button>
                <button type="button" class="btn btn-primary" id="drConfirmYesBtn"><i class="bi bi-check-lg me-1"></i>Yes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createDeliveryModal">
    <div class="modal-dialog" style="max-width: 95vw;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-truck me-2"></i>Record Delivery</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?controller=warehouse&action=createMultipleDelivery">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Delivery Receipt (DR) Number *</label>
                                <input type="text" name="dr_number" id="modalDrNumber" class="form-control" placeholder="Enter DR number" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Purchase Order *</label>
                                <select name="po_id" id="poSelect" class="form-select" required>
                                    <option value="">Select PO</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Available Items &amp; Lots *</label>
                                <div id="availableItemsContainer" class="border rounded p-2" style="max-height: 400px; overflow-y: auto;">
                                    <div class="text-muted text-center py-3"><i class="bi bi-hourglass-split"></i> Loading available items...</div>
                                </div>
                                <input type="hidden" name="lot_ids" id="selectedLotIds">
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Plate No. *</label>
                                    <input type="text" name="plate_number" class="form-control" placeholder="e.g. ABC 1234" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Vehicle Type *</label>
                                    <select name="vehicle_type" class="form-select" required>
                                        <option value="">Select Vehicle</option>
                                        <option value="4 Wheels">4 Wheels</option>
                                        <option value="6 Wheels">6 Wheels</option>
                                        <option value="10 Wheels">10 Wheels</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Logistic Provider *</label>
                                    <select name="logistic_provider" class="form-select" required>
                                        <option value="">Select Provider</option>
                                        <option value="FLJJ">FLJJ</option>
                                        <option value="RPI">RPI</option>
                                        <option value="Transportify">Transportify</option>
                                        <option value="Lalamove">Lalamove</option>
                                        <option value="Other">Others</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Delivery Date</label>
                                <input type="date" name="delivery_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="3" placeholder="Optional notes..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div id="lotSummary" style="display: none;">
                                <label class="form-label fw-bold">Selected Lots</label>
                                <div id="lotSummaryContent"></div>
                            </div>
                        </div>
                    </div>
                    <div style="display: none;">
                        <input type="number" name="delivery_quantity" id="deliveryQty" class="form-control" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Delivery</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delivery Preview Modal -->
<div class="modal fade" id="deliveryPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Confirm Delivery</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Please review the delivery details before saving.</p>
                <table class="table table-bordered mb-0">
                    <tr><th style="width:40%">DR Number</th><td id="previewDR"></td></tr>
                    <tr><th>Plate No.</th><td id="previewPlate"></td></tr>
                    <tr><th>Vehicle Type</th><td id="previewVehicle"></td></tr>
                    <tr><th>Logistic Provider</th><td id="previewLogistic"></td></tr>
                    <tr><th>Purchase Order</th><td id="previewPO"></td></tr>
                    <tr><th>Item</th><td id="previewItem"></td></tr>
                    <tr><th>Lot Details</th><td id="previewLots"></td></tr>
                    <tr><th>Total Quantity</th><td id="previewQty"></td></tr>
                    <tr><th>Delivery Date</th><td id="previewDate"></td></tr>
                    <tr><th>Remarks</th><td id="previewRemarks"></td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmDeliveryBtn"><i class="bi bi-check-lg me-1"></i>Confirm & Save</button>
            </div>
        </div>
    </div>
</div>

<!-- View Delivery Modal -->
<div class="modal fade" id="viewDeliveryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Delivery Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>DR Number:</strong> <span id="viewDrNumber">-</span>
                    </div>
                    <div class="col-md-6">
                        <strong>PO Number:</strong> <span id="viewPoNumber">-</span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Customer:</strong> <span id="viewCustomer">-</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Delivery Date:</strong> <span id="viewDate">-</span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Remarks:</strong> <span id="viewRemarks">-</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Report / Edit:</strong> <span id="viewReportRemarks">-</span>
                    </div>
                </div>
                <hr>
                <h6 class="mb-3">Lot Items</h6>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Item Code</th>
                            <th>Item Description</th>
                            <th>Lot Number</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Cases</th>
                        </tr>
                    </thead>
                    <tbody id="viewLotItemsBody">
                    </tbody>
                    <tfoot id="viewLotItemsFoot">
                        <tr class="table-light fw-bold">
                            <td colspan="3" class="text-end">Total:</td>
                            <td class="text-end" id="viewTotalQty">0</td>
                            <td class="text-end" id="viewTotalCases">0 CS</td>
                        </tr>
                    </tfoot>
                </table>
                <hr>
                <h6 class="mb-2"><i class="bi bi-paperclip me-1"></i>DR Attachments</h6>
                <div id="viewDRPhotoSection">
                    <div id="viewDRPhotoContainer" class="d-flex flex-wrap gap-2"></div>
                </div>
                <hr>
                <h6 class="mb-2"><i class="bi bi-arrow-counterclockwise me-1"></i>Backload / Return History</h6>
                <div id="viewBackloadSection">
                    <div id="viewBackloadContainer"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Report Delivery Modal -->
<div class="modal fade" id="reportDeliveryModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-flag me-2"></i>Report Delivery Issue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="reportDeliveryForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">DR Number</label>
                        <input type="text" id="reportDeliveryDr" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type of Report <span class="text-danger">*</span></label>
                        <select id="reportDeliveryType" class="form-select" required>
                            <option value="dr_number">DR Number</option>
                            <option value="quantity">Quantity</option>
                        </select>
                    </div>
                    <div class="mb-3" id="reportLotPickerRow" style="display:none;">
                        <label class="form-label">Select Lot <span class="text-danger">*</span></label>
                        <select id="reportLotPicker" class="form-select">
                            <option value="">-- Select Lot --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Concern / Issue <span class="text-danger">*</span></label>
                        <textarea id="reportDeliveryRemarks" class="form-control" rows="4" placeholder="Describe the issue (e.g. cancel delivery, mistyped DR number, etc.)" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-send me-1"></i>Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Attach DR Photo Modal -->
<div class="modal fade" id="attachDRModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-paperclip me-2"></i>Attach DR File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Upload a photo or PDF of the physical Delivery Receipt as proof it was printed.</p>
                <div class="mb-3">
                    <label class="form-label">DR Number</label>
                    <input type="text" id="attachDRNumber" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Actual DR File <span class="text-danger">*</span></label>
                    <div id="dropZone" class="border border-secondary border-dashed rounded p-4 text-center" style="cursor:pointer; min-height: 120px; display:flex; align-items:center; justify-content:center; flex-direction:column;">
                        <i class="bi bi-cloud-arrow-up fs-1 text-muted"></i>
                        <p class="mb-1 text-muted">Drag & drop file here or <strong>click to browse</strong></p>
                        <small class="text-muted">JPG, PNG, GIF, WebP, PDF only (max 10MB)</small>
                        <input type="file" id="drPhotoInput" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" class="d-none">
                    </div>
                    <div id="photoPreview" class="mt-3" style="display:none;">
                        <img id="previewImg" src="" alt="Preview" class="img-fluid rounded" style="max-height:200px;">
                        <p id="previewName" class="text-muted mt-1 mb-0"></p>
                    </div>
                </div>
                <input type="hidden" id="attachDeliveryId">
                <input type="hidden" id="attachPoId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Done</button>
                <button type="button" class="btn btn-success" id="submitDRPhotoBtn"><i class="bi bi-upload me-1"></i>Upload</button>
            </div>
        </div>
    </div>
</div>

<!-- Backload Modal -->
<div class="modal fade" id="backloadModal" tabindex="-1">
    <div class="modal-dialog" style="max-width: 95vw;">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-arrow-counterclockwise me-2"></i>Backload / Return</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2">DR: <strong id="backloadDrNumber">-</strong> | PO: <strong id="backloadPoNumber">-</strong></p>
                <p class="text-muted mb-3">Enter the quantity returned in <strong>cases</strong>. Total returned will be converted to pcs automatically.</p>
                <form id="backloadForm" method="POST" action="?controller=warehouse&action=backloadDelivery">
                    <input type="hidden" id="backloadDeliveryId" name="delivery_id">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" id="backloadLotsTable">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Lot Number</th>
                                    <th>Delivered (CS)</th>
                                    <th>Already Returned (CS)</th>
                                    <th>Max Returnable (CS)</th>
                                    <th style="width:100px">Return Qty (CS)</th>
                                    <th style="width:140px">Qty (pcs)</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody id="backloadLotsBody">
                                <tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 text-end">
                        <strong>Total Return: <span id="backloadTotalPcs">0</span> pcs</strong>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                <button type="button" class="btn btn-warning" id="submitBackloadBtn"><i class="bi bi-arrow-counterclockwise me-1"></i>Submit Backload</button>
            </div>
        </div>
    </div>
</div>

<!-- Backload Preview Modal -->
<div class="modal fade" id="backloadPreviewModal" tabindex="-1">
    <div class="modal-dialog" style="max-width: 95vw;">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Confirm Backload</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Please review the backload details before submitting.</p>
                <div class="row mb-3">
                    <div class="col-md-4"><strong>DR Number:</strong> <span id="prevBackloadDr">-</span></div>
                    <div class="col-md-4"><strong>PO Number:</strong> <span id="prevBackloadPo">-</span></div>
                    <div class="col-md-4"><strong>Total Return:</strong> <span id="prevBackloadTotal" class="text-danger fw-bold">0</span> pcs</div>
                </div>
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Lot Number</th>
                            <th>Qty Returned (pcs)</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody id="prevBackloadBody"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmBackloadBtn"><i class="bi bi-arrow-counterclockwise me-1"></i>Confirm & Submit</button>
            </div>
        </div>
    </div>
</div>

<script>
var _searchTimer;
document.getElementById('searchDelivery').addEventListener('input', function() {
    clearTimeout(_searchTimer);
    var form = this.closest('form');
    _searchTimer = setTimeout(function() { form.submit(); }, 500);
});

(function() {
    var s = document.getElementById('searchDelivery');
    if (s && s.value) { s.focus(); s.setSelectionRange(s.value.length, s.value.length); }
})();

function applyDeliveryFilters() {
    var params = new URLSearchParams();
    params.set('controller', 'warehouse');
    params.set('action', 'deliveries');
    var s = document.getElementById('searchDelivery');
    if (s && s.value) params.set('search', s.value);
    var c = document.getElementById('filterCustomer');
    if (c && c.value) params.set('filter_customer', c.value);
    var i = document.getElementById('filterItem');
    if (i && i.value) params.set('filter_item', i.value);
    window.location.href = '?' + params.toString();
}

function populateDeliveryFilters() {
    const items = new Set();
    document.querySelectorAll('#deliveryTableBody tr').forEach(row => {
        if (row.querySelector('td[colspan]')) return;
        const itemCell = row.cells[2];
        if (itemCell) {
            itemCell.querySelectorAll('small').forEach(s => {
                const t = s.textContent.trim().split('(')[0].trim();
                if (t && t !== '-') items.add(t);
            });
        }
    });
    const itemSel = document.getElementById('filterItem');
    items.forEach(i => { const o = document.createElement('option'); o.value = i; o.textContent = i; itemSel.appendChild(o); });
}

document.getElementById('filterCustomer').addEventListener('change', applyDeliveryFilters);
document.getElementById('filterItem').addEventListener('change', applyDeliveryFilters);

document.addEventListener('DOMContentLoaded', populateDeliveryFilters);

// ==================== DELIVERY FLOW ====================
(function() {
    var container = document.getElementById('availableItemsContainer');
    var poSelect = document.getElementById('poSelect');
    var saveBtn = document.querySelector('#createDeliveryModal form button[type="submit"]');
    function loadAllPOs() {
        fetch('?controller=warehouse&action=getActivePOsForAssignment')
            .then(function(r) { return r.json(); })
            .then(function(pos) {
                poSelect.innerHTML = '<option value="">Select PO</option>';
                if (!pos || pos.length === 0) return;
                pos.forEach(function(po) {
                    var opt = document.createElement('option');
                    opt.value = po.po_id;
                    opt.textContent = (po.po_number || po.customer_po_number || 'PO #' + po.po_id) + ' \u2014 ' + po.customer_name;
                    poSelect.appendChild(opt);
                });
            });
    }

    function loadAvailableItems(poId) {
        container.innerHTML = '<div class="text-muted text-center py-3"><i class="bi bi-hourglass-split"></i> Loading...</div>';
        loadedItemMap = {};
        allItemsData = [];
        var url = '?controller=warehouse&action=getAvailableItemsForDelivery';
        if (poId) {
            url += '&po_id=' + encodeURIComponent(poId);
        }
        fetch(url, { credentials: 'same-origin' })
            .then(function(r) {
                if (!r.ok) {
                    throw new Error('Unable to load available items.');
                }
                return r.text();
            })
            .then(function(text) {
                if (!text || !text.trim()) {
                    throw new Error('Empty response.');
                }
                var trimmed = text.trim();
                if (trimmed.startsWith('<')) {
                    throw new Error('Authentication or session issue while loading items.');
                }
                var items = JSON.parse(trimmed);
                container.innerHTML = '';
                allItemsData = items || [];
                if (allItemsData.length === 0) {
                    container.innerHTML = '<div class="text-muted text-center py-3">No items available for delivery.</div>';
                    return;
                }
                renderItems(allItemsData);
            })
            .catch(function(err) {
                console.error('loadAvailableItems failed:', err);
                container.innerHTML = '<div class="alert alert-warning mb-0">Unable to load available items. Please refresh the page or log back in and try again.</div>';
            });
    }

    function renderItems(items) {
        container.innerHTML = '';
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-3 no-items-msg">No items available for delivery.</div>';
            return;
        }
        items.forEach(function(item) {
            var selectedPoId = poSelect && poSelect.value ? String(poSelect.value) : '';
            var visibleLots = (item.lots || []).filter(function(lot) {
                return !selectedPoId || lot.in_selected_po === 1;
            });
            if (selectedPoId && visibleLots.length === 0) {
                return;
            }

            var itemDiv = document.createElement('div');
            itemDiv.className = 'mb-3 border rounded p-2';
            var header = document.createElement('div');
            header.className = 'fw-bold text-primary mb-1';
            var progressBarHtml = '';
            if (item.po_quantity !== undefined && item.po_quantity > 0) {
                var poQty = item.po_quantity;
                var poDelivered = item.po_delivered || 0;
                var pct = Math.min(100, (poDelivered / poQty) * 100);
                var isComplete = poDelivered >= poQty;
                var barClass = isComplete ? 'bg-success' : (pct > 0 ? 'bg-warning' : 'bg-danger');
                var badgeClass = isComplete ? 'bg-success' : (pct > 0 ? 'bg-warning text-dark' : 'bg-secondary');
                var conv = item.po_uom_conversion || 0;
                var qtyLabel, statusText;
                if (conv > 0) {
                    var deliveredCases = Math.floor(poDelivered / conv);
                    var orderedCases = Math.floor(poQty / conv);
                    var remainingCases = orderedCases - deliveredCases;
                    qtyLabel = deliveredCases + ' / ' + orderedCases + ' CS';
                    statusText = isComplete ? 'Complete' : (pct > 0 ? 'Partial (' + remainingCases + ' CS left)' : 'Pending');
                } else {
                    qtyLabel = poDelivered.toLocaleString() + ' / ' + poQty.toLocaleString() + ' pcs';
                    statusText = isComplete ? 'Complete' : (pct > 0 ? 'Partial (' + (poQty - poDelivered).toLocaleString() + ' left)' : 'Pending');
                }
                progressBarHtml = '<div class="d-flex align-items-center gap-2 mb-1 ms-3">' +
                    '<div class="progress flex-grow-1" style="height:8px;max-width:200px;">' +
                        '<div class="progress-bar ' + barClass + '" style="width:' + pct + '%"></div>' +
                    '</div>' +
                    '<small class="text-muted text-nowrap">' + qtyLabel + '</small>' +
                    '<span class="badge ' + badgeClass + ' text-nowrap" style="font-size:0.65rem;">' + statusText + '</span>' +
                '</div>';
            }
            header.innerHTML = '<i class="bi bi-box-seam me-1"></i>' + (item.item_code || '') + ' - ' + (item.item_description || '') +
                ' <small class="text-muted">(' + (item.item_uom || 'PCS') + ')</small>' + progressBarHtml;
            itemDiv.appendChild(header);

            visibleLots.forEach(function(lot, lotIdx) {
                var lotId = lot.lot_id;
                var subLots = lot.sub_lots || [{lot_id: lotId, available: lot.available_quantity || 0}];
                loadedItemMap[lotId] = { item_id: item.item_id, item_code: item.item_code, item_description: item.item_description };
                var lotNum = lot.lot_number || '-';
                var avail = lot.available_quantity || 0;
                var conv = lot.uom_conversion || 0;
                var uom = item.item_uom || 'PCS';
                var availCS = conv > 0 ? Math.floor(avail / conv) : 0;
                var availRem = conv > 0 ? avail % conv : 0;
                var availLabel = conv > 0 ? availCS + ' CS' + (availRem > 0 ? ' / ' + availRem + ' pcs' : '') : avail + ' ' + uom;

                var isFifoRecommended = lotIdx === 0 && visibleLots.length > 1;
                var fifoBadge = isFifoRecommended
                    ? ' <span class="badge bg-success ms-1" style="font-size:0.65rem;"><i class="bi bi-arrow-down-circle me-1"></i>FIFO Recommended</span>'
                    : '';

                var lotDateStr = '';
                if (lot.date_created) {
                    var d = new Date(lot.date_created);
                    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    var formattedDate = months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
                    var now = new Date();
                    var diffMs = now - d;
                    var ageDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                    lotDateStr = ' <small class="text-muted ms-1" title="' + lot.date_created + '"><i class="bi bi-calendar3 me-1"></i>' + formattedDate + ' <span class="text-secondary">(' + ageDays + ' days)</span></small>';
                }

                var fifoWarnHtml = '';
                if (!isFifoRecommended && visibleLots.length > 1) {
                    var oldestLot = visibleLots[0];
                    var oldestDateStr = '';
                    if (oldestLot.date_created) {
                        var od = new Date(oldestLot.date_created);
                        var oMonths = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                        oldestDateStr = oMonths[od.getMonth()] + ' ' + od.getDate() + ', ' + od.getFullYear();
                    }
                    fifoWarnHtml = '<div class="fifo-warn mt-1 ms-4" style="display:none;" id="fifoWarn_' + lotId + '">' +
                        '<small class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Older lot <strong>' + (oldestLot.lot_number || '-') + '</strong>' + (oldestDateStr ? ' (' + oldestDateStr + ')' : '') + ' still has stock. Are you sure?</small>' +
                        '</div>';
                }

                var row = document.createElement('div');
                row.className = 'border rounded p-2 mb-1 bg-light';
                row.setAttribute('data-lot-row', lotId);
                row.innerHTML =
                    '<div class="form-check">' +
                        '<input class="form-check-input" type="checkbox" id="availChk_' + lotId + '" data-lot-id="' + lotId + '" data-lot-number="' + lotNum + '" data-type="avail" data-sub-lots=\'' + JSON.stringify(subLots).replace(/'/g, "&#39;") + '\' data-lot-index="' + lotIdx + '">' +
                        '<label class="form-check-label fw-bold" for="availChk_' + lotId + '">' +
                            '<i class="bi bi-tag me-1"></i>' + lotNum + fifoBadge +
                            ' <span class="ms-1 text-muted">Avail: ' + availLabel + '</span>' +
                            lotDateStr +
                        '</label>' +
                    '</div>' +
                    '<div class="row g-2 mt-1 ms-4" style="display:none;" id="availFields_' + lotId + '">' +
                        '<div class="col-md-4">' +
                            '<label class="form-label small">Cases</label>' +
                            '<input type="number" class="form-control form-control-sm" id="lotCaseAvail_' + lotId + '" min="0" placeholder="CS">' +
                        '</div>' +
                        '<div class="col-md-4">' +
                            '<label class="form-label small">Pieces (' + uom + ')</label>' +
                            '<input type="number" class="form-control form-control-sm" id="lotQtyAvail_' + lotId + '" min="1" max="' + avail + '" data-max="' + avail + '" data-conv="' + conv + '" placeholder="Qty">' +
                        '</div>' +
                        '<div class="col-md-4" id="lotWarn_' + lotId + '"></div>' +
                    '</div>' +
                    fifoWarnHtml;

                itemDiv.appendChild(row);

                var chk = row.querySelector('#availChk_' + lotId);
                var fieldsDiv = row.querySelector('#availFields_' + lotId);
                var caseEl = row.querySelector('#lotCaseAvail_' + lotId);
                var qtyEl = row.querySelector('#lotQtyAvail_' + lotId);

                chk.addEventListener('change', function() {
                    fieldsDiv.style.display = this.checked ? '' : 'none';
                    if (!this.checked) { caseEl.value = ''; qtyEl.value = ''; }
                    else { caseEl.focus(); }

                    var fifoWarn = document.getElementById('fifoWarn_' + lotId);
                    if (fifoWarn) {
                        if (this.checked && lotIdx > 0) {
                            fifoWarn.style.display = '';
                        } else {
                            fifoWarn.style.display = 'none';
                        }
                    }
                });

                if (conv > 0) {
                    caseEl.addEventListener('input', function() {
                        var cs = parseInt(this.value) || 0;
                        qtyEl.value = cs * conv;
                        validateLotOverShipment(qtyEl, avail, conv, lotId, item);
                    });
                    qtyEl.addEventListener('input', function() {
                        var qty = parseInt(this.value) || 0;
                        caseEl.value = conv > 0 ? Math.floor(qty / conv) : '';
                        var w = document.getElementById('lotWarn_' + lotId);
                        if (w) {
                            if (qty > avail) {
                                w.innerHTML = '<small class="text-danger"><i class="bi bi-exclamation-circle"></i> Exceeds available ' + avail + ' pcs.</small>';
                            } else if (qty > 0 && conv > 0 && qty % conv !== 0) {
                                var rem = qty % conv;
                                w.innerHTML = '<small class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' + rem + ' pc' + (rem > 1 ? 's' : '') + ' excess. Use ' + (qty - rem) + ' or ' + (qty + (conv - rem)) + '.</small>';
                            } else {
                                w.textContent = '';
                            }
                        }
                        validateLotOverShipment(qtyEl, avail, conv, lotId, item);
                    });
                } else {
                    qtyEl.addEventListener('input', function() {
                        var qty = parseInt(this.value) || 0;
                        var w = document.getElementById('lotWarn_' + lotId);
                        if (w) {
                            if (qty > avail) {
                                w.innerHTML = '<small class="text-danger"><i class="bi bi-exclamation-circle"></i> Exceeds available ' + avail + ' pcs.</small>';
                            } else {
                                w.textContent = '';
                            }
                        }
                        validateLotOverShipment(qtyEl, avail, 0, lotId, item);
                    });
                }
            });

            container.appendChild(itemDiv);
        });
    }

    function validateLotOverShipment(qtyEl, avail, conv, lotId, item) {
        var qty = parseInt(qtyEl.value) || 0;
        var w = document.getElementById('lotWarn_' + lotId);
        if (!w || !item || item.po_quantity === undefined || item.po_quantity <= 0) return;

        var poConv = item.po_uom_conversion || conv || 0;
        var poRemaining = item.po_quantity - (item.po_delivered || 0);
        if (poRemaining < 0) poRemaining = 0;

        var existingHtml = w.innerHTML;
        var overShipWarn = '';
        if (qty > 0 && qty > poRemaining) {
            if (poConv > 0) {
                overShipWarn = '<small class="text-danger fw-bold"><i class="bi bi-exclamation-circle"></i> Cannot exceed remaining balance of ' + Math.floor(poRemaining / poConv) + ' CS (' + poRemaining + ' pcs) for this PO item.</small>';
            } else {
                overShipWarn = '<small class="text-danger fw-bold"><i class="bi bi-exclamation-circle"></i> Cannot exceed remaining balance of ' + poRemaining + ' pcs for this PO item.</small>';
            }
            qtyEl.classList.add('is-invalid');
        } else {
            qtyEl.classList.remove('is-invalid');
        }

        if (overShipWarn) {
            w.innerHTML = existingHtml ? existingHtml + '<br>' + overShipWarn : overShipWarn;
        } else {
            w.innerHTML = existingHtml.replace(/<br><small class="text-danger fw-bold">.*?<\/small>$/, '').replace(/<small class="text-danger fw-bold">.*?<\/small>$/, '');
        }

        updateSubmitButton();
    }

    function updateSubmitButton() {
        var saveBtn = document.querySelector('#createDeliveryModal form button[type="submit"]');
        if (!saveBtn) return;
        var anyOver = document.querySelectorAll('#availableItemsContainer .is-invalid').length > 0;
        saveBtn.disabled = anyOver;
    }

    poSelect.addEventListener('change', function() {
        loadAvailableItems(this.value || null);
    });

    document.getElementById('createDeliveryModal').addEventListener('shown.bs.modal', function() {
        loadAllPOs();
        loadAvailableItems(poSelect.value || null);
    });
})();

var allItemsData = [];
var loadedItemMap = {};

function checkOverShipment(lotData, items) {
    var lotToItem = {};
    items.forEach(function(item) {
        (item.lots || []).forEach(function(lot) {
            lotToItem[lot.lot_id] = item;
        });
    });

    var perItemDelivery = {};
    for (var lotId in lotData) {
        var d = lotData[lotId];
        var item = lotToItem[lotId];
        if (!item || item.po_quantity === undefined) continue;
        var iid = item.item_id;
        if (!perItemDelivery[iid]) {
            perItemDelivery[iid] = { item: item, totalQty: 0 };
        }
        perItemDelivery[iid].totalQty += d.qty;
    }

    var overItems = [];
    for (var iid in perItemDelivery) {
        var entry = perItemDelivery[iid];
        var poQty = entry.item.po_quantity || 0;
        var poDelivered = entry.item.po_delivered || 0;
        var remaining = poQty - poDelivered;
        if (remaining < 0) remaining = 0;
        if (entry.totalQty > remaining && remaining >= 0 && poQty > 0) {
            overItems.push({
                item_code: entry.item.item_code,
                item_description: entry.item.item_description,
                po_quantity: poQty,
                po_delivered: poDelivered,
                po_uom_conversion: entry.item.po_uom_conversion || 0,
                remaining: remaining,
                attempted: entry.totalQty,
                excess: entry.totalQty - remaining
            });
        }
    }
    return overItems;
}

document.getElementById('createDeliveryModal').addEventListener('hidden.bs.modal', function() {
    var form = this.querySelector('form');
    form.reset();
    document.getElementById('availableItemsContainer').innerHTML = '';
    document.getElementById('selectedLotIds').value = '';
    var saveBtn = form.querySelector('button[type="submit"]');
    saveBtn.disabled = false;
    deliveryFormConfirmed = false;
});

document.querySelector('#createDeliveryModal form').addEventListener('submit', function(e) {
    if (deliveryFormConfirmed) return;
    e.preventDefault();

    var poSelect = document.getElementById('poSelect');

    if (!poSelect.value) {
        alert('Please select a Purchase Order before creating a delivery.');
        return;
    }

    var lotData = {};
    var hasError = false;

    document.querySelectorAll('#availableItemsContainer input[type="checkbox"]:checked').forEach(function(chk) {
        if (chk.dataset.type !== 'avail') return;
        var lotId = chk.dataset.lotId;
        var qtyInput = document.getElementById('lotQtyAvail_' + lotId);
        var qty = parseInt(qtyInput.value) || 0;
        var max = parseInt(qtyInput.dataset.max) || 0;
        var conv = parseInt(qtyInput.dataset.conv) || 0;

        if (qty <= 0) {
            hasError = true;
            alert('Please enter a quantity for lot #' + lotId);
            return;
        }
        if (qty > max) {
            hasError = true;
            alert('Quantity ' + qty + ' exceeds max ' + max + ' for lot #' + lotId);
            return;
        }
        if (conv > 0 && qty % conv !== 0) {
            hasError = true;
            var rem = qty % conv;
            alert('Quantity for lot #' + lotId + ' is ' + qty + ' pcs, but ' + conv + ' pcs/case means ' + rem + ' pc' + (rem > 1 ? 's' : '') + ' excess.\n\nUse ' + (qty - rem) + ' or ' + (qty + (conv - rem)) + ' pcs.');
            return;
        }

        lotData[lotId] = { qty: qty, conv: conv, subLots: JSON.parse(chk.dataset.subLots || '[]') };
    });

    if (hasError) return;

    var lotPairs = [];
    for (var lotId in lotData) {
        var d = lotData[lotId];
        var subLots = d.subLots || [{lot_id: lotId, available: d.qty}];
        var remaining = d.qty;
        for (var si = 0; si < subLots.length && remaining > 0; si++) {
            var sl = subLots[si];
            var slQty = Math.min(remaining, sl.available);
            lotPairs.push(sl.lot_id + ':' + slQty + ':0:' + d.conv);
            remaining -= slQty;
        }
        if (remaining > 0) {
            lotPairs.push(subLots[subLots.length - 1].lot_id + ':' + remaining + ':0:' + d.conv);
        }
    }

    if (lotPairs.length === 0) {
        alert('Please select at least one lot');
        return;
    }

    document.getElementById('selectedLotIds').value = lotPairs.join(',');

    var overItems = checkOverShipment(lotData, allItemsData);
    if (overItems.length > 0) {
        var msg = 'Delivery blocked. The following items exceed remaining PO balance:\n\n';
        overItems.forEach(function(item) {
            var conv = item.po_uom_conversion || 0;
            if (conv > 0) {
                msg += item.item_code + ': ' + item.attempted.toLocaleString() + ' pcs (' + Math.floor(item.attempted / conv) + ' CS) requested, ' + item.remaining.toLocaleString() + ' pcs (' + Math.floor(item.remaining / conv) + ' CS) remaining\n';
            } else {
                msg += item.item_code + ': ' + item.attempted.toLocaleString() + ' pcs requested, ' + item.remaining.toLocaleString() + ' pcs remaining\n';
            }
        });
        alert(msg);
        return;
    }

    var drNumber = document.getElementById('modalDrNumber').value.trim();
    if (drNumber) {
        fetch('?controller=warehouse&action=checkDRNumber&dr_number=' + encodeURIComponent(drNumber))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.exists) {
                    alert('DR number "' + drNumber + '" already used. Try another DR number.');
                    return;
                }
                showDeliveryPreview();
            })
            .catch(function() {
                showDeliveryPreview();
            });
    } else {
        showDeliveryPreview();
    }

    function showDeliveryPreview() {
        var poOption = poSelect.options[poSelect.selectedIndex];
        var poText = poOption && poOption.value ? poOption.textContent.trim() : 'Not assigned';
        var deliveryDate = document.querySelector('#createDeliveryModal input[name="delivery_date"]').value;
        var remarks = document.querySelector('#createDeliveryModal textarea[name="remarks"]').value.trim() || '-';

        document.getElementById('previewDR').textContent = drNumber;
        document.getElementById('previewPO').textContent = poText;
        document.getElementById('previewDate').textContent = deliveryDate;
        document.getElementById('previewRemarks').textContent = remarks;

        var plateNo = document.querySelector('#createDeliveryModal input[name="plate_number"]').value.trim() || '-';
        var vehicleType = document.querySelector('#createDeliveryModal select[name="vehicle_type"]').value || '-';
        var logisticProvider = document.querySelector('#createDeliveryModal select[name="logistic_provider"]').value || '-';
        document.getElementById('previewPlate').textContent = plateNo;
        document.getElementById('previewVehicle').textContent = vehicleType;
        document.getElementById('previewLogistic').textContent = logisticProvider;

        var lotsHtml = '<table class="table table-sm table-bordered mb-0">';
        lotsHtml += '<thead><tr><th>Item</th><th>Lot No.</th><th>Qty</th></tr></thead><tbody>';
        var totalQty = 0;
        for (var lotId in lotData) {
            var d = lotData[lotId];
            var lotLabel = 'Lot #' + lotId;
            var chkEl = document.getElementById('availChk_' + lotId);
            if (chkEl && chkEl.dataset.lotNumber) lotLabel = chkEl.dataset.lotNumber;
            var itemName = 'Independent FG';
            if (loadedItemMap[lotId]) itemName = loadedItemMap[lotId].item_code || loadedItemMap[lotId].item_description || 'Independent FG';
            totalQty += d.qty;
            lotsHtml += '<tr><td>' + itemName + '</td><td>' + lotLabel + '</td><td>' + d.qty + '</td></tr>';
        }
        lotsHtml += '</tbody></table>';
        document.getElementById('previewLots').innerHTML = lotsHtml;
        var firstLotId = Object.keys(lotData)[0];
        var firstItemName = 'Independent FG';
        if (firstLotId && loadedItemMap[firstLotId]) firstItemName = loadedItemMap[firstLotId].item_code || loadedItemMap[firstLotId].item_description || 'Independent FG';
        document.getElementById('previewItem').textContent = firstItemName;
        document.getElementById('previewQty').textContent = totalQty;

        var previewModal = new bootstrap.Modal(document.getElementById('deliveryPreviewModal'));
        previewModal.show();
    }
});

var deliveryFormConfirmed = false;

document.getElementById('confirmDeliveryBtn').addEventListener('click', function() {
    var previewModal = bootstrap.Modal.getInstance(document.getElementById('deliveryPreviewModal'));
    previewModal.hide();
    deliveryFormConfirmed = true;
    document.querySelector('#createDeliveryModal form').submit();
});

var drInputModal, drConfirmModal;
var drState = { drNumber: '' };

document.getElementById('printDRBtn').addEventListener('click', function() {
    document.getElementById('drNumberInput').value = '';
    drInputModal = new bootstrap.Modal(document.getElementById('drInputModal'));
    drInputModal.show();
});

document.getElementById('drInputOkBtn').addEventListener('click', function() {
    var value = document.getElementById('drNumberInput').value.trim();
    if (value === '') {
        alert('Please enter a DR number');
        return;
    }
    drState.drNumber = value;
    drInputModal.hide();
    document.getElementById('drConfirmNumber').textContent = value;
    drConfirmModal = new bootstrap.Modal(document.getElementById('drConfirmModal'));
    drConfirmModal.show();
});

document.getElementById('drNumberInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('drInputOkBtn').click();
    }
});

document.getElementById('drConfirmEditBtn').addEventListener('click', function() {
    drConfirmModal.hide();
    document.getElementById('drNumberInput').value = drState.drNumber;
    drInputModal = new bootstrap.Modal(document.getElementById('drInputModal'));
    drInputModal.show();
});

document.getElementById('drConfirmYesBtn').addEventListener('click', function() {
    var drNumber = drState.drNumber;
    drConfirmModal.hide();

    fetch('?controller=warehouse&action=checkDRNumber&dr_number=' + encodeURIComponent(drNumber))
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.exists && data.po_ids && data.po_ids.length > 0) {
                window.location.href = '?controller=warehouse&action=printDRPreview&dr_number=' + encodeURIComponent(drNumber) + '&po_id=' + data.po_ids[0];
            } else {
                alert('Error: DR number "' + drNumber + '" not found. Please check the DR number and try again.');
            }
        })
        .catch(function() {
            alert('Error: Could not verify DR number. Please try again.');
    }).catch(function(err) {
        console.error('Promise.all error:', err);
    });
});

document.querySelectorAll('.viewDeliveryBtn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        if (this.disabled) return;
        document.getElementById('viewDrNumber').textContent = this.dataset.dr || '-';
        document.getElementById('viewPoNumber').textContent = this.dataset.po || '-';
        document.getElementById('viewCustomer').textContent = this.dataset.customer || '-';
        document.getElementById('viewDate').textContent = this.dataset.date || '-';
        var remarks = this.dataset.remarks || '-';
        var remarksEl = document.getElementById('viewRemarks');
        remarksEl.textContent = remarks;
        var reportRemarks = this.dataset.reportRemarks || '-';
        var reportRemarksEl = document.getElementById('viewReportRemarks');
        if (this.dataset.remarksType === 'report') {
            reportRemarksEl.innerHTML = '<span style="color:red;font-weight:bold;">' + reportRemarks.replace(/</g, '&lt;') + '</span>';
        } else if (this.dataset.remarksType === 'edited') {
            reportRemarksEl.innerHTML = '<span style="color:#e6a800;font-weight:bold;">' + reportRemarks.replace(/</g, '&lt;') + '</span>';
        } else {
            reportRemarksEl.textContent = reportRemarks;
        }
        var lotItems = JSON.parse(this.dataset.lotItems || '[]');
        var merged = {};
        lotItems.forEach(function(item) {
            var key = (item.lot_number || '') + '||' + (item.item_code || '');
            if (merged[key]) {
                merged[key].qty += item.qty || 0;
            } else {
                merged[key] = Object.assign({}, item);
            }
        });
        var tbody = document.getElementById('viewLotItemsBody');
        tbody.innerHTML = '';
        var total = 0;
        var totalCases = 0;
        Object.values(merged).forEach(function(item) {
            total += item.qty || 0;
            var conv = item.actual_uom_conversion || item.uom_conversion || null;
            var uom = item.item_uom || '';
            var cases = (conv && uom !== 'CS') ? Math.floor((item.qty || 0) / conv) : 0;
            totalCases += cases;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + (item.item_code || '-') + '</td>' +
                '<td>' + (item.item_description || '-') + '</td>' +
                '<td>' + (item.lot_number || '-') + '</td>' +
                '<td class="text-end">' + (item.qty || 0) + '</td>' +
                '<td class="text-end">' + (cases > 0 ? cases + ' CS' : '—') + '</td>';
            tbody.appendChild(tr);
        });
        document.getElementById('viewTotalQty').textContent = total;
        document.getElementById('viewTotalCases').textContent = totalCases > 0 ? totalCases + ' CS' : '—';

        var photoContainer = document.getElementById('viewDRPhotoContainer');
        photoContainer.innerHTML = '';
        var receipts = [];
        try { receipts = JSON.parse(this.dataset.receipts || '[]'); } catch(e) {}
        if (receipts.length > 0) {
            receipts.forEach(function(r) {
                var path = (typeof URL_ROOT !== 'undefined' ? URL_ROOT : '/') + (r.file_path || '');
                var receiptId = r.receipt_id || '';
                var wrapper = document.createElement('div');
                wrapper.className = 'position-relative d-inline-block';
                wrapper.id = 'receipt_' + receiptId;
                if (path.toLowerCase().endsWith('.pdf')) {
                    wrapper.innerHTML = '<a href="' + path + '" target="_blank" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger position-absolute deleteReceiptBtn" style="top:-6px;right:-6px;width:18px;height:18px;padding:0;font-size:10px;border-radius:50%;" data-receipt-id="' + receiptId + '" title="Remove attachment"><i class="bi bi-x"></i></button>';
                } else {
                    wrapper.innerHTML = '<a href="' + path + '" target="_blank"><img src="' + path + '" alt="DR Attachment" style="max-height:120px;border-radius:6px;border:1px solid #ddd;" onerror="this.parentElement.innerHTML=\'<span class=text-muted>File not found</span>\'"></a>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger position-absolute deleteReceiptBtn" style="top:-6px;right:-6px;width:18px;height:18px;padding:0;font-size:10px;border-radius:50%;" data-receipt-id="' + receiptId + '" title="Remove attachment"><i class="bi bi-x"></i></button>';
                }
                photoContainer.appendChild(wrapper);
            });
        } else {
            photoContainer.innerHTML = '<span class="text-muted">No attachments attached for this DR</span>';
        }

        var backloadContainer = document.getElementById('viewBackloadContainer');
        backloadContainer.innerHTML = '';
        var backloads = [];
        try { backloads = JSON.parse(this.dataset.backloads || '[]'); } catch(e) {}
        if (backloads.length > 0) {
            var blHtml = '<table class="table table-sm table-bordered mb-0"><thead><tr><th>Date</th><th>Lot</th><th>Qty Returned</th><th>Cases</th><th>Reason</th></tr></thead><tbody>';
            backloads.forEach(function(bl) {
                var casesText = (bl.cases !== null && bl.cases !== undefined) ? bl.cases + ' CS' : '-';
                blHtml += '<tr><td>' + (bl.backload_date || '-') + '</td><td>' + (bl.lot_number || '-') + '</td><td>' + bl.quantity + '</td><td>' + casesText + '</td><td>' + (bl.reason || '-') + '</td></tr>';
            });
            blHtml += '</tbody></table>';
            backloadContainer.innerHTML = blHtml;
        } else {
            backloadContainer.innerHTML = '<span class="text-muted">No backloads recorded for this delivery</span>';
        }
    });
});

document.getElementById('viewDRPhotoContainer').addEventListener('click', function(e) {
    var btn = e.target.closest('.deleteReceiptBtn');
    if (!btn) return;
    if (!confirm('Remove this attachment?')) return;
    var receiptId = btn.dataset.receiptId;
    var formData = new FormData();
    formData.append('receipt_id', receiptId);
    fetch('?controller=warehouse&action=deleteDRPhoto', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var el = document.getElementById('receipt_' + receiptId);
            if (el) el.remove();
            if (!document.getElementById('viewDRPhotoContainer').children.length) {
                document.getElementById('viewDRPhotoContainer').innerHTML = '<span class="text-muted">No attachments attached for this DR</span>';
            }
        } else {
            alert('Error: ' + (data.error || 'Failed to delete'));
        }
    })
    .catch(function(err) { alert('Error: ' + err.message); });
});

var reportDeliveryModal = null;
var reportLotItems = [];
document.querySelectorAll('.reportDeliveryBtn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        if (this.disabled) return;
        document.getElementById('reportDeliveryDr').value = this.dataset.dr || '-';
        document.getElementById('reportDeliveryRemarks').value = '';
        document.getElementById('reportDeliveryType').value = 'dr_number';
        document.getElementById('reportLotPickerRow').style.display = 'none';
        document.getElementById('reportLotPicker').innerHTML = '<option value="">-- Select Lot --</option>';

        reportLotItems = JSON.parse(this.dataset.lotItems || '[]');
        reportDeliveryModal = new bootstrap.Modal(document.getElementById('reportDeliveryModal'));
        reportDeliveryModal.show();

        document.getElementById('reportDeliveryForm').onsubmit = function(e) {
            e.preventDefault();
            var deliveryId = btn.dataset.deliveryId;
            var poId = btn.dataset.poId;
            var remarks = document.getElementById('reportDeliveryRemarks').value.trim();
            var reportType = document.getElementById('reportDeliveryType').value;
            var lotPicker = document.getElementById('reportLotPicker');
            var selectedLotIndex = lotPicker.value;

            if (!remarks) {
                alert('Please describe the concern');
                return;
            }

            var formData = new FormData();
            formData.append('delivery_id', deliveryId);
            formData.append('remarks', remarks);
            formData.append('report_type', reportType);
            formData.append('po_id', poId);
            formData.append('poi_id', btn.dataset.poiId || '');

            if (reportType === 'quantity') {
                if (selectedLotIndex === '') {
                    alert('Please select a lot');
                    return;
                }
                var lot = reportLotItems[parseInt(selectedLotIndex)];
                formData.append('lot_id', lot.lot_id);
                formData.append('poi_id', lot.poi_id);
                formData.append('old_quantity', lot.qty);
            }

            fetch('?controller=warehouse&action=reportDelivery', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    reportDeliveryModal.hide();
                    var cell = btn.closest('tr').querySelector('td:nth-child(10)');
                    cell.innerHTML = '<span style="color:red;font-weight:bold;">' + remarks.replace(/</g, '&lt;') + '</span>';
                    showToast('Report submitted successfully', 'success');
                } else {
                    alert('Error: ' + (data.error || 'Failed to submit report'));
                }
            })
            .catch(function(err) {
                alert('Error submitting report: ' + err.message);
            });
        };
    });
});

document.getElementById('reportDeliveryType').addEventListener('change', function() {
    var lotRow = document.getElementById('reportLotPickerRow');
    var lotPicker = document.getElementById('reportLotPicker');
    if (this.value === 'quantity') {
        lotPicker.innerHTML = '<option value="">-- Select Lot --</option>';
        reportLotItems.forEach(function(li, idx) {
            var opt = document.createElement('option');
            opt.value = idx;
            opt.textContent = (li.item_description || '') + ' | ' + (li.lot_number || '') + ' | Qty: ' + (li.qty || 0);
            lotPicker.appendChild(opt);
        });
        lotRow.style.display = 'block';
    } else {
        lotRow.style.display = 'none';
        lotPicker.value = '';
    }
});

var attachDRModal = null;
var selectedDRFile = null;

document.querySelectorAll('.attachDRBtn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        if (this.disabled) return;
        document.getElementById('attachDeliveryId').value = this.dataset.deliveryId;
        document.getElementById('attachPoId').value = this.dataset.poId;
        document.getElementById('attachDRNumber').value = this.dataset.dr || '-';
        document.getElementById('photoPreview').style.display = 'none';
        document.getElementById('drPhotoInput').value = '';
        selectedDRFile = null;
        attachDRModal = new bootstrap.Modal(document.getElementById('attachDRModal'));
        attachDRModal.show();
    });
});

var dropZone = document.getElementById('dropZone');
var drPhotoInput = document.getElementById('drPhotoInput');

dropZone.addEventListener('click', function() { drPhotoInput.click(); });
dropZone.addEventListener('dragover', function(e) { e.preventDefault(); dropZone.classList.add('border-primary', 'bg-light'); });
dropZone.addEventListener('dragleave', function() { dropZone.classList.remove('border-primary', 'bg-light'); });
dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    dropZone.classList.remove('border-primary', 'bg-light');
    if (e.dataTransfer.files.length > 0) {
        handleDRFile(e.dataTransfer.files[0]);
    }
});

drPhotoInput.addEventListener('change', function() {
    if (this.files.length > 0) {
        handleDRFile(this.files[0]);
    }
});

function handleDRFile(file) {
    var allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    if (!allowed.includes(file.type)) {
        alert('Invalid file type. Allowed: JPG, PNG, GIF, WebP, PDF');
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        alert('File size must be less than 10MB');
        return;
    }
    selectedDRFile = file;
    var previewImg = document.getElementById('previewImg');
    var previewName = document.getElementById('previewName');
    previewName.textContent = file.name;
    if (file.type === 'application/pdf') {
        previewImg.src = '';
        previewImg.alt = 'PDF File';
        previewImg.style.display = 'none';
        var pdfIcon = document.createElement('div');
        pdfIcon.innerHTML = '<i class="bi bi-file-earmark-pdf text-danger" style="font-size:3rem;"></i><br><small class="text-muted">' + file.name + '</small>';
        var existing = document.getElementById('previewPdfIcon');
        if (existing) existing.remove();
        pdfIcon.id = 'previewPdfIcon';
        document.getElementById('photoPreview').appendChild(pdfIcon);
    } else {
        var existingPdf = document.getElementById('previewPdfIcon');
        if (existingPdf) existingPdf.remove();
        previewImg.style.display = 'block';
        var reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
    document.getElementById('photoPreview').style.display = 'block';
}

document.getElementById('submitDRPhotoBtn').addEventListener('click', function() {
    if (!selectedDRFile) {
        alert('Please select a file first');
        return;
    }
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Uploading...';
    var formData = new FormData();
    formData.append('delivery_id', document.getElementById('attachDeliveryId').value);
    formData.append('po_id', document.getElementById('attachPoId').value);
    formData.append('dr_photo', selectedDRFile);

    fetch('?controller=warehouse&action=uploadDRPhoto', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-upload me-1"></i>Upload';
        if (data.success) {
            showToast('File uploaded successfully!', 'success');
            document.getElementById('drPhotoInput').value = '';
            document.getElementById('photoPreview').style.display = 'none';
            var pdfIcon = document.getElementById('previewPdfIcon');
            if (pdfIcon) pdfIcon.remove();
            selectedDRFile = null;
        } else {
            alert('Error: ' + (data.error || 'Failed to upload'));
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-upload me-1"></i>Upload';
        alert('Error uploading file: ' + err.message);
    });
});

function showToast(message, type) {
    var toast = document.createElement('div');
    toast.className = 'alert alert-' + (type || 'success') + ' alert-dismissible fade show position-fixed';
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    toast.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    document.body.appendChild(toast);
    setTimeout(function() { if (toast.parentNode) toast.remove(); }, 3000);
}

var backloadModal = null;
document.querySelectorAll('.backloadBtn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var deliveryId = this.dataset.deliveryId;
        document.getElementById('backloadDeliveryId').value = deliveryId;
        document.getElementById('backloadDrNumber').textContent = this.dataset.dr || '-';
        document.getElementById('backloadPoNumber').textContent = this.dataset.po || '-';
        document.getElementById('backloadLotsBody').innerHTML = '<tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>';

        fetch('?controller=warehouse&action=getDeliveryLotsForBackload&delivery_id=' + deliveryId)
            .then(function(r) { return r.json(); })
            .then(function(lots) {
                var tbody = document.getElementById('backloadLotsBody');
                if (lots.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No lots available for backload</td></tr>';
                    return;
                }
                var html = '';
                lots.forEach(function(lot, idx) {
                    var conv = lot.uom_conversion || 0;
                    var isCases = lot.item_uom !== 'CS' && conv > 0;
                    var deliveredCS = isCases ? Math.floor(lot.delivered_qty / conv) : lot.delivered_qty;
                    var returnedCS = isCases ? Math.floor(lot.already_backloaded / conv) : lot.already_backloaded;
                    var maxCS = isCases ? Math.floor(lot.available_to_backload / conv) : lot.available_to_backload;
                    var labelCS = isCases ? ' CS' : '';
                    html += '<tr>';
                    html += '<td>' + (lot.item_description || lot.item_code || '-') + '</td>';
                    html += '<td>' + (lot.lot_number || '-') + '</td>';
                    html += '<td>' + deliveredCS + labelCS + '</td>';
                    html += '<td>' + returnedCS + labelCS + '</td>';
                    html += '<td><strong>' + maxCS + labelCS + '</strong></td>';
                    html += '<td><input type="number" name="backload_qty[]" class="form-control form-control-sm backload-cs-input" min="1" max="' + maxCS + '" placeholder="0" data-conv="' + conv + '" data-max-pcs="' + lot.available_to_backload + '" data-idx="' + idx + '"></td>';
                    html += '<td class="text-muted" id="backloadPcs_' + idx + '">0 pcs</td>';
                    html += '<td><input type="text" name="backload_reason[]" class="form-control form-control-sm" placeholder="Reason (e.g. dented box)"></td>';
                    html += '<input type="hidden" name="lot_id[]" value="' + lot.lot_id + '">';
                    html += '<input type="hidden" name="backload_cases[]" class="backload-cases-hidden" data-idx="' + idx + '" value="">';
                    html += '</tr>';
                });
                tbody.innerHTML = html;

                tbody.querySelectorAll('.backload-cs-input').forEach(function(input) {
                    input.addEventListener('input', function() {
                        var conv = parseInt(this.dataset.conv) || 1;
                        var maxPcs = parseInt(this.dataset.maxPcs) || 0;
                        var cs = parseInt(this.value) || 0;
                        var maxCS = Math.floor(maxPcs / conv);
                        var pcs = cs * conv;
                        var idx = this.dataset.idx;

                        if (cs > maxCS && cs > 0) {
                            this.value = maxCS;
                            cs = maxCS;
                            pcs = maxCS * conv;
                            this.classList.add('is-invalid');
                            var feedback = this.parentNode.querySelector('.invalid-feedback');
                            if (!feedback) {
                                feedback = document.createElement('div');
                                feedback.className = 'invalid-feedback';
                                this.parentNode.appendChild(feedback);
                            }
                            feedback.textContent = 'Cannot exceed delivered amount (' + maxCS + ' CS)';
                        } else {
                            this.classList.remove('is-invalid');
                            var fb = this.parentNode.querySelector('.invalid-feedback');
                            if (fb) fb.remove();
                        }

                        var casesHidden = tbody.querySelector('.backload-cases-hidden[data-idx="' + idx + '"]');
                        if (casesHidden) casesHidden.value = cs > 0 ? cs : '';

                        document.getElementById('backloadPcs_' + idx).textContent = pcs + ' pcs';
                        var totalPcs = 0;
                        tbody.querySelectorAll('.backload-cs-input').forEach(function(inp) {
                            var c = parseInt(inp.dataset.conv) || 1;
                            var v = parseInt(inp.value) || 0;
                            totalPcs += v * c;
                        });
                        document.getElementById('backloadTotalPcs').textContent = totalPcs;
                    });
                });
            });

        backloadModal = new bootstrap.Modal(document.getElementById('backloadModal'));
        backloadModal.show();
    });
});

document.getElementById('submitBackloadBtn').addEventListener('click', function() {
    var form = document.getElementById('backloadForm');
    var lotIds = form.querySelectorAll('input[name="lot_id[]"]');
    var csInputs = form.querySelectorAll('input[name="backload_qty[]"]');
    var reasons = form.querySelectorAll('input[name="backload_reason[]"]');
    var hasValid = false;
    var previewRows = [];
    var totalPcs = 0;

    for (var i = 0; i < lotIds.length; i++) {
        var cs = parseInt(csInputs[i].value) || 0;
        var conv = parseInt(csInputs[i].dataset.conv) || 1;
        var maxPcs = parseInt(csInputs[i].dataset.maxPcs) || 0;
        var maxCS = Math.floor(maxPcs / conv);
        var pcs = cs * conv;
        var reason = reasons[i].value.trim();
        var row = csInputs[i].closest('tr');
        var itemDesc = row ? row.cells[0].textContent : '-';
        var lotNum = row ? row.cells[1].textContent : '-';

        if (cs > maxCS) {
            alert('Return quantity for "' + lotNum + '" (' + cs + ' CS) exceeds the delivered amount (' + maxCS + ' CS). Please correct it.');
            csInputs[i].focus();
            return;
        }

        if (pcs > 0 && reason) {
            hasValid = true;
            totalPcs += pcs;
            previewRows.push({ item: itemDesc, lot: lotNum, pcs: pcs, cs: cs, reason: reason });
        } else if (pcs > 0 && !reason) {
            alert('Please enter a reason for all returned quantities.');
            reasons[i].focus();
            return;
        }
    }

    if (!hasValid) {
        alert('Please enter a return quantity for at least one lot.');
        return;
    }

    document.getElementById('prevBackloadDr').textContent = document.getElementById('backloadDrNumber').textContent;
    document.getElementById('prevBackloadPo').textContent = document.getElementById('backloadPoNumber').textContent;
    document.getElementById('prevBackloadTotal').textContent = totalPcs;

    var tbody = document.getElementById('prevBackloadBody');
    tbody.innerHTML = '';
        previewRows.forEach(function(r) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + r.item + '</td><td>' + r.lot + '</td><td class="fw-bold">' + r.cs + ' CS (' + r.pcs + ' pcs)</td><td>' + r.reason + '</td>';
            tbody.appendChild(tr);
        });

    var previewModal = new bootstrap.Modal(document.getElementById('backloadPreviewModal'));
    previewModal.show();
    });

document.getElementById('confirmBackloadBtn').addEventListener('click', function() {
    bootstrap.Modal.getInstance(document.getElementById('backloadPreviewModal')).hide();
    var form = document.getElementById('backloadForm');
    var csInputs = form.querySelectorAll('input[name="backload_qty[]"]');
    csInputs.forEach(function(input) {
        var conv = parseInt(input.dataset.conv) || 1;
        var cs = parseInt(input.value) || 0;
        input.value = cs * conv;
    });
    form.submit();
});
</script>