<h4><i class="bi bi-box-seam me-2"></i>Finished Goods(FG)</h4>
<p class="text-muted mb-4">Input finished goods production</p>

<div class="card data-card">
    <div class="card-body">
        <form method="POST" action="?controller=production&action=saveFgInput" id="fgInputForm" novalidate>
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Prepared by *</label>
                    <input type="text" name="prepared_by_name" class="form-control" required placeholder="e.g. Juan Dela Cruz" value="<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Checked by</label>
                    <input type="text" name="checked_by_name" class="form-control" placeholder="Optional">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Received by</label>
                    <input type="text" name="received_by_name" class="form-control" placeholder="Optional">
                </div>
            </div>

            <hr>

            <div class="mb-3">
                <label class="form-label fw-bold mb-0">Lot Entries</label>
            </div>

            <div id="lotContainer">
                <div class="lot-entry mb-3 border rounded p-3 bg-light" data-entry-index="0">
                    <div class="row g-2 mb-2">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Select Item *</label>
                            <div class="position-relative">
                                <input type="text" name="item_search[]" class="form-control item-search-input" placeholder="Search by item code or description..." autocomplete="off" required>
                                <input type="hidden" name="item_id[]" class="selected-item-id" value="">
                                <div class="item-dropdown position-absolute w-100 bg-white border rounded shadow-sm d-none" style="z-index: 1050; max-height: 300px; overflow-y: auto;"></div>
                            </div>
                            <div class="selected-item-info mt-1 d-none">
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i><span class="selected-item-name"></span></span>
                                <button type="button" class="btn btn-sm btn-link text-danger clear-item-btn"><i class="bi bi-x"></i> Clear</button>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 align-items-start">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">LOT NUMBER <span class="text-danger">*</span></label>
                            <select name="lot_number[]" class="form-select lot-number-select" data-entry-item="" required>
                                <option value="">Select item first</option>
                            </select>
                            <div class="lot-live-info text-muted small" style="min-height:1.2em;"></div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">ADD QUANTITY <span class="text-danger">*</span></label>
                            <input type="number" name="added_quantity[]" class="form-control" min="1" required>
                            <div class="lot-live-preview text-muted small" style="position:absolute; bottom:-1.4em; left:0; right:0; min-height:1.2em;"></div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">SHIFT <span class="text-danger">*</span></label>
                            <select name="shift[]" class="form-select filter-select" required>
                                <option value="">-- Select --</option>
                                <option value="1st Shift">1st Shift</option>
                                <option value="2nd Shift">2nd Shift</option>
                                <option value="3rd Shift">3rd Shift</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">STATUS</label>
                            <select name="reject_status[]" class="form-select filter-select">
                                <option value="Good">Good</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">MARK LOT AS CLOSE</label>
                            <div class="form-check form-switch d-flex align-items-center gap-2">
                                <!-- Hidden 0 keeps mark_closed[] aligned with other per-row arrays -->
                                <input type="hidden" name="mark_closed[]" value="0" class="mark-closed-flag">
                                <input type="checkbox" class="form-check-input mark-closed-toggle" onchange="this.closest('.lot-entry').querySelector('.mark-closed-flag').value = this.checked ? 1 : 0;">
                                <label class="form-check-label small">After save, close this lot</label>
                            </div>
                        </div>
                        <div class="col-md-1 text-end">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-danger btn-sm remove-lot" style="display:none;"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6">
                            <label class="form-label small">Remarks</label>
                            <input type="text" name="sts_remarks[]" class="form-control form-control-sm" placeholder="Optional remarks">
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-primary btn-sm mb-3" id="addLotBtn"><i class="bi bi-plus"></i> Add Lot</button>

            <hr>

            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">STS reference numbers will be auto-generated upon save.</small>
                <div class="d-flex gap-2">
                    <a href="?controller=production&action=purchaseOrders" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="submitBtn"><i class="bi bi-save me-2"></i>Save FG Input</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Confirm FG Input</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Please review the FG production details before saving.</p>
                <div id="prevLots"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmSaveBtn"><i class="bi bi-check-lg me-1"></i>Confirm & Save</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var saving = false;

    function setLotSelectLoading(entry) {
        var lotSelect = entry.querySelector('select[name="lot_number[]"]');
        if (!lotSelect) return;
        lotSelect.innerHTML = '<option value="">Loading LMR lots...</option>';
        lotSelect.setAttribute('data-entry-item', '');
        lotSelect.classList.remove('is-invalid');
        setLotInfo(entry, null);
    }

    function setLotInfo(entry, lot) {
        var info = entry.querySelector('.lot-live-info');
        if (!info) return;
        if (!lot) { info.textContent = ''; return; }
        // Keep figures out of the operator's view — only closed state matters
        info.innerHTML = lot.closed
            ? '<span class="text-danger fw-semibold"><i class="bi bi-lock-fill me-1"></i>This lot is Closed</span>'
            : '<span class="text-success"><i class="bi bi-unlock me-1"></i>Open lot</span>';
    }

    function renderLotSelect(entry, itemId, lots) {
        var lotSelect = entry.querySelector('select[name="lot_number[]"]');
        if (!lotSelect) return;
        entry._lmrLots = lots;
        lotSelect.innerHTML = '';
        lotSelect.setAttribute('data-entry-item', String(itemId));
        lotSelect.classList.remove('is-invalid');
        setLotInfo(entry, null);

        if (!lots || !lots.length) {
            lotSelect.appendChild(new Option('No LMR lots for this item', ''));
            return;
        }
        // Closed ones stay visible but disabled; label is the lot number only.
        var anyOpen = false;
        lots.forEach(function(lot) {
            var label = lot.lot_number + (lot.closed ? ' [Closed]' : '');
            var opt = new Option(label, lot.lot_number, false, false);
            if (lot.closed) {
                opt.disabled = true;
            } else {
                anyOpen = true;
            }
            lotSelect.appendChild(opt);
        });
        if (!anyOpen) {
            lotSelect.appendChild(new Option('— All lots closed for this item —', ''));
        }
    }

    function loadLmrLots(entry, itemId) {
        var lotSelect = entry.querySelector('select[name="lot_number[]"]');
        if (!lotSelect) return;
        setLotSelectLoading(entry);
        lotSelect.setAttribute('data-entry-item', String(itemId));
        fetch('?controller=production&action=getLmrLotsForItem&item_id=' + encodeURIComponent(itemId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function(r) { return r.json(); })
            .then(function(lots) {
                // Ignore stale responses for a previous item selection
                if (entry.querySelector('.selected-item-id').value !== String(itemId)) return;
                renderLotSelect(entry, itemId, Array.isArray(lots) ? lots : []);
            })
            .catch(function() {
                if (entry.querySelector('.selected-item-id').value !== String(itemId)) return;
                lotSelect.innerHTML = '<option value="">Failed to load LMR lots — reselect the item</option>';
                lotSelect.setAttribute('data-entry-item', '');
            });
    }

    function resetLotEntry(entry) {
        var lotSelect = entry.querySelector('select[name="lot_number[]"]');
        if (!lotSelect) return;
        lotSelect.innerHTML = '<option value="">Select item first</option>';
        lotSelect.setAttribute('data-entry-item', '');
        lotSelect.classList.remove('is-invalid');
        setLotInfo(entry, null);
    }

    function initItemSearch(entry) {
        var searchInput = entry.querySelector('.item-search-input');
        var dropdown = entry.querySelector('.item-dropdown');
        var selectedIdInput = entry.querySelector('.selected-item-id');
        var selectedItemInfo = entry.querySelector('.selected-item-info');
        var selectedItemName = entry.querySelector('.selected-item-name');
        var clearBtn = entry.querySelector('.clear-item-btn');
        var searchTimeout = null;

        searchInput.addEventListener('input', function() {
            var q = this.value.trim();
            clearTimeout(searchTimeout);
            if (q.length < 1) {
                dropdown.classList.add('d-none');
                return;
            }
            searchTimeout = setTimeout(function() {
                fetch('?controller=production&action=searchItems&q=' + encodeURIComponent(q))
                    .then(function(r) { return r.json(); })
                    .then(function(items) {
                        dropdown.innerHTML = '';
                        if (!items || items.length === 0) {
                            dropdown.innerHTML = '<div class="p-2 text-muted">No items found</div>';
                            dropdown.classList.remove('d-none');
                            return;
                        }
                        var seenIds = new Set();
                        var seenCodes = new Set();
                        items.forEach(function(item) {
                            var itemCode = (item.item_code || '').trim();
                            if (itemCode) {
                                if (seenCodes.has(itemCode)) return;
                                seenCodes.add(itemCode);
                            }
                            if (seenIds.has(item.item_id)) return;
                            seenIds.add(item.item_id);
                            var div = document.createElement('div');
                            div.className = 'p-2 border-bottom item-option';
                            div.style.cursor = 'pointer';
                            div.innerHTML = '<strong>' + (item.item_code || '') + '</strong> - ' + (item.item_description || '') +
                                '<br><small class="text-muted">UOM: ' + (item.item_uom || '') + (item.uom_conversion ? ' (' + item.uom_conversion + ' pcs/case)' : '') + '</small>';
                            div.addEventListener('click', function() {
                                selectedIdInput.value = item.item_id;
                                searchInput.value = item.item_code + ' - ' + item.item_description;
                                selectedItemName.textContent = item.item_code + ' - ' + item.item_description;
                                selectedItemInfo.classList.remove('d-none');
                                dropdown.classList.add('d-none');
                                entry.setAttribute('data-uom-conversion', item.uom_conversion || '');
                                loadLmrLots(entry, item.item_id);
                            });
                            div.addEventListener('mouseenter', function() { this.style.background = '#f0f4ff'; });
                            div.addEventListener('mouseleave', function() { this.style.background = ''; });
                            dropdown.appendChild(div);
                        });
                        dropdown.classList.remove('d-none');
                    });
            }, 300);
        });

        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length >= 1 && dropdown.children.length > 0) {
                dropdown.classList.remove('d-none');
            }
        });

        clearBtn.addEventListener('click', function() {
            selectedIdInput.value = '';
            searchInput.value = '';
            selectedItemInfo.classList.add('d-none');
            resetLotEntry(entry);
        });
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.item-search-input') && !e.target.closest('.item-dropdown')) {
            document.querySelectorAll('.item-dropdown').forEach(function(d) { d.classList.add('d-none'); });
        }
    });

    function initLotEntry(entry) {
        initItemSearch(entry);
        updateRemoveButtons();

        var qtyInput = entry.querySelector('input[name="added_quantity[]"]');
        var previewDiv = entry.querySelector('.lot-live-preview');
        if (qtyInput && previewDiv) {
            qtyInput.addEventListener('input', function() {
                var qty = parseInt(this.value) || 0;
                if (qty <= 0) {
                    previewDiv.textContent = '';
                    return;
                }
                previewDiv.innerHTML = '<i class="bi bi-calculator me-1"></i>Adding <strong>' + qty.toLocaleString() + '</strong> pcs';
            });
        }

        var lotSelect = entry.querySelector('select[name="lot_number[]"]');
        if (lotSelect) {
            lotSelect.addEventListener('change', function() {
                var lots = entry._lmrLots || [];
                var chosen = null;
                for (var i = 0; i < lots.length; i++) {
                    if (String(lots[i].lot_number) === this.value) { chosen = lots[i]; break; }
                }
                setLotInfo(entry, chosen);
            });
        }
    }

    var firstEntry = document.querySelector('#lotContainer .lot-entry');
    if (firstEntry) initLotEntry(firstEntry);

    document.getElementById('addLotBtn').addEventListener('click', function() {
        var container = document.getElementById('lotContainer');
        var entries = container.querySelectorAll('.lot-entry');
        var firstEntry = entries[0];
        var entry = firstEntry.cloneNode(true);

        entry.querySelectorAll('input').forEach(function(el) { el.value = ''; });
        entry.querySelectorAll('select').forEach(function(el) { el.selectedIndex = 0; });
        entry.querySelectorAll('.mark-closed-flag').forEach(function(el) { el.value = '0'; });
        entry.querySelectorAll('.mark-closed-toggle').forEach(function(el) { el.checked = false; });
        entry.querySelectorAll('.selected-item-info').forEach(function(el) { el.classList.add('d-none'); });
        entry.querySelectorAll('.item-dropdown').forEach(function(el) { el.classList.add('d-none'); });
        resetLotEntry(entry);
        entry.setAttribute('data-uom-conversion', '');

        container.appendChild(entry);
        initLotEntry(entry);
        if (typeof initSearchableDropdowns === 'function') {
            initSearchableDropdowns(entry);
        }
        entry.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var firstInput = entry.querySelector('.item-search-input');
        if (firstInput) firstInput.focus();
    });

    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-lot')) {
            var entry = e.target.closest('.lot-entry');
            var container = document.getElementById('lotContainer');
            if (container.querySelectorAll('.lot-entry').length > 1) {
                entry.remove();
                updateRemoveButtons();
            }
        }
    });

    function updateRemoveButtons() {
        var entries = document.querySelectorAll('#lotContainer .lot-entry');
        entries.forEach(function(entry) {
            var btn = entry.querySelector('.remove-lot');
            if (btn) btn.style.display = entries.length > 1 ? 'inline-flex' : 'none';
        });
    }

    document.getElementById('fgInputForm').addEventListener('submit', function(e) {
        e.preventDefault();

        var entries = document.querySelectorAll('#lotContainer .lot-entry');
        var firstError = null;

        // Clear previous errors
        entries.forEach(function(entry) {
            entry.querySelectorAll('.is-invalid').forEach(function(el) {
                el.classList.remove('is-invalid');
            });
            entry.style.borderColor = '';
        });

        // Validate every lot entry
        entries.forEach(function(entry, idx) {
            var itemId = entry.querySelector('.selected-item-id').value.trim();
            var lotSelect = entry.querySelector('select[name="lot_number[]"]');
            var lotNum = lotSelect.value.trim();
            var qty = parseInt(entry.querySelector('input[name="added_quantity[]"]').value) || 0;
            var shift = entry.querySelector('select[name="shift[]"]').value;

            // Skip completely empty rows
            var hasAny = itemId || lotNum || qty > 0 || shift;
            if (!hasAny) return;

            var missing = [];
            if (!itemId) {
                missing.push('Item');
                var searchInput = entry.querySelector('.item-search-input');
                if (searchInput) searchInput.classList.add('is-invalid');
            }
            if (!lotNum) {
                missing.push(lotSelect.getAttribute('data-entry-item')
                    ? 'an open LMR lot (all closed or none for this item)'
                    : 'Lot Number');
                lotSelect.classList.add('is-invalid');
            }
            if (qty <= 0) {
                missing.push('Quantity');
                entry.querySelector('input[name="added_quantity[]"]').classList.add('is-invalid');
            }
            if (!shift) {
                missing.push('Shift');
                entry.querySelector('select[name="shift[]"]').classList.add('is-invalid');
            }

            if (missing.length > 0) {
                entry.style.borderColor = 'red';
                if (!firstError) firstError = entry;
                var lotLabel = lotNum || ('Lot entry #' + (idx + 1));
                Dlg.alert('Please complete all required fields (' + missing.join(', ') + ') for ' + lotLabel + '.');
            }
        });

        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        var lotsHtml = '<table class="table table-sm table-bordered"><thead><tr><th>Item</th><th>Lot No.</th><th>Quantity</th><th>PCS/Case</th><th>Shift</th></tr></thead><tbody>';
        entries.forEach(function(entry) {
            var lotNum = entry.querySelector('select[name="lot_number[]"]').value.trim();
            var qty = entry.querySelector('input[name="added_quantity[]"]').value;
            var defaultConv = entry.getAttribute('data-uom-conversion') || '';
            var displayConv = defaultConv || '-';
            var shift = entry.querySelector('select[name="shift[]"]').value;
            var itemName = entry.querySelector('.selected-item-name') ? entry.querySelector('.selected-item-name').textContent : '';
            if (lotNum && parseInt(qty) > 0) {
                lotsHtml += '<tr><td>' + itemName + '</td><td>' + lotNum + '</td><td>' + qty + '</td><td>' + displayConv + ' <small class="text-muted">(default)</small></td><td>' + shift + '</td></tr>';
            }
        });
        lotsHtml += '</tbody></table>';
        document.getElementById('prevLots').innerHTML = lotsHtml;

        var previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
        previewModal.show();
    });

    document.getElementById('confirmSaveBtn').addEventListener('click', function() {
        if (saving) return;
        saving = true;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
        document.getElementById('fgInputForm').submit();
    });

    document.getElementById('previewModal').addEventListener('show.bs.modal', function() {
        var btn = document.getElementById('confirmSaveBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm & Save';
        saving = false;
    });
});
</script>
