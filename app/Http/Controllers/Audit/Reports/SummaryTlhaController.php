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

    // Ambil temuanList + daftar entry Perbaikan per Temuan.
    // PerbaikanRows = 1 elemen per entry Tindak Lanjut/Perbaikan (TindakLanjut & Deadline sejajar).
    // Reguler bisa banyak entry, Fraud hanya 1 entry; kalau belum ada entry sama sekali tetap 1 baris ('-').
    private function getTemuanListWithPerbaikan($auditId) {
        $temuanList = TrnInternalAuditDtl::where('AuditId', $auditId)
            ->orderBy('DtlId')
            ->get();

        foreach ($temuanList as $item) {
            $perbaikanList = \DB::table('TrnInternalAuditPerbaikan')
                ->where('DtlId', $item->DtlId)
                ->orderBy('PerbaikanId')
                ->get();

            $rows = [];
            foreach ($perbaikanList as $p) {
                $detail = trim(strip_tags((string) ($p->DetailPerbaikan ?? ''), '<strong><b><em><i><u><br>'));
                if ($detail === '') {
                    $detail = '-';
                }
                $action = trim((string) ($p->Action ?? ''));

                $rows[] = [
                    'TindakLanjut' => ($action !== '' ? '[' . $action . '] ' : '') . $detail,
                    'Deadline'     => $p->Deadline,
                ];
            }

            if (count($rows) === 0) {
                $rows[] = ['TindakLanjut' => '-', 'Deadline' => null];
            }

            $item->PerbaikanRows = $rows;
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

        return view('audit/reports/summary-tlha/preview')->with($data)->render();
    }

    public function exportExcel(Request $req) {
        $hdr = $this->getHdrDetail($req->AuditId);
        if (!$hdr) {
            abort(404, 'Data tidak ditemukan');
        }

        $temuanList = $this->getTemuanListWithPerbaikan($req->AuditId);

        return $this->exportExcelSummary($hdr, $temuanList);
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

    // Estimasi tinggi (pt) teks HTML hasil TinyMCE di kolom selebar $colWidth (satuan lebar kolom Excel).
    // Dipakai hanya untuk sel yang di-merge vertikal: Excel tidak auto-fit tinggi baris untuk sel merge,
    // jadi tinggi sub-baris dihitung manual supaya teks panjang tidak terpotong.
    // Perkiraan sengaja agak longgar (lebih baik ada sisa ruang daripada teks terpotong).
    private function estimateTextHeight($html, $colWidth, $fontSize) {
        $text = preg_replace('/<br\s*\/?>|<\/p>|<\/div>|<\/li>/i', "\n", (string) $html);
        $text = rtrim(html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8'));

        $charsPerLine = max(1, (int) floor(($colWidth * 9) / max(1, $fontSize)));

        $lines = 0;
        foreach (explode("\n", $text) as $par) {
            $lines += max(1, (int) ceil(mb_strlen(trim($par)) / $charsPerLine));
        }

        return $lines * ($fontSize * 1.3);
    }

    // Satu template untuk semua Jenis Audit (Reguler & Fraud).
    // Per Temuan: 1 baris judul + N sub-baris detail (N = jumlah entry Tindak Lanjut/Perbaikan).
    // Kolom Tindak Lanjut (I) & Deadline (J) dipecah per entry, kolom lain di-merge vertikal sepanjang N.
    // Kalau N = 1 tidak ada merge vertikal (tampil normal).
    private function exportExcelSummary($hdr, $temuanList) {
        $export = new ExportExcel();
        $export->setWorksheet(public_path() . '/template/reports/ReportInternalAudit-SummaryTLHA.xlsx')
            ->setCellStart('A')
            ->setCellEnd('K')
            ->setRowStart(10)
            ->appendItem('A4', $hdr->JudulKondisi ?? '-')
            ->appendItem('A6', trim(($hdr->JenisAudit ?? '-') . ' ' . ($hdr->DepartmentAudityName ?? '')));

        $ws  = $export->getWorksheet();
        $row = 10;

        foreach ($temuanList as $index => $item) {
            $rows     = $item->PerbaikanRows;
            $total    = count($rows);
            $rowTitle = $row;
            $rowStart = $rowTitle + 1;
            $rowEnd   = $rowStart + $total - 1;

            // Baris Judul Temuan (merge B:K) - JudulTemuan field TinyMCE, forceBold ikut desain title row
            $export->appendItem("A{$rowTitle}", $index + 1)
                ->setMergeCells("B{$rowTitle}:K{$rowTitle}")
                ->setStyle("A{$rowTitle}:K{$rowTitle}", $this->styleBold())
                ->setStyle("A{$rowTitle}:K{$rowTitle}", $this->styleAlignLeftTop())
                ->setCellAligment("A{$rowTitle}", Alignment::HORIZONTAL_CENTER)
                ->setBorder("A{$rowTitle}:K{$rowTitle}");
            $this->setRichTextCell($export, "B{$rowTitle}", $item->JudulTemuan ?? '-', true);

            // Merge vertikal semua kolom selain Tindak Lanjut (I) & Deadline (J), hanya kalau entry > 1
            if ($total > 1) {
                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'K'] as $col) {
                    $export->setMergeCells("{$col}{$rowStart}:{$col}{$rowEnd}");
                }
            }

            // Kerugian & Deadline tetap plain text (bukan hasil TinyMCE)
            $export->appendItem("E{$rowStart}", $item->Kerugian ? 'Rp ' . number_format($item->Kerugian, 0, ',', '.') : '-');
            foreach ($rows as $i => $entry) {
                $export->appendItem('J' . ($rowStart + $i), $entry['Deadline'] ? Libraries::showDate($entry['Deadline']) : '-');
            }

            $export->setStyle("B{$rowStart}:K{$rowEnd}", $this->styleJustify())
                ->setCellAligment("E{$rowStart}", Alignment::HORIZONTAL_RIGHT)
                ->setCellAligment("J{$rowStart}:J{$rowEnd}", Alignment::HORIZONTAL_CENTER)
                ->setBorder("A{$rowStart}:K{$rowEnd}");

            // Field TinyMCE pakai RichText. Kolom yang di-merge ditulis di sel paling atas.
            $this->setRichTextCell($export, "B{$rowStart}", $item->DetailTemuan ?? '-');
            $this->setRichTextCell($export, "C{$rowStart}", $item->IndikasiAwal ?? '-');
            $this->setRichTextCell($export, "D{$rowStart}", $item->Resiko ?? '-');
            $this->setRichTextCell($export, "F{$rowStart}", $item->PeraturanSOP ?? '-');
            $this->setRichTextCell($export, "G{$rowStart}", $item->SanksiKaryawan ?? '-');
            $this->setRichTextCell($export, "H{$rowStart}", $item->SanksiAtasan ?? '-');
            $this->setRichTextCell($export, "K{$rowStart}", $item->PengembalianKerugian ?? '-');
            foreach ($rows as $i => $entry) {
                $this->setRichTextCell($export, 'I' . ($rowStart + $i), $entry['TindakLanjut']);
            }

            // Tinggi sub-baris dihitung manual hanya kalau ada merge vertikal (N > 1).
            // N = 1 dibiarkan auto-fit seperti sebelumnya.
            if ($total > 1) {
                $fs    = $ws->getStyle("B{$rowStart}")->getFont()->getSize();
                $lineH = $fs * 1.3;

                $mergedHtml = [
                    'B' => $item->DetailTemuan,
                    'C' => $item->IndikasiAwal,
                    'D' => $item->Resiko,
                    'F' => $item->PeraturanSOP,
                    'G' => $item->SanksiKaryawan,
                    'H' => $item->SanksiAtasan,
                    'K' => $item->PengembalianKerugian,
                ];
                $mergedH = 0;
                foreach ($mergedHtml as $col => $html) {
                    $w       = $ws->getColumnDimension($col)->getWidth();
                    $mergedH = max($mergedH, $this->estimateTextHeight($html ?? '-', $w > 0 ? $w : 10, $fs));
                }
                $mergedH += $lineH; // cadangan 1 baris

                $wI      = $ws->getColumnDimension('I')->getWidth();
                $heights = [];
                $sum     = 0;
                foreach ($rows as $i => $entry) {
                    $h = $this->estimateTextHeight($entry['TindakLanjut'], $wI > 0 ? $wI : 10, $fs) + 4;
                    $heights[$i] = max($lineH + 4, $h);
                    $sum += $heights[$i];
                }

                $extra = ($sum < $mergedH) ? (($mergedH - $sum) / $total) : 0;
                foreach ($heights as $i => $h) {
                    $ws->getRowDimension($rowStart + $i)->setRowHeight(min(409, $h + $extra));
                }
            }

            $row = $rowEnd + 1;
        }

        $export->setFileName(env('APP_NAME') . ' Summary Kasus Hasil Audit.xlsx');
        $export->exportTo('Xlsx');
    }


}