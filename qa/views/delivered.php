<h4><i class="bi bi-truck me-2"></i>Deliveries</h4>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="hidden" name="controller" value="qa">
            <input type="hidden" name="action" value="delivered">
            <select name="filter_customer" class="form-select form-select-sm filter-select" style="width:170px" onchange="this.form.submit()">
                <option value="">All Customers</option>
                <?php foreach (($allCustomers ?? []) as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= ($filterCustomer ?? '') === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_item" class="form-select form-select-sm filter-select" style="width:170px" onchange="this.form.submit()">
                <option value="">All Items</option>
                <?php foreach (($allItems ?? []) as $i): ?>
                    <option value="<?= htmlspecialchars($i) ?>" <?= ($filterItem ?? '') === $i ? 'selected' : '' ?>><?= htmlspecialchars($i) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_dr" class="form-select form-select-sm filter-select" style="width:140px" onchange="this.form.submit()">
                <option value="">All DR Numbers</option>
                <?php foreach (($allDRs ?? []) as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= ($filterDR ?? '') === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_po" class="form-select form-select-sm filter-select" style="width:170px" onchange="this.form.submit()">
                <option value="">All PO Numbers</option>
                <?php foreach (($allPOs ?? []) as $p): ?>
                    <option value="<?= htmlspecialchars($p) ?>" <?= ($filterPo ?? '') === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_delivered_by" class="form-select form-select-sm filter-select" style="width:160px" onchange="this.form.submit()">
                <option value="">All Delivered By</option>
                <?php foreach (($allDeliveredBy ?? []) as $u): ?>
                    <option value="<?= htmlspecialchars($u) ?>" <?= ($filterDeliveredBy ?? '') === $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="filter_type" class="form-select form-select-sm filter-select" style="width:130px" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="normal" <?= ($filterType ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option>
            </select>
            <input type="date" name="filter_date" class="form-control form-control-sm" style="width:150px" value="<?= htmlspecialchars($filterDate ?? '') ?>" title="Filter by Delivery Date" onchange="this.form.submit()">
        </form>
        <a href="?controller=qa&action=delivered" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Clear</a>
    </div>
    <div class="search-box" style="width: 300px;">
        <form method="GET" class="d-flex align-items-center">
            <input type="hidden" name="controller" value="qa">
            <input type="hidden" name="action" value="delivered">
            <input type="hidden" name="filter_customer" value="<?= htmlspecialchars($filterCustomer ?? '') ?>">
            <input type="hidden" name="filter_item" value="<?= htmlspecialchars($filterItem ?? '') ?>">
            <input type="hidden" name="filter_dr" value="<?= htmlspecialchars($filterDR ?? '') ?>">
            <input type="hidden" name="filter_po" value="<?= htmlspecialchars($filterPo ?? '') ?>">
            <input type="hidden" name="filter_delivered_by" value="<?= htmlspecialchars($filterDeliveredBy ?? '') ?>">
            <input type="hidden" name="filter_type" value="<?= htmlspecialchars($filterType ?? '') ?>">
            <input type="hidden" name="filter_date" value="<?= htmlspecialchars($filterDate ?? '') ?>">
            <i class="bi bi-search"></i>
            <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search ?? '') ?>">
        </form>
    </div>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Customer</th>
                    <th>DR Number</th>
                    <th>SI Number</th>
                    <th>Item</th>
                    <th>Lot Number</th>
                    <th>Quantity</th>
                    <th>Delivery Date</th>
                    <th>Cases</th>
                    <th>Type</th>
                    <th>Remarks</th>
                    <th>Delivered By</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="deliveryTableBody">
                <?php if (!empty($deliveries)): ?>
                <?php foreach ($deliveries as $d):
                    $lotItems = json_decode($d['lot_items'] ?? '[]', true);
                    $hasLotItems = is_array($lotItems) && count($lotItems) > 0;
                    $isActive = ($d['active_status'] ?? 1) == 1;
                ?>
                <tr class="<?= $isActive ? '' : 'text-decoration-line-through' ?>">
                    <td><strong class="text-primary"><?= htmlspecialchars($d['customer_po_number']) ?></strong></td>
                    <td><?= htmlspecialchars($d['customer_name'] ?? '-') ?></td>
                    <td>
                        <?= htmlspecialchars($d['dr_number'] ?? '') ?: '<span class="text-muted">-</span>' ?>
                        <?php if (!empty($d['old_dr_number'])): ?>
                            <br><small style="color:#e6a800;font-weight:bold;">(old: <?= htmlspecialchars($d['old_dr_number']) ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($d['si_number'])): ?>
                            <strong class="text-success"><?= htmlspecialchars($d['si_number']) ?></strong>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($hasLotItems): ?>
                            <?php foreach ($lotItems as $idx => $li): ?>
                                <?= $idx > 0 ? '<hr class="my-1 border-secondary">' : '' ?>
                                <small><?= htmlspecialchars($li['item_description'] ?? '') ?></small>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <small><?= htmlspecialchars($d['item_code'] ?? '-') ?> - <?= htmlspecialchars($d['item_description'] ?? '') ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($hasLotItems): ?>
                            <?php foreach ($lotItems as $idx => $li): ?>
                                <?= $idx > 0 ? '<hr class="my-1 border-secondary">' : '' ?>
                                <small class="text-muted"><?= htmlspecialchars($li['lot_number'] ?? '-') ?></small>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <small class="text-muted"><?= htmlspecialchars($d['lot_number'] ?? '-') ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($hasLotItems): ?>
                            <?php foreach ($lotItems as $idx => $li): ?>
                                <?= $idx > 0 ? '<hr class="my-1 border-secondary">' : '' ?>
                                <?= formatQty($li['qty'] ?? 0) ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?= formatQty($d['delivery_quantity'] ?? 0) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= date('Y-m-d', strtotime($d['delivery_date'])) ?></td>
                    <td>
                        <?php
                        if ($hasLotItems) {
                            $grouped = [];
                            foreach ($lotItems as $li) {
                                $key = $li['item_description'] ?? $li['item_code'] ?? 'Item';
                                if (!isset($grouped[$key])) $grouped[$key] = ['qty' => 0, 'conv' => null, 'uom' => ''];
                                $grouped[$key]['qty'] += $li['qty'] ?? 0;
                                if (!empty($li['actual_uom_conversion'])) $grouped[$key]['conv'] = $li['actual_uom_conversion'];
                                elseif (empty($grouped[$key]['conv']) && !empty($li['uom_conversion'])) $grouped[$key]['conv'] = $li['uom_conversion'];
                                if (!empty($li['item_uom'])) $grouped[$key]['uom'] = $li['item_uom'];
                            }
                            $caseParts = [];
                            foreach ($grouped as $desc => $info) {
                                $c = $info['conv'];
                                $u = $info['uom'];
                                if ($c && $u !== 'CS') {
                                    $caseParts[] = htmlspecialchars($desc) . ': ' . floor($info['qty'] / $c) . ' CS';
                                }
                            }
                            echo !empty($caseParts) ? implode('<br>', $caseParts) : '<span class="text-muted">â€”</span>';
                        } else {
                            $conv = $d['actual_uom_conversion'] ?? $d['uom_conversion'] ?? null;
                            $itemUom = $d['item_uom'] ?? '';
                            $desc = $d['item_description'] ?? '';
                            if ($conv && $itemUom !== 'CS') {
                                echo htmlspecialchars($desc) . ': ' . floor(($d['delivery_quantity'] ?? 0) / $conv) . ' CS';
                            } else {
                                echo '<span class="text-muted">â€”</span>';
                            }
                        }
                        ?>
                    </td>
                    <td>
                        <span class="badge bg-secondary">Normal</span>
                    </td>
                    <td>
                        <?php if (!empty($d['remarks'])): ?>
                            <span><?= htmlspecialchars($d['remarks']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($d['delivered_by_name'] ?? '-') ?></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-primary view-po-btn"
                            data-po-id="<?= $d['po_id'] ?>"
                            data-delivery-id="<?= $d['delivery_id'] ?>"
                            data-dr="<?= htmlspecialchars($d['dr_number'] ?? '') ?>"
                            data-si="<?= htmlspecialchars($d['si_number'] ?? '') ?>"
                            data-lot-items="<?= htmlspecialchars($d['lot_items'] ?? '[]') ?>"
                            data-delivery-date="<?= date('Y-m-d', strtotime($d['delivery_date'])) ?>"
                            data-receipts="<?= htmlspecialchars(json_encode($receipts_map[$d['delivery_id']] ?? [])) ?>"
                            title="View PO Details">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr><td colspan="13" class="text-center text-muted py-4">No delivery records found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (($totalPages ?? 1) > 1): ?>
<?php
$pages = \App\Helpers\Pagination::getPageRange($page, $totalPages);
$pageParams = 'controller=qa&action=delivered';
foreach (['search', 'filter_customer', 'filter_item', 'filter_dr', 'filter_po', 'filter_delivered_by', 'filter_type', 'filter_date'] as $p) {
    $val = $GLOBALS['_GET'][$p] ?? $$p ?? '';
    if ($val !== '') $pageParams .= '&' . $p . '=' . urlencode($val);
}
?>
<nav>
    <ul class="pagination justify-content-center mt-4">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= $pageParams ?>&page=<?= $page - 1 ?>">&laquo; Prev</a>
        </li>
        <?php foreach ($pages as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php else: ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= $pageParams ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= $pageParams ?>&page=<?= $page + 1 ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<!-- View PO Details Modal -->
<div class="modal fade" id="viewPOModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>PO Details - <span id="viewPONumber"></span> <span id="viewSIBadge" class="badge bg-success ms-2" style="display:none;"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <p class="mb-1"><strong>Customer Code:</strong></p>
                        <p class="text-muted" id="viewCustomerCode">-</p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1"><strong>Customer Name:</strong></p>
                        <p class="text-muted" id="viewCustomerName">-</p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1"><strong>Customer TIN:</strong></p>
                        <p class="text-muted" id="viewCustomerTin">-</p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1"><strong>Terms:</strong></p>
                        <p class="text-muted" id="viewCustomerTerms">-</p>
                    </div>
                </div>
                <h5 class="mb-3">Items</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>Lot Number</th>
                                <th class="text-end">Delivered</th>
                                <th class="text-end">Cases</th>
                                <th>DR Number</th>
                                <th>SI Number</th>
                            </tr>
                        </thead>
                        <tbody id="viewPOItems"></tbody>
                    </table>
                </div>
                <hr>
                <h6 class="mb-2"><i class="bi bi-paperclip me-1"></i>DR Attachments</h6>
                <div id="viewDRPhotoSection">
                    <div id="viewDRPhotoContainer" class="d-flex flex-wrap gap-2"></div>
                </div>
                <hr>
                <h6 class="mb-2"><i class="bi bi-receipt me-1"></i>SI Attachments</h6>
                <div id="viewSISection">
                    <div id="viewSIContainer" class="d-flex flex-wrap gap-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-po-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const poId = this.getAttribute('data-po-id');
            const drNumber = this.getAttribute('data-dr') || '-';
            const siNumber = this.getAttribute('data-si') || '';
            var receipts = [];
            try { receipts = JSON.parse(this.getAttribute('data-receipts') || '[]'); } catch(e) {}
            const lotItemsRaw = this.getAttribute('data-lot-items') || '[]';
            const lotItemsRawArr = JSON.parse(lotItemsRaw);
            const mergedLI = {};
            lotItemsRawArr.forEach(function(item) {
                var key = (item.lot_number || '') + '||' + (item.item_code || '');
                if (mergedLI[key]) { mergedLI[key].qty += item.qty || 0; } else { mergedLI[key] = Object.assign({}, item); }
            });
            const lotItems = Object.values(mergedLI);
            const hasLotItems = Array.isArray(lotItems) && lotItems.length > 0;

            fetch('?controller=warehouse&action=getPODetails&id=' + poId)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    const po = data.po;

                    document.getElementById('viewPONumber').textContent = po.customer_po_number || '-';
                    var siBadge = document.getElementById('viewSIBadge');
                    if (siNumber) {
                        siBadge.textContent = siNumber;
                        siBadge.style.display = '';
                    } else {
                        siBadge.style.display = 'none';
                    }
                    document.getElementById('viewCustomerCode').textContent = po.customer_code || '-';
                    document.getElementById('viewCustomerName').textContent = po.customer_name || '-';
                    document.getElementById('viewCustomerTin').textContent = po.customer_tin || '-';
                    document.getElementById('viewCustomerTerms').textContent = (po.customer_terms || 0) + ' days';

                    const tbody = document.getElementById('viewPOItems');
                    tbody.innerHTML = '';

                    if (hasLotItems) {
                        lotItems.forEach(function(li) {
                            var qty = li.qty || 0;
                            var conv = li.actual_uom_conversion || li.uom_conversion || null;
                            var uom = li.item_uom || '';
                            var cases = (conv && uom !== 'CS') ? Math.floor(qty / conv) : 0;

                            var row = '<tr>' +
                                '<td>' + (li.item_code || '-') + '</td>' +
                                '<td>' + (li.item_description || '-') + '</td>' +
                                '<td>' + (li.lot_number || '-') + '</td>' +
                                '<td class="text-end">' + qty + ' pcs</td>' +
                                '<td class="text-end">' + (cases > 0 ? cases + ' CS' : '---') + '</td>' +
                                '<td><span class="badge bg-secondary">' + drNumber + '</span></td>' +
                                '<td>' + (siNumber ? '<span class="badge bg-success">' + siNumber + '</span>' : '<span class="text-muted">-</span>') + '</td>' +
                                '</tr>';
                            tbody.innerHTML += row;
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No lot items found for this delivery</td></tr>';
                    }

                    const modal = new bootstrap.Modal(document.getElementById('viewPOModal'));
                    modal.show();

                    var photoContainer = document.getElementById('viewDRPhotoContainer');
                    var siContainer = document.getElementById('viewSIContainer');
                    photoContainer.innerHTML = '';
                    siContainer.innerHTML = '';

                    var drReceipts = receipts.filter(function(r) { return (r.type || 'dr') === 'dr'; });
                    var siReceipts = receipts.filter(function(r) { return r.type === 'si'; });

                    if (drReceipts.length > 0) {
                        drReceipts.forEach(function(r) {
                            var path = (typeof URL_ROOT !== 'undefined' ? URL_ROOT : '/') + (r.file_path || '');
                            var wrapper = document.createElement('div');
                            wrapper.className = 'position-relative d-inline-block';
                            if (path.toLowerCase().endsWith('.pdf')) {
                                wrapper.innerHTML = '<a href="' + path + '" target="_blank" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>';
                            } else {
                                wrapper.innerHTML = '<a href="' + path + '" target="_blank"><img src="' + path + '" alt="DR Attachment" style="max-height:120px;border-radius:6px;border:1px solid #ddd;" onerror="this.parentElement.innerHTML=\'<span class=text-muted>File not found</span>\'"></a>';
                            }
                            photoContainer.appendChild(wrapper);
                        });
                    } else {
                        photoContainer.innerHTML = '<span class="text-muted">No DR attachments</span>';
                    }

                    if (siReceipts.length > 0) {
                        siReceipts.forEach(function(r) {
                            var path = (typeof URL_ROOT !== 'undefined' ? URL_ROOT : '/') + (r.file_path || '');
                            var wrapper = document.createElement('div');
                            wrapper.className = 'position-relative d-inline-block';
                            if (path.toLowerCase().endsWith('.pdf')) {
                                wrapper.innerHTML = '<a href="' + path + '" target="_blank" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>';
                            } else {
                                wrapper.innerHTML = '<a href="' + path + '" target="_blank"><img src="' + path + '" alt="SI Attachment" style="max-height:120px;border-radius:6px;border:1px solid #ddd;" onerror="this.parentElement.innerHTML=\'<span class=text-muted>File not found</span>\'"></a>';
                            }
                            siContainer.appendChild(wrapper);
                        });
                    } else {
                        siContainer.innerHTML = '<span class="text-muted">No SI attachments</span>';
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    Dlg.alert('Failed to load PO details: ' + error.message);
                });
        });
    });
});
</script>
