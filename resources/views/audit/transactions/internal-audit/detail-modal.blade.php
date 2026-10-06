<style>
    #txtPeriodePemeriksaanDetail { text-align: left; },
    #txtTanggalPemeriksaanDetail { text-align: left; }
    .pdp-header-table,
    .pic-deadline-pair-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .pdp-header-table td,
    .pic-deadline-pair-table td { padding: 6px 8px; text-align: center; vertical-align: middle; }
    .pdp-header-table td.pdp-dl,
    .pdp-header-table td.pdp-pg,
    .pic-deadline-pair-table td.pdp-dl,
    .pic-deadline-pair-table td.pdp-pg { border-left: 1px solid #ddd; }
    .pic-deadline-pair-table tr + tr td { border-top: 1px solid #ddd; }
    #divTemuanDbDetail .datagrid-body td { vertical-align: middle !important; padding-top: 0 !important; }
    #divTemuanDbDetail .datagrid-cell[class*="PicAuditeeList"] {
        padding: 0 !important;
        overflow: hidden !important;
        display: flex !important;
        align-items: center !important;
        height: 100% !important;
    }
    #tblLampiranDetail table { border-collapse: collapse !important; width: 100%; margin-bottom: 5px !important; }
    #tblLampiranDetail table th, #tblLampiranDetail table td { border: 1px solid #aaa !important; padding: 6px 10px !important; }
    #tblLampiranDetail table thead tr { background-color: #99CCCC; }
    .progress-col-wrap { display:flex; gap:10px; margin-top:5px; }
    .progress-col { flex:1; border:1px solid #ddd; border-radius:4px; overflow:hidden; }
    .progress-col-header { background:#f0f0f0; padding:6px 10px; font-weight:600; border-bottom:1px solid #ddd; }
    .progress-col-body { max-height:280px; overflow-y:auto; } /* ~5 item histori, sisanya discroll */
    .progress-item { padding:8px 10px; border-bottom:1px solid #eee; font-size:13px; }
    .progress-item:last-child { border-bottom:none; }
    .progress-item .progress-badge { display:inline-block; background:#99CCCC; color:#333; padding:1px 8px; border-radius:10px; font-size:11px; margin-right:6px; }
    .progress-item .progress-meta { color:#888; font-size:11px; margin-top:3px; }
    .progress-item .progress-perbaikan { color:#555; font-size:12px; margin-top:4px; font-style:italic; }
    .progress-empty { padding:15px; text-align:center; color:#999; font-size:13px; }
</style>

<script type="text/javascript">
function alertBoxAuto(opts) {
    alertBox('show', opts);
    setTimeout(function(){
        alertBox('hide', (opts && opts.id) ? { id: opts.id } : undefined);
    }, 5000);
}

// Render satu <table> HTML asli berisi pasangan PIC Auditee & Deadline per entry Tindak Lanjut/Perbaikan.
// Dipakai sebagai formatter untuk SATU kolom gabungan (bukan dua kolom terpisah) - supaya baris
// PIC & Deadline otomatis sejajar lewat perilaku native <tr>/<td>, tanpa perlu JS penyamaan tinggi.
function formatPicDeadlinePairCellDetail(value, row) {
    // Fraud / Spesial Audit: tidak ada PIC Auditee, hanya Deadline | Progress (1 entry per Temuan)
    if (isFraudDetail) {
        var dlListF = row.DeadlineList ? String(row.DeadlineList).split('|') : [];
        var pgListF = row.ProgressList ? String(row.ProgressList).split('|') : [];
        var dRawF   = $.trim(dlListF[0] || '');
        var dLabelF = '-';
        if (dRawF) {
            var todayF   = new Date();
            todayF.setHours(0, 0, 0, 0);
            var dtF      = new Date(dRawF);
            var diffDayF = Math.ceil((dtF - todayF) / (1000 * 60 * 60 * 24));
            var labelF   = dtF.toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'});
            if (diffDayF < 0) {
                dLabelF = '<span style="background-color: #FFCCCC; color: #990000; padding: 2px 6px; border-radius: 3px; display:inline-block;">' + labelF + '</span>';
            } else if (diffDayF <= 30) {
                dLabelF = '<span style="background-color: #FFF3C4; color: #8A6D00; padding: 2px 6px; border-radius: 3px; display:inline-block;">' + labelF + '</span>';
            } else {
                dLabelF = '<span style="display:inline-block;">' + labelF + '</span>';
            }
        }
        var pgF = $.trim(pgListF[0] || '');
        return '<table class="pic-deadline-pair-table"><colgroup><col style="width:60%"><col></colgroup><tbody>' +
            '<tr><td class="pdp-pic">' + dLabelF + '</td><td class="pdp-pg">' + (pgF !== '' ? pgF : '0') + '%</td></tr>' +
            '</tbody></table>';
    }
    var picList = row.PicAuditeeList ? String(row.PicAuditeeList).split('|') : [];
    var dlList  = row.DeadlineList ? String(row.DeadlineList).split('|') : [];
    var pgList  = row.ProgressList ? String(row.ProgressList).split('|') : [];
    var count   = Math.max(picList.length, dlList.length, pgList.length);
    if (count === 0) return '-';

    var today = new Date();
    today.setHours(0, 0, 0, 0);

    var html = '<table class="pic-deadline-pair-table"><colgroup><col style="width:44%"><col style="width:36%"><col></colgroup><tbody>';
    for (var i = 0; i < count; i++) {
        var pic  = $.trim(picList[i] || '') || '-';
        var dRaw = $.trim(dlList[i] || '');
        var dLabel = '-';
        if (dRaw) {
            var dt      = new Date(dRaw);
            var diffDay = Math.ceil((dt - today) / (1000 * 60 * 60 * 24));
            var label   = dt.toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'});
            if (diffDay < 0) {
                dLabel = '<span style="background-color: #FFCCCC; color: #990000; padding: 2px 6px; border-radius: 3px; display:inline-block;">' + label + '</span>';
            } else if (diffDay <= 30) {
                dLabel = '<span style="background-color: #FFF3C4; color: #8A6D00; padding: 2px 6px; border-radius: 3px; display:inline-block;">' + label + '</span>';
            } else {
                dLabel = '<span style="display:inline-block;">' + label + '</span>';
            }
        }
        var pg      = $.trim(pgList[i] || '');
        var pgLabel = (pg !== '' ? pg : '0') + '%';
        html += '<tr><td class="pdp-pic">' + pic + '</td><td class="pdp-dl">' + dLabel + '</td><td class="pdp-pg">' + pgLabel + '</td></tr>';
    }
    html += '</tbody></table>';
    return html;
}

// Preview lampiran di tab baru (bukan download). PDF & gambar dibuka lewat blob dengan MIME yang benar.
window.openLampiranPreview = function(url) {
    var ext = (String(url).split('?')[0].split('.').pop() || '').toLowerCase();
    var mimeMap = { pdf: 'application/pdf', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', bmp: 'image/bmp' };
    var mime = mimeMap[ext];
    if (!mime) { window.open(url, '_blank'); return; }

    var w = window.open('', '_blank');
    if (!w) { window.open(url, '_blank'); return; }
    fetch(url).then(function(r) {
        if (!r.ok) throw new Error('fetch failed');
        return r.blob();
    }).then(function(b) {
        w.location.href = URL.createObjectURL(new Blob([b], { type: mime }));
    }).catch(function() {
        w.location.href = url;
    });
};

// Persentase tiap komentar Auditee (Reguler) ditentukan dari penilaian Auditor:
// - komentar Auditee terakhir sebelum penilaian Auditor -> ikut persentase penilaian tsb
// - komentar Auditee lain di antara dua penilaian -> ikut penilaian Auditor sebelumnya (0% jika belum ada)
function calcAuditeePercentIA(auditorList, auditeeList) {
    function ts(x) { return x.CreatedAt ? new Date(x.CreatedAt).getTime() : 0; }
    function byTime(a, b) { return (ts(a) - ts(b)) || (Number(a.ItemId) - Number(b.ItemId)); }

    var ratings = $.grep(auditorList || [], function(a){ return a.ItemType !== 'Lampiran'; }).sort(byTime);
    var entries = $.grep(auditeeList || [], function(e){
        return e.ItemType !== 'Lampiran' && (!e.ApprovalStatus || e.ApprovalStatus === 'Approved');
    }).sort(byTime);

    var map = {};
    $.each(entries, function(i, e) {
        var prev = null, next = null;
        $.each(ratings, function(j, r) {
            if (ts(r) <= ts(e)) { prev = r; } else { next = r; return false; }
        });
        var isLastBeforeNext = !!next && !(entries[i + 1] && ts(entries[i + 1]) < ts(next));
        map[e.ItemId] = {
            percent : isLastBeforeNext ? (next.Progress || 0) : (prev ? (prev.Progress || 0) : 0),
            waiting : !next && i === entries.length - 1
        };
    });
    return map;
}

var selectedDtlIdDetail = null;
var selectedPerbaikanIdDetail = null;
var latestByPerbaikanMapDetail = {}; // PerbaikanId -> Progress terakhir, diisi sekali dari loadProgressHistoryByDtl
var allProgressAuditorDetail = [];   // histori Auditor SEMUA Perbaikan di Temuan ini, difilter client-side per PerbaikanId
var allProgressAuditeeDetail = [];   // histori Auditee SEMUA Perbaikan di Temuan ini, difilter client-side per PerbaikanId
var isCompletedDetail = false;
var isCancelledDetail = false;           // true jika status Cancelled final (merah) -> semua input readonly kecuali dropdown Tindak Lanjut
var isPicAuditorDetail = false;          // login user ada di PIC Auditor (Model = Informasi Kondisi)
var isApproverDetail = false;            // login user = Approver 1 atau Approver 2
var canActDetail = false;                // true jika PIC Auditor ATAU Approver -> boleh beraktivitas
var approverUserId1Detail = '';
var approverUserId2Detail = '';
var origFlagApproval1Detail = '0';
var origFlagApproval2Detail = '0';
var loginUserIdIA = '{{ $loginUserId ?? '' }}';
var loginUserNameIA = '{{ $loginUserName ?? '' }}';
var loginNikIdIA = '{{ $loginNikId ?? '' }}';
var isFraudDetail = false;               // true jika Jenis Audit = Fraud / Spesial Audit -> tampilkan form tambah Lampiran
var lampiranTempDetail = [];             // lampiran (existing + baru) untuk DtlId yang sedang dipilih
var deletedLampiranIdsDetail = [];       // LampiranId (DB) yang dihapus user, dikirim saat Update Progress

$(document).ready(function(){

    function toggleProgressReadonlyDetail() {
        var disabled = isCompletedDetail || !canActDetail || isCancelledDetail;
        $('#txtKeteranganDetail').prop('readonly', disabled);
        $('#cboProgressDetail').prop('disabled', disabled);

        var lampiranDisabled = isCompletedDetail || !canActDetail || isCancelledDetail || !selectedPerbaikanIdDetail;
        $('#txtNamaFileLampiranDetail').prop('readonly', lampiranDisabled);
        $('#txtFileLampiranDetail').prop('disabled', lampiranDisabled);
        if ($('#txtFileLampiranDetail').data('fileinput')) {
            $('#txtFileLampiranDetail').fileinput(lampiranDisabled ? 'disable' : 'enable');
        }
        $('#btnAddLampiranDetail').prop('disabled', lampiranDisabled).css({ opacity: lampiranDisabled ? 0.5 : 1, cursor: lampiranDisabled ? 'not-allowed' : 'pointer' });
        // Tombol Save tetap aktif walau Status Completed (supaya perubahan Workflow bisa disimpan),
        // tapi terkunci total jika login user bukan PIC Auditor / Approver 1 / Approver 2
        var saveLocked = !canActDetail || isCancelledDetail;
        $('#btnSaveProgressDetail, #btnCancelAuditDetail').prop('disabled', saveLocked).css({ opacity: saveLocked ? 0.5 : 1, cursor: saveLocked ? 'not-allowed' : 'pointer' });
    }

    // Hitung ulang akses: PIC Auditor ATAU Approver 1/2 -> boleh beraktivitas
    function refreshAccessDetail() {
        canActDetail = isPicAuditorDetail || isApproverDetail;
        toggleProgressReadonlyDetail();
    }

    function toggleWorkflowEditability() {
        if (isCancelledDetail) {
            $('#cboFlagApproval1IA').prop('disabled', true);
            $('#cboFlagApproval2IA').prop('disabled', true);
            return;
        }
        var totalProgressVal = parseInt($('#txtTotalProgressDetail').val()) || 0;
        if (totalProgressVal < 100) {
            $('#cboFlagApproval1IA').prop('disabled', true);
            $('#cboFlagApproval2IA').prop('disabled', true);
            return;
        }

        var normalizedLogin = loginUserIdIA ? String(loginUserIdIA).trim() : '';
        var canEditFlag1 = approverUserId1Detail && normalizedLogin && String(approverUserId1Detail).trim() === normalizedLogin;
        var canEditFlag2 = approverUserId2Detail && normalizedLogin && String(approverUserId2Detail).trim() === normalizedLogin;
        var flag1 = String($('#cboFlagApproval1IA').val());
        var flag2 = String($('#cboFlagApproval2IA').val());

        if (flag1 === '1') {
            $('#cboFlagApproval1IA').prop('disabled', true);
        } else {
            $('#cboFlagApproval1IA').prop('disabled', !canEditFlag1);
        }

        if (flag2 === '1') {
            $('#cboFlagApproval2IA').prop('disabled', true);
        } else if (flag1 !== '1') {
            $('#cboFlagApproval2IA').prop('disabled', true);
        } else {
            $('#cboFlagApproval2IA').prop('disabled', !canEditFlag2);
        }
    }

    renderGrid('grdTemuanDbDetail', {
        url           : '{{ url("audit/transactions/audit-list/get-temuan-list") }}',
        toolbar       : false,
        btnDefault    : { add: false, delete: false, edit: false },
        autoLoad      : false,
        pagination    : true,
        pageSize      : 5,
        pageList      : [5, 10, 20],
        showFilterBar : false,
        minHeight     : 150,
        title         : '',
        rownumbers    : false,
        fitColumns    : true,
        columns : [[
            { field: 'No', title: 'No', width: 50, align: 'center', valign: 'middle', formatter: function(value, row, index) { return index + 1; }},
            { field: 'JudulTemuan', title: 'Judul Temuan', align: 'center' },
            { field: 'PicAuditeeList', title: '<table class="pdp-header-table"><colgroup><col style="width:44%"><col style="width:36%"><col></colgroup><tr><td class="pdp-pic">PIC Auditee</td><td class="pdp-dl">Deadline</td><td class="pdp-pg">Progress</td></tr></table>', align: 'center', width: 350, sortable: false, formatter: formatPicDeadlinePairCellDetail }
        ]],
        filters: [],
        onClickRow: function(index, row) {
            if (!row || !row.DtlId) return;
            selectedDtlIdDetail = row.DtlId;
            renderTemuanFieldViewDetail(row);
            lampiranTempDetail = [];
            renderLampiranDetail();
            loadProgressHistoryByDtl(row.DtlId, function() {
                loadPerbaikanDropdownDetail(row.DtlId);
            });
        }
    });

    function renderLampiranDetail() {
        // Sama seperti status-penyelesaian/modal.blade.php - tabel ini cuma menampilkan lampiran
        // yang BELUM tersimpan ke database (masih di memory browser, sesi ini). Lampiran yang
        // sudah tersimpan tidak ditampilkan lagi di sini karena sudah kelihatan di panel Auditee.
        var newOnlyDetail = $.grep(lampiranTempDetail, function(l){ return !l.isExisting; });

        var html = '<table class="table table-bordered table-condensed" style="margin-top:15px; width:100%; table-layout: fixed;">';
        html += '<thead><tr>' +
            '<th style="text-align:center; width: 25%;">Keterangan</th>' +
            '<th style="text-align:center; width: 47%;">File</th>' +
            '<th style="text-align:center; width: 20%;">Created By</th>' +
            '<th style="text-align:center; width: 8%;">Aksi</th>' +
            '</tr></thead><tbody>';

        if (newOnlyDetail.length === 0) {
            html += '<tr><td colspan="4" style="text-align:center; color:#999;">Belum ada lampiran baru</td></tr>';
        } else {
            $.each(lampiranTempDetail, function(i, item) {
                if (item.isExisting) return; // skip - sudah kelihatan di panel Auditor/Auditee

                var fileUrl = "/upload/temp/" + item.FileLampiran;
                var createdByLabel = '-';
                if (item.CreatedByName) {
                    var tglLabel = item.CreatedAt ? new Date(item.CreatedAt).toLocaleDateString('id-ID', {day:'2-digit', month:'2-digit', year:'numeric'}) + ' . ' + new Date(item.CreatedAt).toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'}) : '';
                    var roleLabel = item.AuditRole ? (' - ' + item.AuditRole) : '';
                    createdByLabel = item.CreatedByName + roleLabel + '<br><span style="color:#888; font-size:11px;">' + tglLabel + '</span>';
                }
                html += '<tr>';
                html += '<td style="word-wrap: break-word; vertical-align: middle; text-align: center;">' + (item.NamaFile || '-') + '</td>';
                html += '<td style="vertical-align: middle; text-align: center;">' +
                        '<div style="display: inline-flex; align-items: center; gap: 5px; text-align: left;">' +
                            '<button type="button" onclick="window.open(\'' + fileUrl + '\', \'_blank\')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:#0066CC; flex-shrink: 0;">' +
                            '<i class="fa fa-eye"></i></button>' +
                            '<span style="word-break: break-all; line-height: 1.4; font-size: 11px; color: #555;">' + item.FileLampiran + '</span>' +
                        '</div></td>';
                html += '<td style="vertical-align: middle; text-align: center; font-size:12px;">' + createdByLabel + '</td>';
                html += '<td style="vertical-align: middle; text-align: center;"><button type="button" onclick="deleteLampiranDetail(' + i + ')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:red;"><i class="fa fa-trash"></i></button></td>';
                html += '</tr>';
            });
        }
        html += '</tbody></table>';
        $('#tblLampiranDetail').html(html);
    }

    function loadLampiranDetail(perbaikanId) {
        deletedLampiranIdsDetail = [];
        if (!perbaikanId) {
            lampiranTempDetail = [];
            renderLampiranDetail();
            return;
        }
        ajax({
            url      : '{{ url("audit/transactions/audit-list/get-lampiran-list-array") }}',
            postData : { PerbaikanId: perbaikanId },
            blockId  : 'dlgInternalAuditDetail',
            success  : function(ret) {
                var list = (ret && ret.data) ? ret.data : [];
                lampiranTempDetail = $.map(list, function(l) {
                    return {
                        NamaFile: l.NamaFile, FileLampiran: l.FileLampiran,
                        isExisting: true, LampiranId: l.LampiranId,
                        CreatedByName: l.CreatedByName, AuditRole: l.AuditRole, CreatedAt: l.CreatedAt
                    };
                });
                renderLampiranDetail();
            }
        });
    }

    function sanitizeFileNameDetail(fileName) {
        var dotIndex = fileName.lastIndexOf('.');
        var name = dotIndex > -1 ? fileName.substring(0, dotIndex) : fileName;
        var ext  = dotIndex > -1 ? fileName.substring(dotIndex) : '';
        name = name.replace(/\s*-\s*/g, '_').replace(/[.,\s]/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '');
        return name + ext;
    }

    document.getElementById('txtFileLampiranDetail').addEventListener('change', function (e) {
        var input = e.target;
        if (!input.files || input.files.length === 0) return;
        var file = input.files[0];
        var newName = sanitizeFileNameDetail(file.name);
        if (newName === file.name) return;
        var renamedFile = new File([file], newName, { type: file.type });
        var dt = new DataTransfer();
        dt.items.add(renamedFile);
        input.files = dt.files;
    }, true);

    renderUploadFile({
        id: 'txtFileLampiranDetail',
        postData: { prefix: 'InternalAudit' },
        maxFileSize: 5,
        allowedFileExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'bmp', 'doc', 'docx']
    });

    $('#txtFileLampiranDetail').on('fileuploaded', function(event, data){
        $('#hdnFileLampiranDetailUploaded').val(data.response.filename);
    });

    $('#btnAddLampiranDetail').click(function(){
        if (!selectedPerbaikanIdDetail) {
            alertBoxAuto({ id: 'frmProgressDetailAlert', msg: 'Pilih Tindak Lanjut yang mau diupdate terlebih dahulu..', mode: 'warning' });
            return;
        }

        var namaFile     = $('#txtNamaFileLampiranDetail').val();
        var fileLampiran = $('#hdnFileLampiranDetailUploaded').val();

        if (!fileLampiran) {
            alertBoxAuto({ id: 'frmProgressDetailAlert', msg: 'Pilih file terlebih dahulu', mode: 'warning' });
            return;
        }

        lampiranTempDetail.push({
            NamaFile: namaFile, FileLampiran: fileLampiran, isExisting: false, LampiranId: null,
            CreatedByName: loginUserNameIA, AuditRole: 'Auditor', CreatedAt: null
        });

        $('#txtNamaFileLampiranDetail').val('');
        $('#hdnFileLampiranDetailUploaded').val('');
        $('#txtFileLampiranDetail').fileinput('clear');

        renderLampiranDetail();
    });

    window.deleteLampiranDetail = function(index) {
        var item = lampiranTempDetail[index];
        if (item && item.isExisting && item.LampiranId) {
            deletedLampiranIdsDetail.push(item.LampiranId);
        }
        lampiranTempDetail.splice(index, 1);
        renderLampiranDetail();
    };

    // Histori gabungan SEMUA Tindak Lanjut milik Temuan ini - dipanggil sekali tiap row Temuan
    // diklik, TIDAK ikut berubah waktu dropdown Tindak Lanjut diganti (beda dari loadProgressTemuan
    // di bawah yang scoped per-PerbaikanId, dipakai buat prefill input Progress baru).
    // Ambil SEMUA histori (Auditor+Auditee, semua entry Perbaikan) sekali per row Temuan diklik,
    // simpan ke variabel global. Render ke panel TIDAK dilakukan di sini - itu tugas
    // renderProgressPanelForPerbaikanDetail() yang difilter per PerbaikanId (dipanggil dari
    // handler change dropdown), supaya panel tetap "terfilter" sesuai Tindak Lanjut yang dipilih
    // tapi tanpa ajax tambahan tiap ganti dropdown.
    function loadProgressHistoryByDtl(dtlId, cb) {
        ajax({
            url      : '{{ url("audit/transactions/audit-list/get-progress-list-by-dtl") }}',
            postData : { DtlId: dtlId },
            blockId  : 'dlgInternalAuditDetail',
            success  : function(ret) {
                if (!ret.result) return;
                allProgressAuditorDetail = ret.auditor || [];
                allProgressAuditeeDetail = ret.auditee || [];
                latestByPerbaikanMapDetail = ret.latestByPerbaikan || {};
                if (cb) cb();
            }
        });
    }

    // Render panel Auditor/Auditee, difilter dari allProgressAuditorDetail/allProgressAuditeeDetail
    // yang sudah di-fetch sekali - murni client-side, dipanggil tiap dropdown Tindak Lanjut diganti.
    function renderProgressPanelForPerbaikanDetail(perbaikanId) {
        var isReguler = ($('#txtJenisAuditDetail').val() === 'Pemeriksaan Reguler');
        var auditorFiltered = $.grep(allProgressAuditorDetail, function(item){ return String(item.PerbaikanId) === String(perbaikanId); });
        var auditeeFiltered = $.grep(allProgressAuditeeDetail, function(item){ return String(item.PerbaikanId) === String(perbaikanId); });

        if (isReguler) {
            $('#wrapProgressTwoCol').show();
            $('#wrapProgressOneCol').hide();
            renderProgressColumn('#colProgressAuditor', auditorFiltered);
            renderProgressColumn('#colProgressAuditee', auditeeFiltered, calcAuditeePercentIA(auditorFiltered, auditeeFiltered));
        } else {
            $('#wrapProgressTwoCol').hide();
            $('#wrapProgressOneCol').show();
            renderProgressColumn('#colProgressSingle', auditorFiltered);
        }
    }

    function renderProgressPanelEmptyDetail(msg) {
        $('#wrapProgressTwoCol, #wrapProgressOneCol').hide();
        $('#colProgressAuditor, #colProgressAuditee, #colProgressSingle').html('<div class="progress-empty">' + msg + '</div>');
    }

    function loadPerbaikanDropdownDetail(dtlId) {
        ajax({
            url      : '{{ url("audit/transactions/audit-list/get-perbaikan-dropdown") }}',
            postData : { DtlId: dtlId },
            blockId  : 'dlgInternalAuditDetail',
            success  : function(ret) {
                var list = (ret && ret.data) ? ret.data : [];
                var select = $('#cboPerbaikanDetail');
                select.empty();

                if (list.length === 0) {
                    select.append(new Option('- Tidak ada Tindak Lanjut -', ''));
                    select.val('').trigger('change').prop('disabled', true);
                    selectedPerbaikanIdDetail = null;
                    return;
                }

                $.each(list, function(i, item) {
                    select.append(new Option(item.Label, item.PerbaikanId));
                });

                // Dropdown Tindak Lanjut selalu aktif di sini karena detail-modal ini ranahnya
                // Auditor - baik Reguler maupun Fraud, Auditor bebas pilih Tindak Lanjut mana
                // yang mau dilihat/diupdate progressnya. Ganti dropdown sekarang murni
                // client-side (lihat handler change.perbaikanDetail), tanpa ajax/loading.
                select.val(list[0].PerbaikanId).trigger('change').prop('disabled', isFraudDetail);
            }
        });
    }

    // Ganti dropdown Tindak Lanjut sekarang PURE client-side - tidak ada ajax/loading sama
    // sekali. Data "progress terakhir per PerbaikanId" sudah diambil sekaligus (sekali per row
    // Temuan diklik) lewat loadProgressHistoryByDtl() -> latestByPerbaikanMapDetail, jadi tinggal
    // dibaca dari situ.
    $(document).off('change.perbaikanDetail').on('change.perbaikanDetail', '#cboPerbaikanDetail', function(){
        var val = $(this).val();
        selectedPerbaikanIdDetail = val || null;
        if (!val) {
            renderProgressPanelEmptyDetail('Tidak ada Tindak Lanjut yang dapat diupdate');
            loadLampiranDetail(null);
            toggleProgressReadonlyDetail();
            return;
        }

        var isReguler = ($('#txtJenisAuditDetail').val() === 'Pemeriksaan Reguler');
        // Progress bisa diubah Auditor (Reguler maupun Fraud), dikunci hanya jika Status Completed
        // Progress readonly untuk Reguler (ikut input terakhir dari Auditee)
        $('#cboProgressDetail').val(latestByPerbaikanMapDetail[val] || '').trigger('change');
        $('#txtKeteranganDetail').val('');
        toggleProgressReadonlyDetail();

        renderProgressPanelForPerbaikanDetail(val);
        loadLampiranDetail(val);
    });

    function renderProgressColumn(selector, list, pctMap) {
        if (!list || list.length === 0) {
            $(selector).html('<div class="progress-empty">Belum ada histori</div>');
            return;
        }
        var html = '';
        $.each(list, function(i, item) {
            var tgl = item.CreatedAt ? new Date(item.CreatedAt).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'}) : '-';
            // Label Tindak Lanjut dihapus dari sini - sudah terwakili oleh dropdown "Tindak
            // Lanjut / Perbaikan" di atas panel ini, jadi tidak perlu diulang per item (biar
            // tidak makan banyak space).
            // Lampiran (ItemType='Lampiran') tampil sebagai tombol preview (mata) ke file,
            // bukan badge persen - sama seperti sisi Auditee (status-penyelesaian/modal.blade.php).
            var mainContent;
            if (item.ItemType === 'Lampiran') {
                var fileUrlIA = "{{ ENV('ASSET_FILE') }}netfile/InternalAudit/" + item.FileLampiran;
                mainContent = '<button type="button" onclick="openLampiranPreview(\'' + fileUrlIA + '\')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:#0066CC; margin-right:6px;"><i class="fa fa-eye"></i></button>' +
                    (item.Keterangan || '-');
            } else {
                var pctVal = item.Progress || 0, waitingHtml = '';
                if (pctMap) {
                    var pctInfo = pctMap[item.ItemId];
                    if (pctInfo) {
                        pctVal = pctInfo.percent;
                        if (pctInfo.waiting) waitingHtml = ' <span style="color:#999; font-size:11px; font-style:italic;">(menunggu penilaian Auditor)</span>';
                    } else {
                        pctVal = null;
                    }
                }
                mainContent = (pctVal === null ? '' : '<span class="progress-badge">' + pctVal + '%</span>') + (item.Keterangan || '-') + waitingHtml;
            }
            html += '<div class="progress-item">' +
                mainContent +
                '<div class="progress-meta">' + (item.CreatedByName || '-') + ' &middot; ' + tgl + '</div>' +
                '</div>';
        });
        $(selector).html(html);
    }

    $('#btnSaveProgressDetail').click(function(){
        var auditId       = $('#hdnAuditIdDetail').val();
        var wantsProgress = !isCompletedDetail && !!$('#txtKeteranganDetail').val();

        var newLampiranDetail      = $.grep(lampiranTempDetail, function(l){ return !l.isExisting; });
        var namaFileListDetail     = $.map(newLampiranDetail, function(l){ return l.NamaFile; });
        var fileLampiranListDetail = $.map(newLampiranDetail, function(l){ return l.FileLampiran; });
        var hasLampiranChanges     = (newLampiranDetail.length > 0 || deletedLampiranIdsDetail.length > 0);

        var newFlag1 = String($('#cboFlagApproval1IA').is(':disabled') ? origFlagApproval1Detail : $('#cboFlagApproval1IA').val());
        var newFlag2 = String($('#cboFlagApproval2IA').is(':disabled') ? origFlagApproval2Detail : $('#cboFlagApproval2IA').val());
        var flag1Changed = (newFlag1 !== origFlagApproval1Detail);
        var flag2Changed = (newFlag2 !== origFlagApproval2Detail);
        var workflowChanged = flag1Changed || flag2Changed;

        if (!isCompletedDetail && selectedPerbaikanIdDetail && !$('#txtKeteranganDetail').val() && !workflowChanged && !hasLampiranChanges) {
            alertBoxAuto({ id: 'frmProgressDetailAlert', msg: 'Keterangan wajib diisi', mode: 'warning' });
            return;
        }

        if (!workflowChanged && !wantsProgress && !hasLampiranChanges) {
            alertBoxAuto({ id: 'frmProgressDetailAlert', msg: 'Pilih Daftar Temuan nya terlebih dahulu', mode: 'warning' });
            return;
        }

        if (wantsProgress && !selectedPerbaikanIdDetail) {
            alertBoxAuto({ id: 'frmProgressDetailAlert', msg: 'Pilih Tindak Lanjut yang mau diupdate terlebih dahulu..', mode: 'warning' });
            return;
        }

        if (hasLampiranChanges && !selectedPerbaikanIdDetail) {
            alertBoxAuto({ id: 'frmProgressDetailAlert', msg: 'Pilih Tindak Lanjut yang mau diupdate terlebih dahulu..', mode: 'warning' });
            return;
        }

        if (wantsProgress && $('#txtJenisAuditDetail').val() === 'Pemeriksaan Reguler' && !$('#cboProgressDetail').val()) {
            alertBoxAuto({ id: 'frmProgressDetailAlert', msg: 'Progress wajib dipilih', mode: 'warning' });
            return;
        }

        function saveProgressStep(cb) {
            if (!wantsProgress && !hasLampiranChanges) { cb(); return; }
            ajax({
                url      : '{{ url("audit/transactions/audit-list/save-progress") }}',
                postData : {
                    PerbaikanId        : selectedPerbaikanIdDetail,
                    Progress           : $('#cboProgressDetail').val(),
                    Keterangan         : $('#txtKeteranganDetail').val(),
                    NamaFileList       : JSON.stringify(namaFileListDetail),
                    FileLampiranList   : JSON.stringify(fileLampiranListDetail),
                    DeletedLampiranIds : JSON.stringify(deletedLampiranIdsDetail)
                },
                blockId  : 'dlgInternalAuditDetail',
                alertId  : 'frmProgressDetailAlert',
                success  : function(ret) {
                    if (!ret.result) {
                        alertBoxAuto({ id: 'frmProgressDetailAlert', msg: ret.msg, mode: 'error' });
                        return;
                    }
                    $('#txtTotalProgressDetail').val((ret.TotalProgress || 0) + '%');
                    toggleWorkflowEditability();
                    if (selectedDtlIdDetail) {
                        loadProgressHistoryByDtl(selectedDtlIdDetail, function() {
                            if (selectedPerbaikanIdDetail) {
                                var isReguler = ($('#txtJenisAuditDetail').val() === 'Pemeriksaan Reguler');
                                $('#cboProgressDetail').val(latestByPerbaikanMapDetail[selectedPerbaikanIdDetail] || '').trigger('change');
                                $('#txtKeteranganDetail').val('');
                                toggleProgressReadonlyDetail();
                                renderProgressPanelForPerbaikanDetail(selectedPerbaikanIdDetail);
                            }
                        });
                    }
                    loadLampiranDetail(selectedPerbaikanIdDetail);
                    if ($('#grdTemuanDbDetail').data('datagrid')) {
                        $('#grdTemuanDbDetail').datagrid('reload');
                    }
                    cb();
                }
            });
        }

        function saveWorkflowStep(cb) {
            if (!workflowChanged) { cb(); return; }
            ajax({
                url      : '{{ url("audit/transactions/audit-list/update-workflow") }}',
                postData : {
                    AuditId       : auditId,
                    FlagApproval1 : newFlag1,
                    FlagApproval2 : newFlag2
                },
                blockId  : 'dlgInternalAuditDetail',
                alertId  : 'frmProgressDetailAlert',
                success  : function(ret) {
                    if (!ret.result) {
                        alertBoxAuto({ id: 'frmProgressDetailAlert', msg: ret.msg, mode: 'error' });
                        return;
                    }
                    origFlagApproval1Detail = ret.FlagApproval1;
                    origFlagApproval2Detail = ret.FlagApproval2;
                    $('#cboFlagApproval1IA').val(ret.FlagApproval1).trigger('change');
                    $('#cboFlagApproval2IA').val(ret.FlagApproval2).trigger('change');
                    $('#txtStatusDetail').val(ret.Status || 'In Progress');
                    isCompletedDetail = (ret.Status === 'Completed');
                    toggleWorkflowEditability();
                    toggleProgressReadonlyDetail();
                    cb();
                }
            });
        }

        saveWorkflowStep(function(){
            saveProgressStep(function(){
                alertBoxAuto({ id: 'frmProgressDetailAlert', msg: 'Data berhasil disimpan!', mode: 'success' });
                if ($('#grdInternalAudit').data('datagrid')) {
                    $('#grdInternalAudit').datagrid('reload');
                }
            });
        });
    });

    var popupFieldMapView = {
        JudulTemuan: '#popupJudulTemuanView',
        DetailTemuan: '#popupDetailTemuanView',
        IndikasiAwal: '#popupIndikasiAwalView',
        Resiko: '#popupResikoView',
        Kerugian: '#popupKerugianView',
        PeraturanSOP: '#popupPeraturanSOPView',
        SanksiKaryawan: '#popupSanksiKaryawanView',
        SanksiAtasan: '#popupSanksiAtasanView',
        RekomendasiAuditor: '#popupRekomendasiAuditorView',
        PengembalianKerugian: '#popupPengembalianKerugianView'
    };
    var activePopupFieldView = 'JudulTemuan';
    var perbaikanListView = [];

    // Header kolom gabungan grdTemuanDbDetail: Reguler = PIC | Deadline | Progress, Fraud = Deadline | Progress
    function updateGrdTemuanDetailHeader() {
        if (!$('#grdTemuanDbDetail').data('datagrid')) return;
        var headerHtml = isFraudDetail
            ? '<table class="pdp-header-table"><colgroup><col style="width:60%"><col></colgroup><tr><td class="pdp-pic">Deadline</td><td class="pdp-pg">Progress</td></tr></table>'
            : '<table class="pdp-header-table"><colgroup><col style="width:44%"><col style="width:36%"><col></colgroup><tr><td class="pdp-pic">PIC Auditee</td><td class="pdp-dl">Deadline</td><td class="pdp-pg">Progress</td></tr></table>';
        var col = $('#grdTemuanDbDetail').datagrid('getColumnOption', 'PicAuditeeList');
        if (col) col.title = headerHtml;
        $('#grdTemuanDbDetail').datagrid('getPanel').find('.datagrid-header td[field="PicAuditeeList"] .datagrid-cell').html(headerHtml);
    }

    function renderPerbaikanListView() {
        if (isFraudDetail) {
            var fItem = perbaikanListView[0];
            var fHtml = '<table class="table table-bordered table-condensed" style="margin-top:0; width:100%;">' +
                '<thead><tr style="background-color:#99CCCC;">' +
                '<th style="text-align:center; width:15%;">Deadline</th>' +
                '<th style="text-align:center;">Detail Tindak Lanjut / Perbaikan</th>' +
                '</tr></thead><tbody>';
            if (!fItem) {
                fHtml += '<tr><td colspan="2" style="text-align:center; color:#999;">Belum ada data</td></tr>';
            } else {
                var fDeadline = fItem.Deadline ? new Date(fItem.Deadline).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-';
                fHtml += '<tr><td style="text-align:center;">' + fDeadline + '</td><td>' + (fItem.DetailPerbaikan || '-') + '</td></tr>';
            }
            fHtml += '</tbody></table>';
            $('#popupFieldViewerView').html(fHtml);
            return;
        }
        var html = '<table class="table table-bordered table-condensed" style="margin-top:0; width:100%;">';
        html += '<thead><tr style="background-color:#99CCCC;">' +
            '<th style="text-align:center; width:10%;">Action</th>' +
            '<th style="text-align:center; width:9%;">Deadline</th>' +
            '<th style="text-align:center; width:14%;">Approval Auditee</th>' +
            '<th style="text-align:center; width:16%;">PIC</th>' +
            '<th style="text-align:center;">Tindak Lanjut / Perbaikan</th>' +
            '</tr></thead><tbody>';
        if (perbaikanListView.length === 0) {
            html += '<tr><td colspan="5" style="text-align:center; color:#999;">Belum ada data</td></tr>';
        } else {
            $.each(perbaikanListView, function(i, item) {
                var deadlineLabel = item.Deadline ? new Date(item.Deadline).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-';
                var picNames = $.map(item.ListPic || [], function(p){ return p.Name; }).join(', ') || '-';
                html += '<tr><td style="text-align:center;">' + (item.Action || '-') + '</td>' +
                    '<td style="text-align:center;">' + deadlineLabel + '</td>' +
                    '<td>' + (item.ApprovalAuditeeName || '-') + '</td>' +
                    '<td>' + picNames + '</td>' +
                    '<td>' + (item.DetailPerbaikan || '-') + '</td></tr>';
            });
        }
        html += '</tbody></table>';
        $('#popupFieldViewerView').html(html);
    }

    function renderPopupFieldViewerView() {
        var val;
        // Container #popupFieldViewerView punya inline font-size:14px (buat field teks biasa).
        // Khusus tabel Tindak Lanjut/Perbaikan, font-size itu dilepas supaya tabel mewarisi ukuran
        // font default halaman - biar sama persis dengan #tblPerbaikanTemp di internal-audit/modal.blade.php.
        if (activePopupFieldView === 'TindakLanjut') {
            $('#popupFieldViewerView').css('font-size', '');
            renderPerbaikanListView();
            return;
        }
        $('#popupFieldViewerView').css('font-size', '14px');
        if (activePopupFieldView === 'Kerugian') {
            var num = parseFloat($('#popupKerugianView').val());
            val = isNaN(num) || num === 0 ? '-' : 'Rp ' + num.toLocaleString('id-ID');
            $('#popupFieldViewerView').text(val);
        } else {
            val = $(popupFieldMapView[activePopupFieldView]).val() || '-';
            $('#popupFieldViewerView').html(val);
        }
    }

    $(document).off('click', '.popup-field-btn-view').on('click', '.popup-field-btn-view', function(){
        var newField = $(this).data('field');
        if (newField === activePopupFieldView) return;
        $('.popup-field-btn-view').removeClass('active');
        $(this).addClass('active');
        activePopupFieldView = newField;
        renderPopupFieldViewerView();
    });

    // renderModal('dlgTemuanDetailPopupView', 'Detail Temuan', 'modal-lg'); // Popup Detail Temuan dinonaktifkan, digantikan inline field-menu di bawah grid, uncomment utk restore

    function renderTemuanFieldViewDetail(row) {
        if (!row) return;

        $('#popupJudulTemuanView').val(row.JudulTemuan || '');
        $('#popupDetailTemuanView').val(row.DetailTemuan || '');
        $('#popupIndikasiAwalView').val(row.IndikasiAwal || '');
        $('#popupResikoView').val(row.Resiko || '');
        $('#popupKerugianView').val(row.Kerugian || 0);
        $('#popupPeraturanSOPView').val(row.PeraturanSOP || '');
        $('#popupSanksiKaryawanView').val(row.SanksiKaryawan || '');
        $('#popupSanksiAtasanView').val(row.SanksiAtasan || '');
        $('#popupRekomendasiAuditorView').val(row.RekomendasiAuditor || '');
        $('#popupPengembalianKerugianView').val(row.PengembalianKerugian || '');

        $('.popup-field-btn-view').removeClass('active');
        $('.popup-field-btn-view[data-field="JudulTemuan"]').addClass('active');
        activePopupFieldView = 'JudulTemuan';
        perbaikanListView = [];
        renderPopupFieldViewerView();

        ajax({
            url      : '{{ url("audit/transactions/audit-list/get-perbaikan-list-array") }}',
            postData : { DtlId: row.DtlId },
            blockId  : 'dlgInternalAuditDetail',
            success  : function(ret) {
                perbaikanListView = (ret && ret.data) ? ret.data : [];
                if (activePopupFieldView === 'TindakLanjut') renderPopupFieldViewerView();
            }
        });
    }

    window.showInternalAuditDetailReadonly = function(row) {
        row = row || {};

        selectedDtlIdDetail = null;
        selectedPerbaikanIdDetail = null;
        $('#cboPerbaikanDetail').empty().append(new Option('-', '')).val('').prop('disabled', true);
        $('#frmProgressDetailAlert').html('');
        $('#wrapProgressTwoCol').hide();
        $('#wrapProgressOneCol').hide();
        $('#colProgressAuditor, #colProgressAuditee, #colProgressSingle').html('<div class="progress-empty">Pilih temuan terlebih dahulu</div>');
        $('#cboProgressDetail').val('').prop('disabled', true);
        $('#txtKeteranganDetail').val('');

        $('#popupJudulTemuanView, #popupDetailTemuanView, #popupIndikasiAwalView, #popupResikoView, #popupKerugianView, #popupPeraturanSOPView, #popupSanksiKaryawanView, #popupSanksiAtasanView, #popupTindakLanjutView, #popupPengembalianKerugianView, #popupDeadlineView').val('');
        $('.popup-field-btn-view').removeClass('active');
        $('.popup-field-btn-view[data-field="JudulTemuan"]').addClass('active');
        activePopupFieldView = 'JudulTemuan';
        $('#popupFieldViewerView').text('-');
        $('#tblLampiranDetail').html('');

        lampiranTempDetail = [];
        deletedLampiranIdsDetail = [];
        $('#txtNamaFileLampiranDetail').val('');
        $('#hdnFileLampiranDetailUploaded').val('');

        $('#hdnAuditIdDetail').val(row.AuditId || '');
        $('#txtNoSuratTugasDetail').val(row.NoSuratTugas || '-');
        $('#txtNoGaroonDetail').val(row.NoGaroon || '-');
        var fmtPeriodeDetail = function(d) { return new Date(d).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}); };
        var periodeLabelDetail = row.PeriodeStart ? (row.PeriodeEnd ? fmtPeriodeDetail(row.PeriodeStart) + ' s/d ' + fmtPeriodeDetail(row.PeriodeEnd) : fmtPeriodeDetail(row.PeriodeStart)) : '-';
        $('#txtPeriodePemeriksaanDetail').val(periodeLabelDetail);
        $('#txtTanggalPemeriksaanDetail').val(row.TanggalPemeriksaan ? new Date(row.TanggalPemeriksaan).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-');
        $('#txtTanggalUploadDetail').val(row.TanggalUpload ? new Date(row.TanggalUpload).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-');
        $('#txtJudulKondisiDetail').val(row.JudulKondisi || '');
        $('#txtJenisAuditDetail').val(row.JenisAudit || '');
        isFraudDetail = (row.JenisAudit === 'Fraud / Spesial Audit');
        $('.popup-field-btn-view[data-field="TindakLanjut"]').text('Tindak Lanjut / Perbaikan');
        updateGrdTemuanDetailHeader();
        $('#txtDepartmentAudityDetail').val(row.DepartmentAudityName || '');
        $('#txtTotalProgressDetail').val((row.TotalProgress || 0) + '%');
        $('#txtListPicDetail').val('');

        $('#txtStatusDetail').val(row.Status || 'In Progress');
        isCompletedDetail = ((row.Status || 'In Progress') === 'Completed');
        isCancelledDetail = (row.Status === 'Cancelled');
        toggleProgressReadonlyDetail();

        $('#txtApproval1IA').val(row.ApproverName1 || '-');
        $('#txtApproval2IA').val(row.ApproverName2 || '-');
        approverUserId1Detail = row.ApproverUserId1 || '';
        approverUserId2Detail = row.ApproverUserId2 || '';
        origFlagApproval1Detail = String(row.FlagApproval1 || '0');
        origFlagApproval2Detail = String(row.FlagApproval2 || '0');

        // Akses: terkunci dulu sampai status PIC Auditor selesai dicek (ajax get-pic-list-array di bawah)
        var normalizedLoginAccess = loginUserIdIA ? String(loginUserIdIA).trim() : '';
        isPicAuditorDetail = false;
        isApproverDetail = !!normalizedLoginAccess && (
            (approverUserId1Detail && String(approverUserId1Detail).trim() === normalizedLoginAccess) ||
            (approverUserId2Detail && String(approverUserId2Detail).trim() === normalizedLoginAccess)
        );
        refreshAccessDetail();
        $('#cboFlagApproval1IA').val(origFlagApproval1Detail).trigger('change');
        $('#cboFlagApproval2IA').val(origFlagApproval2Detail).trigger('change');
        toggleWorkflowEditability();

        if (row.JenisAudit === 'Pemeriksaan Reguler') {
            $('#wrapDepartmentAudityDetail, #lblDepartmentAudityDetail').show();
        } else {
            $('#wrapDepartmentAudityDetail, #lblDepartmentAudityDetail').hide();
        }

        // List PIC (readonly) berlaku untuk kedua Jenis Audit (Reguler maupun Fraud/Spesial)
        $('#wrapListPicDetail, #lblListPicDetail').show();
        // Department Auditee hanya tampil untuk Reguler; saat disembunyikan, PIC Auditor tetap di kolom kanan
        $('#lblListPicDetail').toggleClass('col-sm-offset-6', row.JenisAudit !== 'Pemeriksaan Reguler');
        ajax({
            url      : '{{ url("audit/transactions/audit-list/get-pic-list-array") }}',
            postData : { AuditId: row.AuditId },
            blockId  : 'dlgInternalAuditDetail',
            success  : function(ret) {
                var list = (ret && ret.data) ? ret.data : [];
                var arrNamaPic = $.map(list, function(item){ return item.Name; });
                $('#txtListPicDetail').val(arrNamaPic.length ? arrNamaPic.join(', ') : '-');

                var arrNikPic = $.map(list, function(item){ return String(item.NikId); });
                isPicAuditorDetail = !!loginNikIdIA && arrNikPic.indexOf(String(loginNikIdIA)) !== -1;
                refreshAccessDetail();
            }
        });  

        setTimeout(function() {
            if ($('#grdTemuanDbDetail').data('datagrid')) {
                $('#grdTemuanDbDetail').datagrid('resize');
            }
            gridFilterData('grdTemuanDbDetail', [{field: 'AuditId', value: row.AuditId}], {AuditId: row.AuditId});
        }, 100);

        $('#frmAuditHeader').hide();
        $('#dlgInternalAuditDetail').show();
    };

    $('#btnBackInternalAuditDetail').click(function(){
        $('#dlgInternalAuditDetail').hide();
        $('#frmAuditHeader').show();
        $('#grdInternalAudit').datagrid('reload');
    });

    $('#btnCancelAuditDetail').click(function(){
        if (!confirm('Ajukan pembatalan pemeriksaan ini? Pembatalan baru berlaku setelah dikonfirmasi oleh Approval 1 atau Approval 2.')) return;

        var auditId = $('#hdnAuditIdDetail').val();
        ajax({
            url      : '{{ url("audit/transactions/audit-list/cancel") }}',
            postData : { AuditId: auditId },
            blockId  : 'dlgInternalAuditDetail',
            alertId  : 'frmProgressDetailAlert',
            success  : function(ret) {
                if (!ret.result) {
                    alertBoxAuto({ id: 'frmProgressDetailAlert', msg: ret.msg, mode: 'error' });
                    return;
                }
                $('#dlgInternalAuditDetail').hide();
                $('#frmAuditHeader').show();
                if ($('#grdInternalAudit').data('datagrid')) {
                    $('#grdInternalAudit').datagrid('reload');
                }
                alertBoxAuto({ msg: 'Pembatalan diajukan, menunggu approval', mode: 'success' });
            }
        });
    });

});
</script>

<div id="dlgInternalAuditDetail" style="display: none;">
    <div class="box">

        <div style="position: absolute; top: 10px; left: 10px; z-index: 100;">
            <button type="button" id="btnBackInternalAuditDetail" style="background-color: white; color: #0066CC; border: 1px solid #ccc; padding: 6px 12px; border-radius: 3px; cursor: pointer; margin: 0;">
                <i class="fa fa-arrow-circle-o-left text-blue"></i>&nbsp;Back
            </button>
        </div>

        <div class="box-header" style="padding-top: 50px;"></div>

        <div class="modal-body">

            <div class="form-horizontal">
                <input type="hidden" id="hdnAuditIdDetail" value=""/>

                                <div class="frame">
                    <div class="frame-header"><strong>Informasi Kondisi</strong></div><br>
                    <div class="form-group">
                        <label class="col-sm-2">No Surat Tugas</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtNoSuratTugasDetail" readonly/>
                        </div>
                        <label class="col-sm-2">Periode Pemeriksaan</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtPeriodePemeriksaanDetail" readonly/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2">No Garoon</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtNoGaroonDetail" readonly/>
                        </div>
                        <label class="col-sm-2">Tanggal Pemeriksaan</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtTanggalPemeriksaanDetail" readonly/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2">Judul Pemeriksaan</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtJudulKondisiDetail" readonly/>
                        </div>
                        <label class="col-sm-2">Tanggal Upload</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtTanggalUploadDetail" readonly/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2">Jenis Audit</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtJenisAuditDetail" readonly/>
                        </div>
                        <label class="col-sm-2">Status</label>
                        <div class="col-sm-4">
                            <div class="input-group">                                
                                <input type="text" id="txtStatusDetail" readonly/>
                                <span class="input-group-addon">Total Progress </span>
                                <input type="text" id="txtTotalProgressDetail" readonly/>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2" id="lblDepartmentAudityDetail" style="display:none;">Department Auditee</label>
                        <div class="col-sm-4" id="wrapDepartmentAudityDetail" style="display:none;">
                            <input type="text" id="txtDepartmentAudityDetail" readonly/>
                        </div>
                        <label class="col-sm-2" id="lblListPicDetail" style="display:none;">PIC Auditor</label>
                        <div class="col-sm-4" id="wrapListPicDetail" style="display:none;">
                            <input type="text" id="txtListPicDetail" readonly/>
                        </div>
                    </div>
                </div>

                <div class="frame">
                    <div class="frame-header"><strong>Daftar Temuan Tersimpan</strong></div>
                    <div class="row">
                        <div class="col-md-12">
                            <div id="grdTemuanDbDetail"></div>
                        </div>
                    </div>

                    <div class="temuan-field-wrap" style="margin-top:10px;">
                        <div class="temuan-field-menu" id="grpPopupFieldMenuView">
                            <button type="button" class="temuan-field-btn popup-field-btn-view active" data-field="JudulTemuan">Judul Temuan</button>
                            <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="DetailTemuan">Detail Temuan</button>
                            <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="IndikasiAwal">Indikasi Awal & Bukti / Penyebab</button>
                            <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="Resiko">Resiko</button>
                            <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="Kerugian">Kerugian Perusahaan</button>
                            <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="PeraturanSOP">Peraturan / SOP Perusahaan yang Dilanggar</button>
                            <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="SanksiKaryawan">Jenis Sanksi Berdasar PP & SOP (Karyawan)</button>
                            <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="SanksiAtasan">Jenis Sanksi Berdasar PP & SOP (Atasan)</button>
                            <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="TindakLanjut">Tindak Lanjut / Perbaikan</button>
                            <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="RekomendasiAuditor">Rekomendasi Auditor</button>
                            <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="PengembalianKerugian">Pengembalian Kerugian Perusahaan</button>
                        </div>
                        <div class="temuan-field-editor">
                            <div id="popupFieldViewerView" style="height:100%; max-height:400px; overflow-y:auto; padding:10px; white-space:pre-wrap; font-size:14px; font-family:Arial, sans-serif;">-</div>
                            <input type="hidden" id="popupJudulTemuanView" value=""/>
                            <input type="hidden" id="popupDetailTemuanView" value=""/>
                            <input type="hidden" id="popupIndikasiAwalView" value=""/>
                            <input type="hidden" id="popupResikoView" value=""/>
                            <input type="hidden" id="popupKerugianView" value=""/>
                            <input type="hidden" id="popupPeraturanSOPView" value=""/>
                            <input type="hidden" id="popupSanksiKaryawanView" value=""/>
                            <input type="hidden" id="popupSanksiAtasanView" value=""/>
                            <input type="hidden" id="popupRekomendasiAuditorView" value=""/>
                            <input type="hidden" id="popupPengembalianKerugianView" value=""/>
                        </div>
                    </div>

                </div>

                <div class="frame" id="wrapProgressTemuan">
                    <div class="frame-header"><strong>Progress Temuan</strong></div><br>

                    <div id="frmProgressDetailAlert"></div>

                    <div id="wrapProgressTwoCol" class="progress-col-wrap" style="display:none;">
                        <div class="progress-col">
                            <div class="progress-col-header">Auditor</div>
                            <div class="progress-col-body" id="colProgressAuditor"></div>
                        </div>
                        <div class="progress-col">
                            <div class="progress-col-header">Auditee</div>
                            <div class="progress-col-body" id="colProgressAuditee"></div>
                        </div>
                    </div>

                    <div id="wrapProgressOneCol" style="display:none;">
                        <div class="progress-col">
                            <div class="progress-col-header">Auditor</div>
                            <div class="progress-col-body" id="colProgressSingle"></div>
                        </div>
                    </div>

                    <div class="row" style="margin-top:15px;">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="col-sm-4">Approval 1</label>
                                <div class="col-sm-8">
                                    <input type="text" id="txtApproval1IA" readonly/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4">Workflow 1</label>
                                <div class="col-sm-8">
                                    <select id="cboFlagApproval1IA" name="FlagApproval1" formatter="combo-array" style="display:none;">
                                        <option value="0">In Progress</option>
                                        <option value="1">Approved</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="col-sm-4">Approval 2</label>
                                <div class="col-sm-8">
                                    <input type="text" id="txtApproval2IA" readonly/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4">Workflow 2</label>
                                <div class="col-sm-8">
                                    <select id="cboFlagApproval2IA" name="FlagApproval2" formatter="combo-array" style="display:none;">
                                        <option value="0">In Progress</option>
                                        <option value="1">Approved</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr style="margin-top:15px; margin-bottom:15px; border-top:1px solid #ddd;"/>
                    <div class="row" style="margin-top:15px;">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="col-sm-4">Tindak Lanjut / Perbaikan</label>
                                <div class="col-sm-8">
                                    <select id="cboPerbaikanDetail" name="PerbaikanDetail" formatter="combo-array" style="display:none;">
                                        <option value="">-</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="col-sm-4">Progress</label>
                                <div class="col-sm-8">
                                    <select id="cboProgressDetail" name="ProgressDetail" formatter="combo-array">
                                        <option value="">-</option>
                                        <option value="10">10%</option>
                                        <option value="20">20%</option>
                                        <option value="30">30%</option>
                                        <option value="40">40%</option>
                                        <option value="50">50%</option>
                                        <option value="60">60%</option>
                                        <option value="70">70%</option>
                                        <option value="80">80%</option>
                                        <option value="90">90%</option>
                                        <option value="100">100%</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="col-sm-2">Keterangan</label>
                                <div class="col-sm-10">
                                    <textarea id="txtKeteranganDetail" rows="3" style="width:100%;" placeholder="Keterangan Auditor..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="tblLampiranDetail"></div>

                    <div class="row" id="wrapAddLampiranDetail" style="margin-top:20px;">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="col-sm-4">Keterangan File</label>
                                <div class="col-sm-8"><input type="text" id="txtNamaFileLampiranDetail" placeholder="Keterangan file..."/></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="col-sm-4">File</label>
                                <div class="col-sm-8">
                                    <div class="input-group">
                                        <input type="file" id="txtFileLampiranDetail" name="txtFileLampiranDetail"/>
                                        <input type="hidden" id="hdnFileLampiranDetailUploaded" value=""/>
                                        <span class="input-group-btn" style="vertical-align: top;">
                                            <button type="button" id="btnAddLampiranDetail" style="background-color: white; color: #0066CC; border: 1px solid #ccc; padding: 6px 12px; border-radius: 3px; cursor: pointer; margin-top: 0; vertical-align: top;">
                                                <i class="fa fa-plus" style="color: #0066CC;"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:2px; margin-top:2px;">
                        <button type="button" id="btnCancelAuditDetail" style="background-color: white; color: #d9534f; border: 1px solid #d9534f; padding: 6px 12px; border-radius: 3px; cursor: pointer; margin: 0;">
                            <i class="fa fa-ban" style="color: #d9534f;"></i>&nbsp;Cancel Pemeriksaan
                        </button>
                        <button type="button" id="btnSaveProgressDetail" style="background-color: white; color: #28a745; border: 1px solid #28a745; padding: 6px 12px; border-radius: 3px; cursor: pointer; margin: 0;">
                            <i class="fa fa-save" style="color: #28a745;"></i>&nbsp;Update Progress
                        </button>
                    </div>                    
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ==== POPUP DETAIL TEMUAN (VIEW) DINONAKTIFKAN - digantikan field-menu inline di frame "Daftar Temuan Tersimpan" di atas. uncomment utk restore (perhatikan id akan duplikat dgn versi inline) ====
<div id="dlgTemuanDetailPopupView" style="display: none">
    <div class="modal-body">
        <div class="form-horizontal">
            <div class="temuan-field-wrap">
                <div class="temuan-field-menu" id="grpPopupFieldMenuView">
                    <button type="button" class="temuan-field-btn popup-field-btn-view active" data-field="JudulTemuan">Kondisi</button>
                    <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="DetailTemuan">Kondisi Detail</button>
                    <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="IndikasiAwal">Indikasi Awal & Bukti yang Didapat</button>
                    <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="Resiko">Resiko</button>
                    <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="Kerugian">Kerugian Perusahaan</button>
                    <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="PeraturanSOP">Peraturan / SOP Perusahaan yang Dilanggar</button>
                    <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="SanksiKaryawan">Jenis Sanksi - Karyawan</button>
                    <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="SanksiAtasan">Jenis Sanksi - Atasan</button>
                    <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="TindakLanjut">Tindak Lanjut / Perbaikan</button>
                    <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="PengembalianKerugian">Pengembalian Kerugian Perusahaan</button>
                    <button type="button" class="temuan-field-btn popup-field-btn-view" data-field="Deadline">Deadline</button>
                </div>
                <div class="temuan-field-editor">
                    <div id="popupFieldViewerView" style="height:350px; overflow-y:auto; padding:10px; white-space:pre-wrap; font-size:14px; font-family:Arial, sans-serif;"></div>
                    <input type="hidden" id="popupDeadlineView" value=""/>
                    <input type="hidden" id="popupJudulTemuanView" value=""/>
                    <input type="hidden" id="popupDetailTemuanView" value=""/>
                    <input type="hidden" id="popupIndikasiAwalView" value=""/>
                    <input type="hidden" id="popupResikoView" value=""/>
                    <input type="hidden" id="popupKerugianView" value=""/>
                    <input type="hidden" id="popupPeraturanSOPView" value=""/>
                    <input type="hidden" id="popupSanksiKaryawanView" value=""/>
                    <input type="hidden" id="popupSanksiAtasanView" value=""/>
                    <input type="hidden" id="popupTindakLanjutView" value=""/>
                    <input type="hidden" id="popupPengembalianKerugianView" value=""/>
                </div>
            </div>
        </div>
    </div>
</div>
==== END POPUP DETAIL TEMUAN ==== -->