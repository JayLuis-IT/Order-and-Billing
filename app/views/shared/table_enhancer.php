<?php /* Shared table enhancement: MRP/transaction group separators + client-side pagination.
   Auto-detects tables whose header has a REF / MRP / SOURCE column (separators) and
   paginates any body with more than one page of rows. */ ?>
<style>
    tr.row-group-sep td {
        background: #eef3fb;
        border-left: 3px solid #0d6efd;
        font-size: .78rem;
        padding: .4rem .75rem;
        color: #41506b;
    }
    tr.row-group-sep td i { color: #0d6efd; }
    tr.row-group-sep td .badge { vertical-align: middle; margin-right: .5rem; }
    .table-enhancer-pager { padding: .65rem 1rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; border-top: 1px solid #eceff4; }
    .table-enhancer-pager .pagination { margin: 0; }
    .table-enhancer-info { font-size: .8rem; color: #77808f; }
</style>
<script>
(function() {
    var PER_PAGE = 10;

    function findSourceCol(table) {
        var headRow = table.tHead && table.tHead.rows[0];
        if (!headRow) return -1;
        for (var i = 0; i < headRow.cells.length; i++) {
            var t = (headRow.cells[i].textContent || '').trim();
            if (/REF|MRP|SOURCE/i.test(t)) return i;
        }
        return -1;
    }

    function makeSep(key, table) {
        var sep = document.createElement('tr');
        sep.className = 'row-group-sep';
        var td = sep.insertCell(-1);
        td.colSpan = table.tHead.rows[0].cells.length;
        td.innerHTML = '<i class="bi bi-diagram-3 me-1"></i>Source: <strong>' + key.replace(/</g, '&lt;') + '</strong>';
        // Pages can register per-source action buttons (e.g. View / Process
        // for a whole MRP group) in window.TABLE_GROUP_ACTIONS[sourceKey].
        var acts = (window.TABLE_GROUP_ACTIONS || {})[key];
        if (acts) {
            var wrap = document.createElement('span');
            wrap.className = 'float-end d-inline-flex gap-1';
            wrap.innerHTML = acts;
            td.appendChild(wrap);
        }
        return sep;
    }

    function groupKeyOf(text) {
        if (!text) return '';
        // Prefer the MRP reference (any of: MRP#2026-0001, MRP-#12, MRP RUN #3, MRP REF ...)
        var m = text.match(/MRP\s*[#-]\s*\S+/i) ||
                text.match(/MRP\s*(?:RUN|REF|\)?)\s*[#:-]?\s*\S+/i);
        if (m) return m[0].replace(/[^\w@#-]+$/, '').trim();
        // Placeholder for a blank PO REF — rows without a source get no separator
        // (instead of a noisy "Source: -" bar).
        if (text.replace(/\s+/g, '') === '-') return '';
        return text;
    }

    function enhanceTable(table) {
        var tbody = table.tBodies[0];
        if (!tbody) return;
        var srcCol = table.classList.contains('no-source-grouping') ? -1 : findSourceCol(table);
        var dataRows = Array.prototype.slice.call(tbody.rows).filter(function(r) {
            return r.cells.length > 1 && !r.querySelector('td[colspan]') && !r.classList.contains('row-group-sep');
        });
        if (dataRows.length <= 1) return;

        // Assign group keys from the source column (MRP ref wins over PO ref)
        dataRows.forEach(function(r) {
            r.__key = srcCol >= 0 && r.cells[srcCol]
                ? groupKeyOf((r.cells[srcCol].textContent || '').replace(/\s+/g, ' ').trim())
                : '';
        });

        // 1 · Static separators — every highest-cost-change group boundary gets a bar
        // (kept when a group is small enough for a single page; re-built per page below)
        var needsPagination = dataRows.length > PER_PAGE;
        if (!needsPagination) {
            if (srcCol >= 0 && dataRows.length >= 2) {
                var prevKey = null;
                dataRows.forEach(function(r) {
                    if (r.__key && r.__key !== prevKey) {
                        r.parentNode.insertBefore(makeSep(r.__key, table), r);
                    }
                    prevKey = r.__key;
                });
            }
            return;
        }

        // 2 · Pagination (client-side), rebuilding group separators per page
        var pages = [], cur = [];
        dataRows.forEach(function(r) {
            if (cur.length >= PER_PAGE) { pages.push(cur); cur = []; }
            cur.push(r);
        });
        if (cur.length) pages.push(cur);
        var currentPage = 0;

        function renderPage() {
            dataRows.forEach(function(r) { r.style.display = 'none'; });
            Array.prototype.slice.call(tbody.querySelectorAll('tr.row-group-sep')).forEach(function(s) { s.remove(); });
            var prevKey = null;
            (pages[currentPage] || []).forEach(function(r) {
                if (srcCol >= 0 && r.__key && r.__key !== prevKey) {
                    r.parentNode.insertBefore(makeSep(r.__key, table), r);
                }
                prevKey = r.__key;
                r.style.display = '';
            });
            info.textContent = 'Showing ' + (currentPage * PER_PAGE + 1) + '–' +
                Math.min((currentPage + 1) * PER_PAGE, dataRows.length) + ' of ' + dataRows.length + ' rows' +
                (srcCol >= 0 ? ' · grouped per transaction source' : '');
            Array.prototype.forEach.call(pagerUl.children, function(li, idx) {
                if (idx === 0) li.classList.toggle('disabled', currentPage === 0);
                else if (idx === pagerUl.children.length - 1) li.classList.toggle('disabled', currentPage === pages.length - 1);
                else li.classList.toggle('active', (idx - 1) === currentPage);
            });
        }

        var pagerUl = document.createElement('ul');
        pagerUl.className = 'pagination pagination-sm mb-0';
        function addPagerLi(label) {
            var li = document.createElement('li');
            li.className = 'page-item';
            var a = document.createElement('a');
            a.className = 'page-link';
            a.href = 'javascript:void(0)';
            a.innerHTML = label;
            li.appendChild(a);
            pagerUl.appendChild(li);
            return li;
        }
        var prevLi = addPagerLi('&laquo;');
        prevLi.addEventListener('click', function() { if (currentPage > 0) { currentPage--; renderPage(); } });
        pages.forEach(function(_, i) {
            var li = addPagerLi(String(i + 1));
            li.addEventListener('click', function() { currentPage = i; renderPage(); });
        });
        var nextLi = addPagerLi('&raquo;');
        nextLi.addEventListener('click', function() { if (currentPage < pages.length - 1) { currentPage++; renderPage(); } });

        var pagerWrap = document.createElement('div');
        pagerWrap.className = 'table-enhancer-pager';
        var info = document.createElement('span');
        info.className = 'table-enhancer-info';
        pagerWrap.appendChild(info);
        pagerWrap.appendChild(pagerUl);
        var cardBody = table.closest('.table-responsive').parentNode;
        cardBody.appendChild(pagerWrap);
        renderPage();
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.table-responsive > table').forEach(enhanceTable);
    });
})();
</script>
