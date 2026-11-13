<?php
/** Standalone printable Purchase Request Form — Cianan Corp. (MPF-001-01) */
$fmt = function ($v) { return htmlspecialchars((string) ($v ?? '')); };
$classLabel = $pr['classification'] === 'repeat_order' ? 'Repeat Order' : 'New';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $fmt($pr['pr_number']) ?> — Purchase Request Form</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Calibri, Arial, sans-serif; background: #e5e7eb; }
    .page {
        width: 8.5in; min-height: 11in; margin: 20px auto; background: #fff;
        padding: 0.4in 0.45in; position: relative; box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    @media print {
        body { background: #fff; }
        .page { margin: 0; box-shadow: none; }
        .no-print { display: none !important; }
    }
    .toolbar {
        max-width: 8.5in; margin: 10px auto 0; text-align: right;
    }
    .toolbar button {
        font: 600 13px Calibri, Arial, sans-serif; padding: 6px 16px; margin-left: 6px;
        border: none; border-radius: 4px; cursor: pointer;
    }
    .btn-print { background: #2563eb; color: #fff; }
    .btn-close-x { background: #6b7280; color: #fff; }

    .header { display: flex; align-items: center; border-bottom: 3px double #000; padding-bottom: 6px; }
    .header img { height: 58px; margin-right: 12px; }
    .header .company { flex: 1; }
    .header .company h1 { font-size: 22px; letter-spacing: 2px; }
    .header .company p { font-size: 10.5px; color: #333; }
    .header .doc-box { text-align: center; border: 1px solid #000; padding: 4px 10px; font-size: 11px; }
    .header .doc-box .doc-title { font-weight: 700; font-size: 14px; text-transform: uppercase; }
    .header .doc-box .doc-ref { font-size: 9.5px; }

    .meta { display: flex; flex-wrap: wrap; margin-top: 8px; font-size: 12px; row-gap: 3px; }
    .meta .field { width: 50%; display: flex; }
    .meta .field label { font-weight: 700; width: 110px; }
    .meta .field .val { flex: 1; border-bottom: 1px solid #000; }

    table.items { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; }
    table.items th, table.items td { border: 1px solid #000; padding: 3px 5px; }
    table.items th { background: #d9d9d9; text-align: center; font-size: 10.5px; text-transform: uppercase; }
    table.items td.c { text-align: center; }
    table.items td.r { text-align: right; }
    table.items tbody tr { height: 22px; }

    .signatures { display: flex; margin-top: 22px; gap: 12px; }
    .sig { flex: 1; text-align: center; }
    /* Blank space above the name is for the wet signature; the printed name
       sits flush on top of the signature line (2px gap). */
    .sig .line { border-top: 1px solid #000; padding-top: 2px; font-size: 11px; font-weight: 700; }
    .sig .name { font-size: 11px; min-height: 14px; margin-top: 34px; }
    .sig .role { font-size: 9.5px; color: #333; }

    .notes { margin-top: 18px; font-size: 10.5px; }
    .notes .notes-title { font-weight: 700; border-bottom: 1px solid #000; margin-bottom: 3px; }
    .notes ul { list-style: disc; padding-left: 18px; }

    .footer { position: absolute; bottom: 0.35in; left: 0.45in; right: 0.45in;
              border-top: 1px solid #000; padding-top: 3px; font-size: 9px;
              display: flex; justify-content: space-between; color: #333; }
</style>
</head>
<body>
    <div class="toolbar no-print">
        <button class="btn-close-x" onclick="window.close()">Close</button>
        <button class="btn-print" onclick="window.print()">Print PR</button>
    </div>

    <div class="page">
        <div class="header">
            <img src="<?= URL_ROOT ?>public/images/logo.png" alt="Cianan Corp.">
            <div class="company">
                <h1>CIANAN CORP.</h1>
                <p>Manufacturing Excellence — Order &amp; Billing System</p>
            </div>
            <div class="doc-box">
                <div class="doc-title">Purchase Request Form</div>
                <div class="doc-ref">Reference Code: MPF-001-01</div>
            </div>
        </div>

        <div class="meta">
            <div class="field"><label>Date:</label><div class="val"><?= $fmt(date('F d, Y', strtotime($pr['date_created']))) ?></div></div>
            <div class="field"><label>Department:</label><div class="val"><?= $fmt(ucfirst($pr['department'])) ?></div></div>
            <div class="field"><label>Classification:</label><div class="val"><?= $fmt($classLabel) ?></div></div>
            <div class="field"><label>Purpose / Project:</label><div class="val"><?= $fmt($pr['purpose']) ?></div></div>
            <div class="field"><label>Priority Level:</label><div class="val"><?= $fmt(ucfirst($pr['priority'])) ?></div></div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:34px">No.</th>
                    <th style="width:90px">Item Code</th>
                    <th>Item Name / Description</th>
                    <th style="width:95px">Item Specs</th>
                    <th style="width:75px">Dimension</th>
                    <th style="width:55px">Qty</th>
                    <th style="width:55px">U/M</th>
                    <th style="width:130px">Preferred Supplier</th>
                </tr>
            </thead>
            <tbody>
                <?php $rows = max(10, count($items)); for ($i = 0; $i < $rows; $i++): $it = $items[$i] ?? null; ?>
                <tr>
                    <td class="c"><?= $i + 1 ?></td>
                    <td><?= $it ? $fmt($it['item_code']) : '' ?></td>
                    <td><?= $it ? $fmt($it['item_name']) : '' ?></td>
                    <td><?= $it ? $fmt($it['specs']) : '' ?></td>
                    <td><?= $it ? $fmt($it['dimension']) : '' ?></td>
                    <td class="r"><?= $it && $it['qty'] !== null ? $fmt(rtrim(rtrim(number_format((float) $it['qty'], 2), '0'), '.')) : '' ?></td>
                    <td class="c"><?= $it ? $fmt($it['uom']) : '' ?></td>
                    <td><?= $it ? $fmt($it['preferred_supplier']) : '' ?></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div class="signatures">
            <div class="sig">
                <div class="name"><?= $fmt($pr['prepared_by']) ?></div>
                <div class="line">Prepared by</div>
                <div class="role">Requestor</div>
            </div>
            <div class="sig">
                <div class="name"><?= $fmt($pr['checked_by']) ?></div>
                <div class="line">Checked by</div>
                <div class="role">Supervisor</div>
            </div>
            <div class="sig">
                <div class="name"><?= $fmt($pr['approved_by_name']) ?></div>
                <div class="line">Approved by</div>
                <div class="role">President</div>
            </div>
            <div class="sig">
                <div class="name"><?= $fmt($pr['received_by']) ?></div>
                <div class="line">Received by</div>
                <div class="role">Procurement</div>
            </div>
        </div>

        <div class="notes">
            <div class="notes-title">Notes / Supporting Documents</div>
            <?php
            $notesText = trim((string) $pr['notes']);
            $notesList = $notesText !== '' ? preg_split('/\r\n|\r|\n/', $notesText) : [];
            ?>
            <?php if ($notesList): ?>
            <ul>
                <?php foreach ($notesList as $n): if (trim($n) === '') continue; ?>
                <li><?= $fmt(trim($n)) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <div class="footer">
            <span>CIANAN CORP. — Purchase Request Form</span>
            <span>MPF-001-01</span>
        </div>
    </div>
</body>
</html>
