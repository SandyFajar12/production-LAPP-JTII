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

    renderGrid('grdInternalAudit', {
        url : '{{ url("audit/transactions/audit-list/get-list") }}',
        btnDefault : {edit: false, delete: false, add: true, upload: false, view: false},
        btnCustom: [
            { id: 'btnEdit', icon: 'fa-pencil text-blue', title: 'Ubah Data' },
            { id: 'btnView', icon: 'fa-tasks text-blue', title: 'Update Progress' }
        ],
        title : 'Internal Audit',
        columns : [[
            { field: 'JudulKondisi', title: 'Judul Pemeriksaan', align: 'center' },
            { field: 'JumlahTemuan', title: 'Total Temuan', align: 'center', width: 100 },            
            { field: 'JenisAudit', title: 'Jenis Audit', align: 'center', width: 130 },
            //{ field: 'DepartmentAudityName', title: 'Department Auditee', align: 'center', width: 140, formatter: function(value) { return value || '-'; } },
            { field: 'Status', title: 'Status', align: 'center', width: 100, formatter: function(value, row) {
                if (value === 'Completed') {
                    return '<span style="display:inline-block; background-color:rgba(40,167,69,0.15); color:#1c7430; font-weight:400; padding:2px 12px; border-radius:12px;">Completed</span>';
                }
                if (value === 'CancelPending') {
                    // Kuning = pembatalan sudah diajukan, menunggu konfirmasi Approval 1/2
                    return '<span title="Menunggu approval pembatalan" style="display:inline-block; background-color:rgba(255,193,7,0.25); color:#8a6d00; font-weight:400; padding:2px 12px; border-radius:12px;">Cancelled</span>';
                }
                if (value === 'Cancelled') {
                    // Merah = pembatalan sudah disetujui (final)
                    return '<span title="Pemeriksaan dibatalkan" style="display:inline-block; background-color:rgba(217,83,79,0.15); color:#a94442; font-weight:400; padding:2px 12px; border-radius:12px;">Cancelled</span>';
                }
                return value || 'In Progress';
            }},
            /*
            { field: 'DeadlineList', title: 'Deadline', align: 'center', width: 140, formatter: function(value) {
                if (!value) return '-';
                var dates = value.split(',');
                var formatted = $.map(dates, function(d) {
                    d = $.trim(d);
                    if (!d) return null;
                    return new Date(d).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'});
                });
                return formatted.join('<br>');
            }},
            */
            { field: 'TotalProgress', title: 'Progress (%)', align: 'center', width: 100, formatter: function(value) { return (value || 0) + '%'; } },            
            { field: 'CreatedByNames', title: 'Created By', align: 'center', width: 100, formatter: function(value, row) {
                var base = value || '-';
                if ((row.Status === 'Cancelled' || row.Status === 'CancelPending') && row.CancelledByName) {
                    base += '<br><span style="color:#d9534f;">' + row.CancelledByName + '</span>';
                }
                return base;
            }},            
            { field: 'CreatedAt', title: 'Create Date', align: 'center', width: 100, formatter: function(value, row) {
                if (!value) return '-';
                var base = new Date(value).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'});
                if ((row.Status === 'Cancelled' || row.Status === 'CancelPending') && row.UpdatedAt) {
                    var cancelledDateLabel = new Date(row.UpdatedAt).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'});
                    base += '<br><span style="color:#d9534f;">' + cancelledDateLabel + '</span>';
                }
                return base;
            }}
        ]],
        filters : [
            { field: 'JudulKondisi' }
        ]
    });

    $('#grdInternalAudit_btnAdd').click(function(){
        showInternalAuditDetail({});
    });

    window.AppNotif = {
        module: 'INTERNAL_AUDIT_LIST',
        refKey: 'AuditId',
        countUrl: '{{ url("app-notifications/internal-audit-list/count") }}',
        itemsUrl: '{{ url("app-notifications/internal-audit-list/items") }}',
        markReadOneUrl: '{{ url("app-notifications/internal-audit-list/mark-read-one") }}',
        markReadManyUrl: '{{ url("app-notifications/internal-audit-list/mark-read-many") }}',
        buildMarkReadOneData: function(refId) {
            return { AuditId: refId };
        },
        onItemClick: function(refId) {
            $('#dlgInternalAudit').hide();
            $('#dlgInternalAuditDetail').hide();
            $('#frmAuditHeader').show();
            $('#grdInternalAudit').datagrid('load', { NotifAuditId: refId });
        },
        onShowMore: function(ids) {
            $('#grdInternalAudit').datagrid('reload');
        }
    };

    if (typeof initAppNotif === 'function') initAppNotif();

    // ===== Konfirmasi pembatalan pemeriksaan (khusus Approval 1 / Approval 2) =====
    var loginUserIdIdx = '{{ $loginUserId ?? '' }}';

    renderModal('dlgCancelConfirmIA', 'Konfirmasi Pembatalan', 'modal-sm');

    function isLoginApproverOfRow(row) {
        var u = String(loginUserIdIdx || '').trim();
        if (!u) return false;
        return (row.ApproverUserId1 && String(row.ApproverUserId1).trim() === u)
            || (row.ApproverUserId2 && String(row.ApproverUserId2).trim() === u);
    }

    // Baris berstatus Cancelled kuning (menunggu approval) yang di-klik Edit / Progress
    function handleCancelPending(row) {
        if (!isLoginApproverOfRow(row)) {
            alertBoxAuto({ msg: 'Pemeriksaan ini sedang menunggu approval pembatalan', mode: 'warning' });
            return;
        }

        $('#hdnCancelConfirmAuditId').val(row.AuditId);
        $('#lblCancelJudul').text(row.JudulKondisi || '-');
        $('#lblCancelPengaju').text(row.CancelledByName || '-');
        $('#lblCancelTanggal').text(row.UpdatedAt ? new Date(row.UpdatedAt).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-');
        $('#frmCancelConfirmIAAlert').html('');
        $('#dlgCancelConfirmIA').modal('show');
    }

    function respondCancel(url) {
        ajax({
            url      : url,
            postData : { AuditId: $('#hdnCancelConfirmAuditId').val() },
            blockId  : 'dlgCancelConfirmIA',
            alertId  : 'frmCancelConfirmIAAlert',
            success  : function(ret) {
                if (!ret.result) {
                    alertBoxAuto({ id: 'frmCancelConfirmIAAlert', msg: ret.msg, mode: 'error' });
                    return;
                }
                // Popup tutup + grid reload saja, tidak lanjut membuka form
                $('#dlgCancelConfirmIA').modal('hide');
                $('#grdInternalAudit').datagrid('reload');
            }
        });
    }

    $('#btnCancelConfirmNo').click(function(){
        respondCancel('{{ url("audit/transactions/audit-list/cancel-reject") }}');
    });

    $('#btnCancelConfirmYes').click(function(){
        respondCancel('{{ url("audit/transactions/audit-list/cancel-approve") }}');
    });

    setTimeout(function() {
        $('#grdInternalAudit_btnEdit').click(function(){
            var row = $('#grdInternalAudit').datagrid('getSelected');
            if (!row) {
                alertBoxAuto({msg: 'Pilih data terlebih dahulu', mode: 'warning'});
                return;
            }
            if (row.Status === 'CancelPending') {
                handleCancelPending(row);
                return;
            }
            if (row.Status === 'Cancelled') {
                alertBoxAuto({msg: 'Data Pemeriksaan ini sudah di Cancelled, tidak dapat diperbaiki kembali', mode: 'warning'});
                return;
            }
            showInternalAuditDetail(row, true);
        });

        $('#grdInternalAudit_btnView').click(function(){
            var row = $('#grdInternalAudit').datagrid('getSelected');
            if (!row) {
                alertBoxAuto({msg: 'Pilih data terlebih dahulu', mode: 'warning'});
                return;
            }
            if (row.Status === 'CancelPending') {
                handleCancelPending(row);
                return;
            }
            // Status Cancelled (merah) tetap boleh masuk, tapi detail-modal akan full readonly
            showInternalAuditDetailReadonly(row);
        });
    }, 50);
});
</script>

<div class="form-horizontal">
    <div id="frmAuditHeader">
        <div class="row">
            <div class="col-sm-12">
                <div id="grdInternalAudit"></div>
            </div>
        </div>
    </div>
</div>

<div id="dlgCancelConfirmIA" style="display: none">
    <div class="modal-body">
        <div id="frmCancelConfirmIAAlert"></div>
        <input type="hidden" id="hdnCancelConfirmAuditId" value=""/>
        <p style="margin-bottom:12px;">Apakah Anda yakin ingin membatalkan data pemeriksaan berikut?</p>
        <div style="margin-bottom:6px;"><strong>Judul Pemeriksaan</strong><br><span id="lblCancelJudul">-</span></div>
        <div style="margin-bottom:6px;"><strong>Diajukan oleh</strong><br><span id="lblCancelPengaju">-</span></div>
        <div style="margin-bottom:6px;"><strong>Tanggal Pengajuan</strong><br><span id="lblCancelTanggal">-</span></div>
        <div style="display:flex; justify-content:flex-end; gap:6px; margin-top:12px;">
            <button type="button" id="btnCancelConfirmNo" style="background-color: white; color: #555; border: 1px solid #ccc; padding: 6px 12px; border-radius: 3px; cursor: pointer; margin: 0;">
                <i class="fa fa-times"></i>&nbsp;No
            </button>
            <button type="button" id="btnCancelConfirmYes" style="background-color: white; color: #d9534f; border: 1px solid #d9534f; padding: 6px 12px; border-radius: 3px; cursor: pointer; margin: 0;">
                <i class="fa fa-check"></i>&nbsp;Yes
            </button>
        </div>
    </div>
</div>

@include('audit.transactions.internal-audit.modal')
@include('audit.transactions.internal-audit.detail-modal')
@if($isFullPage) @endsection @endif