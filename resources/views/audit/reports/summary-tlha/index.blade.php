@extends(($isFullPage) ? 'master-pages.layout-main' : 'master-pages.layout-empty' )
@if($isFullPage) @section('content')@endif

<script type="text/javascript">
$(document).ready(function(){
    renderGrid('grdSummaryTlha', {
        url : '{{ url("audit/reports/summary-tlha/get-list") }}',
        btnDefault : {edit: false, delete: false, add: false, upload: false, view: false},
        btnCustom: [
            { id: 'btnPrint', icon: 'fa-print text-blue', title: 'Print' }
        ],
        title : 'Summary TLHA',
        columns : [[
            { field: 'JudulKondisi', title: 'Judul Kondisi', align: 'center' },
            { field: 'JumlahTemuan', title: 'Total Temuan', align: 'center', width: 100 },
            { field: 'JenisAudit', title: 'Jenis Audit', align: 'center', width: 130 },
            { field: 'DepartmentAudityName', title: 'Department Auditee', align: 'center', width: 140, formatter: function(value) { return value || '-'; } },
            { field: 'Status', title: 'Status', align: 'center', width: 100, formatter: function(value) {
                if (value === 'Completed') {
                    return '<span style="display:inline-block; background-color:rgba(40,167,69,0.15); color:#1c7430; font-weight:400; padding:2px 12px; border-radius:12px;">Completed</span>';
                }
                return value || 'In Progress';
            }},
            { field: 'TotalProgress', title: 'Progress (%)', align: 'center', width: 100, formatter: function(value) { return (value || 0) + '%'; } },
            { field: 'CreatedByNames', title: 'Created By', align: 'center', width: 100, formatter: function(value) { return value || '-'; } },
            { field: 'CreatedAt', title: 'Create Date', align: 'center', width: 100, formatter: function(value) {
                if (!value) return '-';
                return new Date(value).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'});
            }}
        ]],
        filters : [
            { field: 'JudulKondisi' },
            { field: 'JenisAudit', type: 'combobox', options: {
                data: [
                    {value: '', text: '-'},
                    {value: 'Pemeriksaan Reguler', text: 'Pemeriksaan Reguler'},
                    {value: 'Fraud / Spesial Audit', text: 'Fraud / Spesial Audit'}
                ],
                valueField: 'value',
                textField: 'text',
                panelHeight: 'auto'
            }}
        ]
    });

    setTimeout(function() {
        $('#grdSummaryTlha_btnPrint').click(function(){
            var row = $('#grdSummaryTlha').datagrid('getSelected');
            if (!row) {
                alertBox('show', {msg: 'Pilih data terlebih dahulu', mode: 'warning'});
                return;
            }

            alertBox('hide');
            $('#frmReport').html('');
            $('#dlgReport .modal-title').html('Summary TLHA');

            ajax({
                url      : '{{ url("audit/reports/summary-tlha/preview") }}',
                postData : { AuditId: row.AuditId },
                dataType : 'html',
                success  : function(html) {
                    $('#frmReport').html(html);
                    $('#dlgReport').modal('show');
                }
            });
        });
    }, 50);
});
</script>

<div class="form-horizontal">
    <div id="frmSummaryTlhaHeader">
        <div class="row">
            <div class="col-sm-12">
                <div id="grdSummaryTlha"></div>
            </div>
        </div>
    </div>
</div>

@if($isFullPage) @endsection @endif