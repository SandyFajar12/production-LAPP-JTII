<style>
    .frame { border: 1px solid #ccc; padding: 10px; margin: 10px; border-radius: 5px; }
    .frame-header { background-color: #99CCCC; padding: 5px 10px; border-bottom: 1px solid #999; border-radius: 5px 5px 0 0; font-weight: 600; }
    #dlgStatusPenyelesaian .box { position: relative; }

    #txtPeriodePemeriksaanSP { text-align: left; },
    #txtTanggalPemeriksaanSP { text-align: left; }
    #tblLampiranSP table { border-collapse: collapse !important; width: 100%; margin-top: 18px !important;}
    #tblLampiranSP table th, #tblLampiranSP table td { border: 1px solid #aaa !important; padding: 6px 10px !important; }
    #tblLampiranSP table thead tr { background-color: #99CCCC; }

    .pdp-header-table,
    .pic-deadline-pair-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .pdp-header-table td,
    .pic-deadline-pair-table td { padding: 6px 8px; text-align: center; vertical-align: middle; }
    .pdp-header-table td.pdp-dl,
    .pdp-header-table td.pdp-pg,
    .pic-deadline-pair-table td.pdp-dl,
    .pic-deadline-pair-table td.pdp-pg { border-left: 1px solid #ddd; }
    .pic-deadline-pair-table tr + tr td { border-top: 1px solid #ddd; }
    #divTemuanDbSP .datagrid-body td { vertical-align: middle !important; padding-top: 0 !important; }
    #divTemuanDbSP .datagrid-cell[class*="PicAuditeeList"] {
        padding: 0 !important;
        overflow: hidden !important;
        display: flex !important;
        align-items: center !important;
        height: 100% !important;
    }

    .temuan-field-wrap { display:flex; align-items:stretch; border:1px solid #ddd; border-radius:0; overflow:hidden; }
    .temuan-field-menu { width:25%; border-right:1px solid #ddd; overflow-y:auto; }
    .temuan-field-btn { position:relative; display:block; width:100%; text-align:left; border:none; border-bottom:1px solid #ddd; background:#fff; padding:8px 12px; margin:0; cursor:pointer; }
    .temuan-field-btn:last-child { border-bottom:none; }
    .temuan-field-btn.active { background-color:#99CCCC; color:#333; }
    .temuan-field-editor { width:75%; }

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
    #wrapApprovalCardSP .progress-meta { color:#888; font-size:11px; margin-top:6px; }
    #wrapApprovalCardSP .approval-pending-item { padding:2px 0; font-size:13px; }
</style>

<script type="text/javascript">
        function alertBoxAuto(opts) {
            alertBox('show', opts);
            setTimeout(function(){
                alertBox('hide', (opts && opts.id) ? { id: opts.id } : undefined);
                if (opts && opts.id) { $('#' + opts.id).empty(); }
            }, 5000);
        }

        // Deadline sekarang ada di level Perbaikan (TrnInternalAuditPerbaikan), 1 Temuan bisa
        // punya banyak Deadline (satu per entry Tindak Lanjut/Perbaikan). Ditampilkan gabungan
        // per baris (bukan koma), masing-masing baris tetap dapat highlight merah/kuning sendiri.
        function formatDeadlineListCell(value) {
            if (!value) return '-';
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var labels = $.map(String(value).split('|'), function(d) {
                d = $.trim(d);
                if (!d) return null;
                var dt      = new Date(d);
                var diffMs  = dt - today;
                var diffDay = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
                var label   = dt.toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'});
                if (diffDay < 0) {
                    return '<span style="background-color: #FFCCCC; color: #990000; padding: 2px 6px; border-radius: 3px; display:inline-block;">' + label + '</span>';
                } else if (diffDay <= 30) {
                    return '<span style="background-color: #FFF3C4; color: #8A6D00; padding: 2px 6px; border-radius: 3px; display:inline-block;">' + label + '</span>';
                }
                return '<span style="display:inline-block;">' + label + '</span>';
            });
            return labels.length > 0 ? labels.join('<br>') : '-';
        }

        function formatDeadlineProgressPairCell(value, row) {
            var dlList = row.DeadlineList ? String(row.DeadlineList).split('|') : [];
            var pgList = row.ProgressList ? String(row.ProgressList).split('|') : [];
            var count  = Math.max(dlList.length, pgList.length);
            if (count === 0) return '-';

            var today = new Date();
            today.setHours(0, 0, 0, 0);

            var html = '<table class="pic-deadline-pair-table"><colgroup><col style="width:60%"><col></colgroup><tbody>';
            for (var i = 0; i < count; i++) {
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
                html += '<tr><td class="pdp-dl">' + dLabel + '</td><td class="pdp-pg">' + pgLabel + '</td></tr>';
            }
            html += '</tbody></table>';
            return html;
        }

        // Versi 3-kolom (PIC Auditee | Deadline | Progress) untuk grdTemuanDbSP - nyontek persis
        // pola formatPicDeadlinePairCell di internal-audit/detail-modal.blade.php.
        function formatPicDeadlineProgressPairCellSP(value, row) {
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
        // Hanya komentar berstatus Approved yang dihitung; Pending/Rejected tidak punya badge persen.
        function calcAuditeePercentSP(auditorList, auditeeList) {
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

        var selectedDtlIdSP = null;
        var selectedPerbaikanIdSP = null; // PerbaikanId Tindak Lanjut yang sedang dipilih di dropdown
        var latestByPerbaikanMapSP = {};  // PerbaikanId -> Progress terakhir, diisi sekali dari loadProgressHistoryByDtlSP
        var allProgressAuditorSP = [];    // histori Auditor SEMUA Perbaikan di Temuan ini, difilter client-side per PerbaikanId
        var allProgressAuditeeSP = [];    // histori Auditee SEMUA Perbaikan di Temuan ini, difilter client-side per PerbaikanId
        var lampiranTempSP = [];         // lampiran yang sudah ada / baru ditambahkan untuk DtlId terpilih
        var isCompletedSP = false;       // true jika Status Kondisi = Completed -> semua input Progress Temuan readonly
        var loginNikIdSP = '{{ $loginNikId ?? '' }}';   // NikId user login, dipakai buat cek akses edit Progress Temuan (List PIC)
        var loginUserNameSP = '{{ $loginUserName ?? '' }}';
        var isPicMismatchSP = false;     // true jika user login BUKAN PIC Auditee ATAUPUN Approval Auditee di PerbaikanId yang sedang dipilih -> readonly
        var picByPerbaikanMapSP = {};    // PerbaikanId -> IsPic (boolean), diisi dari loadPerbaikanDropdownSP
        var approvalAuditeeByPerbaikanMapSP = {}; // PerbaikanId -> IsApprovalAuditee (boolean), diisi dari loadPerbaikanDropdownSP
        var hasPendingForSelectedSP = false; // true jika PerbaikanId yang dipilih masih punya progress Auditee berstatus Pending -> form input readonly
        var pendingProgressIdSP = null;  // ID (ProgressId/LampiranId) entry Pending yang lagi ditampilkan di card approval
        var pendingPerbaikanIdSP = null; // PerbaikanId yang sedang tampil di card approval (Setuju/Tolak = seluruh entry Pending-nya)
        var pendingItemTypeSP = null;    // 'Progress' | 'Lampiran' - tipe entry Pending yang lagi ditampilkan di card approval

        $(document).ready(function(){

            // Bersihkan sisa kalender daterangepicker dari halaman Internal Audit (menempel di <body>)
            $('.daterangepicker').remove();

            function toggleProgressReadonlySP() {
                // hasPendingForSelectedSP TIDAK lagi bikin form readonly di sini - form tetap
                // aktif walau masih ada progress Pending (bisa jadi PIC lain di Tindak Lanjut
                // yang sama). Guard-nya dipindah ke btnSaveStatusSP (cek + alert khusus) supaya
                // pesannya jelas, bukan cuma "kenapa kok kekunci".
                var disabled = isCompletedSP || isPicMismatchSP;
                // Reguler: nilai Progress ditentukan Auditor, Auditee hanya mengisi Keterangan/Lampiran
                var isRegulerToggleSP = ($('#txtJenisAuditSP').val() === 'Pemeriksaan Reguler');
                $('#cboProgressSP').prop('disabled', disabled || isRegulerToggleSP);
                $('#txtKeteranganSP').prop('readonly', disabled);
                $('#txtNamaFileLampiranSP').prop('readonly', disabled);
                $('#txtFileLampiranSP').prop('disabled', disabled);
                if ($('#txtFileLampiranSP').data('fileinput')) {
                    $('#txtFileLampiranSP').fileinput(disabled ? 'disable' : 'enable');
                }
                $('#btnAddLampiranSP, #btnSaveStatusSP').prop('disabled', disabled).css({ opacity: disabled ? 0.5 : 1, cursor: disabled ? 'not-allowed' : 'pointer' });
            }

            // regulerHiddenFieldsSP & toggleTemuanFieldsByJenisAuditSP dihapus - field menu tidak lagi dibedakan Jenis Audit

            var popupFieldMapSP = {
                JudulTemuan: '#popupJudulTemuanSP',
                DetailTemuan: '#popupDetailTemuanSP',
                IndikasiAwal: '#popupIndikasiAwalSP',
                Resiko: '#popupResikoSP',
                Kerugian: '#popupKerugianSP',
                PeraturanSOP: '#popupPeraturanSOPSP',
                SanksiKaryawan: '#popupSanksiKaryawanSP',
                SanksiAtasan: '#popupSanksiAtasanSP',
                RekomendasiAuditor: '#popupRekomendasiAuditorSP',
                PengembalianKerugian: '#popupPengembalianKerugianSP'
            };
            var activeFieldSP = 'JudulTemuan';
            var perbaikanListSP = []; // list Tindak Lanjut/Perbaikan (readonly) untuk Temuan yang sedang dipilih

            function renderPerbaikanListSP() {
                var html = '<table class="table table-bordered table-condensed" style="margin-top:0; width:100%;">';
                html += '<thead><tr style="background-color:#99CCCC;">' +
                    '<th style="text-align:center; width:10%;">Action</th>' +
                    '<th style="text-align:center; width:9%;">Deadline</th>' +
                    '<th style="text-align:center; width:14%;">Approval Auditee</th>' +
                    '<th style="text-align:center; width:16%;">PIC</th>' +
                    '<th style="text-align:center;">Tindak Lanjut / Perbaikan</th>' +
                    '</tr></thead><tbody>';
                if (perbaikanListSP.length === 0) {
                    html += '<tr><td colspan="5" style="text-align:center; color:#999;">Belum ada data</td></tr>';
                } else {
                    $.each(perbaikanListSP, function(i, item) {
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
                $('#fieldViewerSP').html(html);
            }

            function renderFieldViewerSP() {
                var val;
                // Container #fieldViewerSP punya inline font-size:14px (buat field teks biasa).
                // Khusus tabel Tindak Lanjut/Perbaikan, font-size itu dilepas supaya tabel mewarisi
                // ukuran font default halaman - sama seperti #tblPerbaikanTemp di internal-audit/modal.blade.php.
                if (activeFieldSP === 'TindakLanjut') {
                    $('#fieldViewerSP').css('font-size', '');
                    renderPerbaikanListSP();
                    return;
                }
                $('#fieldViewerSP').css('font-size', '14px');
                if (activeFieldSP === 'Kerugian') {
                    var num = parseFloat($('#popupKerugianSP').val());
                    val = isNaN(num) || num === 0 ? '-' : 'Rp ' + num.toLocaleString('id-ID');
                    $('#fieldViewerSP').text(val);
                } else {
                    val = $(popupFieldMapSP[activeFieldSP]).val() || '-';
                    $('#fieldViewerSP').html(val);
                }
            }

            $(document).off('click', '.temuan-field-btn-sp').on('click', '.temuan-field-btn-sp', function(){
                var newField = $(this).data('field');
                if (newField === activeFieldSP) return;
                $('.temuan-field-btn-sp').removeClass('active');
                $(this).addClass('active');
                activeFieldSP = newField;
                renderFieldViewerSP();
            });

            renderModal('dlgRejectProgressSP', 'Tolak Progress', 'modal-sm');

            // Grid Daftar Temuan Tersimpan (readonly, hanya Judul Temuan & Deadline, tanpa Aksi)
            renderGrid('grdTemuanDbSP', {
                url           : '{{ url("audit/transactions/status-penyelesaian/get-temuan-list") }}',
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
                singleSelect  : true,
                columns : [[
                    { field: 'No', title: 'No', width: 50, align: 'center', valign: 'middle', formatter: function(value, row, index) { return index + 1; }},
                    { field: 'JudulTemuan', title: 'Judul Temuan', align: 'center' },
                    { field: 'PicAuditeeList', title: '<table class="pdp-header-table"><colgroup><col style="width:44%"><col style="width:36%"><col></colgroup><tr><td class="pdp-pic">PIC Auditee</td><td class="pdp-dl">Deadline</td><td class="pdp-pg">Progress</td></tr></table>', align: 'center', width: 360, sortable: false, formatter: formatPicDeadlineProgressPairCellSP }
                ]],
                filters: [],
                onClickRow: function(index, row) {
                    if (!row || !row.DtlId) return;
                    loadTemuanDetailSP(row);
                }
            });

            /*
            function toggleTemuanFieldsByJenisAuditSP() {
                var isReguler = ($('#txtJenisAuditSP').val() === 'Pemeriksaan Reguler');
                $.each(regulerHiddenFieldsSP, function(i, field) {
                    var btn = $('#grpTemuanFieldMenuSP .temuan-field-btn-sp[data-field="' + field + '"]');
                    if (isReguler) { btn.hide(); } else { btn.show(); }
                });
                var labelJudul = isReguler ? 'Penyebab' : 'Indikasi Awal & Bukti yang Didapat';
                var labelPengembalian = isReguler ? 'Rekomendasi Auditor' : 'Pengembalian Kerugian Perusahaan';
                $('#grpTemuanFieldMenuSP .temuan-field-btn-sp[data-field="IndikasiAwal"]').text(labelJudul);
                $('#grpTemuanFieldMenuSP .temuan-field-btn-sp[data-field="PengembalianKerugian"]').text(labelPengembalian);
            }
            */

            function renderProgressColumnSP(selector, list, showApprovalBadge, pctMap) {
                if (!list || list.length === 0) {
                    $(selector).html('<div class="progress-empty">Belum ada histori</div>');
                    return;
                }
                var html = '';
                $.each(list, function(i, item) {
                    var tgl = item.CreatedAt ? new Date(item.CreatedAt).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'}) : '-';
                    // Label Tindak Lanjut dihapus dari sini - sudah terwakili oleh dropdown "Tindak
                    // Lanjut / Perbaikan" di atas panel ini, jadi tidak perlu diulang per item.
                    var badge = '';
                    var noteHtml = '';
                    if (showApprovalBadge) {
                        if (item.ApprovalStatus === 'Approved') {
                            badge = '<i class="fa fa-check-circle" style="color:#28a745;" title="Disetujui"></i> ';
                        } else if (item.ApprovalStatus === 'Rejected') {
                            badge = '<i class="fa fa-times-circle" style="color:#d9534f;" title="Ditolak"></i> ';
                            if (item.ApprovalNote) {
                                noteHtml = '<div class="progress-meta" style="color:#d9534f;">Catatan: ' + item.ApprovalNote + '</div>';
                            }
                        } else {
                            badge = '<i class="fa fa-clock-o" style="color:#f0ad4e;" title="Menunggu Approval"></i> ';
                        }
                    }
                    // Lampiran (ItemType='Lampiran') tampil beda dari Progress: bukan badge persen,
                    // tapi tombol preview (mata) ke file-nya; Keterangan-nya adalah "Keterangan File".
                    var mainContent;
                    if (item.ItemType === 'Lampiran') {
                        var fileUrlSP = "{{ ENV('ASSET_FILE') }}netfile/InternalAudit/" + item.FileLampiran;
                        mainContent = '<button type="button" onclick="openLampiranPreview(\'' + fileUrlSP + '\')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:#0066CC; margin-right:6px;"><i class="fa fa-eye"></i></button>' +
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
                        badge + mainContent +
                        '<div class="progress-meta">' + (item.CreatedByName || '-') + ' &middot; ' + tgl + '</div>' +
                        noteHtml +
                        '</div>';
                });
                $(selector).html(html);
            }

            // Histori gabungan SEMUA Tindak Lanjut milik Temuan ini - dipanggil sekali tiap row
            // Temuan diklik, sama pola-nya dengan loadProgressHistoryByDtl di detail-modal.blade.php
            // Ambil SEMUA histori (Auditor+Auditee, semua entry Perbaikan) sekali per row Temuan
            // diklik, simpan ke variabel global. Render panel dipisah ke
            // renderProgressPanelForPerbaikanSP() yang difilter per PerbaikanId - dipanggil dari
            // handler change dropdown, jadi panel tetap terfilter tanpa ajax tambahan.
            function loadProgressHistoryByDtlSP(dtlId, cb) {
                ajax({
                    url      : '{{ url("audit/transactions/status-penyelesaian/get-progress-list-by-dtl") }}',
                    postData : { DtlId: dtlId },
                    blockId  : 'dlgStatusPenyelesaian',
                    success  : function(ret) {
                        if (!ret.result) return;
                        allProgressAuditorSP = ret.auditor || [];
                        allProgressAuditeeSP = ret.auditee || [];
                        latestByPerbaikanMapSP = ret.latestByPerbaikan || {};
                        if (cb) cb();
                    }
                });
            }

            function renderProgressPanelForPerbaikanSP(perbaikanId) {
                var isRegulerSP = ($('#txtJenisAuditSP').val() === 'Pemeriksaan Reguler');
                var auditorFiltered = $.grep(allProgressAuditorSP, function(item){ return String(item.PerbaikanId) === String(perbaikanId); });
                var auditeeFiltered = $.grep(allProgressAuditeeSP, function(item){ return String(item.PerbaikanId) === String(perbaikanId); });

                if (isRegulerSP) {
                    $('#wrapProgressTwoColSP').show();
                    $('#wrapProgressOneColSP').hide();
                    renderProgressColumnSP('#colProgressAuditorSP', auditorFiltered, false);
                    renderProgressColumnSP('#colProgressAuditeeSP', auditeeFiltered, true, calcAuditeePercentSP(auditorFiltered, auditeeFiltered));
                    renderApprovalCardSP(perbaikanId, auditeeFiltered);
                } else {
                    $('#wrapProgressTwoColSP').hide();
                    $('#wrapProgressOneColSP').show();
                    renderProgressColumnSP('#colProgressSingleSP', auditorFiltered, false);
                    $('#wrapApprovalCardSP').hide().html('');
                }
            }

            // Card approval - muncul kalau (a) masih ada entry Pending (Progress dan/atau Lampiran) di PerbaikanId ini,
            // DAN (b) login user = Approval Auditee untuk PerbaikanId itu. Semua entry Pending dari 1 pengiriman
            // PIC Auditee ditampilkan sekaligus dan diproses SEKALI (Setuju/Tolak berlaku untuk semuanya).
            function renderApprovalCardSP(perbaikanId, auditeeList) {
                var pendingItems = $.grep(auditeeList || [], function(item) { return item.ApprovalStatus === 'Pending'; });
                var isApprover   = !!approvalAuditeeByPerbaikanMapSP[perbaikanId];

                if (pendingItems.length === 0 || !isApprover) {
                    pendingPerbaikanIdSP = null;
                    $('#wrapApprovalCardSP').hide().html('');
                    return;
                }

                pendingPerbaikanIdSP = perbaikanId;

                var progressItems = $.grep(pendingItems, function(item) { return item.ItemType !== 'Lampiran'; });
                var lampiranItems = $.grep(pendingItems, function(item) { return item.ItemType === 'Lampiran'; });

                var bodyHtml = '';

                // Keterangan progress dulu
                $.each(progressItems, function(i, item) {
                    bodyHtml += '<div class="approval-pending-item">' + (item.Keterangan || '-') + '</div>';
                });

                // Lalu lampiran: tombol preview (mata) + keterangan file
                $.each(lampiranItems, function(i, item) {
                    var fileUrlCardSP = "{{ ENV('ASSET_FILE') }}netfile/InternalAudit/" + item.FileLampiran;
                    bodyHtml += '<div class="approval-pending-item">' +
                        '<button type="button" onclick="openLampiranPreview(\'' + fileUrlCardSP + '\')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:#0066CC; margin-right:6px;"><i class="fa fa-eye"></i></button>' +
                        (item.Keterangan || '-') + '</div>';
                });

                var html = '<div class="progress-col-header" style="background:#FFF3C4;">Menunggu Approval Anda</div>' +
                    '<div style="padding:8px 10px;">' +
                    bodyHtml +
                    '<div class="progress-meta">' + (pendingItems[0].CreatedByName || '-') + '</div>' +
                    '<div style="display:flex; justify-content:flex-end; gap:6px; margin-top:8px;">' +
                        '<button type="button" id="btnRejectProgressSP" style="background:white; color:#d9534f; border:1px solid #d9534f; padding:4px 10px; border-radius:3px; cursor:pointer;"><i class="fa fa-times"></i> Tolak</button>' +
                        '<button type="button" id="btnApproveProgressSP" style="background:white; color:#28a745; border:1px solid #28a745; padding:4px 10px; border-radius:3px; cursor:pointer;"><i class="fa fa-check"></i> Setuju</button>' +
                    '</div></div>';

                $('#wrapApprovalCardSP').html(html).show();
            }

            // Setuju / Tolak memproses SEMUA entry Pending (Progress + Lampiran) milik Tindak Lanjut yang sedang dipilih, sekali jalan
            $(document).off('click', '#btnApproveProgressSP').on('click', '#btnApproveProgressSP', function(){
                if (!pendingPerbaikanIdSP) return;
                if (!confirm('Setujui progress/lampiran ini?')) return;
                ajax({
                    url      : '{{ url("audit/transactions/status-penyelesaian/approve-pending") }}',
                    postData : { PerbaikanId: pendingPerbaikanIdSP },
                    blockId  : 'dlgStatusPenyelesaian',
                    alertId  : 'frmLampiranSPAlert',
                    success  : function(ret) {
                        if (!ret.result) {
                            alertBoxAuto({ id: 'frmLampiranSPAlert', msg: ret.msg, mode: 'error' });
                            return;
                        }
                        $('#txtTotalProgressSP').val((ret.TotalProgress || 0) + '%');
                        alertBoxAuto({ id: 'frmLampiranSPAlert', msg: 'Berhasil disetujui!', mode: 'success' });
                        refreshAfterApprovalSP();
                    }
                });
            });

            $(document).off('click', '#btnRejectProgressSP').on('click', '#btnRejectProgressSP', function(){
                if (!pendingPerbaikanIdSP) return;
                $('#txtCatatanTolakSP').val('');
                $('#frmRejectProgressSPAlert').html('');
                $('#dlgRejectProgressSP').modal('show');
            });

            $('#btnConfirmRejectSP').click(function(){
                var note = $('#txtCatatanTolakSP').val();
                if (!note) {
                    alertBoxAuto({ id: 'frmRejectProgressSPAlert', msg: 'Catatan penolakan wajib diisi', mode: 'warning' });
                    return;
                }
                if (!pendingPerbaikanIdSP) return;
                ajax({
                    url      : '{{ url("audit/transactions/status-penyelesaian/reject-pending") }}',
                    postData : { PerbaikanId: pendingPerbaikanIdSP, Note: note },
                    blockId  : 'dlgRejectProgressSP',
                    alertId  : 'frmRejectProgressSPAlert',
                    success  : function(ret) {
                        if (!ret.result) {
                            alertBoxAuto({ id: 'frmRejectProgressSPAlert', msg: ret.msg, mode: 'error' });
                            return;
                        }
                        $('#dlgRejectProgressSP').modal('hide');
                        alertBoxAuto({ id: 'frmLampiranSPAlert', msg: 'Berhasil ditolak', mode: 'success' });
                        refreshAfterApprovalSP();
                    }
                });
            });

            function refreshAfterApprovalSP() {
                if (selectedDtlIdSP) {
                    loadProgressHistoryByDtlSP(selectedDtlIdSP, function() {
                        if (selectedPerbaikanIdSP) {
                            renderProgressPanelForPerbaikanSP(selectedPerbaikanIdSP);
                            hasPendingForSelectedSP = $.grep(allProgressAuditeeSP, function(item){
                                return String(item.PerbaikanId) === String(selectedPerbaikanIdSP) && item.ApprovalStatus === 'Pending';
                            }).length > 0;
                            toggleProgressReadonlySP();
                        }
                    });
                }
                if ($('#grdTemuanDbSP').data('datagrid')) { $('#grdTemuanDbSP').datagrid('reload'); }
                if ($('#grdStatusPenyelesaian').data('datagrid')) { $('#grdStatusPenyelesaian').datagrid('reload'); }
            }

            function renderProgressPanelEmptySP(msg) {
                $('#wrapProgressTwoColSP, #wrapProgressOneColSP').hide();
                $('#colProgressAuditorSP, #colProgressAuditeeSP, #colProgressSingleSP').html('<div class="progress-empty">' + msg + '</div>');
            }

            function loadPerbaikanDropdownSP(dtlId) {
                ajax({
                    url      : '{{ url("audit/transactions/status-penyelesaian/get-perbaikan-dropdown") }}',
                    postData : { DtlId: dtlId },
                    blockId  : 'dlgStatusPenyelesaian',
                    success  : function(ret) {
                        var list = (ret && ret.data) ? ret.data : [];
                        var select = $('#cboPerbaikanSP');
                        select.empty();

                        picByPerbaikanMapSP = {};
                        approvalAuditeeByPerbaikanMapSP = {};
                        $.each(list, function(i, item) {
                            picByPerbaikanMapSP[item.PerbaikanId] = !!item.IsPic;
                            approvalAuditeeByPerbaikanMapSP[item.PerbaikanId] = !!item.IsApprovalAuditee;
                        });

                        if (list.length === 0) {
                            select.append(new Option('- Tidak ada Tindak Lanjut -', ''));
                            select.val('').trigger('change').prop('disabled', true);
                            selectedPerbaikanIdSP = null;
                            isPicMismatchSP = true;
                            $('#cboProgressSP').val('').prop('disabled', true);
                            $('#txtKeteranganSP').prop('readonly', true);
                            $('#btnSaveStatusSP').prop('disabled', true).css({ opacity: 0.5, cursor: 'not-allowed' });
                            return;
                        }

                        $.each(list, function(i, item) {
                            select.append(new Option(item.Label, item.PerbaikanId));
                        });

                        // Dropdown selalu tampil unfiltered (semua entry Tindak Lanjut milik Temuan
                        // ini) - akses edit Progress ditentukan PER ITEM lewat IsPic (lihat
                        // handler change.perbaikanSP), bukan lagi memfilter isi dropdown itu sendiri.
                        select.val(list[0].PerbaikanId).trigger('change').prop('disabled', false);
                    }
                });
            }

            $(document).off('change.perbaikanSP').on('change.perbaikanSP', '#cboPerbaikanSP', function(){
                var val = $(this).val();
                selectedPerbaikanIdSP = val || null;
                if (!val) {
                    renderProgressPanelEmptySP('Tidak ada Tindak Lanjut yang dapat diupdate');
                    lampiranTempSP = [];
                    deletedLampiranIdsSP = [];
                    renderLampiranSP();
                    return;
                }
                // Form (Progress/Keterangan/Lampiran) dibuka untuk PIC Auditee MAUPUN Approval
                // Auditee di Tindak Lanjut ini - bukan cuma PIC saja.
                isPicMismatchSP = !picByPerbaikanMapSP[val] && !approvalAuditeeByPerbaikanMapSP[val];
                hasPendingForSelectedSP = $.grep(allProgressAuditeeSP, function(item){
                    return String(item.PerbaikanId) === String(val) && item.ApprovalStatus === 'Pending';
                }).length > 0;
                toggleProgressReadonlySP();
                $('#cboProgressSP').val(latestByPerbaikanMapSP[val] || '').trigger('change');
                renderProgressPanelForPerbaikanSP(val);

                deletedLampiranIdsSP = [];
                ajax({
                    url      : '{{ url("audit/transactions/status-penyelesaian/get-lampiran-list-array") }}',
                    postData : { PerbaikanId: val },
                    blockId  : 'dlgStatusPenyelesaian',
                    success  : function(ret) {
                        var list = (ret && ret.data) ? ret.data : [];
                        lampiranTempSP = $.map(list, function(l) {
                            return {
                                NamaFile: l.NamaFile, FileLampiran: l.FileLampiran,
                                isExisting: true, LampiranId: l.LampiranId,
                                CreatedByName: l.CreatedByName, AuditRole: l.AuditRole, CreatedAt: l.CreatedAt,
                                ApprovalStatus: l.ApprovalStatus, ApprovalNote: l.ApprovalNote
                            };
                        });
                        renderLampiranSP();
                    }
                });
            });

            function loadTemuanDetailSP(row) {
                if (!row || !row.DtlId) return;
                selectedDtlIdSP = row.DtlId;
                hasPendingForSelectedSP = false;
                $('#wrapApprovalCardSP').hide().html('');

                var isRegulerSP = ($('#txtJenisAuditSP').val() === 'Pemeriksaan Reguler');
                if (isRegulerSP) {
                    $('#wrapProgressTwoColSP').show();
                    $('#wrapProgressOneColSP').hide();
                } else {
                    $('#wrapProgressTwoColSP').hide();
                    $('#wrapProgressOneColSP').show();
                }
                loadProgressHistoryByDtlSP(row.DtlId, function() {
                    loadPerbaikanDropdownSP(row.DtlId);
                });

                $('#popupJudulTemuanSP').val(row.JudulTemuan || '');
                $('#popupDetailTemuanSP').val(row.DetailTemuan || '');
                $('#popupIndikasiAwalSP').val(row.IndikasiAwal || '');
                $('#popupResikoSP').val(row.Resiko || '');
                $('#popupKerugianSP').val(row.Kerugian || 0);
                $('#popupPeraturanSOPSP').val(row.PeraturanSOP || '');
                $('#popupSanksiKaryawanSP').val(row.SanksiKaryawan || '');
                $('#popupSanksiAtasanSP').val(row.SanksiAtasan || '');
                $('#popupRekomendasiAuditorSP').val(row.RekomendasiAuditor || '');
                $('#popupPengembalianKerugianSP').val(row.PengembalianKerugian || '');

                // Akses edit Progress sekarang ditentukan PER ITEM Tindak Lanjut (lihat
                // picByPerbaikanMapSP & handler change.perbaikanSP), bukan lagi 1x cek per Temuan -
                // jadi ajax "Level 2" lama (get-pic-list-array by DtlId) sudah tidak diperlukan di sini.
                ajax({
                    url      : '{{ url("audit/transactions/audit-list/get-perbaikan-list-array") }}',
                    postData : { DtlId: row.DtlId },
                    blockId  : 'dlgStatusPenyelesaian',
                    success  : function(ret) {
                        perbaikanListSP = (ret && ret.data) ? ret.data : [];
                        if (activeFieldSP === 'TindakLanjut') renderFieldViewerSP();
                    }
                });

                $('.temuan-field-btn-sp').removeClass('active');
                $('.temuan-field-btn-sp[data-field="JudulTemuan"]').addClass('active');
                activeFieldSP = 'JudulTemuan';
                renderFieldViewerSP();

                // Progress sekarang diambil per entry Perbaikan (via loadPerbaikanDropdownSP ->
                // loadProgressHistorySP), bukan lagi prefill dari row.Progress (agregat Temuan).
                $('#txtKeteranganSP').val('');

                // Lampiran sekarang dikaitkan ke PerbaikanId (bukan DtlId) - baru di-load
                // saat user memilih dropdown Tindak Lanjut (lihat handler change.perbaikanSP)
                deletedLampiranIdsSP = [];
                lampiranTempSP = [];
                renderLampiranSP();

                $('#frmLampiranSPAlert').html('');
                $('#frmProgressPendingAlertSP').html('');
            }

            function sanitizeFileNameSP(fileName) {
                var dotIndex = fileName.lastIndexOf('.');
                var name = dotIndex > -1 ? fileName.substring(0, dotIndex) : fileName;
                var ext  = dotIndex > -1 ? fileName.substring(dotIndex) : '';
                name = name.replace(/\s*-\s*/g, '_').replace(/[.,\s]/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '');
                return name + ext;
            }

            document.getElementById('txtFileLampiranSP').addEventListener('change', function (e) {
                var input = e.target;
                if (!input.files || input.files.length === 0) return;
                var file = input.files[0];
                var newName = sanitizeFileNameSP(file.name);
                if (newName === file.name) return;
                var renamedFile = new File([file], newName, { type: file.type });
                var dt = new DataTransfer();
                dt.items.add(renamedFile);
                input.files = dt.files;
            }, true);

            renderUploadFile({
                id: 'txtFileLampiranSP',
                postData: { prefix: 'InternalAudit' },
                maxFileSize: 5,
                allowedFileExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'bmp', 'doc', 'docx']
            });

            $('#txtFileLampiranSP').on('fileuploaded', function(event, data){
                $('#hdnFileLampiranSPUploaded').val(data.response.filename);
            });

            var deletedLampiranIdsSP = [];

            $('#btnAddLampiranSP').click(function(){
                if (!selectedPerbaikanIdSP) {
                    alertBoxAuto({ id: 'frmLampiranSPAlert', msg: 'Pilih Tindak Lanjut yang mau diupdate terlebih dahulu..', mode: 'warning' });
                    return;
                }

                var namaFile     = $('#txtNamaFileLampiranSP').val();
                var fileLampiran = $('#hdnFileLampiranSPUploaded').val();

                if (!fileLampiran) {
                    alertBoxAuto({ id: 'frmLampiranSPAlert', msg: 'Pilih file terlebih dahulu', mode: 'warning' });
                    return;
                }

                lampiranTempSP.push({
                    NamaFile: namaFile, FileLampiran: fileLampiran, isExisting: false, LampiranId: null,
                    CreatedByName: loginUserNameSP, AuditRole: 'Auditee', CreatedAt: null
                });

                $('#txtNamaFileLampiranSP').val('');
                $('#hdnFileLampiranSPUploaded').val('');
                $('#txtFileLampiranSP').fileinput('clear');

                renderLampiranSP();
            });

            window.deleteLampiranSP = function(index) {
                var item = lampiranTempSP[index];
                if (item && item.isExisting && item.LampiranId) {
                    deletedLampiranIdsSP.push(item.LampiranId);
                }
                lampiranTempSP.splice(index, 1);
                renderLampiranSP();
            };

            function renderLampiranSP() {
                // Tabel ini cuma menampilkan lampiran yang BELUM tersimpan ke database (masih di
                // memory browser, sesi ini) - lampiran yang sudah tersimpan tidak perlu ditampilkan
                // lagi di sini karena sudah kelihatan di panel Auditor/Auditee di atas, jadi kalau
                // ditampilkan dobel di sini jadi terasa numpuk.
                var newOnlySP = $.grep(lampiranTempSP, function(l){ return !l.isExisting; });

                var html = '<table class="table table-bordered table-condensed" style="margin-top:5px; width:100%; table-layout: fixed;">';
                html += '<thead><tr>' +
                    '<th style="text-align:center; width: 25%;">Keterangan</th>' +
                    '<th style="text-align:center; width: 47%;">File</th>' +
                    '<th style="text-align:center; width: 20%;">Created By</th>' +
                    '<th style="text-align:center; width: 8%;">Aksi</th>' +
                    '</tr></thead><tbody>';

                if (newOnlySP.length === 0) {
                    html += '<tr><td colspan="4" style="text-align:center; color:#999;">Belum ada lampiran baru</td></tr>';
                } else {
                    $.each(lampiranTempSP, function(i, item) {
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
                        html += '<td style="vertical-align: middle; text-align: center;"><button type="button" onclick="deleteLampiranSP(' + i + ')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:red;"><i class="fa fa-trash"></i></button></td>';
                        html += '</tr>';
                    });
                }
                html += '</tbody></table>';
                $('#tblLampiranSP').html(html);
            }

            function resetStatusFormSP() {
                $('#cboProgressSP').val('').trigger('change');
                $('#txtKeteranganSP').val('');
                lampiranTempSP = [];
                deletedLampiranIdsSP = [];
                renderLampiranSP();
            }

            function reloadLampiranExistingSP() {
                if (!selectedPerbaikanIdSP) return;
                ajax({
                    url      : '{{ url("audit/transactions/status-penyelesaian/get-lampiran-list-array") }}',
                    postData : { PerbaikanId: selectedPerbaikanIdSP },
                    blockId  : 'dlgStatusPenyelesaian',
                    success  : function(ret) {
                        var list = (ret && ret.data) ? ret.data : [];
                        lampiranTempSP = $.map(list, function(l) {
                            return {
                                NamaFile: l.NamaFile, FileLampiran: l.FileLampiran,
                                isExisting: true, LampiranId: l.LampiranId,
                                CreatedByName: l.CreatedByName, AuditRole: l.AuditRole, CreatedAt: l.CreatedAt,
                                ApprovalStatus: l.ApprovalStatus, ApprovalNote: l.ApprovalNote
                            };
                        });
                        renderLampiranSP();
                    }
                });
            }

            $('#btnSaveStatusSP').click(function(){
                if (isCompletedSP) return;

                if (!selectedDtlIdSP) {
                    alertBoxAuto({ id: 'frmLampiranSPAlert', msg: 'Pilih data yang mau di tambahkan terlebih dahulu..', mode: 'warning' });
                    return;
                }

                var newLampiran      = $.grep(lampiranTempSP, function(l){ return !l.isExisting; });
                var namaFileList     = $.map(newLampiran, function(l){ return l.NamaFile; });
                var fileLampiranList = $.map(newLampiran, function(l){ return l.FileLampiran; });

                // Salah satu wajib diisi: Keterangan ATAU ada Lampiran baru yang ditambahkan
                // (belum ke-save ke DB, masih di tabel sementara browser). Kalau dua-duanya
                // kosong, tolak simpan.
                if (!$('#txtKeteranganSP').val() && newLampiran.length === 0) {
                    alertBoxAuto({ id: 'frmLampiranSPAlert', msg: 'Keterangan atau Lampiran wajib diisi salah satu', mode: 'warning' });
                    return;
                }

                // Sama seperti backend - Progress dianggap "diisi" hanya kalau Keterangan ada
                // isinya. Dropdown Progress selalu ter-prefill otomatis (lihat change.perbaikanSP),
                // jadi tidak bisa dipakai sendirian buat mendeteksi niat submit Progress baru.
                var hasProgressInputSP = !!$('#txtKeteranganSP').val();

                // Masih ada progress Pending buat Tindak Lanjut ini (siapapun yang input) -
                // jangan proses submit progress baru, cukup kasih tau supaya dikoordinasikan
                // dulu ke Approval Auditee terkait. Update lampiran-only (tanpa isi Progress/
                // Keterangan) tetap boleh jalan.
                if (hasProgressInputSP && hasPendingForSelectedSP) {
                    alertBoxAuto({ id: 'frmProgressPendingAlertSP', msg: 'Masih ada update progress terpending, tolong koordinasikan dengan approval auditee terkait', mode: 'warning' });
                    return;
                }

                if (hasProgressInputSP && !selectedPerbaikanIdSP) {
                    alertBoxAuto({ id: 'frmLampiranSPAlert', msg: 'Pilih Tindak Lanjut yang mau diupdate terlebih dahulu..', mode: 'warning' });
                    return;
                }

                if ((newLampiran.length > 0 || deletedLampiranIdsSP.length > 0) && !selectedPerbaikanIdSP) {
                    alertBoxAuto({ id: 'frmLampiranSPAlert', msg: 'Pilih Tindak Lanjut yang mau diupdate terlebih dahulu..', mode: 'warning' });
                    return;
                }

                ajax({
                    url      : '{{ url("audit/transactions/status-penyelesaian/update-status") }}',
                    postData : {
                        DtlId              : selectedDtlIdSP,
                        PerbaikanId        : selectedPerbaikanIdSP,
                        Progress           : $('#cboProgressSP').val(),
                        Keterangan         : $('#txtKeteranganSP').val(),
                        NamaFileList       : JSON.stringify(namaFileList),
                        FileLampiranList   : JSON.stringify(fileLampiranList),
                        DeletedLampiranIds : JSON.stringify(deletedLampiranIdsSP)
                    },
                    blockId  : 'dlgStatusPenyelesaian',
                    alertId  : 'frmLampiranSPAlert',
                    success  : function(ret) {
                        if (!ret.result) {
                            alertBoxAuto({ id: 'frmLampiranSPAlert', msg: ret.msg, mode: 'error' });
                            return;
                        }
                        alertBoxAuto({ id: 'frmLampiranSPAlert', msg: 'Data berhasil disimpan!', mode: 'success' });

                        $('#txtTotalProgressSP').val((ret.TotalProgress || 0) + '%');
                        $('#txtKeteranganSP').val('');
                        lampiranTempSP = [];
                        deletedLampiranIdsSP = [];
                        renderLampiranSP();
                        reloadLampiranExistingSP();
                        if (selectedDtlIdSP) {
                            loadProgressHistoryByDtlSP(selectedDtlIdSP, function() {
                                if (selectedPerbaikanIdSP) {
                                    $('#cboProgressSP').val(latestByPerbaikanMapSP[selectedPerbaikanIdSP] || '').trigger('change');
                                    renderProgressPanelForPerbaikanSP(selectedPerbaikanIdSP);
                                }
                            });
                        }
                        if ($('#grdTemuanDbSP').data('datagrid')) {
                            $('#grdTemuanDbSP').datagrid('reload');
                        }
                        if ($('#grdStatusPenyelesaian').data('datagrid')) {
                            $('#grdStatusPenyelesaian').datagrid('reload');
                        }
                    }
                });
            });

            // Reset & load ketika buka form (add / edit / view)
            window.showStatusPenyelesaianDetail = function(row, isEdit, isView) {
                row = row || {};

                selectedDtlIdSP = null;
                selectedPerbaikanIdSP = null;
                picByPerbaikanMapSP = {};
                approvalAuditeeByPerbaikanMapSP = {};
                hasPendingForSelectedSP = false;
                pendingProgressIdSP = null;
                $('#wrapApprovalCardSP').hide().html('');
                $('#cboPerbaikanSP').empty().append(new Option('-', '')).val('').prop('disabled', true);
                lampiranTempSP = [];
                deletedLampiranIdsSP = [];
                renderLampiranSP();
                $('#tblLampiranSP').html('');
                $('#cboProgressSP').val('');
                $('#txtKeteranganSP').val('');
                $('#frmLampiranSPAlert').html('');
                $('#frmProgressPendingAlertSP').html('');

                $('#wrapProgressTwoColSP, #wrapProgressOneColSP').hide();
                $('#colProgressAuditorSP, #colProgressAuditeeSP, #colProgressSingleSP').html('<div class="progress-empty">Pilih temuan terlebih dahulu</div>');

                $('#hdnAuditIdSP').val(row.AuditId || '');
                $('#txtNoSuratTugasSP').val(row.NoSuratTugas || '-');
                $('#txtNoGaroonSP').val(row.NoGaroon || '-');
                var fmtPeriodeSP = function(d) { return new Date(d).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}); };
                var periodeLabelSP = row.PeriodeStart ? (row.PeriodeEnd ? fmtPeriodeSP(row.PeriodeStart) + ' s/d ' + fmtPeriodeSP(row.PeriodeEnd) : fmtPeriodeSP(row.PeriodeStart)) : '-';
                $('#txtPeriodePemeriksaanSP').val(periodeLabelSP);
                $('#txtTanggalPemeriksaanSP').val(row.TanggalPemeriksaan ? new Date(row.TanggalPemeriksaan).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-');
                $('#txtTanggalUploadSP').val(row.TanggalUpload ? new Date(row.TanggalUpload).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-');
                $('#txtJudulKondisiSP').val(row.JudulKondisi || '');
                $('#txtJenisAuditSP').val(row.JenisAudit || '');
                $('#txtDepartmentAuditySP').val(row.DepartmentAudityName || '');
                $('#txtListPicSP').val('');
                $('#txtTotalProgressSP').val((row.TotalProgress || 0) + '%');
                $('#txtStatusSP').val(row.Status || 'In Progress');
                $('#approver1').val(row.ApproverName1 || '-');
                $('#workflow1').val((String(row.FlagApproval1 || '0') === '1') ? 'Approved' : 'In Progress');
                $('#approver2').val(row.ApproverName2 || '-');
                $('#workflow2').val((String(row.FlagApproval2 || '0') === '1') ? 'Approved' : 'In Progress');
                isCompletedSP = (row.Status === 'Completed');
                isPicMismatchSP = true; // akan ditentukan ulang per-PerbaikanId (dropdown Tindak Lanjut) saat user pilih Temuan
                toggleProgressReadonlySP();

                // List PIC Informasi Kondisi (frame-header) - harus PIC Auditor (Model = 'Informasi
                // Kondisi'), BUKAN get-pic-list-array-audit (itu khusus PIC Tindak Lanjut/Auditee,
                // dipakai buat access-gate Level 1 di index.blade.php, jangan diubah/dipakai di sini).
                ajax({
                    url      : '{{ url("audit/transactions/audit-list/get-pic-list-array") }}',
                    postData : { AuditId: row.AuditId },
                    blockId  : 'dlgStatusPenyelesaian',
                    success  : function(ret) {
                        var list = (ret && ret.data) ? ret.data : [];
                        var arrNamaPic = $.map(list, function(item){ return item.Name; });
                        $('#txtListPicSP').val(arrNamaPic.length ? arrNamaPic.join(', ') : '-');
                    }
                });
                if (row.JenisAudit === 'Pemeriksaan Reguler') {
                    $('#wrapDepartmentAuditySP, #lblDepartmentAuditySP').show();
                } else {
                    $('#wrapDepartmentAuditySP, #lblDepartmentAuditySP').hide();
                }
                $('#wrapListPicSP, #lblListPicSP').show();

                $('.temuan-field-btn-sp').removeClass('active');
                $('.temuan-field-btn-sp[data-field="JudulTemuan"]').addClass('active');
                activeFieldSP = 'JudulTemuan';
                perbaikanListSP = [];
                $('#popupJudulTemuanSP, #popupDetailTemuanSP, #popupIndikasiAwalSP, #popupResikoSP, #popupKerugianSP, #popupPeraturanSOPSP, #popupSanksiKaryawanSP, #popupSanksiAtasanSP, #popupRekomendasiAuditorSP, #popupPengembalianKerugianSP').val('');
                $('#fieldViewerSP').text('-');

                if (row.AuditId) {
                    setTimeout(function() {
                        if ($('#grdTemuanDbSP').data('datagrid')) {
                            $('#grdTemuanDbSP').datagrid('resize');
                        }
                        gridFilterData('grdTemuanDbSP', [{field: 'AuditId', value: row.AuditId}], {AuditId: row.AuditId});
                    }, 100);
                } else {
                    if ($('#grdTemuanDbSP').data('datagrid')) {
                        $('#grdTemuanDbSP').datagrid('loadData', { total: 0, rows: [] });
                    }
                }

                $('#frmStatusPenyelesaianHeader').hide();
                $('#dlgStatusPenyelesaian').show();
            };

            $('#btnBackStatusPenyelesaian').click(function(){
                $('#dlgStatusPenyelesaian').hide();
                $('#frmStatusPenyelesaianHeader').show();
                $('#grdStatusPenyelesaian').datagrid('reload');
            });

        });
</script>

<div id="dlgStatusPenyelesaian" style="display: none;">
    <div class="box">

        <div style="position: absolute; top: 10px; left: 10px; z-index: 100;">
            <button type="button" id="btnBackStatusPenyelesaian" style="background-color: white; color: #0066CC; border: 1px solid #ccc; padding: 6px 12px; border-radius: 3px; cursor: pointer; margin: 0;">
                <i class="fa fa-arrow-circle-o-left text-blue"></i>&nbsp;Back
            </button>
        </div>

        <div class="box-header" style="padding-top: 50px;"></div>

        <div class="modal-body">

            <div class="form-horizontal">
                <input type="hidden" id="hdnAuditIdSP" value=""/>

                {{-- Informasi Kondisi (readonly) --}}
                <div class="frame">
                    <div class="frame-header"><strong>Informasi Kondisi</strong></div><br>
                    <div class="form-group">
                        <label class="col-sm-2">No Surat Tugas</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtNoSuratTugasSP" readonly/>
                        </div>
                        <label class="col-sm-2">Periode Pemeriksaan</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtPeriodePemeriksaanSP" readonly/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2">No Garoon</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtNoGaroonSP" readonly/>
                        </div>
                        <label class="col-sm-2">Tanggal Pemeriksaan</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtTanggalPemeriksaanSP" readonly/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2">Judul Pemeriksaan</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtJudulKondisiSP" readonly/>
                        </div>
                        <label class="col-sm-2">Tanggal Upload</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtTanggalUploadSP" readonly/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2">Jenis Audit</label>
                        <div class="col-sm-4">
                            <input type="text" id="txtJenisAuditSP" readonly/>
                        </div>
                        <label class="col-sm-2">Status</label>
                        <div class="col-sm-4">
                            <div class="input-group">
                                <input type="text" id="txtStatusSP" readonly/>
                                <span class="input-group-addon">Total Progress</span>                                
                                <input type="text" id="txtTotalProgressSP" readonly/>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2" id="lblDepartmentAuditySP" style="display:none;">Department Auditee</label>
                        <div class="col-sm-4" id="wrapDepartmentAuditySP" style="display:none;">
                            <input type="text" id="txtDepartmentAuditySP" readonly/>
                        </div>
                        <label class="col-sm-2">Approval 1</label>
                        <div class="col-sm-4">
                            <div class="input-group">
                                <input type="text" id="approver1" readonly/>
                                <span class="input-group-addon">Workflow</span>
                                <input type="text" id="workflow1" readonly/>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2" id="lblListPicSP" style="display:none;">PIC Auditor</label>
                        <div class="col-sm-4" id="wrapListPicSP" style="display:none;">
                            <input type="text" id="txtListPicSP" readonly/>
                        </div>
                        <label class="col-sm-2">Approval 2</label>
                        <div class="col-sm-4">
                            <div class="input-group">
                                <input type="text" id="approver2" readonly/>
                                <span class="input-group-addon">Workflow</span>
                                <input type="text" id="workflow2" readonly/>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Daftar Temuan Tersimpan (grid + field viewer readonly) --}}
                <div class="frame">
                    <div class="frame-header"><strong>Daftar Temuan Tersimpan</strong></div>
                    <div class="row">
                        <div class="col-md-12">
                            <div id="grdTemuanDbSP"></div>
                        </div>
                    </div>

                    <div class="temuan-field-wrap" style="margin-top:10px;">
                        <div class="temuan-field-menu" id="grpTemuanFieldMenuSP">
                            <button type="button" class="temuan-field-btn temuan-field-btn-sp active" data-field="JudulTemuan">Judul Temuan</button>
                            <button type="button" class="temuan-field-btn temuan-field-btn-sp" data-field="DetailTemuan">Detail Temuan</button>
                            <button type="button" class="temuan-field-btn temuan-field-btn-sp" data-field="IndikasiAwal">Indikasi Awal & Bukti / Penyebab</button>
                            <button type="button" class="temuan-field-btn temuan-field-btn-sp" data-field="Resiko">Resiko</button>
                            <button type="button" class="temuan-field-btn temuan-field-btn-sp" data-field="Kerugian">Kerugian Perusahaan</button>
                            <button type="button" class="temuan-field-btn temuan-field-btn-sp" data-field="PeraturanSOP">Peraturan / SOP Perusahaan yang Dilanggar</button>
                            <button type="button" class="temuan-field-btn temuan-field-btn-sp" data-field="SanksiKaryawan">Jenis Sanksi Berdasar PP & SOP (Karyawan)</button>
                            <button type="button" class="temuan-field-btn temuan-field-btn-sp" data-field="SanksiAtasan">Jenis Sanksi Berdasar PP & SOP (Atasan)</button>
                            <button type="button" class="temuan-field-btn temuan-field-btn-sp" data-field="TindakLanjut">Tindak Lanjut / Perbaikan</button>
                            <button type="button" class="temuan-field-btn temuan-field-btn-sp" data-field="RekomendasiAuditor">Rekomendasi Auditor</button>
                            <button type="button" class="temuan-field-btn temuan-field-btn-sp" data-field="PengembalianKerugian">Pengembalian Kerugian Perusahaan</button>
                        </div>
                        <div class="temuan-field-editor">
                            <div id="fieldViewerSP" style="height:100%; max-height:400px; overflow-y:auto; padding:10px; white-space:pre-wrap; font-size:14px; font-family:Arial, sans-serif;">-</div>

                            <input type="hidden" id="popupJudulTemuanSP" value=""/>
                            <input type="hidden" id="popupDetailTemuanSP" value=""/>
                            <input type="hidden" id="popupIndikasiAwalSP" value=""/>
                            <input type="hidden" id="popupResikoSP" value=""/>
                            <input type="hidden" id="popupKerugianSP" value=""/>
                            <input type="hidden" id="popupPeraturanSOPSP" value=""/>
                            <input type="hidden" id="popupSanksiKaryawanSP" value=""/>
                            <input type="hidden" id="popupSanksiAtasanSP" value=""/>
                            <input type="hidden" id="popupRekomendasiAuditorSP" value=""/>
                            <input type="hidden" id="popupPengembalianKerugianSP" value=""/>
                        </div>
                    </div>

                </div>

                {{-- Progress Temuan --}}
                <div class="frame">
                    <div class="frame-header"><strong>Progress Temuan</strong></div><br>
                    <div id="frmLampiranSPAlert"></div>
                    <div id="frmProgressPendingAlertSP"></div>

                    <div id="wrapProgressTwoColSP" class="progress-col-wrap" style="margin-bottom:15px; display:none;">
                        <div class="progress-col">
                            <div class="progress-col-header">Auditor</div>
                            <div class="progress-col-body" id="colProgressAuditorSP"><div class="progress-empty">Pilih temuan terlebih dahulu</div></div>
                        </div>
                        <div class="progress-col">
                            <div class="progress-col-header">Auditee</div>
                            <div class="progress-col-body" id="colProgressAuditeeSP"><div class="progress-empty">Pilih temuan terlebih dahulu</div></div>
                            <div id="wrapApprovalCardSP" style="display:none; border-top:1px solid #ddd;"></div>
                        </div>
                    </div>

                    <div id="wrapProgressOneColSP" style="margin-bottom:15px; display:none;">
                        <div class="progress-col">
                            <div class="progress-col-header">Auditor</div>
                            <div class="progress-col-body" id="colProgressSingleSP"><div class="progress-empty">Pilih temuan terlebih dahulu</div></div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="col-sm-4 mandatory">Tindak Lanjut / Perbaikan</label>
                                <div class="col-sm-8">
                                    <select id="cboPerbaikanSP" name="PerbaikanSP" formatter="combo-array" style="display:none;">
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
                                    <select id="cboProgressSP" name="Progress" formatter="combo-array">
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
                                <label class="col-sm-2 mandatory">Keterangan</label>
                                <div class="col-sm-10">
                                    <textarea id="txtKeteranganSP" rows="4" style="width:100%;" placeholder="Keterangan detail progress..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="tblLampiranSP"></div>

                    <div class="row" style="margin-top:20px;">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="col-sm-4">Keterangan File</label>
                                <div class="col-sm-8"><input type="text" id="txtNamaFileLampiranSP" placeholder="Keterangan file..."/></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="col-sm-4">File</label>
                                <div class="col-sm-8">
                                    <div class="input-group">
                                        <input type="file" id="txtFileLampiranSP" name="txtFileLampiranSP"/>
                                        <input type="hidden" id="hdnFileLampiranSPUploaded" value=""/>
                                        <span class="input-group-btn" style="vertical-align: top;">
                                            <button type="button" id="btnAddLampiranSP" style="background-color: white; color: #0066CC; border: 1px solid #ccc; padding: 6px 12px; border-radius: 3px; cursor: pointer; margin-top: 0; vertical-align: top;">
                                                <i class="fa fa-plus" style="color: #0066CC;"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; margin-bottom:2px; margin-top:2px;">
                        <button type="button" id="btnSaveStatusSP" style="background-color: white; color: #28a745; border: 1px solid #28a745; padding: 6px 12px; border-radius: 3px; cursor: pointer; margin: 0;">
                            <i class="fa fa-save" style="color: #28a745;"></i>&nbsp;Update Progress
                        </button>
                    </div>                    

                </div>

            </div>
        </div>

    </div>
</div>

<div id="dlgRejectProgressSP" style="display: none">
    <div class="modal-body">   
        <div id="frmRejectProgressSPAlert"></div>
        <div class="form-horizontal">
            <div class="form-group">
                <label class="col-sm-3 mandatory">Catatan Penolakan</label>
                <div class="col-sm-9">
                    <textarea id="txtCatatanTolakSP" rows="4" style="width:100%;" placeholder="Alasan penolakan..."></textarea>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; margin-top:10px;">
                <button type="button" id="btnConfirmRejectSP" style="background-color: white; color: #d9534f; border: 1px solid #d9534f; padding: 6px 12px; border-radius: 3px; cursor: pointer;">
                    <i class="fa fa-times"></i>&nbsp;Konfirmasi Tolak
                </button>
            </div>
        </div>
    </div>
</div>