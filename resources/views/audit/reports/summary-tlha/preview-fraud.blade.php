@extends('master-pages.layout-report')

@section('contentCssReport')
<style>
    @media print {
        @page { size: A4 landscape; }
        .table thead tr th, .table tbody tr td { white-space: pre-line !important; }
    }
</style>
@endsection

@section('contentJsReport')
<script type="text/javascript">
    $(document).ready(function(){
        $('#btnExportPDF').hide();
    });
</script>
@endsection

@section('additionalButton')
<a href="#" id="btnExportExcelLink" class="btn btn-primary" title="Export to Excel" style="display:inline-block;">
    <i class="fa fa-file-excel-o"></i>
</a>
<script type="text/javascript">
    $(document).ready(function(){
        $('#btnExportExcelLink').click(function(){
            var url = '{{ url("audit/reports/summary-tlha/exportExcel") }}'
                + '?AuditId=' + encodeURIComponent('{{ $hdr->AuditId }}');
            window.location.href = url;
        });
    });
</script>
@endsection

@section('contentReport')
@php use App\Libs\Libraries; @endphp

<section size="A4" layout="landscape" id="sectionExport">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.4; }
        .table { font-family: Arial, sans-serif; }
        .table thead tr th, .table tbody tr td { font-family: Arial, sans-serif;}
        .table thead tr th, .table tbody tr td { white-space: pre-line; }
    </style>
    <div class="page-export">
        <div style="page-break-after: unset">
            <!-- @include('master-pages.report.header') -->
            <div class="separator-print">&nbsp;</div>

            <table class="table" style="margin-bottom:5px; width:100%; text-align:center; border:none;">
                <tr><td style="font-size:16px; border:none;"><b>SUMMARY KASUS HASIL AUDIT</b></td></tr>
                <tr><td style="font-size:13px; border:none;"><b>PT JTRUST INVESTMENTS INDONESIA</b></td></tr>
                <tr><td style="font-size:12px; border:none;"><b>{{ $hdr->JenisAudit }} {{ $hdr->DepartmentAudityName }}</b></td></tr>
            </table>

            @if($temuanList->count() > 0)
                <table class="table" style="width:100%; border-collapse:collapse;" border="1">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width:30px; text-align:center; background:#fff;">NO</th>
                            <th rowspan="2" style="width:180px; text-align:center; background:#fff;">KONDISI</th>
                            <th rowspan="2" style="width:150px; text-align:center; background:#fff;">INDIKASI AWAL & BUKTI YANG DIDAPAT</th>
                            <th rowspan="2" style="width:100px; text-align:center; background:#fff;">RISIKO</th>
                            <th rowspan="2" style="width:100px; text-align:center; background:#fff;">KERUGIAN</th>
                            <th rowspan="2" style="width:150px; text-align:center; background:#fff;">PERATURAN/SOP PERUSAHAAN YANG DILANGGAR</th>
                            <th colspan="2" style="width:160px; text-align:center; background:#fff;">JENIS SANKSI BERDASARKAN PERATURAN PERUSAHAAN DAN SOP TINDAK PELANGGARAN & PENGENAAN SANKSI (DIPUTUSKAN PADA SAAT KOMITE SANKSI)</th>
                            <th rowspan="2" style="width:150px; text-align:center; background:#fff;">TINDAK LANJUT / PERBAIKAN</th>
                            <th rowspan="2" style="width:150px; text-align:center; background:#fff;">PENGEMBALIAN KERUGIAN PERUSAHAAN</th>
                            <th rowspan="2" style="width:100px; text-align:center; background:#fff;">DEADLINE</th>
                        </tr>
                        <tr>
                            <th style="width:80px; text-align:center; background:#fff;">KARYAWAN</th>
                            <th style="width:80px; text-align:center; background:#fff;">ATASAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align:center;">1</td>
                            <td colspan="10" style="text-align:left;"><b>{{ $hdr->JudulKondisi }}</b></td>
                        </tr>
                        @foreach($temuanList as $index => $item)
                        <tr>
                            <td style="text-align:center;">{{ chr(97 + $index) }}</td>
                            <td colspan="10" style="text-align:left;"><b>{!! $item->JudulTemuan !!}</b></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td style="text-align:justify;">{!! $item->DetailTemuan ?? '-' !!}</td>
                            <td style="text-align:justify;">{!! $item->IndikasiAwal ?? '-' !!}</td>
                            <td style="text-align:justify;">{!! $item->Resiko ?? '-' !!}</td>
                            <td style="text-align:right;">{{ $item->Kerugian ? 'Rp ' . number_format($item->Kerugian, 0, ',', '.') : '-' }}</td>
                            <td style="text-align:justify;">{!! $item->PeraturanSOP ?? '-' !!}</td>
                            <td style="text-align:justify;">{!! $item->SanksiKaryawan ?? '-' !!}</td>
                            <td style="text-align:justify;">{!! $item->SanksiAtasan ?? '-' !!}</td>
                            <td style="text-align:justify;">{!! $item->TindakLanjut ?? '-' !!}</td>
                            <td style="text-align:justify;">{!! $item->PengembalianKerugian ?? '-' !!}</td>
                            <td style="text-align:center;">{{ $item->Deadline ? Libraries::showDate($item->Deadline) : '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="text-red" style="margin-bottom:5px">Data Tidak Ditemukan</div>
            @endif
        </div>
    </div>
</section>
@endsection