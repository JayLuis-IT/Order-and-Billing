<div class="card data-card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-funnel me-2"></i>Filters</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-end" id="drFilterForm">
            <input type="hidden" name="controller" value="admin">
            <input type="hidden" name="action" value="deliveryReport">
            <div>
                <label class="form-label small text-muted">Search</label>
                <input type="text" name="search" id="drSearch" class="form-control form-control-sm" placeholder="DR#, SI#, PO#, Customer, Item..." value="<?= htmlspecialchars($filters['search']) ?>" style="width:280px">
            </div>
            <div>
                <label class="form-label small text-muted">Customer</label>
                <select name="customer_id" class="form-select form-select-sm filter-select" style="width:200px" onchange="document.getElementById('drFilterForm').submit()">
                    <option value="">All Customers</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['customer_id'] ?>" <?= $filters['customer_id'] == $c['customer_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label small text-muted">Delivery Date</label>
                <div style="position:relative; width:200px;">
                    <input type="text" id="drDateRange" class="form-control form-control-sm" placeholder="Select Date Range" readonly style="width:100%; background-color:#fff; cursor:pointer;" title="Click to select delivery date range" value="<?= htmlspecialchars(($filters['date_from'] !== '' ? date('m/d/Y', strtotime($filters['date_from'])) : '') . ($filters['date_to'] !== '' ? ' - ' . date('m/d/Y', strtotime($filters['date_to'])) : '')) ?>">
                    <input type="date" name="date_from" id="drDateFrom" value="<?= htmlspecialchars($filters['date_from']) ?>" style="position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; pointer-events:none; border:0; z-index:-1;">
                    <input type="date" name="date_to" id="drDateTo" value="<?= htmlspecialchars($filters['date_to']) ?>" style="position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; pointer-events:none; border:0; z-index:-1;">
                    <div id="drCalendarPopup" style="display:none; position:absolute; top:calc(100% + 4px); left:0; z-index:1060; background:#fff; border:1px solid #dee2e6; border-radius:8px; box-shadow:0 6px 20px rgba(0,0,0,.18); padding:10px; width:270px;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <button type="button" id="drCalPrev" class="btn btn-sm btn-light border-0"><i class="bi bi-chevron-left"></i></button>
                            <strong id="drCalMonthLabel" style="font-size:.9rem;"></strong>
                            <button type="button" id="drCalNext" class="btn btn-sm btn-light border-0"><i class="bi bi-chevron-right"></i></button>
                        </div>
                        <div style="display:grid; grid-template-columns:repeat(7,1fr); text-align:center; font-size:.7rem; font-weight:600; color:#6c757d; margin-bottom:2px;">
                            <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                        </div>
                        <div id="drCalDays" style="display:grid; grid-template-columns:repeat(7,1fr); text-align:center; gap:2px;"></div>
                        <div id="drCalHint" class="text-center mt-2" style="font-size:.78rem; color:#8a6d00; background:#fff8dc; border-radius:6px; padding:4px 6px;">Select the starting date</div>
                    </div>
                </div>
            </div>
            <?php
            $exportParams = http_build_query(array_filter([
                'controller' => 'admin',
                'action' => 'exportDeliveryReport',
                'search' => $filters['search'],
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'customer_id' => $filters['customer_id'],
            ]));
            ?>
            <button type="button" class="btn btn-sm btn-success" id="drExportPreviewBtn"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</button>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
            <a href="?controller=admin&action=deliveryReport" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-circle me-1"></i>Clear</a>
        </form>
    </div>
</div>

<div class="card data-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-truck me-2"></i>Delivery Records</h6>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary"><?= count($deliveries) ?> records</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>DR Number</th>
                    <th>SI Number</th>
                    <th>Customer</th>
                    <th>PO Number</th>
                    <th>Item</th>
                    <th>Item Code</th>
                    <th>Lot Number</th>
                    <th>Quantity</th>
                    <th>Cases</th>
                    <th>Plate No.</th>
                    <th>Vehicle</th>
                    <th>Logistic Provider</th>
                    <th>Type</th>
                    <th>Delivery Date</th>
                    <th>Delivered By</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deliveries)): ?>
                    <tr>
                        <td colspan="17" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No delivery records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $rowNum = 0; ?>
                    <?php foreach ($deliveries as $d): ?>
                        <?php
                        $lotItems = json_decode($d['lot_items'] ?? '[]', true);
                        if (!is_array($lotItems) || count($lotItems) === 0) {
                            $lotItems = [[
                                'item_description' => $d['item_description'] ?? 'Unknown',
                                'lot_number' => $d['lot_number'] ?? '—',
                                'qty' => $d['delivery_quantity'] ?? 0,
                                'actual_uom_conversion' => $d['actual_uom_conversion'] ?? null,
                                'uom_conversion' => $d['uom_conversion'] ?? null,
                                'item_uom' => $d['item_uom'] ?? ''
                            ]];
                        }
                        $totalLotRows = count($lotItems);
                        $rowNum++;

                        $grouped = [];
                        foreach ($lotItems as $li) {
                            $key = $li['item_description'] ?? $li['item_code'] ?? 'Unknown';
                            if (!isset($grouped[$key])) $grouped[$key] = ['code' => $li['item_code'] ?? '', 'items' => []];
                            $grouped[$key]['items'][] = $li;
                        }
                        ?>
                        <?php foreach ($grouped as $itemName => $group): ?>
                            <?php $items = $group['items']; ?>
                            <?php $itemRowCount = count($items); ?>
                            <?php foreach ($items as $idx => $li): ?>
                                <?php
                                $lotNo = htmlspecialchars($li['lot_number'] ?? '?');
                                $qty = intval($li['qty'] ?? 0);
                                $conv = $li['actual_uom_conversion'] ?? $li['uom_conversion'] ?? null;
                                $uom = $li['item_uom'] ?? '';
                                $cases = '';
                                if ($conv && $uom !== 'CS') {
                                    $cases = floor($qty / $conv) . ' CS';
                                }
                                ?>
                                <tr>
                                    <?php if ($itemName === array_key_first($grouped) && $idx === 0): ?>
                                        <td rowspan="<?= $totalLotRows ?>" class="text-muted align-middle"><?= $rowNum ?></td>
                                        <td rowspan="<?= $totalLotRows ?>" class="align-middle">
                                            <?php if (!empty($d['dr_number'])): ?>
                                                <span class="badge bg-success"><?= htmlspecialchars($d['dr_number']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td rowspan="<?= $totalLotRows ?>" class="align-middle">
                                            <?php if (!empty($d['si_number'])): ?>
                                                <span class="badge bg-info"><?= htmlspecialchars($d['si_number']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td rowspan="<?= $totalLotRows ?>" class="align-middle"><?= htmlspecialchars($d['customer_name'] ?? '') ?></td>
                                        <td rowspan="<?= $totalLotRows ?>" class="align-middle"><?= htmlspecialchars($d['customer_po_number'] ?? '') ?></td>
                                    <?php endif; ?>
                                    <?php if ($idx === 0): ?>
                                        <td rowspan="<?= $itemRowCount ?>" class="align-middle fw-semibold"><?= htmlspecialchars($itemName) ?></td>
                                        <td rowspan="<?= $itemRowCount ?>" class="align-middle"><?= htmlspecialchars($group['code']) ?></td>
                                    <?php endif; ?>
                                    <td><?= $lotNo ?></td>
                                    <td class="fw-bold"><?= number_format($qty) ?></td>
                                    <td><?= $cases ?></td>
                                    <?php if ($itemName === array_key_first($grouped) && $idx === 0): ?>
                                        <td rowspan="<?= $totalLotRows ?>" class="align-middle"><?= htmlspecialchars($d['plate_number'] ?? '—') ?></td>
                                        <td rowspan="<?= $totalLotRows ?>" class="align-middle"><?= htmlspecialchars($d['vehicle_type'] ?? '—') ?></td>
                                        <td rowspan="<?= $totalLotRows ?>" class="align-middle"><?= htmlspecialchars($d['logistic_provider'] ?? '—') ?></td>
                                        <td rowspan="<?= $totalLotRows ?>" class="align-middle"><span class="badge bg-secondary"><?= htmlspecialchars($d['production_type'] ?? '') ?></span></td>
                                        <td rowspan="<?= $totalLotRows ?>" class="align-middle"><?= htmlspecialchars($d['delivery_date'] ?? '') ?></td>
                                        <td rowspan="<?= $totalLotRows ?>" class="align-middle"><?= htmlspecialchars($d['delivered_by_name'] ?? '') ?></td>
                                        <td rowspan="<?= $totalLotRows ?>" class="align-middle" style="max-width:200px">
                                            <?php if (!empty($d['report_remarks'])): ?>
                                                <span class="text-danger small" title="<?= htmlspecialchars($d['report_remarks']) ?>">
                                                    <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars(mb_strimwidth($d['report_remarks'], 0, 30, '...')) ?>
                                                </span>
                                            <?php elseif (!empty($d['remarks'])): ?>
                                                <span class="text-muted small"><?= htmlspecialchars(mb_strimwidth($d['remarks'], 0, 30, '...')) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var _drSearchTimer;
    var searchInput = document.getElementById('drSearch');
    if (searchInput) {
        if (sessionStorage.getItem('drReportScroll')) {
            window.scrollTo(0, parseInt(sessionStorage.getItem('drReportScroll')));
            sessionStorage.removeItem('drReportScroll');
            if (searchInput.value) { searchInput.focus(); var _len = searchInput.value.length; searchInput.setSelectionRange(_len, _len); }
        }
        searchInput.addEventListener('input', function() {
            clearTimeout(_drSearchTimer);
            _drSearchTimer = setTimeout(function() {
                sessionStorage.setItem('drReportScroll', window.scrollY);
                document.getElementById('drFilterForm').submit();
            }, 600);
        });
    }

    // Custom two-step date range calendar with yellow highlights
    var rangeInput = document.getElementById('drDateRange');
    var dateFrom = document.getElementById('drDateFrom');
    var dateTo = document.getElementById('drDateTo');
    var calPopup = document.getElementById('drCalendarPopup');
    var calDays = document.getElementById('drCalDays');
    var calLabel = document.getElementById('drCalMonthLabel');
    var calHint = document.getElementById('drCalHint');
    var picking = 'from';
    var pickStart = null;
    var pickEnd = null;
    var viewYear, viewMonth;
    var monthNamesCal = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    var defaultPlaceholder = rangeInput.getAttribute('placeholder');

    function pad2(n) { return (n < 10 ? '0' : '') + n; }
    function ymd(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }

    function updateRangeDisplay() {
        var from = dateFrom.value ? new Date(dateFrom.value + 'T00:00:00').toLocaleDateString('en-US') : '';
        var to = dateTo.value ? new Date(dateTo.value + 'T00:00:00').toLocaleDateString('en-US') : '';
        rangeInput.value = (from || to) ? (from + (to ? ' - ' + to : '')) : '';
        rangeInput.placeholder = defaultPlaceholder;
    }

    function renderCalendar() {
        calLabel.textContent = monthNamesCal[viewMonth] + ' ' + viewYear;
        calHint.textContent = picking === 'from' ? 'Select the starting date' : 'Now select the ending date';
        var firstDow = new Date(viewYear, viewMonth, 1).getDay();
        var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
        var todayYmd = ymd(new Date());
        var html = '';
        for (var i = 0; i < firstDow; i++) html += '<span></span>';
        for (var d = 1; d <= daysInMonth; d++) {
            var cur = viewYear + '-' + pad2(viewMonth + 1) + '-' + pad2(d);
            var style = 'cursor:pointer; border-radius:6px; padding:4px 0; font-size:.8rem;';
            var isStart = pickStart === cur;
            var isEnd = pickStart !== null && pickEnd === cur;
            if (pickStart && cur < pickStart) {
                style += ' color:#ced4da; cursor:not-allowed;';
            } else {
                style += ' color:#212529;';
            }
            if (isStart || isEnd) {
                style += ' background:#f6c344; font-weight:700;';
            } else if (pickStart && pickEnd && cur > pickStart && cur < pickEnd) {
                style += ' background:#fff3b0;';
            } else if (cur === todayYmd) {
                style += ' border:1px solid #f6c344;';
            }
            html += '<span class="cal-day" data-date="' + cur + '" style="' + style + '">' + d + '</span>';
        }
        calDays.innerHTML = html;
        calDays.querySelectorAll('.cal-day').forEach(function(el) {
            el.addEventListener('click', function() { selectDate(this.getAttribute('data-date')); });
        });
    }

    function selectDate(dateStr) {
        if (picking === 'from') {
            pickStart = dateStr;
            pickEnd = null;
            dateFrom.value = dateStr;
            dateTo.value = '';
            picking = 'to';
            var d = new Date(dateStr + 'T00:00:00');
            viewYear = d.getFullYear();
            viewMonth = d.getMonth();
            renderCalendar();
        } else {
            if (pickStart && dateStr < pickStart) return;
            pickEnd = dateStr;
            dateTo.value = dateStr;
            picking = 'from';
            calPopup.style.display = 'none';
            updateRangeDisplay();
            document.getElementById('drFilterForm').submit();
        }
    }

    rangeInput.addEventListener('click', function(e) {
        e.stopPropagation();
        if (calPopup.style.display === 'none') {
            picking = 'from';
            pickStart = null;
            pickEnd = null;
            dateFrom.value = '';
            dateTo.value = '';
            rangeInput.value = '';
            rangeInput.placeholder = 'Select the starting date';
            var now = new Date();
            viewYear = now.getFullYear();
            viewMonth = now.getMonth();
            calPopup.style.display = '';
            renderCalendar();
        } else {
            calPopup.style.display = 'none';
        }
    });

    document.getElementById('drCalPrev').addEventListener('click', function(e) {
        e.stopPropagation();
        viewMonth--;
        if (viewMonth < 0) { viewMonth = 11; viewYear--; }
        renderCalendar();
    });
    document.getElementById('drCalNext').addEventListener('click', function(e) {
        e.stopPropagation();
        viewMonth++;
        if (viewMonth > 11) { viewMonth = 0; viewYear++; }
        renderCalendar();
    });
    calPopup.addEventListener('click', function(e) { e.stopPropagation(); });
    document.addEventListener('click', function(e) {
        if (!calPopup.contains(e.target) && e.target !== rangeInput) calPopup.style.display = 'none';
    });

    // Export preview modal
    window.exportRowsData = <?= json_encode($deliveries ? array_merge(...array_map(function($d) {
        $lotItems = json_decode($d['lot_items'] ?? '[]', true);
        if (!is_array($lotItems) || count($lotItems) === 0) {
            $lotItems = [[
                'item_description' => $d['item_description'] ?? 'Unknown',
                'lot_number' => $d['lot_number'] ?? '—',
                'actual_uom_conversion' => $d['actual_uom_conversion'] ?? null,
                'uom_conversion' => $d['uom_conversion'] ?? null,
            ]];
        }
        $out = [];
        foreach ($lotItems as $li) {
            $conv = $li['actual_uom_conversion'] ?? $li['uom_conversion'] ?? null;
            $out[] = [
                'si_number' => $d['si_number'] ?? '',
                'customer' => $d['customer_name'] ?? '',
                'dr_date' => !empty($d['delivery_date']) ? date('Y-m-d', strtotime($d['delivery_date'])) : '',
                'dr_number' => $d['dr_number'] ?? '',
                'item' => $li['item_description'] ?? '',
                'qty' => intval($li['qty'] ?? 0),
                'lot' => $li['lot_number'] ?? '',
                'multiplier' => ($conv && intval($conv) > 0) ? intval($conv) : '',
            ];
        }
        return $out;
    }, $deliveries)) : []) ?>;
});

var drExportCols = ['si_number', 'customer', 'dr_date', 'dr_number', 'item', 'qty', 'lot', 'multiplier'];
var drExportLabels = { si_number: 'SI Number', customer: 'Customer', dr_date: 'DR Date', dr_number: 'DR Number', item: 'Item Name', lot: 'Lot Number', multiplier: 'Multiplier (PCS to CS)' };


</script>

<!-- Export Preview Modal -->
<div class="modal fade" id="drExportPreviewModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-excel me-2"></i>Export Delivery Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted">Preview (first 100 rows)</small>
                    <small class="text-muted" id="drExportPreviewCount"></small>
                </div>
                <div class="table-responsive border rounded" style="max-height: 450px; overflow: auto;">
                    <table class="table table-sm table-bordered mb-0" id="drExportPreviewTable">
                        <thead class="table-light" id="drExportPreviewHead" style="position: sticky; top: 0;">
                        </thead>
                        <tbody id="drExportPreviewBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="drExportDownloadBtn"><i class="bi bi-download me-1"></i>Download Excel</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var drExportBaseParams = '<?= $exportParams ?>';

    function renderDrPreview() {
        var simpleKeys = ['si_number', 'customer', 'dr_date', 'dr_number', 'item', 'lot', 'multiplier'];
        var head = document.getElementById('drExportPreviewHead');
        var r1 = '<tr>', r2 = '<tr>';
        drExportCols.forEach(function(c) {
            if (c === 'qty') {
                r1 += '<th colspan="2" class="text-center">Qty Delivered</th>';
                r2 += '<th class="text-center">PCS</th><th class="text-center">CS</th>';
            } else {
                r1 += '<th rowspan="2" class="align-middle">' + drExportLabels[c] + '</th>';
            }
        });
        head.innerHTML = r1 + '</tr>' + r2 + '</tr>';
        var exportRows = window.exportRowsData || [];
        document.getElementById('drExportPreviewCount').textContent = exportRows.length + ' rows';
        var shown = exportRows.slice(0, 100);
        function esc(v) { var d = document.createElement('div'); d.textContent = v; return d.innerHTML; }
        document.getElementById('drExportPreviewBody').innerHTML = shown.map(function(r) {
            return '<tr>' + drExportCols.map(function(c) {
                if (c === 'qty') {
                    var mult = parseInt(r.multiplier) || 0;
                    var cs = mult > 0 ? Math.floor(r.qty / mult) : r.qty;
                    return '<td>' + esc(String(r.qty)) + ' pcs</td><td>' + esc(String(cs)) + ' cs</td>';
                }
                return '<td>' + esc(String(r[c])) + '</td>';
            }).join('') + '</tr>';
        }).join('');
    }

    document.getElementById('drExportPreviewBtn').addEventListener('click', function() {
        var modal = new bootstrap.Modal(document.getElementById('drExportPreviewModal'));
        modal.show();
    });
    document.getElementById('drExportPreviewModal').addEventListener('shown.bs.modal', renderDrPreview);

    document.getElementById('drExportDownloadBtn').addEventListener('click', function() {
        window.location.href = 'index.php?' + drExportBaseParams;
    });
});
</script>









