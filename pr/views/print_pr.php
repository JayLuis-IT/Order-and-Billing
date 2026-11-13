<?php
/** Standalone printable Purchase Requisition — Cianan Corp. PR###### (ERP format) */
$fmt = function ($v) { return htmlspecialchars((string) ($v ?? '')); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $fmt($pr['pr_number']) ?> — Purchase Requisition</title>
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
    .toolbar { max-width: 8.5in; margin: 10px auto 0; text-align: right; }
    .toolbar button {
        font: 600 13px Calibri, Arial, sans-serif; padding: 6px 16px; margin-left: 6px;
        border: none; border-radius: 4px; cursor: pointer;
    }
    .btn-print { background: #2563eb; color: #fff; }
    .btn-close-x { background: #6b7280; color: #fff; }

    .header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
              border-bottom: 2px solid #000; padding-bottom: 8px; }
    .header .left { flex: 1; }
    .header .left h1 { font-size: 20px; letter-spacing: 2px; }
    .header .left .addr { font-size: 10px; color: #222; margin-top: 2px; max-width: 3.4in; }
    .header .left .siteline { font-size: 11px; margin-top: 8px; }
    .header .left .siteline b { display: inline-block; width: 62px; }
    .header .left .siteline .sub { display: inline-block; font-size: 10px; color: #222; vertical-align: top; max-width: 2.9in; }
    .header .right { width: 3.6in; font-size: 11px; }
    /* Clean boxes: rounded border, label/value lines — no inner gridlines. */
    .header .right .meta-box {
        flex: 1; border: 1px solid #000; border-radius: 8px;
        padding: 4px 8px; line-height: 1.45;
    }
    .header .right .boxes { display: flex; gap: 6px; }
    .header .right .meta-box .line { display: flex; }
    .header .right .meta-box .line .k { width: 0.95in; font-weight: 700; }
    .header .right .meta-box .line .sep { width: 0.16in; }
    .header .right .meta-box .line .v { flex: 1; }
    .header .right .purpose-box {
        border: 1px solid #000; border-radius: 8px;
        padding: 4px 8px; margin-top: 6px; line-height: 1.45;
    }
    .header .right .purpose-box .line { display: flex; }
    .header .right .purpose-box .line .k { width: 0.95in; font-weight: 700; }
    .header .right .purpose-box .line .sep { width: 0.16in; }

    table.items { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10.5px;
                  border-bottom: 1px solid #000; }
    table.items th, table.items td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
    /* Official format: no horizontal gridlines in the item rows — only the
       outer box and the vertical column dividers remain. */
    table.items tbody td { border-top: none; border-bottom: none; }
    table.items th { background: #d9d9d9; text-align: center; font-size: 10px; text-transform: uppercase; }
    table.items td.c { text-align: center; }
    table.items td.r { text-align: right; white-space: nowrap; }
    table.items td.num { font-size: 9.5px; white-space: nowrap; }
    table.items tbody tr.filler { height: 20px; }
    table.items td.nf-row { text-align: center; font-size: 10.5px; padding: 3px 5px; }
    .shipto-note { font-size: 10px; margin-top: 3px; color: #222; }

    .topline { display: flex; flex-direction: column; align-items: flex-end; margin-bottom: 4px; }
    .topline .prnum { font-size: 11px; }
    .topline .page-of { font-size: 10px; color: #222; }

    .signatures { display: flex; gap: 10px; margin-top: 26px; }
    .sigbox { flex: 1; border: 1px solid #000; padding: 6px; text-align: center; }
    .sigbox .space { height: 52px; }
    .sigbox .name { font-size: 10.5px; font-weight: 700; border-top: 1px solid #000; padding-top: 2px; }
    .sigbox .role { font-size: 9.5px; color: #333; }
    .sigbox .date { font-size: 9.5px; color: #333; }

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
        <div class="topline">
            <div class="prnum">PR Number: <b><?= $fmt($pr['pr_number']) ?></b></div>
            <div class="page-of">Page 1 of 1</div>
        </div>
        <div class="header">
            <div class="left">
                <h1>CIANAN CORP.</h1>
                <div class="addr">Block 7, Lot 7, Springbook St. Sterling Technopark,<br>Brgy. Maguyam, Silang, Cavite</div>
                <div class="siteline"><b>PO Site:</b> <span class="sub"><?= $fmt($poSite['name']) ?></span></div>
                <div class="siteline"><b>Ship To:</b>
                    <span class="sub">
                        <?= $fmt($shipTo['name']) ?><br>
                        <?= $fmt($shipTo['address']) ?>
                    </span>
                </div>
            </div>
            <div class="right">
                <div class="boxes">
                    <div class="meta-box">
                        <div class="line"><span class="k">Priority</span><span class="sep">:</span><span class="v"><?= $fmt(ucfirst($pr['priority'])) ?></span></div>
                        <div class="line"><span class="k">PR Status</span><span class="sep">:</span><span class="v"><?= $fmt($pr['pr_status']) ?></span></div>
                        <div class="line"><span class="k">Printed</span><span class="sep">:</span><span class="v"><?= $pr['printed'] ? 'Yes' : 'No' ?></span></div>
                    </div>
                    <div class="meta-box">
                        <div class="line"><span class="k">Date</span><span class="sep">:</span><span class="v"><?= $fmt(date('F d, Y', strtotime($pr['pr_date']))) ?></span></div>
                        <div class="line"><span class="k">Department</span><span class="sep">:</span><span class="v"><?= $fmt($pr['department']) ?></span></div>
                        <div class="line"><span class="k">Requisitioner</span><span class="sep">:</span><span class="v"><?= $fmt($pr['requisitioner']) ?></span></div>
                    </div>
                </div>
                <div class="purpose-box">
                    <div class="line"><span class="k">Purpose</span><span class="sep">:</span></div>
                    <div><?= $fmt($pr['purpose']) ?></div>
                </div>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:135px">Item Number</th>
                    <th>Description</th>
                    <th style="width:100px">Account Charged</th>
                    <th style="width:55px">Unit</th>
                    <th style="width:70px">Quantity</th>
                    <th style="width:80px">Date Req'd</th>
                </tr>
            </thead>
            <tbody>
                <?php $rows = max(8, count($items)); for ($i = 0; $i < $rows; $i++): $it = $items[$i] ?? null; ?>
                <tr<?= $it ? '' : ' class="filler"' ?>>
                    <td class="num"><?= $it ? $fmt($it['item_number']) : '' ?></td>
                    <td><?= $it ? $fmt($it['description']) : '' ?></td>
                    <td class="c"><?= $it ? $fmt($it['account_charged']) : '' ?></td>
                    <td class="c"><?= $it ? $fmt($it['unit']) : '' ?></td>
                    <td class="r"><?= $it && $it['qty'] !== null ? $fmt(number_format((float) $it['qty'], 3)) : '' ?></td>
                    <td class="c"><?= $it && $it['date_required'] ? $fmt(date('m/d/Y', strtotime($it['date_required']))) : '' ?></td>
                </tr>
                <?php endfor; ?>
                <tr>
                    <td class="nf-row" colspan="6">*** Nothing follows for Ship-to <?= $fmt($shipTo['name']) ?> ***</td>
                </tr>
            </tbody>
        </table>
        <div class="shipto-note">Ship-to: <?= $fmt($shipToCode) ?> - <?= $fmt($shipTo['name']) ?> — <?= $fmt($shipTo['address']) ?></div>

        <div class="signatures">
            <div class="sigbox">
                <div class="space"></div>
                <div class="name"><?= $fmt($pr['requisitioner']) ?></div>
                <div class="role">Requisitioned By</div>
            </div>
            <div class="sigbox">
                <div class="space"></div>
                <div class="name">&nbsp;</div>
                <div class="role">Approved By / Date</div>
            </div>
            <div class="sigbox">
                <div class="space"></div>
                <div class="name">&nbsp;</div>
                <div class="role">Received By</div>
            </div>
        </div>

        <div class="footer">
            <span>CIANAN CORP. — Purchase Requisition</span>
            <span><?= $fmt($pr['pr_number']) ?></span>
        </div>
    </div>
</body>
</html>
