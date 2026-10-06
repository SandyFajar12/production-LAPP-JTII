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
        body { font-family: Arial, sans-serif; font-size: 10px; line-height: 1.4; }
        .table { font-family: Arial, sans-serif; font-size: 10px; }
        .table thead tr th, .table tbody tr td { font-family: Arial, sans-serif; font-size: 10px; }
        .table thead tr th, .table tbody tr td { white-space: pre-line; }
    </style>
    <div class="page-export">
        <div style="page-break-after: unset">
            <!-- @include('master-pages.report.header') -->
            <div class="separator-print">&nbsp;</div>

            <table class="table" style="margin-bottom:5px; width:100%; text-align:center; border:none;">
                <tr><td style="font-size:16px; border:none;"><b>LAPORAN TINDAK LANJUT HASIL INTERNAL CONTROL</b></td></tr>
                <tr><td style="font-size:13px; border:none;"><b>PT JTRUST INVESTMENTS INDONESIA</b></td></tr>
                <tr><td style="font-size:12px; border:none;"><b>{{ $hdr->JenisAudit }} - {{ $hdr->DepartmentAudityName }}</b></td></tr>
            </table>

            @if($temuanList->count() > 0)
                <table class="table" style="width:100%; border-collapse:collapse;" border="1">
                    <thead>
                        <tr style="background-color:#fff; color:#000;">
                            <th style="width:30px; text-align:center;">NO</th>
                            <th style="width:200px; text-align:center;">KONDISI</th>
                            <th style="width:180px; text-align:center;">PENYEBAB</th>
                            <th style="width:180px; text-align:center;">TINDAK LANJUT / PERBAIKAN / PENCEGAHAN (AUDITEE)</th>
                            <th style="width:100px; text-align:center;">DEADLINE</th>
                            <th style="width:180px; text-align:center;">REKOMENDASI AUDITOR</th>
                            <th style="width:120px; text-align:center;">STATUS<br>(On Progress / Done)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($temuanList as $index => $item)
                        <tr>
                            <td rowspan="2" style="text-align:center;">{{ $index + 1 }}</td>
                            <td colspan="6" style="text-align:left;"><b>{!! $item->JudulTemuan !!}</b></td>
                        </tr>
                        <tr>
                            <td style="text-align:justify;">{!! $item->DetailTemuan ?? '-' !!}</td>
                            <td style="text-align:justify;">{!! $item->IndikasiAwal ?? '-' !!}</td>
                            <td style="text-align:justify;">{!! $item->TindakLanjut ?? '-' !!}</td>
                            <td style="text-align:center;">{{ $item->Deadline ? Libraries::showDate($item->Deadline) : '-' }}</td>
                            <td style="text-align:justify;">{!! $item->PengembalianKerugian ?? '-' !!}</td>
                            <td style="text-align:center;">{{ $hdr->Status }}</td>
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