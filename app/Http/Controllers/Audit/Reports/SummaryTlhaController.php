<?php

namespace App\Http\Controllers\Audit\Reports;

use Symfony\Component\HttpFoundation\Request;
use App\Http\Controllers\Controller;
use App\Libs\ExportExcel;
use App\Libs\Libraries;
use App\Models\Audit\Transactions\TrnInternalAuditHdr;
use App\Models\Audit\Transactions\TrnInternalAuditDtl;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class SummaryTlhaController extends Controller
{
    public function index(Request $req) {
        $data['isFullPage'] = $this->getPageType($req);
        return view('audit/reports/summary-tlha/index')->with($data)->render();
    }

    public function getList(Request $req) {
        $qry = TrnInternalAuditHdr::select(
                \DB::raw('"TrnInternalAuditHdr"."AuditId" as "AuditId"'),
                \DB::raw('"TrnInternalAuditHdr"."JudulKondisi" as "JudulKondisi"'),
                \DB::raw('"TrnInternalAuditHdr"."JenisAudit" as "JenisAudit"'),
                \DB::raw('"TrnInternalAuditHdr"."DepartmentAudity" as "DepartmentAudity"'),
                \DB::raw('CASE WHEN COALESCE("TrnInternalAuditHdr"."FlagApproval1", 0) = 1 AND COALESCE("TrnInternalAuditHdr"."FlagApproval2", 0) = 1 THEN \'Completed\' ELSE \'In Progress\' END as "Status"'),
                \DB::raw('"MstDepartemen"."DeptName" as "DepartmentAudityName"'),
                \DB::raw('"TrnInternalAuditHdr"."CreatedAt" as "CreatedAt"'),
                \DB::raw('"TrnInternalAuditHdr"."UpdatedAt" as "UpdatedAt"'),
                \DB::raw('COALESCE(dtl."JumlahTemuan", 0) as "JumlahTemuan"'),
                \DB::raw('COALESCE(dtl."TotalProgress", 0) as "TotalProgress"'),
                \DB::raw('dtl."CreatedByNames" as "CreatedByNames"')
            )
            ->leftJoin(\DB::raw('(
                SELECT dtl."AuditId",
                       COUNT(*) as "JumlahTemuan",
                       ROUND(AVG(COALESCE(lp."Progress", 0))) as "TotalProgress",
                       STRING_AGG(DISTINCT su."EmpName", \', \') as "CreatedByNames"
                FROM "TrnInternalAuditDtl" dtl
                LEFT JOIN "SecUser" su ON su."UserId" = dtl."CreatedBy"
                LEFT JOIN (
                    SELECT DISTINCT ON ("DtlId") "DtlId", "Progress"
                    FROM "TrnInternalAuditProgress"
                    WHERE "ApprovalStatus" = \'Approved\'
                    ORDER BY "DtlId", "CreatedAt" DESC, "ProgressId" DESC
                ) lp ON lp."DtlId" = dtl."DtlId"
                GROUP BY dtl."AuditId"
            ) as dtl'), 'dtl.AuditId', '=', 'TrnInternalAuditHdr.AuditId')
            ->leftJoin('MstDepartemen', 'MstDepartemen.DeptId', '=', 'TrnInternalAuditHdr.DepartmentAudity')
            ->orderByRaw('COALESCE("TrnInternalAuditHdr"."UpdatedAt", "TrnInternalAuditHdr"."CreatedAt") DESC NULLS LAST');

        if (!empty($req->JudulKondisi)) {
            $qry->where('TrnInternalAuditHdr.JudulKondisi', 'like', '%' . $req->JudulKondisi . '%');
        }

        if (!empty($req->JenisAudit)) {
            $qry->where('TrnInternalAuditHdr.JenisAudit', $req->JenisAudit);
        }

        if (!empty($req->Status)) {
            if ($req->Status === 'Completed') {
                $qry->where('TrnInternalAuditHdr.FlagApproval1', 1)
                    ->where('TrnInternalAuditHdr.FlagApproval2', 1);
            } else {
                $qry->where(function($q) {
                    $q->where('TrnInternalAuditHdr.FlagApproval1', '!=', 1)
                      ->orWhere('TrnInternalAuditHdr.FlagApproval2', '!=', 1)
                      ->orWhereNull('TrnInternalAuditHdr.FlagApproval1')
                      ->orWhereNull('TrnInternalAuditHdr.FlagApproval2');
                });
            }
        }

        return $this->grid->load($qry);
    }

    private function getHdrDetail($auditId) {
        return TrnInternalAuditHdr::select(
                \DB::raw('"TrnInternalAuditHdr"."AuditId" as "AuditId"'),
                \DB::raw('"TrnInternalAuditHdr"."JudulKondisi" as "JudulKondisi"'),
                \DB::raw('"TrnInternalAuditHdr"."JenisAudit" as "JenisAudit"'),
                \DB::raw('CASE WHEN COALESCE("TrnInternalAuditHdr"."FlagApproval1", 0) = 1 AND COALESCE("TrnInternalAuditHdr"."FlagApproval2", 0) = 1 THEN \'Completed\' ELSE \'In Progress\' END as "Status"'),
                \DB::raw('"MstDepartemen"."DeptName" as "DepartmentAudityName"')
            )
            ->leftJoin('MstDepartemen', 'MstDepartemen.DeptId', '=', 'TrnInternalAuditHdr.DepartmentAudity')
            ->where('TrnInternalAuditHdr.AuditId', $auditId)
            ->first();
    }

    // Ambil temuanList + aggregasi Deadline & TindakLanjut (gabungan Action + Detail dari semua entry Perbaikan)
    private function getTemuanListWithPerbaikan($auditId) {
        $temuanList = TrnInternalAuditDtl::where('AuditId', $auditId)
            ->orderBy('DtlId')
            ->get();

        foreach ($temuanList as $item) {
            $perbaikanList = \DB::table('TrnInternalAuditPerbaikan')
                ->where('DtlId', $item->DtlId)
                ->orderBy('PerbaikanId')
                ->get();

            $item->Deadline = $perbaikanList->min('Deadline');
            $item->TindakLanjut = $perbaikanList->count() > 0
                ? $perbaikanList->map(function($p) {
                    return '[' . $p->Action . '] ' . strip_tags($p->DetailPerbaikan ?? '-', '<strong><b><em><i><u><br>');
                })->implode('<br>')
                : '-';
        }

        return $temuanList;
    }

    public function preview(Request $req) {
        $hdr = $this->getHdrDetail($req->AuditId);
        if (!$hdr) {
            return response('Data tidak ditemukan', 404);
        }

        $data['hdr'] = $hdr;
        $data['temuanList'] = $this->getTemuanListWithPerbaikan($req->AuditId);

        if ($hdr->JenisAudit === 'Pemeriksaan Reguler') {
            return view('audit/reports/summary-tlha/preview-reguler')->with($data)->render();
        }

        return view('audit/reports/summary-tlha/preview-fraud')->with($data)->render();
    }

    public function exportExcel(Request $req) {
        $hdr = $this->getHdrDetail($req->AuditId);
        if (!$hdr) {
            abort(404, 'Data tidak ditemukan');
        }

        $temuanList = $this->getTemuanListWithPerbaikan($req->AuditId);

        if ($hdr->JenisAudit === 'Pemeriksaan Reguler') {
            return $this->exportExcelReguler($hdr, $temuanList);
        }

        return $this->exportExcelFraud($hdr, $temuanList);
    }

    private function styleBold() {
        return ['font' => ['bold' => true]];
    }

    private function styleAlignLeftTop() {
        return ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER]];
    }

    private function styleJustify() {
        return ['alignment' => ['horizontal' => Alignment::HORIZONTAL_JUSTIFY, 'vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true]];
    }

    // Set value cell dengan RichText (bold/italic/underline per-kata), tanpa ubah App\Libs\ExportExcel.
    // Cukup pakai getWorksheet() yang sudah di-expose di wrapper itu.
    private function setRichTextCell($export, $position, $html, $forceBold = false, $forceUnderline = false) {
        // Ambil font name & size asli dari template di cell ini SEBELUM value di-overwrite,
        // supaya Run bold/italic/underline bisa ikut style template, bukan default PhpSpreadsheet.
        $baseFont = $export->getWorksheet()->getStyle($position)->getFont();
        $baseFontName = $baseFont->getName();
        $baseFontSize = $baseFont->getSize();

        $richText = $this->htmlToRichText($html, $forceBold, $forceUnderline, $baseFontName, $baseFontSize);
        $export->getWorksheet()->getCell($position)->setValue($richText);
        return $export;
    }

    // Parse HTML hasil TinyMCE (<strong>/<b>, <em>/<i>, <u>, <br>) jadi object RichText PhpSpreadsheet
    private function htmlToRichText($html, $forceBold = false, $forceUnderline = false, $baseFontName = null, $baseFontSize = null) {
        $richText = new RichText();
        $html = trim((string) $html);

        if ($html === '' || $html === '-') {
            $richText->createText($html === '' ? '-' : $html);
            return $richText;
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $container = $doc->getElementsByTagName('div')->item(0);

        if (!$container) {
            $richText->createText($html);
            return $richText;
        }

        $this->walkRichTextNode($richText, $container, $forceBold, false, false, $forceUnderline, $baseFontName, $baseFontSize);

        if (count($richText->getRichTextElements()) === 0) {
            $richText->createText('-');
        }

        return $richText;
    }

    // Rekursif jalanin node HTML, kumpulkan flag bold/italic/underline yang aktif,
    // lalu bikin Run baru tiap ketemu text node. SEMUA run (baik polos maupun
    // bold/italic/underline) dipasang font name & size yang SAMA, diambil dari
    // $baseFontName/$baseFontSize (style asli cell di template) - supaya tidak ada
    // campuran ukuran font "ikut cell" vs "ikut kode" dalam satu cell yang sama.
    // Hanya bold/italic/underline yang jadi pembeda formatting, size & font family tetap seragam.
    private function walkRichTextNode($richText, $node, $bold, $italic, $underline, $forceUnderline, $baseFontName = null, $baseFontSize = null) {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = $child->textContent;
                if ($text === '') {
                    continue;
                }

                $run = $richText->createTextRun($text);
                $font = $run->getFont();
                if ($baseFontName) {
                    $font->setName($baseFontName);
                }
                if ($baseFontSize) {
                    $font->setSize($baseFontSize);
                }
                if ($bold) {
                    $font->setBold(true);
                }
                if ($italic) {
                    $font->setItalic(true);
                }
                if ($underline || $forceUnderline) {
                    $font->setUnderline(Font::UNDERLINE_SINGLE);
                }
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtoupper($child->tagName);

                if ($tag === 'BR') {
                    $richText->createText("\n");
                    continue;
                }

                $childBold      = $bold || in_array($tag, ['STRONG', 'B']);
                $childItalic    = $italic || in_array($tag, ['EM', 'I']);
                $childUnderline = $underline || ($tag === 'U');

                $this->walkRichTextNode($richText, $child, $childBold, $childItalic, $childUnderline, $forceUnderline, $baseFontName, $baseFontSize);
            }
        }
    }

    private function exportExcelFraud($hdr, $temuanList) {
        $export = new ExportExcel();
        $export->setWorksheet(public_path() . '/template/reports/ReportInternalAudit-FraudSpesialAudit.xlsx')
            ->setCellStart('A')
            ->setCellEnd('K')
            ->setRowStart(10)
            ->appendItem('A6', trim(($hdr->JenisAudit ?? '-') . ' ' . ($hdr->DepartmentAudityName ?? '')));

        $row = 10;

        // Baris Judul Kondisi (merge B:K) - JudulKondisi bukan field TinyMCE, tetap plain text
        $export->appendItem("A{$row}", '1')
            ->setMergeCells("B{$row}:K{$row}")
            ->appendItem("B{$row}", $hdr->JudulKondisi)
            ->setStyle("A{$row}:K{$row}", $this->styleBold())
            ->setStyle("A{$row}:K{$row}", $this->styleAlignLeftTop())
            ->setCellAligment("A{$row}", Alignment::HORIZONTAL_CENTER)
            ->setBorder("A{$row}:K{$row}");
        $row++;

        foreach ($temuanList as $index => $item) {
            // Baris Judul Temuan (merge B:K) - JudulTemuan field TinyMCE, forceBold ikut desain title row
            $export->appendItem("A{$row}", chr(97 + $index))
                ->setMergeCells("B{$row}:K{$row}")
                ->setStyle("A{$row}:K{$row}", $this->styleBold())
                ->setStyle("A{$row}:K{$row}", $this->styleAlignLeftTop())
                ->setCellAligment("A{$row}", Alignment::HORIZONTAL_CENTER)
                ->setBorder("A{$row}:K{$row}");
            $this->setRichTextCell($export, "B{$row}", $item->JudulTemuan ?? '-', true);
            $row++;

            // Baris Detail - field TinyMCE pakai RichText, Kerugian & Deadline tetap plain text
            $export->appendItem("E{$row}", $item->Kerugian ? 'Rp ' . number_format($item->Kerugian, 0, ',', '.') : '-')
                ->appendItem("K{$row}", $item->Deadline ? Libraries::showDate($item->Deadline) : '-')
                ->setStyle("B{$row}:K{$row}", $this->styleJustify())
                ->setCellAligment("E{$row}", Alignment::HORIZONTAL_RIGHT)
                ->setCellAligment("K{$row}", Alignment::HORIZONTAL_CENTER)
                ->setBorder("A{$row}:K{$row}");

            $this->setRichTextCell($export, "B{$row}", $item->DetailTemuan ?? '-');
            $this->setRichTextCell($export, "C{$row}", $item->IndikasiAwal ?? '-');
            $this->setRichTextCell($export, "D{$row}", $item->Resiko ?? '-');
            $this->setRichTextCell($export, "F{$row}", $item->PeraturanSOP ?? '-');
            $this->setRichTextCell($export, "G{$row}", $item->SanksiKaryawan ?? '-');
            $this->setRichTextCell($export, "H{$row}", $item->SanksiAtasan ?? '-');
            $this->setRichTextCell($export, "I{$row}", $item->TindakLanjut ?? '-');
            $this->setRichTextCell($export, "J{$row}", $item->PengembalianKerugian ?? '-');
            $row++;
        }

        $export->setFileName(env('APP_NAME') . ' Summary Kasus Hasil Audit.xlsx');
        $export->exportTo('Xlsx');
    }

    private function exportExcelReguler($hdr, $temuanList) {
        $export = new ExportExcel();
        $export->setWorksheet(public_path() . '/template/reports/ReportInternalAudit-PemeriksaanReguler.xlsx')
            ->setCellStart('A')
            ->setCellEnd('G')
            ->setRowStart(9)
            ->appendItem('A6', trim(($hdr->JenisAudit ?? '-') . ' - ' . ($hdr->DepartmentAudityName ?? '')));

        $row = 9;

        foreach ($temuanList as $index => $item) {
            $rowTitle  = $row;
            $rowDetail = $row + 1;

            $export->setMergeCells("A{$rowTitle}:A{$rowDetail}")
                ->appendItem("A{$rowTitle}", $index + 1)
                ->setCellAligment("A{$rowTitle}:A{$rowDetail}", Alignment::HORIZONTAL_CENTER)
                ->setCellVligment("A{$rowTitle}:A{$rowDetail}", Alignment::VERTICAL_TOP)
                ->setMergeCells("B{$rowTitle}:G{$rowTitle}")
                ->setStyle("B{$rowTitle}:G{$rowTitle}", $this->styleBold())
                ->setStyle("B{$rowTitle}:G{$rowTitle}", $this->styleAlignLeftTop());
            // JudulTemuan field TinyMCE, forceBold ikut desain title row
            $this->setRichTextCell($export, "B{$rowTitle}", $item->JudulTemuan ?? '-', true);

            // Deadline & Status tetap plain text (bukan hasil TinyMCE)
            $export->appendItem("E{$rowDetail}", $item->Deadline ? Libraries::showDate($item->Deadline) : '-')
                ->appendItem("G{$rowDetail}", $hdr->Status)
                ->setStyle("B{$rowDetail}:G{$rowDetail}", $this->styleJustify())
                ->setFontBold("G{$rowDetail}")
                ->setCellAligment("E{$rowDetail}", Alignment::HORIZONTAL_CENTER)
                ->setCellAligment("G{$rowDetail}", Alignment::HORIZONTAL_CENTER)
                ->setBorder("A{$rowTitle}:G{$rowDetail}");

            // DetailTemuan, IndikasiAwal, TindakLanjut & PengembalianKerugian: RichText murni ikut formatting user
            $this->setRichTextCell($export, "B{$rowDetail}", $item->DetailTemuan ?? '-');
            $this->setRichTextCell($export, "C{$rowDetail}", $item->IndikasiAwal ?? '-');
            $this->setRichTextCell($export, "D{$rowDetail}", $item->TindakLanjut ?? '-');
            $this->setRichTextCell($export, "F{$rowDetail}", $item->PengembalianKerugian ?? '-');

            $row = $rowDetail + 1;
        }

        $export->setFileName(env('APP_NAME') . ' Laporan Tindak Lanjut Hasil Internal Control.xlsx');
        $export->exportTo('Xlsx');
    }
}