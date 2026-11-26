<?php
namespace App\Helpers;

class XlsxExport {
    private $styles = [];
    private $strings = [];
    private $stringMap = [];
    private $sheets = [];
    private $currentSheet = '';


    public function __construct() {
        $this->currentSheet = 'Sheet1';
        $this->sheets[$this->currentSheet] = ['rows' => [], 'merges' => [], 'colWidths' => [], 'autoFilter' => null];
    }

    public function addSheet($name) {
        if (count($this->sheets) === 1 && isset($this->sheets['Sheet1']) && empty($this->sheets['Sheet1']['rows'])) {
            unset($this->sheets['Sheet1']);
        }
        $this->currentSheet = $name;
        if (!isset($this->sheets[$name])) {
            $this->sheets[$name] = ['rows' => [], 'merges' => [], 'colWidths' => [], 'autoFilter' => null];
        }
    }

    public function setColWidths($widths) {
        $this->sheets[$this->currentSheet]['colWidths'] = $widths;
    }

    public function addRow($cells, $style = null) {
        $this->sheets[$this->currentSheet]['rows'][] = ['cells' => $cells, 'style' => $style];
        return count($this->sheets[$this->currentSheet]['rows']);
    }

    public function currentRow() {
        return count($this->sheets[$this->currentSheet]['rows']);
    }

    public function addMerge($startCol, $startRow, $endCol, $endRow) {
        $this->sheets[$this->currentSheet]['merges'][] = [
            'start' => $startCol . $startRow,
            'end' => $endCol . $endRow
        ];
    }

    public function setAutoFilter($startCol, $startRow, $endCol, $endRow) {
        $this->sheets[$this->currentSheet]['autoFilter'] = $startCol . $startRow . ':' . $endCol . $endRow;
    }

    public function autoFitColumns() {
        $sheet = &$this->sheets[$this->currentSheet];
        $rows = $sheet['rows'];
        if (empty($rows)) return;

        $maxCol = 0;
        foreach ($rows as $row) {
            $maxCol = max($maxCol, count($row['cells']));
        }

        $widths = [];
        for ($c = 0; $c < $maxCol; $c++) {
            $widths[$c] = 5;
        }

        foreach ($rows as $row) {
            foreach ($row['cells'] as $colIdx => $cell) {
                $value = is_array($cell) ? ($cell['value'] ?? '') : $cell;
                $len = mb_strlen((string)$value);
                $widths[$colIdx] = max($widths[$colIdx], $len + 3);
            }
        }

        foreach ($widths as $colIdx => $w) {
            $sheet['colWidths'][$colIdx] = min($w, 50);
        }
    }

    public function download($filename) {
        while (ob_get_level()) ob_end_clean();

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        $sheetXmls = [];
        $sheetNames = array_keys($this->sheets);
        foreach ($sheetNames as $idx => $name) {
            $sheetXmls[$idx] = $this->sheetXml($name);
        }

        $zip->addFromString('xl/sharedStrings.xml', $this->buildSharedStrings());

        foreach ($sheetNames as $idx => $name) {
            $zip->addFromString('xl/worksheets/sheet' . ($idx + 1) . '.xml', $sheetXmls[$idx]);
        }

        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpFile));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($tmpFile);
        unlink($tmpFile);
        exit;
    }

    private function colLetter($n) {
        $result = '';
        while ($n >= 0) {
            $result = chr(65 + ($n % 26)) . $result;
            $n = intdiv($n, 26) - 1;
        }
        return $result;
    }

    private function addString($str) {
        if (!isset($this->stringMap[$str])) {
            $this->stringMap[$str] = count($this->strings);
            $this->strings[] = $str;
        }
        return $this->stringMap[$str];
    }

    private function cellRef($col, $row) {
        return $this->colLetter($col) . $row;
    }

    private function buildSharedStrings() {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($this->strings) . '" uniqueCount="' . count($this->strings) . '">';
        foreach ($this->strings as $s) {
            $xml .= '<si><t>' . htmlspecialchars($s, ENT_XML1) . '</t></si>';
        }
        $xml .= '</sst>';
        return $xml;
    }

    private function contentTypes() {
        $sheetEntries = '';
        foreach (array_keys($this->sheets) as $idx => $name) {
            $sheetEntries .= '<Override PartName="/xl/worksheets/sheet' . ($idx + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  ' . $sheetEntries . '
</Types>';
    }

    private function rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function workbook() {
        $sheetEntries = '';
        foreach (array_keys($this->sheets) as $idx => $name) {
            $sheetEntries .= '<sheet name="' . htmlspecialchars($name) . '" sheetId="' . ($idx + 1) . '" r:id="rId' . ($idx + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>' . $sheetEntries . '</sheets>
</workbook>';
    }

    private function workbookRels() {
        $sheetRels = '';
        foreach (array_keys($this->sheets) as $idx => $name) {
            $sheetRels .= '<Relationship Id="rId' . ($idx + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($idx + 1) . '.xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  ' . $sheetRels . '
  <Relationship Id="rId' . (count($this->sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rId' . (count($this->sheets) + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>';
    }

    private function stylesXml() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="4">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="14"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  </fonts>
  <fills count="5">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF4472C4"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFD9E2F3"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color indexed="64"/></left>
      <right style="thin"><color indexed="64"/></right>
      <top style="thin"><color indexed="64"/></top>
      <bottom style="thin"><color indexed="64"/></bottom>
      <diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="6">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    <xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>
    <xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>
    <xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>
  </cellXfs>
</styleSheet>';
    }

    private function sheetXml($sheetName) {
        $sheet = $this->sheets[$sheetName];
        $rows = $sheet['rows'];
        $merges = $sheet['merges'];
        $colWidths = $sheet['colWidths'];
        $autoFilter = $sheet['autoFilter'] ?? null;

        $maxCol = 0;
        foreach ($rows as $row) {
            $maxCol = max($maxCol, count($row['cells']));
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

        if (!empty($colWidths)) {
            $xml .= '<cols>';
            foreach ($colWidths as $colIdx => $width) {
                $xml .= '<col min="' . ($colIdx + 1) . '" max="' . ($colIdx + 1) . '" width="' . $width . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($rows as $rowIdx => $row) {
            $r = $rowIdx + 1;
            $xml .= '<row r="' . $r . '">';
            foreach ($row['cells'] as $colIdx => $cell) {
                $c = $this->colLetter($colIdx);
                $ref = $c . $r;
                $style = $row['style'] ?? ($rowIdx === 0 ? 0 : 3);

                if (is_array($cell)) {
                    $value = $cell['value'] ?? '';
                    $type = $cell['type'] ?? 's';
                    $cellStyle = $cell['style'] ?? $style;
                } else {
                    $value = $cell;
                    $type = is_numeric($value) && $value !== '' ? 'n' : 's';
                    $cellStyle = $style;
                }

                if ($type === 's') {
                    $si = $this->addString($value);
                    $xml .= '<c r="' . $ref . '" t="s" s="' . $cellStyle . '"><v>' . $si . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" s="' . $cellStyle . '"><v>' . htmlspecialchars($value) . '</v></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if ($autoFilter) {
            $xml .= '<autoFilter ref="' . $autoFilter . '"/>';
        }

        if (!empty($merges)) {
            $xml .= '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $m) {
                $xml .= '<mergeCell ref="' . $m['start'] . ':' . $m['end'] . '"/>';
            }
            $xml .= '</mergeCells>';
        }

        $xml .= '</worksheet>';
        return $xml;
    }
}
