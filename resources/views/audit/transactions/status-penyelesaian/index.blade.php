@extends(($isFullPage) ? 'master-pages.layout-main' : 'master-pages.layout-empty' )
@if($isFullPage) @section('content')@endif

<script type="text/javascript">
    function alertBoxAuto(opts) {
        alertBox('show', opts);
        setTimeout(function(){
            alertBox('hide', (opts && opts.id) ? { id: opts.id } : undefined);
        }, 5000);
    }
    $(document).ready(function(){
            var loginNikIdIndexSP = '{{ $loginNikId ?? '' }}';

            renderGrid('grdStatusPenyelesaian', {
            url : '{{ url("audit/transactions/status-penyelesaian/get-list") }}',
            btnDefault : {edit: false, delete: false, add: false, upload: false, view: false},
            btnCustom: [
                { id: 'btnEdit', icon: 'fa-tasks text-blue', title: 'Ubah Data' }
            ],
            title : 'Status Penyelesaian',
            columns : [[
                { field: 'JudulKondisi', title: 'Judul Pemeriksaan', align: 'center' },
                { field: 'JumlahTemuan', title: 'Total Temuan', align: 'center', width: 100 },            
                { field: 'JenisAudit', title: 'Jenis Audit', align: 'center', width: 130 },
                //{ field: 'DepartmentAudityName', title: 'Department Auditee', align: 'center', width: 140, formatter: function(value) { return value || '-'; } },
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
                { field: 'JudulKondisi' }
            ]
        });

        window.AppNotif = {
            module: 'INTERNAL_AUDIT_SP',
            refKey: 'AuditId',
            countUrl: '{{ url("app-notifications/internal-audit-sp/count") }}',
            itemsUrl: '{{ url("app-notifications/internal-audit-sp/items") }}',
            markReadOneUrl: '{{ url("app-notifications/internal-audit-sp/mark-read-one") }}',
            markReadManyUrl: '{{ url("app-notifications/internal-audit-sp/mark-read-many") }}',
            buildMarkReadOneData: function(refId) {
                return { AuditId: refId };
            },
            onItemClick: function(refId) {
                $('#dlgStatusPenyelesaian').hide();
                $('#frmStatusPenyelesaianHeader').show();
                $('#grdStatusPenyelesaian').datagrid('load', { NotifAuditId: refId });
            },
            onShowMore: function(ids) {
                $('#grdStatusPenyelesaian').datagrid('reload');
            }
        };

        if (typeof initAppNotif === 'function') initAppNotif();

        setTimeout(function() {
            $('#grdStatusPenyelesaian_btnEdit').click(function(){
                var row = $('#grdStatusPenyelesaian').datagrid('getSelected');
                if (!row) {
                    alertBoxAuto({msg: 'Pilih data terlebih dahulu', mode: 'warning'});
                    return;
                }

                // Fraud / Spesial Audit: halaman Status Penyelesaian (sisi Auditee) diblokir total,
                // walau login user terdaftar sebagai PIC atau Approval Auditee sekalipun - Jenis
                // Audit ini hanya dapat diproses dari sisi Auditor (internal-audit).
                if (row.JenisAudit === 'Fraud / Spesial Audit') {
                    alertBoxAuto({msg: 'Pemeriksaan tersebut hanya dapat dilakukan oleh Auditor.', mode: 'warning'});
                    return;
                }

                // Level 1: cek apakah login termasuk union PIC Tindak Lanjut di seluruh Temuan pada Judul Pemeriksaan ini
                ajax({
                    url      : '{{ url("audit/transactions/status-penyelesaian/get-pic-list-array-audit") }}',
                    postData : { AuditId: row.AuditId },
                    success  : function(ret) {
                        var list = (ret && ret.data) ? ret.data : [];
                        var arrNikId = $.map(list, function(item){ return String(item.NikId); });
                        var isPic = loginNikIdIndexSP ? (arrNikId.indexOf(String(loginNikIdIndexSP)) !== -1) : false;
                        var isApprovalAuditee = !!(ret && ret.IsApprovalAuditee);

                        if (isPic || isApprovalAuditee) {
                            showStatusPenyelesaianDetail(row, true);
                        } else {
                            alertBoxAuto({msg: 'Tidak dapat melihat Progress Audit karena tidak memiliki akses sebagai PIC terkait', mode: 'warning'});
                        }
                    }
                });
            });
        }, 50);
    });
</script>

<div class="form-horizontal">
    <div id="frmStatusPenyelesaianHeader">
        <div class="row">
            <div class="col-sm-12">
                <div id="grdStatusPenyelesaian"></div>
            </div>
        </div>
    </div>
</div>

@include('audit.transactions.status-penyelesaian.modal')
@if($isFullPage) @endsection @endif