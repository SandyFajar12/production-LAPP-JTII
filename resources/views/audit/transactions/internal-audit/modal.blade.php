<script src="{{ asset('plugins/tinymce/tinymce.min.js') }}"></script>
<link rel="stylesheet" href="{{ asset('plugins/daterangepicker/daterangepicker.css') }}"/>
<script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>

<style>
    .frame { border: 1px solid #ccc; padding: 10px; margin: 10px; border-radius: 5px; }
    .frame-header { background-color: #99CCCC; padding: 5px 10px; border-bottom: 1px solid #999; border-radius: 5px 5px 0 0; font-weight: 600; }
    #txtPeriodePemeriksaan,
    #txtTanggalPemeriksaan,
    #txtTanggalUpload { text-align: left !important; background-color: #fff; cursor: pointer; }
    #wrapTanggalUpload input,
    #wrapTanggalUpload .form-control,
    #wrapTanggalUpload span,
    #wrapTanggalPemeriksaan input,
    #wrapTanggalPemeriksaan .form-control,
    #wrapTanggalPemeriksaan span { text-align: left !important; }
    #dlgInternalAudit .box { position: relative; }
    /* Mode readonly (user bukan PIC Auditor / Approval 1 / Approval 2): tidak ada tombol ubah Temuan */
    #dlgInternalAudit.readonly-mode #btnAddTemuan,
    #dlgInternalAudit.readonly-mode #btnCancelTemuan,
    #dlgInternalAudit.readonly-mode #btnClearTemuan,
    #dlgInternalAudit.readonly-mode #grdTemuanDb .datagrid-body button { display: none !important; }
    #divTemuanDb .datagrid-body td,
    #divTemuanTemp .datagrid-body td { vertical-align: middle !important; padding-top: 0 !important; }
    .pdp-header-table,
    .pic-deadline-pair-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .pdp-header-table td,
    .pic-deadline-pair-table td { padding: 6px 8px; text-align: center; vertical-align: middle; }
    .pdp-header-table td.pdp-dl,
    .pic-deadline-pair-table td.pdp-dl { border-left: 1px solid #ddd; }
    .pic-deadline-pair-table tr + tr td { border-top: 1px solid #ddd; }
    #divTemuanDb .datagrid-cell[class*="PicAuditeeList"],
    #divTemuanTemp .datagrid-cell[class*="PicAuditeeList"] {
        padding: 0 !important;
        overflow: hidden !important;
        display: flex !important;
        align-items: center !important;
        height: 100% !important;
    }
    #divTemuanDb .datagrid-cell[class*="-DtlId"],
    #divTemuanTemp .datagrid-cell[class*="-Aksi"] { padding: 0 !important; }
    /* ==== LAMPIRAN TEMUAN DINONAKTIFKAN - uncomment untuk restore ====
    #tblLampiranTemuanTemp table { border-collapse: collapse !important; width: 100%; }
    #tblLampiranTemuanTemp table th, #tblLampiranTemuanTemp table td { border: 1px solid #aaa !important; padding: 6px 10px !important; }
    #tblLampiranTemuanTemp table thead tr { background-color: #99CCCC; }
    ==== END LAMPIRAN TEMUAN ==== */

    .temuan-field-wrap { display:flex; align-items:stretch; border:1px solid #ddd; border-radius:0; overflow:hidden; }
    .temuan-field-menu { width:25%; border-right:1px solid #ddd; overflow-y:auto; }
    .temuan-field-btn { position:relative; display:block; width:100%; text-align:left; border:none; border-bottom:1px solid #ddd; background:#fff; padding:8px 12px; margin:0; cursor:pointer; }
    .temuan-field-btn:last-child { border-bottom:none; }
    .temuan-field-btn.active { background-color:#99CCCC; color:#333; }
    .field-filled-indicator { display:none; position:absolute; right:10px; top:50%; transform:translateY(-50%); color:#000000; font-size:13px; }
    .field-filled-indicator.show { display:inline; }    
    .temuan-field-editor { width:75%; }
    .temuan-field-editor .mce-tinymce,
    .temuan-field-editor .tox-tinymce {
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }

    .temuan-field-editor .mce-tinymce *,
    .temuan-field-editor .tox-tinymce * {
        border-radius: 0 !important;
    }

    /* Editor utama (txtTemuanEditor) tetap mengisi penuh tinggi container-nya */
    #txtTemuanEditor + .mce-tinymce,
    #txtTemuanEditor ~ .mce-tinymce,
    #txtTemuanEditor + .tox-tinymce,
    #txtTemuanEditor ~ .tox-tinymce {
        height: 100% !important;
    }

    /* Editor Detail Tindak Lanjut / Perbaikan diberi tepian sendiri supaya kotaknya jelas */
    #txtCatatanFraud + .mce-tinymce,
    #txtCatatanFraud ~ .mce-tinymce,
    #txtCatatanFraud + .tox-tinymce,
    #txtCatatanFraud ~ .tox-tinymce,
    #txtDetailPerbaikan + .mce-tinymce,
    #txtDetailPerbaikan ~ .mce-tinymce,
    #txtDetailPerbaikan + .tox-tinymce,
    #txtDetailPerbaikan ~ .tox-tinymce {
        border: 1px solid #ddd !important;
        border-radius: 3px !important;
    }
    #wrapKerugianInline { padding:0; }
    #viewerTemuanField { display:none; height:100%; max-height:400px; overflow-y:auto; padding:10px; white-space:pre-wrap; font-size:14px; font-family:Arial, sans-serif; }
</style>

<script type="text/javascript">
    function alertBoxAuto(opts) {
        alertBox('show', opts);
        setTimeout(function(){
            alertBox('hide', (opts && opts.id) ? { id: opts.id } : undefined);
        }, 5000);
    }

    // Deadline sekarang ada di level Perbaikan (TrnInternalAuditPerbaikan), 1 Temuan bisa
    // punya banyak Deadline (satu per entry Tindak Lanjut/Perbaikan). Ditampilkan gabungan
    // per baris (bukan koma), masing-masing baris tetap dapat highlight merah/kuning sendiri.
    // Render satu <table> HTML asli berisi pasangan PIC Auditee & Deadline per entry Tindak Lanjut/Perbaikan.
    // Dipakai sebagai formatter untuk SATU kolom gabungan (bukan dua kolom terpisah) - supaya baris
    // PIC & Deadline otomatis sejajar lewat perilaku native <tr>/<td>, tanpa perlu JS penyamaan tinggi.
    function formatPicDeadlinePairCell(value, row) {
        // Fraud / Spesial Audit: tidak ada PIC Auditee, kolom hanya berisi Deadline (1 entri per Temuan)
        if (isFraudJenis()) {
            var dlF = $.trim((row.DeadlineList ? String(row.DeadlineList).split('|') : [''])[0] || '');
            // Dibungkus div selebar penuh + text-align:center, karena sel grid ini display:flex (isi menempel kiri)
            if (!dlF) return '<div style="width:100%; text-align:center;">-</div>';
            var todayF = new Date();
            todayF.setHours(0, 0, 0, 0);
            var dtF    = new Date(dlF);
            var diffF  = Math.ceil((dtF - todayF) / (1000 * 60 * 60 * 24));
            var labelF = dtF.toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'});
            var badgeF;
            if (diffF < 0) {
                badgeF = '<span style="background-color: #FFCCCC; color: #990000; padding: 2px 6px; border-radius: 3px; display:inline-block;">' + labelF + '</span>';
            } else if (diffF <= 30) {
                badgeF = '<span style="background-color: #FFF3C4; color: #8A6D00; padding: 2px 6px; border-radius: 3px; display:inline-block;">' + labelF + '</span>';
            } else {
                badgeF = '<span style="display:inline-block;">' + labelF + '</span>';
            }
            return '<div style="width:100%; text-align:center;">' + badgeF + '</div>';
        }
        var picList = row.PicAuditeeList ? String(row.PicAuditeeList).split('|') : [];
        var dlList  = row.DeadlineList ? String(row.DeadlineList).split('|') : [];
        var count   = Math.max(picList.length, dlList.length);
        if (count === 0) return '-';

        var today = new Date();
        today.setHours(0, 0, 0, 0);

        var html = '<table class="pic-deadline-pair-table"><colgroup><col style="width:54%"><col></colgroup><tbody>';
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
            html += '<tr><td class="pdp-pic">' + pic + '</td><td class="pdp-dl">' + dLabel + '</td></tr>';
        }
        html += '</tbody></table>';
        return html;
    }

    var temuanList = [];             // list temuan baru (belum tersimpan) sebelum Save Hdr
    var lampiranTemuanTemp = [];     // lampiran untuk temuan yang sedang diisi di sub-form
    var editorReady = false;
    var editMode = null;             // null = mode tambah baru | {type:'temp', index} | {type:'db', dtlId}
    var deletedLampiranIds = [];     // LampiranId (DB) yang dihapus user saat mode edit db         // flag: TinyMCE txtTemuanEditor sudah selesai init atau belum
    var jenisAuditLoading = false;   // true saat Jenis Audit di-set lewat kode (buka form) -> handler change tidak ikut jalan
        var prevJenisAudit = '';         // nilai Jenis Audit sebelum diubah user (untuk revert)
        var fraudPerbaikanId = null;     // PerbaikanId entry Deadline Fraud saat edit data DB (supaya histori Progress tetap nyambung)

        function isFraudJenis() {
            return $('#cboJenisAudit').val() === 'Fraud / Spesial Audit';
        }

        // Catatan (textarea polos) <-> HTML tersanitasi yang disimpan di DetailPerbaikan
        function catatanToHtml(text) {
            text = $.trim(text || '');
            if (!text) return '';
            return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\r\n|\r|\n/g, '<br>');
        }
        function htmlToCatatan(html) {
            return String(html || '').replace(/<br\s*\/?>/gi, '\n').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
        }

    var loginUserIdEdit = '{{ $loginUserId ?? '' }}';
    var loginNikIdEdit  = '{{ $loginNikId ?? '' }}';
    var fieldMenuMode = 'input';
    var viewingItem = null;          // data item yang sedang ditampilkan readonly (row DB atau item temp)

    $(document).ready(function(){

        /* ==== LAMPIRAN TEMUAN DINONAKTIFKAN - uncomment untuk restore ====
        function sanitizeFileName(fileName) {
            var dotIndex = fileName.lastIndexOf('.');
            var name = dotIndex > -1 ? fileName.substring(0, dotIndex) : fileName;
            var ext  = dotIndex > -1 ? fileName.substring(dotIndex) : '';
            name = name.replace(/\s*-\s*/ /* g, '_').replace(/[.,\s]/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '');
        /*  return name + ext;
        }

        document.getElementById('txtFileLampiranTemuan').addEventListener('change', function (e) {
            var input = e.target;
            if (!input.files || input.files.length === 0) return;
            var file = input.files[0];
            var newName = sanitizeFileName(file.name);
            if (newName === file.name) return;
            var renamedFile = new File([file], newName, { type: file.type });
            var dt = new DataTransfer();
            dt.items.add(renamedFile);
            input.files = dt.files;
        }, true);

        renderUploadFile({
            id: 'txtFileLampiranTemuan',
            postData: { prefix: 'InternalAudit' },
            maxFileSize: 5,
            allowedFileExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'bmp', 'doc', 'docx']
        });

        $('#txtFileLampiranTemuan').on('fileuploaded', function(event, data){
            $('#hdnFileLampiranTemuanUploaded').val(data.response.filename);
        });
        ==== END LAMPIRAN TEMUAN ==== */

        // ==== Menu field Tambah Temuan (TinyMCE editor tunggal, digilir per field) ====
        var temuanFieldMap = {
            JudulTemuan: '#txtJudulTemuan',
            DetailTemuan: '#txtDetailTemuan',
            IndikasiAwal: '#txtIndikasiAwal',
            Resiko: '#txtResiko',
            PeraturanSOP: '#txtPeraturanSOP',
            SanksiKaryawan: '#txtSanksiKaryawan',
            SanksiAtasan: '#txtSanksiAtasan',
            RekomendasiAuditor: '#txtRekomendasiAuditor',
            PengembalianKerugian: '#txtPengembalianKerugian'
            // TindakLanjut tidak lagi single-field TinyMCE - jadi sub-modul terpisah (lihat perbaikanListTemp)
        };
        var activeTemuanField = 'JudulTemuan';

        // State sub-modul Tindak Lanjut / Perbaikan (per temuan yang sedang diisi/diedit)
        var perbaikanListTemp = [];      // array of {Action, Deadline, ListPic:[{NikId,Name}], DetailPerbaikan}
        var editModePerbaikan = null;    // null = tambah baru | {index} = edit item di perbaikanListTemp

        // Ambil HTML dari editor, sanitasi supaya cuma tag format (bold/underline/italic/br) yang tersimpan
        function getEditorTextWithNewlines(editor) {
            var html = editor.getContent();
            var div = document.createElement('div');
            div.innerHTML = html;
            var allowedTags = ['STRONG', 'B', 'EM', 'I', 'U', 'BR'];

            function clean(node) {
                var children = Array.prototype.slice.call(node.childNodes);
                children.forEach(function(child) {
                    if (child.nodeType !== 1) return;
                    if (child.tagName === 'P' || child.tagName === 'DIV') {
                        clean(child);
                        var br = document.createElement('br');
                        child.parentNode.insertBefore(br, child.nextSibling);
                        while (child.firstChild) child.parentNode.insertBefore(child.firstChild, child);
                        child.parentNode.removeChild(child);
                        return;
                    }
                    if (allowedTags.indexOf(child.tagName) === -1) {
                        while (child.firstChild) node.insertBefore(child.firstChild, child);
                        node.removeChild(child);
                        return;
                    }
                    child.removeAttribute('style');
                    child.removeAttribute('class');
                    clean(child);
                });
            }
            clean(div);

            var result = div.innerHTML;
            // Sama seperti di sanitizePastedListContent - <br> di sekitar [endif] baru terbentuk
            // SETELAH clean() mengonversi <p>/<div>, jadi stripping-nya harus di sini.
            result = result.replace(/(<br\s*\/?>\s*)+<!--\[endif\]-->/gi, '<!--[endif]-->');
            result = result.replace(/<!--\[endif\]-->\s*(<br\s*\/?>\s*)+/gi, '<!--[endif]-->');
            result = result.replace(/\s*<!--[\s\S]*?-->\s*/g, '');

            result = result.replace(/^(<br\s*\/?>)+/i, '').replace(/(<br\s*\/?>)+$/i, '');
            result = result.replace(/(<br\s*\/?>\s*){3,}/gi, '<br><br>');
            return result;
        }

        // Value yang tersimpan di hidden input sekarang sudah berupa HTML tersanitasi
        // (pakai tag <br> asli dari editor), tapi data LAMA (sebelum toolbar Bold/
        // Underline/Italic ada) tersimpan sebagai teks polos dengan newline asli (\n).
        // Convert newline mentah itu jadi <br> supaya ganti baris tetap kebaca saat
        // data lama dibuka lagi di editor. Aman untuk data baru karena stringnya
        // sudah tidak mengandung karakter \n mentah (sudah berupa tag <br>).
        function textToEditorHtml(html) {
            html = html || '';
            // Lapisan ketiga: bersihkan juga data lama yang mungkin sudah kepalang tersimpan dengan
            // <br> fallback Word ini, supaya temuan lama yang dibuka ulang ikut rapi.
            html = html.replace(/(<br\s*\/?>\s*)+<!--\[endif\]-->/gi, '<!--[endif]-->');
            html = html.replace(/<!--\[endif\]-->\s*(<br\s*\/?>\s*)+/gi, '<!--[endif]-->');
            html = html.replace(/\s*<!--[\s\S]*?-->\s*/g, '');
            return html.replace(/\r\n|\r|\n/g, '<br>');
        }  

        // ===== Periode Pemeriksaan: 1 input date-range -> PeriodeStart & PeriodeEnd (End boleh kosong) =====
        var periodeStart = '';   // 'YYYY-MM-DD'
        var periodeEnd   = '';   // 'YYYY-MM-DD' atau ''
        var $periode     = $('#txtPeriodePemeriksaan');
        var periodePicker = null;

        function setPeriode(start, end) {
            periodeStart = start || '';
            periodeEnd   = periodeStart ? (end || '') : '';
            var txt = periodeStart ? (periodeEnd ? fmtTglID(periodeStart) + ' s/d ' + fmtTglID(periodeEnd) : fmtTglID(periodeStart)) : '';
            $periode.val(txt);
        }

        // Dipanggil SETELAH user klik tanggal di kalender: simpan Start saja dulu (End menyusul),
        // jadi End boleh belum terisi. Start = End ditolak.
        function syncPeriodeFromPicker() {
            if (!periodePicker || !periodePicker.startDate) return;
            var sTxt = periodePicker.startDate.format('YYYY-MM-DD');
            var e    = periodePicker.endDate;

            if (!e) {
                setPeriode(sTxt, '');
            } else {
                var eTxt = e.format('YYYY-MM-DD');
                if (eTxt <= sTxt) {
                    setPeriode(sTxt, '');
                    periodePicker.endDate = null;      // kembali ke mode "menunggu tanggal selesai"
                    periodePicker.updateView();
                    alertBoxAuto({ id: 'frmAuditAlert', msg: 'Tanggal selesai harus setelah tanggal mulai', mode: 'warning' });
                } else {
                    setPeriode(sTxt, eTxt);
                }
            }
            // Tombol OK default-nya mati kalau End belum dipilih; kita izinkan Start saja
            periodePicker.container.find('button.applyBtn').prop('disabled', false);
        }

        if ($.fn.daterangepicker) {
            $('.daterangepicker').remove();   // buang kalender sisa dari pemuatan sebelumnya supaya tidak menumpuk
            $periode.daterangepicker({
                autoUpdateInput : false,   // isi input diatur manual (supaya Start saja boleh)
                autoApply       : false,
                drops           : 'auto',
                locale          : { format: 'YYYY-MM-DD', separator: ' s/d ', applyLabel: 'OK', cancelLabel: 'Clear' }
            });
            periodePicker = $periode.data('daterangepicker');

            // Sinkronkan tampilan kalender dengan nilai saat ini setiap kali dibuka
            $periode.on('show.daterangepicker', function(ev, picker) {
                var s = periodeStart ? moment(periodeStart, 'YYYY-MM-DD') : moment();
                var e = periodeEnd ? moment(periodeEnd, 'YYYY-MM-DD') : s.clone();
                picker.setStartDate(s);
                picker.setEndDate(e);
                picker.updateView();
                picker.container.find('button.applyBtn').prop('disabled', false);
            });

            // Tombol "Clear"
            $periode.on('cancel.daterangepicker', function() {
                setPeriode('', '');
            });

            // Jalan SETELAH handler klik tanggal milik library (setTimeout 0 supaya state picker sudah terbaru)
            periodePicker.container.find('.drp-calendar').on('mousedown.periode', 'td.available', function() {
                setTimeout(syncPeriodeFromPicker, 0);
            });
        } else {
            console.error('daterangepicker.js belum termuat (cek public/plugins/daterangepicker/)');
        }

        // ===== Format tampilan tanggal "29 Sep 2026"; nilai asli tetap 'YYYY-MM-DD' di variabel (dikirim ke server) =====
        var BULAN_ID = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

        function fmtTglID(ymd) {
            var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(ymd || '').substring(0, 10));
            if (!m) return '';
            return m[3] + ' ' + BULAN_ID[parseInt(m[2], 10) - 1] + ' ' + m[1];
        }

        var tglPemeriksaan = '';   // 'YYYY-MM-DD' atau ''
        var tglUpload      = '';   // 'YYYY-MM-DD' atau ''

        function setTanggalPemeriksaan(ymd) {
            tglPemeriksaan = ymd ? String(ymd).substring(0, 10) : '';
            $('#txtTanggalPemeriksaan').val(fmtTglID(tglPemeriksaan));
        }

        function setTanggalUpload(ymd) {
            tglUpload = ymd ? String(ymd).substring(0, 10) : '';
            $('#txtTanggalUpload').val(fmtTglID(tglUpload));
        }

        // Kalender satu tanggal (daterangepicker mode singleDatePicker), tombol OK / Clear seperti Periode Pemeriksaan
        function initTanggalPicker(inputId, getVal, setVal) {
            if (!$.fn.daterangepicker) return;
            var $el = $('#' + inputId);
            $el.daterangepicker({
                singleDatePicker : true,
                showDropdowns    : true,    // pilihan bulan & tahun lewat dropdown (hapus baris ini kalau tidak diinginkan)
                autoUpdateInput  : false,   // isi input diatur manual supaya formatnya "29 Sep 2026"
                autoApply        : false,
                drops            : 'auto',
                locale           : { format: 'YYYY-MM-DD', applyLabel: 'OK', cancelLabel: 'Clear' }
            });

            $el.on('show.daterangepicker', function(ev, picker) {
                var cur = getVal();
                var d   = cur ? moment(cur, 'YYYY-MM-DD') : moment();
                picker.setStartDate(d);
                picker.setEndDate(d);
                picker.updateView();
            });

            $el.on('apply.daterangepicker', function(ev, picker) {
                setVal(picker.startDate.format('YYYY-MM-DD'));
            });

            $el.on('cancel.daterangepicker', function() {
                setVal('');
            });
        }

        initTanggalPicker('txtTanggalPemeriksaan', function(){ return tglPemeriksaan; }, setTanggalPemeriksaan);
        initTanggalPicker('txtTanggalUpload',      function(){ return tglUpload; },      setTanggalUpload);

        comboAjax('cboDepartmentAudity', {url: '{{ url("services/combo/alldepartment") }}', alertId: 'frmAuditAlert'});
        comboAjax('cboApproval1', {url: '{{ url("services/combo/internalAuditApprover") }}', alertId: 'frmAuditAlert'});
        comboAjax('cboApproval2', {url: '{{ url("services/combo/internalAuditApprover") }}', alertId: 'frmAuditAlert'});
        comboAjax('cboListPic', {
            url: '{{ url("audit/transactions/audit-list/get-sub-ordinate-all") }}',
            alertId: 'frmAuditAlert',
            success: function() {
                var listPic = $('#cboListPic');
                if (listPic.hasClass('select2-hidden-accessible')) {
                    listPic.select2('destroy');
                }
                listPic.select2({
                    multiple: true,
                    closeOnSelect: false
                });
                // Paksa kosong lagi - beberapa versi comboAjax/select2 auto-select
                // opsi tertentu (mis. user yang sedang login) begitu combo selesai di-init
                listPic.val(null).trigger('change');
            }
        }, false, false);

        comboAjax('cboListPicPerbaikan', {
            url: '{{ url("audit/transactions/audit-list/get-sub-ordinate-all") }}',
            alertId: 'frmTemuanAlert',
            success: function() {
                var listPicPerbaikan = $('#cboListPicPerbaikan');
                if (listPicPerbaikan.hasClass('select2-hidden-accessible')) {
                    listPicPerbaikan.select2('destroy');
                }
                listPicPerbaikan.select2({
                    multiple: true,
                    closeOnSelect: false
                });
                // Paksa kosong lagi, sama alasan seperti cboListPic
                listPicPerbaikan.val(null).trigger('change');
            }
        }, false, false);

        comboAjax('cboApprovalAuditee', {url: '{{ url("audit/transactions/audit-list/get-all-approver") }}', alertId: 'frmTemuanAlert'});

        $('#chkListPic').on('ifChecked', function(event){
            if ($('#cboListPic').length > 0) {
                $("#cboListPic > option").prop("selected", "selected");
                $("#cboListPic").trigger("change");
            }
        });

        $('#chkListPic').on('ifUnchecked', function(event){
            if ($('#cboListPic').length > 0) {
                $("#cboListPic > option").removeAttr("selected");
                $("#cboListPic").trigger("change");
            }
        });

        function toggleDepartmentAudity() {
            var jenisAudit = $('#cboJenisAudit').val();
            if (jenisAudit === 'Pemeriksaan Reguler') {
                $('#wrapDepartmentAudity, #lblDepartmentAudity').show();
            } else {
                $('#wrapDepartmentAudity, #lblDepartmentAudity').hide();
                $('#cboDepartmentAudity').val('').trigger('change');
            }
            // List PIC berlaku untuk kedua Jenis Audit (Reguler maupun Fraud/Spesial)
            $('#wrapListPic, #lblListPic').show();

        }

        // regulerHiddenFields & toggleTemuanFieldsByJenisAudit dihapus - Jenis Audit tidak lagi
        // mempengaruhi grpTemuanFieldMenu, dropdown Jenis Audit murni informatif saja.

        function forceSwitchTemuanField(newField) {
            var editor = tinymce.get('txtTemuanEditor');
            $('.temuan-field-btn').removeClass('active');
            $('.temuan-field-btn[data-field="' + newField + '"]').addClass('active');
            if (newField === 'Kerugian') {
                if (editor) editor.hide();
                // Paksa sembunyikan textarea asli juga - jaga-jaga kalau tinymce.get() sempat
                // return null (mis. saat instance sedang di-reinit), supaya editor.hide() yang
                // di-skip tidak membuat textarea mentah (isi HTML literal) kelihatan ke user.
                $('#txtTemuanEditor').hide();
                $('#wrapKerugianInline').show();
                $('#wrapTindakLanjutPerbaikan').hide();
            } else if (newField === 'TindakLanjut') {
                if (editor) editor.hide();
                $('#txtTemuanEditor').hide();
                $('#wrapKerugianInline').hide();
                $('#wrapTindakLanjutPerbaikan').show();
                initPerbaikanEditor();
                renderPerbaikanTemp();
            } else {
                $('#wrapKerugianInline').hide();
                $('#wrapTindakLanjutPerbaikan').hide();
                if (editor) {
                    editor.show();
                    editor.setContent(textToEditorHtml($(temuanFieldMap[newField]).val()));
                    if (editor.getBody()) { editor.getBody().scrollTop = 0; }
                }
            }
            activeTemuanField = newField;
        }

        function renderFieldMenuViewer() {
            if (!viewingItem) return;
            var val;
            if (activeTemuanField === 'Kerugian') {
                var num = parseFloat(viewingItem.Kerugian);
                val = (isNaN(num) || num === 0) ? '-' : 'Rp ' + num.toLocaleString('id-ID');
                $('#viewerTemuanField').show();
                $('#wrapTindakLanjutPerbaikan').hide();
                $('#viewerTemuanField').text(val);
            } else if (activeTemuanField === 'TindakLanjut') {
                $('#viewerTemuanField').hide();
                $('#wrapTindakLanjutPerbaikan').show();
                $('#wrapPerbaikanForm').hide();
                if (viewingItem.DtlId) {
                    // Temuan sudah tersimpan di DB (grdTemuanDb) - row grid tidak membawa
                    // PerbaikanList, jadi harus di-fetch ulang lewat endpoint yang sama
                    // dengan yang dipakai editTemuanDb().
                    ajax({
                        url      : '{{ url("audit/transactions/audit-list/get-perbaikan-list-array") }}',
                        postData : { DtlId: viewingItem.DtlId },
                        blockId  : 'dlgInternalAudit',
                        success  : function(ret) {
                            viewingItem.PerbaikanList = (ret && ret.data) ? ret.data : [];
                            renderPerbaikanReadonly(viewingItem.PerbaikanList);
                        }
                    });
                } else {
                    // Temuan baru (grdTemuanTemp, belum tersimpan) - datanya sudah ada di memory
                    renderPerbaikanReadonly(viewingItem.PerbaikanList || []);
                }
            } else {
                $('#viewerTemuanField').show();
                $('#wrapTindakLanjutPerbaikan').hide();
                // Field rich text (bold/underline/italic) -> render sebagai HTML, bukan text polos
                val = viewingItem[activeTemuanField] || '-';
                $('#viewerTemuanField').html(val);
            }
        }

        function showTemuanReadonly(item) {
            if (!item) return;
            fieldMenuMode = 'view';
            viewingItem = item;

            var editor = tinymce.get('txtTemuanEditor');
            if (editor) editor.hide();
            $('#txtTemuanEditor').hide();
            $('#wrapKerugianInline').hide();
            $('#wrapDeadlineInline').hide();
            $('#viewerTemuanField').show();

            $('.temuan-field-btn').removeClass('active');
            $('.temuan-field-btn[data-field="JudulTemuan"]').addClass('active');
            activeTemuanField = 'JudulTemuan';

            renderFieldMenuViewer();
            updateTambahTemuanButtonLabel();
        }

        function exitFieldMenuViewMode() {
            fieldMenuMode = 'input';
            viewingItem = null;
            $('#viewerTemuanField').hide();
            $('#wrapPerbaikanForm').show();
            $('#btnAddPerbaikan').show();
        }

        // ===== Jenis Audit = Fraud / Spesial Audit: tab "Tindak Lanjut / Perbaikan" jadi tab "Deadline" =====
        // ===== Editor TinyMCE "Detail Tindak Lanjut / Perbaikan" khusus Fraud (menggantikan textarea Catatan) =====
        function getFraudEditor() {
            var ed = tinymce.get('txtCatatanFraud');
            return (ed && ed.getContainer() && document.body.contains(ed.getContainer())) ? ed : null;
        }

        // HTML tersanitasi dari editor; kalau editor belum pernah dibuka, pakai nilai textarea (hasil load data)
        function getFraudDetailHtml() {
            var ed = getFraudEditor();
            return ed ? getEditorTextWithNewlines(ed) : ($('#txtCatatanFraud').val() || '');
        }

        function setFraudDetail(html) {
            $('#txtCatatanFraud').val(html || '');
            var ed = getFraudEditor();
            if (ed) ed.setContent(html || '');
        }

        function fraudDetailFilled() {
            return !!$.trim($('<div>').html(getFraudDetailHtml()).text());
        }

        // Header kolom "PIC Auditee | Deadline" di grdTemuanTemp & grdTemuanDb: Fraud hanya "Deadline"
        function updateGrdTemuanHeaders() {
            var headerHtml = isFraudJenis()
                ? '<table class="pdp-header-table"><tr><td class="pdp-pic">Deadline</td></tr></table>'
                : '<table class="pdp-header-table"><colgroup><col style="width:54%"><col></colgroup><tr><td class="pdp-pic">PIC Auditee</td><td class="pdp-dl">Deadline</td></tr></table>';
            $.each(['grdTemuanTemp', 'grdTemuanDb'], function(i, gid) {
                var $g = $('#' + gid);
                if (!$g.data('datagrid')) return;
                var col = $g.datagrid('getColumnOption', 'PicAuditeeList');
                if (col) col.title = headerHtml;
                $g.datagrid('getPanel').find('.datagrid-header td[field="PicAuditeeList"] .datagrid-cell').html(headerHtml);
            });
        }

        // Fraud: editor "Detail Tindak Lanjut / Perbaikan" dipanjangkan sampai sejajar dengan bawah menu field di kiri
        // (tidak ada tabel di bawah editor seperti di Reguler, jadi sisa ruangnya dipakai editor).
        function fitFraudEditorHeight() {
            if (!isFraudJenis()) return;
            var ed = getFraudEditor();
            if (!ed) return;

            var $menu = $('#grpTemuanFieldMenu');
            var $cont = $(ed.getContainer());
            if (!$menu.is(':visible') || !$cont.is(':visible')) return;

            // bawah menu - atas editor - jarak bawah panel (padding 10px + sedikit ruang)
            var h = ($menu.offset().top + $menu.outerHeight()) - $cont.offset().top - 12;
            if (h < 200) h = 200;

            if (ed.theme && typeof ed.theme.resizeTo === 'function') {
                try { ed.theme.resizeTo(null, h); } catch (e) { $cont.css('height', h + 'px'); }
            } else {
                $cont.css('height', h + 'px');
            }
        }
        $(window).off('resize.fraudEditor').on('resize.fraudEditor', fitFraudEditorHeight);

        function loadFraudInputsFromList() {
            var f = isFraudJenis() ? perbaikanListTemp[0] : null;
            $('#txtDeadlineFraud').val(f ? (f.Deadline || '') : '');
            setFraudDetail(f ? textToEditorHtml(f.DetailPerbaikan) : '');
            fraudPerbaikanId = (f && f.PerbaikanId) ? f.PerbaikanId : null;
        }

        // Payload PerbaikanList yang dikirim ke server: Reguler = list biasa, Fraud = 1 item (Deadline + Catatan)
        function buildPerbaikanListForSave() {
            if (!isFraudJenis()) return perbaikanListTemp.slice();
            var deadline = $('#txtDeadlineFraud').val();
            if (!deadline) return [];
            var item = {
                Action              : '',
                Deadline            : deadline,
                ApprovalAuditee     : null,
                ApprovalAuditeeName : '',
                ListPic             : [],
                DetailPerbaikan     : getFraudDetailHtml()
            };
            if (fraudPerbaikanId) item.PerbaikanId = fraudPerbaikanId;
            return [item];
        }

        function applyJenisAuditTindakLanjutUI() {
            // Label menu tetap "Tindak Lanjut / Perbaikan" untuk Reguler maupun Fraud; yang beda hanya isinya
            updateGrdTemuanHeaders();
            if (activeTemuanField === 'TindakLanjut' && fieldMenuMode !== 'view') {
                initPerbaikanEditor();
                renderPerbaikanTemp();
            }
            updateFieldFilledIndicators();
        }

        $('#txtDeadlineFraud, #txtCatatanFraud').on('change input blur changeDate dp.change', function(){
            updateFieldFilledIndicators();
        });

        // Handler ini sengaja didaftarkan SEBELUM change.toggleDept supaya bisa membatalkan perubahan
        // (stopImmediatePropagation) tanpa toggleDepartmentAudity() ikut mengosongkan Department Auditee.
        $('#cboJenisAudit').off('change.jenisAuditTL').on('change.jenisAuditTL', function(e){
            if (jenisAuditLoading) return;
            var newVal = $(this).val() || '';
            if (newVal === prevJenisAudit) return;

            var hasDbTemuan = false;
            if ($('#grdTemuanDb').data('datagrid') && $('#wrapTemuanDb').is(':visible')) {
                hasDbTemuan = $('#grdTemuanDb').datagrid('getRows').length > 0;
            }
            var hasFormData = perbaikanListTemp.length > 0 || !!$('#txtDeadlineFraud').val() || fraudDetailFilled();

            var cancelMsg = null; // null = lanjut, '' = batal tanpa alert, 'teks' = batal + alert
            if (temuanList.length > 0 || hasDbTemuan) {
                cancelMsg = 'Jenis Audit tidak dapat diubah karena sudah ada Temuan. Hapus Temuan terlebih dahulu.';
            } else if (hasFormData && !confirm('Mengganti Jenis Audit akan mengosongkan isian Tindak Lanjut / Deadline yang sedang diisi. Lanjutkan?')) {
                cancelMsg = '';
            }

            if (cancelMsg !== null) {
                e.stopImmediatePropagation();
                jenisAuditLoading = true;
                $(this).val(prevJenisAudit).trigger('change');
                jenisAuditLoading = false;
                if (cancelMsg) alertBoxAuto({ id: 'frmAuditAlert', msg: cancelMsg, mode: 'warning' });
                return;
            }

            if (hasFormData) {
                perbaikanListTemp = [];
                resetFormPerbaikan();
                $('#txtDeadlineFraud').val('');
                setFraudDetail('');
                fraudPerbaikanId = null;
            }
            prevJenisAudit = newVal;
            applyJenisAuditTindakLanjutUI();
        });

        $('#cboJenisAudit').off('change.toggleDept').on('change.toggleDept', function(){
            toggleDepartmentAudity();
        });  

        // Konten list (bullet/numbered) dari luar (Word/Google Docs/dll) sering mengunci "jarak ganti
        // baris"-nya - tiap item list jadi elemen kaku yang backspace-nya nyangkut/susah dirapikan.
        // Kalau hasil paste terdeteksi list, konversi jadi baris teks biasa (<br> antar baris) supaya
        // user bisa bebas hapus/gabung barisnya kayak text biasa. Konten TANPA list (mis. cuma ada
        // Bold/Underline/ganti baris biasa) dibiarkan apa adanya, tidak disentuh.
        function sanitizePastedListContent(html) {
            if (!html || !/<li[\s>]|<ul[\s>]|<ol[\s>]|mso-list\s*:|<!--\[if\s/i.test(html)) {
                return html;
            }

            var div = document.createElement('div');
            div.innerHTML = html;
            var allowedTags = ['STRONG', 'B', 'EM', 'I', 'U', 'BR'];

            // Ratakan elemen blok (P/DIV/LI/UL/OL) jadi <br>, tapi PERTAHANKAN tag format
            // (Bold/Underline/Italic) di dalamnya - beda dari versi sebelumnya yang pakai .text()
            // (buang semua formatting jadi plain text). Tag selain format (span/font/dsb dari
            // Word) tetap dibuang, cuma isinya yang dipertahankan.
            function flatten(node) {
                var children = Array.prototype.slice.call(node.childNodes);
                children.forEach(function(child) {
                    if (child.nodeType !== 1) return;
                    var tag = child.tagName;
                    if (tag === 'P' || tag === 'DIV' || tag === 'LI') {
                        flatten(child);
                        var br = document.createElement('br');
                        child.parentNode.insertBefore(br, child.nextSibling);
                        while (child.firstChild) child.parentNode.insertBefore(child.firstChild, child);
                        child.parentNode.removeChild(child);
                    } else if (tag === 'UL' || tag === 'OL') {
                        flatten(child);
                        while (child.firstChild) child.parentNode.insertBefore(child.firstChild, child);
                        child.parentNode.removeChild(child);
                    } else if (allowedTags.indexOf(tag) === -1) {
                        flatten(child);
                        while (child.firstChild) node.insertBefore(child.firstChild, child);
                        node.removeChild(child);
                    } else {
                        child.removeAttribute('style');
                        child.removeAttribute('class');
                        flatten(child);
                    }
                });
            }

            flatten(div);

            var result = div.innerHTML;
            // <br> di sekitar [endif] baru TERBENTUK di sini (dari <p> yang baru saja dikonversi
            // flatten() di atas) - makanya stripping-nya harus terjadi DI SINI, bukan di html mentah
            // sebelum flatten dipanggil. Comment node sendiri tidak disentuh flatten(), jadi masih
            // ada di titik ini dan dipakai sebagai jangkar buat cari & buang <br> pasangannya dulu,
            // baru comment-nya sendiri dibuang.
            result = result.replace(/(<br\s*\/?>\s*)+<!--\[endif\]-->/gi, '<!--[endif]-->');
            result = result.replace(/<!--\[endif\]-->\s*(<br\s*\/?>\s*)+/gi, '<!--[endif]-->');
            result = result.replace(/\s*<!--[\s\S]*?-->\s*/g, '');

            // Buang <br> nyasar di awal/akhir, dan rapikan <br> beruntun (paragraf kosong ganda dari Word)
            result = result.replace(/^(<br\s*\/?>)+/i, '').replace(/(<br\s*\/?>)+$/i, '');
            result = result.replace(/(<br\s*\/?>\s*){3,}/gi, '<br><br>');

            return result.trim() !== '' ? result : html;
        }

        function initTemuanEditor(onReady) {
            var existing = tinymce.get('txtTemuanEditor');
            if (existing) {
                // Cek apakah instance lama masih benar-benar nempel ke DOM textarea yang sekarang tampil
                if (document.body.contains(existing.getContainer())) {
                    editorReady = true;
                    if (onReady) onReady();
                    return;
                }
                // Instance zombie (dari module sebelumnya) -> hapus dulu
                existing.remove();
            }
            editorReady = false;
            tinymce.init({
                selector: '#txtTemuanEditor',
                menubar: false,
                toolbar: 'bold underline italic',
                statusbar: false,
                height: 350,
                content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
                force_br_newlines: true,
                newline_behavior: 'linebreak',
                valid_elements: 'strong,b,em,i,u,br',
                paste_preprocess: function(plugin, args) {
                    args.content = sanitizePastedListContent(args.content);
                },
                setup: function(editor) {
                    editor.on('init', function() {
                        editorReady = true;
                        if (onReady) onReady();
                    });
                }
            });
        }

        function initPerbaikanEditor() {
            // Reguler memakai editor txtDetailPerbaikan, Fraud memakai txtCatatanFraud (sama-sama TinyMCE)
            var editorId = isFraudJenis() ? 'txtCatatanFraud' : 'txtDetailPerbaikan';
            if (isFraudJenis()) $('#wrapDeadlineFraud').show();   // TinyMCE di-init saat container terlihat
            var existing = tinymce.get(editorId);
            if (existing) {
                if (document.body.contains(existing.getContainer())) return;
                existing.remove();
            }
            tinymce.init({
                selector: '#' + editorId,
                menubar: false,
                toolbar: 'bold underline italic',
                statusbar: false,
                height: 200,
                setup: function(ed) {
                    ed.on('init', function() {
                        fitFraudEditorHeight();
                        setTimeout(fitFraudEditorHeight, 100);   // ulang sekali lagi setelah layout stabil
                    });
                },
                content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
                force_br_newlines: true,
                newline_behavior: 'linebreak',
                valid_elements: 'strong,b,em,i,u,br',
                paste_preprocess: function(plugin, args) {
                    args.content = sanitizePastedListContent(args.content);
                }
            });
        }       

        function saveActiveTemuanField() {
            if (activeTemuanField === 'Kerugian' || activeTemuanField === 'Deadline') return;
            var editor = tinymce.get('txtTemuanEditor');
            if (!editor) return;
            var plainText = getEditorTextWithNewlines(editor);
            $(temuanFieldMap[activeTemuanField]).val(plainText);
        }

        function resetFormPerbaikan() {
            $('#cboActionPerbaikan').val('Corrective Action').trigger('change');
            $('#txtDeadlinePerbaikan').val('');
            $('#cboApprovalAuditee').val('').trigger('change');
            $('#cboListPicPerbaikan').val(null).trigger('change');
            var editorPerbaikan = tinymce.get('txtDetailPerbaikan');
            if (editorPerbaikan) editorPerbaikan.setContent('');
            editModePerbaikan = null;
            $('#btnAddPerbaikan').html('<i class="fa fa-plus"></i>');
        }

        function renderPerbaikanTemp(readonly) {
            if (isFraudJenis()) {
                $('#wrapPerbaikanForm').hide();
                $('#tblPerbaikanTemp').hide();
                setTimeout(fitFraudEditorHeight, 0);   // dijalankan setelah #wrapDeadlineFraud tampil
                $('#wrapDeadlineFraud').show();
                return;
            }
            $('#wrapDeadlineFraud').hide();
            $('#wrapPerbaikanForm').show();
            $('#tblPerbaikanTemp').show();
            var html = '<table class="table table-bordered table-condensed" style="margin-top:0; width:100%; table-layout:fixed;">';
            html += '<thead><tr style="background-color:#99CCCC;">' +
                '<th style="text-align:center; width:10%;">Action</th>' +
                '<th style="text-align:center; width:9%;">Deadline</th>' +
                '<th style="text-align:center; width:14%;">Approval Auditee</th>' +
                '<th style="text-align:center; width:16%;">PIC Auditee</th>' +
                '<th style="text-align:center;">Tindak Lanjut / Perbaikan</th>' +
                (readonly ? '' : '<th style="text-align:center; width:80px;">Aksi</th>') +
                '</tr></thead><tbody>';

            if (perbaikanListTemp.length === 0) {
                html += '<tr><td colspan="' + (readonly ? 5 : 6) + '" style="text-align:center; color:#999;">Belum ada data</td></tr>';
            } else {
                $.each(perbaikanListTemp, function(i, item) {
                    var deadlineLabel = item.Deadline ? new Date(item.Deadline).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-';
                    var picNames = $.map(item.ListPic || [], function(p){ return p.Name; }).join(', ') || '-';
                    html += '<tr>';
                    html += '<td style="text-align:center; word-wrap:break-word;">' + (item.Action || '-') + '</td>';
                    html += '<td style="text-align:center; word-wrap:break-word;">' + deadlineLabel + '</td>';
                    html += '<td style="word-wrap:break-word;">' + (item.ApprovalAuditeeName || '-') + '</td>';
                    html += '<td style="word-wrap:break-word;">' + picNames + '</td>';
                    html += '<td style="word-wrap:break-word; word-break:break-word;">' + (item.DetailPerbaikan || '-') + '</td>';
                    if (!readonly) {
                        html += '<td style="text-align:center;">' +
                            '<button type="button" onclick="editPerbaikanTemp(' + i + ')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:#f0ad4e; margin-right:4px;"><i class="fa fa-pencil"></i></button>' +
                            '<button type="button" onclick="deletePerbaikanTemp(' + i + ')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:red;"><i class="fa fa-trash"></i></button>' +
                            '</td>';
                    }
                    html += '</tr>';
                });
            }
            html += '</tbody></table>';
            $('#tblPerbaikanTemp').html(html);
        }

        // Render tabel Tindak Lanjut / Perbaikan mode view (readonly) langsung dari data yang
        // diberikan, tanpa bergantung ke variabel global perbaikanListTemp yang dipakai form input -
        // supaya data mode view tidak ketimpa/ke-reset oleh state form edit lain.
        function renderPerbaikanReadonly(list) {
            $('#wrapDeadlineFraud').hide();
            $('#tblPerbaikanTemp').show();
            if (isFraudJenis()) {
                var fItem = (list || [])[0];
                var fHtml = '<table class="table table-bordered table-condensed" style="margin-top:0; width:100%; table-layout:fixed;">' +
                    '<thead><tr style="background-color:#99CCCC;">' +
                    '<th style="text-align:center; width:15%;">Deadline</th>' +
                    '<th style="text-align:center;">Detail Tindak Lanjut / Perbaikan</th>' +
                    '</tr></thead><tbody>';
                if (!fItem) {
                    fHtml += '<tr><td colspan="2" style="text-align:center; color:#999;">Belum ada data</td></tr>';
                } else {
                    var fDeadline = fItem.Deadline ? new Date(fItem.Deadline).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-';
                    fHtml += '<tr><td style="text-align:center;">' + fDeadline + '</td>' +
                        '<td style="word-wrap:break-word; word-break:break-word;">' + (fItem.DetailPerbaikan || '-') + '</td></tr>';
                }
                fHtml += '</tbody></table>';
                $('#tblPerbaikanTemp').html(fHtml);
                return;
            }
            list = list || [];
            var html = '<table class="table table-bordered table-condensed" style="margin-top:0; width:100%; table-layout:fixed;">';
            html += '<thead><tr style="background-color:#99CCCC;">' +
                '<th style="text-align:center; width:10%;">Action</th>' +
                '<th style="text-align:center; width:9%;">Deadline</th>' +
                '<th style="text-align:center; width:14%;">Approval Auditee</th>' +
                '<th style="text-align:center; width:16%;">PIC Auditee</th>' +
                '<th style="text-align:center;">Tindak Lanjut / Perbaikan</th>' +
                '</tr></thead><tbody>';

            if (list.length === 0) {
                html += '<tr><td colspan="5" style="text-align:center; color:#999;">Belum ada data</td></tr>';
            } else {
                $.each(list, function(i, item) {
                    var deadlineLabel = item.Deadline ? new Date(item.Deadline).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-';
                    var picNames = $.map(item.ListPic || [], function(p){ return p.Name; }).join(', ') || '-';
                    html += '<tr>';
                    html += '<td style="text-align:center; word-wrap:break-word;">' + (item.Action || '-') + '</td>';
                    html += '<td style="text-align:center; word-wrap:break-word;">' + deadlineLabel + '</td>';
                    html += '<td style="word-wrap:break-word;">' + (item.ApprovalAuditeeName || '-') + '</td>';
                    html += '<td style="word-wrap:break-word;">' + picNames + '</td>';
                    html += '<td style="word-wrap:break-word; word-break:break-word;">' + (item.DetailPerbaikan || '-') + '</td>';
                    html += '</tr>';
                });
            }
            html += '</tbody></table>';
            $('#tblPerbaikanTemp').html(html);
        }

        window.editPerbaikanTemp = function(index) {
            var item = perbaikanListTemp[index];
            if (!item) return;

            $('#cboActionPerbaikan').val(item.Action).trigger('change');
            $('#txtDeadlinePerbaikan').val(item.Deadline || '');

            var approvalSelect = $('#cboApprovalAuditee');
            if (item.ApprovalAuditee && approvalSelect.find('option[value="' + item.ApprovalAuditee + '"]').length === 0) {
                approvalSelect.append(new Option(item.ApprovalAuditeeName || '', item.ApprovalAuditee));
            }
            approvalSelect.val(item.ApprovalAuditee || '').trigger('change');

            var picSelect = $('#cboListPicPerbaikan');
            $.each(item.ListPic || [], function(i, p) {
                if (picSelect.find('option[value="' + p.NikId + '"]').length === 0) {
                    picSelect.append(new Option(p.Name, p.NikId, true, true));
                }
            });
            picSelect.val($.map(item.ListPic || [], function(p){ return p.NikId; })).trigger('change');

            var editorPerbaikan = tinymce.get('txtDetailPerbaikan');
            if (editorPerbaikan) editorPerbaikan.setContent(textToEditorHtml(item.DetailPerbaikan));

            editModePerbaikan = { index: index };
            $('#btnAddPerbaikan').html('<i class="fa fa-save"></i>');
        };

        window.deletePerbaikanTemp = function(index) {
            perbaikanListTemp.splice(index, 1);
            renderPerbaikanTemp();
            updateFieldFilledIndicators();
        };

        $('#btnAddPerbaikan').click(function(){
            var action = $('#cboActionPerbaikan').val();
            var deadline = $('#txtDeadlinePerbaikan').val();
            var approvalAuditee = $('#cboApprovalAuditee').val();
            var approvalAuditeeName = $('#cboApprovalAuditee option:selected').text();
            var editorPerbaikan = tinymce.get('txtDetailPerbaikan');
            var detail = editorPerbaikan ? getEditorTextWithNewlines(editorPerbaikan) : '';
            var listPic = $.map($('#cboListPicPerbaikan').select2('data') || [], function(d){
                return { NikId: d.id, Name: d.text };
            });

            if (!action) {
                alertBoxAuto({ id: 'frmTemuanAlert', msg: 'Action wajib diisi', mode: 'warning' });
                return;
            }

            var item = {
                Action: action,
                Deadline: deadline,
                ApprovalAuditee: approvalAuditee || null,
                ApprovalAuditeeName: approvalAuditee ? approvalAuditeeName : '',
                ListPic: listPic,
                DetailPerbaikan: detail
            };

            if (editModePerbaikan) {
                // Pertahankan PerbaikanId item lama (kalau ada) - supaya histori Progress yang
                // sudah terikat ke PerbaikanId ini tetap nyambung saat disimpan ulang ke server,
                // bukan malah dianggap entry baru & entry lamanya coba dihapus (bentrok FK).
                var oldItem = perbaikanListTemp[editModePerbaikan.index];
                if (oldItem && oldItem.PerbaikanId) {
                    item.PerbaikanId = oldItem.PerbaikanId;
                }
                perbaikanListTemp[editModePerbaikan.index] = item;
            } else {
                perbaikanListTemp.push(item);
            }

            resetFormPerbaikan();
            renderPerbaikanTemp();
            updateFieldFilledIndicators();
        });        

        $(document).off('click', '.temuan-field-btn').on('click', '.temuan-field-btn', function(){
            var newField = $(this).data('field');
            if (newField === activeTemuanField) return;

            if (fieldMenuMode === 'view') {
                $('.temuan-field-btn').removeClass('active');
                $(this).addClass('active');
                activeTemuanField = newField;
                renderFieldMenuViewer();
                return;
            }

            if (newField !== 'TindakLanjut' && activeTemuanField !== 'TindakLanjut' && !editorReady) return;

            if (activeTemuanField !== 'Kerugian' && activeTemuanField !== 'TindakLanjut') {
                saveActiveTemuanField();
            }
            updateFieldFilledIndicators();

            $('.temuan-field-btn').removeClass('active');
            $(this).addClass('active');

            var editor = tinymce.get('txtTemuanEditor');

            if (newField === 'Kerugian') {
                if (editor) editor.hide();
                $('#txtTemuanEditor').hide();
                $('#wrapKerugianInline').show();
                $('#wrapTindakLanjutPerbaikan').hide();
            } else if (newField === 'TindakLanjut') {
                if (editor) editor.hide();
                $('#txtTemuanEditor').hide();
                $('#wrapKerugianInline').hide();
                $('#wrapTindakLanjutPerbaikan').show();
                initPerbaikanEditor();
                renderPerbaikanTemp();
            } else {
                $('#wrapKerugianInline').hide();
                $('#wrapTindakLanjutPerbaikan').hide();
                if (editor) {
                    editor.show();
                    editor.setContent(textToEditorHtml($(temuanFieldMap[newField]).val()));
                    if (editor.getBody()) { editor.getBody().scrollTop = 0; }
                }
            }

            activeTemuanField = newField;
        });

        // Tambah lampiran ke temp list (untuk temuan yang sedang diisi)
        /* ==== LAMPIRAN TEMUAN DINONAKTIFKAN - uncomment untuk restore ====
        // Tambah lampiran ke temp list (untuk temuan yang sedang diisi)
        $('#btnAddLampiranTemuan').click(function(){
            var namaFile     = $('#txtNamaFileLampiranTemuan').val();
            var fileLampiran = $('#hdnFileLampiranTemuanUploaded').val();

            if (!fileLampiran) {
                alertBox('show', { id: 'frmTemuanAlert', msg: 'Pilih file terlebih dahulu', mode: 'warning' });
                return;
            }

            lampiranTemuanTemp.push({ NamaFile: namaFile, FileLampiran: fileLampiran });

            $('#txtNamaFileLampiranTemuan').val('');
            $('#hdnFileLampiranTemuanUploaded').val('');
            $('#txtFileLampiranTemuan').fileinput('clear');

            renderLampiranTemuanTemp();
        });

        window.deleteLampiranTemuanTemp = function(index) {
            var item = lampiranTemuanTemp[index];
            if (item && item.isExisting && item.LampiranId) {
                deletedLampiranIds.push(item.LampiranId);
            }
            lampiranTemuanTemp.splice(index, 1);
            renderLampiranTemuanTemp();
        };

        function renderLampiranTemuanTemp() {
            var html = '<table class="table table-bordered table-condensed" style="margin-top:5px; width:100%; table-layout: fixed;">';
            html += '<thead><tr>' +
                '<th style="text-align:center; width: 30%;">Keterangan</th>' +
                '<th style="text-align:center; width: 55%;">File</th>' +
                '<th style="text-align:center; width: 15%;">Aksi</th>' +
                '</tr></thead><tbody>';

            if (lampiranTemuanTemp.length === 0) {
                html += '<tr><td colspan="3" style="text-align:center; color:#999;">Belum ada lampiran</td></tr>';
            } else {
                $.each(lampiranTemuanTemp, function(i, item) {
                    var fileUrl = item.isExisting
                        ? "{{ ENV('ASSET_FILE') }}netfile/InternalAudit/" + item.FileLampiran
                        : "/upload/temp/" + item.FileLampiran;
                    html += '<tr>';
                    html += '<td style="word-wrap: break-word; vertical-align: middle; text-align: center;">' + (item.NamaFile || '-') + '</td>';
                    html += '<td style="vertical-align: middle; text-align: center;">' +
                            '<div style="display: inline-flex; align-items: center; gap: 5px; text-align: left;">' +
                                '<button type="button" onclick="window.open(\'' + fileUrl + '\', \'_blank\')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:#0066CC; flex-shrink: 0;">' +
                                '<i class="fa fa-eye"></i></button>' +
                                '<span style="word-break: break-all; line-height: 1.4; font-size: 11px; color: #555;">' + item.FileLampiran + '</span>' +
                            '</div></td>';
                    html += '<td style="vertical-align: middle; text-align: center;"><button type="button" onclick="deleteLampiranTemuanTemp(' + i + ')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:red;"><i class="fa fa-trash"></i></button></td>';
                    html += '</tr>';
                });
            }
            html += '</tbody></table>';
            $('#tblLampiranTemuanTemp').html(html);
        }
        ==== END LAMPIRAN TEMUAN ==== */

        function resetFormTemuan() {
            exitFieldMenuViewMode();
            $('#txtJudulTemuan, #txtDetailTemuan, #txtIndikasiAwal, #txtResiko, #txtPeraturanSOP, #txtSanksiKaryawan, #txtSanksiAtasan, #txtRekomendasiAuditor, #txtPengembalianKerugian').val('');
            setMoney('txtKerugian', 0);
            perbaikanListTemp = [];
            resetFormPerbaikan();
            renderPerbaikanTemp();
            lampiranTemuanTemp = [];
            deletedLampiranIds = [];
            // renderLampiranTemuanTemp(); // Lampiran Temuan dinonaktifkan, uncomment untuk restore
            loadFraudInputsFromList(); // perbaikanListTemp sudah [] di titik ini -> input Fraud ikut kosong

            // Reset menu field ke Judul Temuan & kosongkan editor
            $('.temuan-field-btn').removeClass('active');
            $('.temuan-field-btn[data-field="JudulTemuan"]').addClass('active');
            activeTemuanField = 'JudulTemuan';
            $('#wrapKerugianInline').hide();
            $('#wrapDeadlineInline').hide();
            var editor = tinymce.get('txtTemuanEditor');
            if (editor) {
                editor.show();
                editor.setContent('');
                if (editor.getBody()) { editor.getBody().scrollTop = 0; }
            }

            editMode = null;
            updateTambahTemuanButtonLabel();
            updateFieldFilledIndicators();
        }

        function updateTambahTemuanButtonLabel() {
            if (editMode) {
                $('#btnAddTemuan').html('<i class="fa fa-save" style="color:#28a745;"></i>&nbsp;Update Temuan');
                $('#btnAddTemuan').show().css({ 'border-left': '0', 'border-radius': '0 3px 3px 0' });
                $('#btnClearTemuan').hide();
                $('#btnCancelTemuan').show();
            } else if (fieldMenuMode === 'view') {
                $('#btnAddTemuan').hide();
                $('#btnCancelTemuan').hide();
                $('#btnClearTemuan').show().css({ 'border-radius': '3px' });
            } else {
                $('#btnAddTemuan').html('<i class="fa fa-plus" style="color:#28a745;"></i>&nbsp;Tambah Temuan');
                $('#btnAddTemuan').show().css({ 'border-left': '1px solid #28a745', 'border-radius': '3px' });
                $('#btnCancelTemuan').hide();
                $('#btnClearTemuan').hide();
            }
        }

        function updateFieldFilledIndicators() {
            $.each(temuanFieldMap, function(field, selector) {
                var val = $(selector).val();
                // val bisa berisi HTML (mis. "<br>" doang), jadi cek berdasarkan text hasil strip tag
                var plainCheck = $('<div>').html(val || '').text();
                var hasValue = !!(plainCheck && plainCheck.trim() !== '');
                $('#grpTemuanFieldMenu .temuan-field-btn[data-field="' + field + '"] .field-filled-indicator').toggleClass('show', hasValue);
            });
            var kerugianVal = convertMoney($('#txtKerugian').val()) || 0;
            $('#grpTemuanFieldMenu .temuan-field-btn[data-field="Kerugian"] .field-filled-indicator').toggleClass('show', kerugianVal > 0);
            var tlFilled = isFraudJenis() ? !!$.trim($('#txtDeadlineFraud').val() || '') : perbaikanListTemp.length > 0;
            $('#grpTemuanFieldMenu .temuan-field-btn[data-field="TindakLanjut"] .field-filled-indicator').toggleClass('show', tlFilled);
        }  

        renderGrid('grdTemuanTemp', {
            toolbar       : false,
            btnDefault    : { add: false, delete: false, edit: false, upload: false, view: false },
            autoLoad      : false,
            pagination    : true,
            pageSize      : 5,
            pageList      : [5, 10, 20],
            showFilterBar : false,
            minHeight     : 150,
            title         : 'Temuan Baru :',
            rownumbers    : false,
            fitColumns    : true,
            columns : [[
                { field: 'No', title: 'No', width: 50, align: 'center', valign: 'middle', formatter: function(value, row, index) {
                    var opts = $('#grdTemuanTemp').datagrid('options');
                    var pageNum  = opts.pageNumber || 1;
                    var pageSize = opts.pageSize || 5;
                    return (pageNum - 1) * pageSize + index + 1;
                }},
                { field: 'JudulTemuan', title: 'Judul Temuan', align: 'center', valign: 'middle' },
                { field: 'PicAuditeeList', title: '<table class="pdp-header-table"><colgroup><col style="width:54%"><col></colgroup><tr><td class="pdp-pic">PIC Auditee</td><td class="pdp-dl">Deadline</td></tr></table>', align: 'center', width: 280, sortable: false, formatter: formatPicDeadlinePairCell },
                /* { field: 'JumlahLampiran', title: 'Lampiran', align: 'center', width: 90 }, */
                { field: 'Aksi', title: 'Aksi', align: 'center', width: 90, formatter: function(value, row, index) {
                    var opts = $('#grdTemuanTemp').datagrid('options');
                    var pageNum  = opts.pageNumber || 1;
                    var pageSize = opts.pageSize || 5;
                    var realIndex = (pageNum - 1) * pageSize + index;
                    return '<button type="button" onclick="event.stopPropagation(); editTemuanTemp(' + realIndex + ')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:#f0ad4e; margin-right:4px;"><i class="fa fa-pencil"></i></button>' +
                        '<button type="button" onclick="event.stopPropagation(); deleteTemuanTemp(' + realIndex + ')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:red;"><i class="fa fa-trash"></i></button>';
                }}
            ]],
            filters: [],
            onLoadSuccess: function() {
                $('#grdTemuanTemp .datagrid-body td').css('vertical-align', 'middle');
            },
            onClickRow: function(index, row) {
                var opts = $('#grdTemuanTemp').datagrid('options');
                var pageNum  = opts.pageNumber || 1;
                var pageSize = opts.pageSize || 5;
                var realIndex = (pageNum - 1) * pageSize + index;
                var item = temuanList[realIndex];
                if (!item) return;
                showTemuanReadonly(item);
            }
        });

        $('#btnAddTemuan').click(function(){
            addTemuanFromForm();
        });

        $('#btnCancelTemuan').click(function(){
            resetFormTemuan();
        });

        $('#btnClearTemuan').click(function(){
            resetFormTemuan();
        });

        // Tambah temuan ke temp list (belum disimpan ke DB, nunggu Save Hdr)
        function addTemuanFromForm() {
            if (activeTemuanField !== 'Kerugian' && activeTemuanField !== 'TindakLanjut') {
                saveActiveTemuanField();
            }

            var judulTemuan = $('#txtJudulTemuan').val();
            if (!judulTemuan) {
                alertBoxAuto({ id: 'frmTemuanAlert', msg: 'Judul Temuan wajib diisi', mode: 'warning' });
                return;
            }

            if (isFraudJenis() && !$('#txtDeadlineFraud').val()) {
                forceSwitchTemuanField('TindakLanjut');
                alertBoxAuto({ id: 'frmTemuanAlert', msg: 'Deadline wajib diisi', mode: 'warning' });
                return;
            }

            var fieldData = {
                JudulTemuan          : judulTemuan,
                DetailTemuan         : $('#txtDetailTemuan').val(),
                IndikasiAwal         : $('#txtIndikasiAwal').val(),
                Resiko               : $('#txtResiko').val(),
                Kerugian             : convertMoney($('#txtKerugian').val()) || 0,
                PeraturanSOP         : $('#txtPeraturanSOP').val(),
                SanksiKaryawan       : $('#txtSanksiKaryawan').val(),
                SanksiAtasan         : $('#txtSanksiAtasan').val(),
                RekomendasiAuditor   : $('#txtRekomendasiAuditor').val(),
                PengembalianKerugian : $('#txtPengembalianKerugian').val(),
                PerbaikanList        : buildPerbaikanListForSave()
            };

            // Mode tambah baru (belum pernah edit apa pun)
            if (!editMode) {
                //fieldData.Lampiran = lampiranTemuanTemp.slice();
                fieldData.Lampiran = []; // Lampiran Temuan dinonaktifkan, dulunya: lampiranTemuanTemp.slice()
                temuanList.push(fieldData);
                resetFormTemuan();
                renderTemuanTemp();
                return;
            }

            // Mode edit item di grdTemuanTemp (belum tersimpan di DB)
            if (editMode.type === 'temp') {
                //fieldData.Lampiran = lampiranTemuanTemp.slice();
                fieldData.Lampiran = []; // Lampiran Temuan dinonaktifkan, dulunya: lampiranTemuanTemp.slice()
                temuanList[editMode.index] = fieldData;
                resetFormTemuan();
                renderTemuanTemp();
                return;
            }

            // Mode edit item di grdTemuanDb (sudah tersimpan di DB) -> update ke server
            if (editMode.type === 'db') {
                /* ==== LAMPIRAN TEMUAN DINONAKTIFKAN - uncomment untuk restore ====
                var newLampiran = $.grep(lampiranTemuanTemp, function(l){ return !l.isExisting; });
                var namaFileList     = $.map(newLampiran, function(l){ return l.NamaFile; });
                var fileLampiranList = $.map(newLampiran, function(l){ return l.FileLampiran; });
                ==== END LAMPIRAN TEMUAN ==== */

                ajax({
                    url      : '{{ url("audit/transactions/audit-list/update-temuan") }}',
                    postData : $.extend({}, fieldData, {
                        DtlId         : editMode.dtlId,
                        PerbaikanList : JSON.stringify(fieldData.PerbaikanList || [])
                        /* , NamaFileList        : JSON.stringify(namaFileList),
                        FileLampiranList    : JSON.stringify(fileLampiranList),
                        DeletedLampiranIds  : JSON.stringify(deletedLampiranIds) */
                    }),
                    blockId  : 'dlgInternalAudit',
                    alertId  : 'frmAuditAlert',
                    success  : function(ret) {
                        if (!ret.result) {
                            alertBoxAuto({ id: 'frmAuditAlert', msg: ret.msg, mode: 'error' });
                            return;
                        }
                        resetFormTemuan();
                        $('#grdTemuanDb').datagrid('reload');
                        alertBoxAuto({ id: 'frmAuditAlert', msg: 'Temuan berhasil diupdate!', mode: 'success' });
                    }
                });
            }
        }

        window.deleteTemuanTemp = function(index) {
            temuanList.splice(index, 1);
            renderTemuanTemp();
        };

        window.editTemuanTemp = function(index) {
            var item = temuanList[index];
            if (!item) return;

            exitFieldMenuViewMode();
            $('#txtJudulTemuan').val(item.JudulTemuan || '');
            $('#txtDetailTemuan').val(item.DetailTemuan || '');
            $('#txtIndikasiAwal').val(item.IndikasiAwal || '');
            $('#txtResiko').val(item.Resiko || '');
            $('#txtPeraturanSOP').val(item.PeraturanSOP || '');
            $('#txtSanksiKaryawan').val(item.SanksiKaryawan || '');
            $('#txtSanksiAtasan').val(item.SanksiAtasan || '');
            $('#txtRekomendasiAuditor').val(item.RekomendasiAuditor || '');
            $('#txtPengembalianKerugian').val(item.PengembalianKerugian || '');
            setMoney('txtKerugian', item.Kerugian || 0);
            perbaikanListTemp = (item.PerbaikanList || []).slice();
            loadFraudInputsFromList();
            resetFormPerbaikan();

            /* ==== LAMPIRAN TEMUAN DINONAKTIFKAN - uncomment untuk restore ====
            lampiranTemuanTemp = $.map(item.Lampiran || [], function(l) {
                return { NamaFile: l.NamaFile, FileLampiran: l.FileLampiran, isExisting: false, LampiranId: null };
            });
            deletedLampiranIds = [];
            renderLampiranTemuanTemp();
            ==== END LAMPIRAN TEMUAN ==== */

            editMode = { type: 'temp', index: index };
            updateTambahTemuanButtonLabel();
            updateFieldFilledIndicators();

            $('.temuan-field-btn').removeClass('active');
            $('.temuan-field-btn[data-field="JudulTemuan"]').addClass('active');
            activeTemuanField = 'JudulTemuan';
            $('#wrapKerugianInline').hide();
            $('#wrapTindakLanjutPerbaikan').hide();
            var editor = tinymce.get('txtTemuanEditor');
            if (editor) {
                editor.show();
                editor.setContent(textToEditorHtml(item.JudulTemuan));
                if (editor.getBody()) { editor.getBody().scrollTop = 0; }
            }
        };

        window.editTemuanDb = function(dtlId) {
            var rows = $('#grdTemuanDb').datagrid('getRows');
            var row = $.grep(rows, function(r){ return r.DtlId == dtlId; })[0];
            if (!row) return;

            exitFieldMenuViewMode();
            $('#txtJudulTemuan').val(row.JudulTemuan || '');
            $('#txtDetailTemuan').val(row.DetailTemuan || '');
            $('#txtIndikasiAwal').val(row.IndikasiAwal || '');
            $('#txtResiko').val(row.Resiko || '');
            $('#txtPeraturanSOP').val(row.PeraturanSOP || '');
            $('#txtSanksiKaryawan').val(row.SanksiKaryawan || '');
            $('#txtSanksiAtasan').val(row.SanksiAtasan || '');
            $('#txtRekomendasiAuditor').val(row.RekomendasiAuditor || '');
            $('#txtPengembalianKerugian').val(row.PengembalianKerugian || '');
            setMoney('txtKerugian', row.Kerugian || 0);

            ajax({
                url      : '{{ url("audit/transactions/audit-list/get-perbaikan-list-array") }}',
                postData : { DtlId: dtlId },
                blockId  : 'dlgInternalAudit',
                success  : function(ret) {
                    perbaikanListTemp = (ret && ret.data) ? ret.data : [];
                    loadFraudInputsFromList();
                    resetFormPerbaikan();
                    if (activeTemuanField === 'TindakLanjut') renderPerbaikanTemp();
                    updateFieldFilledIndicators();
                }
            });

            /* ==== LAMPIRAN TEMUAN DINONAKTIFKAN - uncomment untuk restore ====
            deletedLampiranIds = [];
            lampiranTemuanTemp = [];
            renderLampiranTemuanTemp();

            ajax({
                url      : '{{ url("audit/transactions/audit-list/get-lampiran-list-array") }}',
                postData : { DtlId: dtlId },
                blockId  : 'dlgInternalAudit',
                success  : function(ret) {
                    var list = (ret && ret.data) ? ret.data : [];
                    lampiranTemuanTemp = $.map(list, function(l) {
                        return { NamaFile: l.NamaFile, FileLampiran: l.FileLampiran, isExisting: true, LampiranId: l.LampiranId };
                    });
                    renderLampiranTemuanTemp();
                }
            });
            ==== END LAMPIRAN TEMUAN ==== */

            editMode = { type: 'db', dtlId: dtlId };
            updateTambahTemuanButtonLabel();
            updateFieldFilledIndicators();

            $('.temuan-field-btn').removeClass('active');
            $('.temuan-field-btn[data-field="JudulTemuan"]').addClass('active');
            activeTemuanField = 'JudulTemuan';
            $('#wrapKerugianInline').hide();
            $('#wrapTindakLanjutPerbaikan').hide();
            var editor = tinymce.get('txtTemuanEditor');
            if (editor) {
                editor.show();
                editor.setContent(textToEditorHtml(row.JudulTemuan));
                if (editor.getBody()) { editor.getBody().scrollTop = 0; }
            }
        };

        function renderTemuanTemp() {
            if (!$('#grdTemuanTemp').data('datagrid')) return;
            var rows = $.map(temuanList, function(item) {
                var perbaikanSorted = (item.PerbaikanList || []).slice().sort(function(a, b) {
                    var da = a.Deadline ? new Date(a.Deadline).getTime() : 0;
                    var db = b.Deadline ? new Date(b.Deadline).getTime() : 0;
                    return da - db;
                });
                var deadlines = $.map(perbaikanSorted, function(p){ return p.Deadline || ''; });
                var picPerEntry = $.map(perbaikanSorted, function(p){
                    var names = $.map(p.ListPic || [], function(pic){ return pic.Name; });
                    return names.join(', ') || '-';
                });
                return { JudulTemuan: item.JudulTemuan, PicAuditeeList: picPerEntry.join('|'), DeadlineList: deadlines.join('|') /*, JumlahLampiran: item.Lampiran.length */ };
            });
            $('#grdTemuanTemp').datagrid('loadData', { total: rows.length, rows: rows });
        }

        // Grid temuan yang sudah tersimpan di DB (mode edit/view)
        renderGrid('grdTemuanDb', {
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
                { field: 'JudulTemuan', title: 'Judul Temuan', align: 'center', valign: 'middle' },
                { field: 'PicAuditeeList', title: '<table class="pdp-header-table"><colgroup><col style="width:54%"><col></colgroup><tr><td class="pdp-pic">PIC Auditee</td><td class="pdp-dl">Deadline</td></tr></table>', align: 'center', width: 280, sortable: false, formatter: formatPicDeadlinePairCell },
                /* { field: 'JumlahLampiran', title: 'Lampiran', align: 'center', width: 90 }, */
                { field: 'DtlId', title: 'Aksi', align: 'center', width: 90, formatter: function(value, row) {
                    var html = '<button type="button" onclick="event.stopPropagation(); editTemuanDb(' + value + ')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:#f0ad4e; margin-right:4px;"><i class="fa fa-pencil"></i></button>';
                    html += '<button type="button" onclick="event.stopPropagation(); deleteTemuanDb(' + value + ')" style="background:white; border:1px solid #ccc; border-radius:3px; cursor:pointer; color:red;"><i class="fa fa-trash"></i></button>';
                    return html;
                }}
            ]],
            filters: [],
            onLoadSuccess: function() {
                $('#grdTemuanDb .datagrid-body td').css('vertical-align', 'middle');
            },
            onClickRow: function(index, row) {
                if (!row || !row.DtlId) return;
                showTemuanReadonly(row);
            }
        });

        window.deleteTemuanDb = function(dtlId) {
            var rows = $('#grdTemuanDb').datagrid('getRows');
            var row = $.grep(rows, function(r){ return r.DtlId == dtlId; })[0];
            if (!row) return;
            showTemuanDetailPopup(row, dtlId);
        };

        window.deleteTemuanDb = function(dtlId) {
            if (!confirm('Hapus temuan ini beserta lampirannya?')) return;
            ajax({
                url      : '{{ url("audit/transactions/audit-list/delete-temuan") }}',
                postData : { DtlId: dtlId },
                blockId  : 'dlgInternalAudit',
                success  : function(ret) {
                    if (ret.result) {
                        $('#grdTemuanDb').datagrid('reload');
                    } else {
                        alertBoxAuto({ msg: ret.msg, mode: 'error' });
                    }
                }
            });
        };

        /* ==== LAMPIRAN TEMUAN DINONAKTIFKAN - uncomment untuk restore ====
        renderGrid('grdLampiranTemuanPopup', {
            url           : '{{ url("audit/transactions/audit-list/get-lampiran-list") }}',
            toolbar       : false,
            btnDefault    : { add: false, delete: false, edit: false },
            autoLoad      : false,
            pagination    : false,
            showFilterBar : false,
            minHeight     : 80,
            title         : '',
            rownumbers    : false,
            fitColumns    : true,
            columns : [[
                { field: 'No', title: 'No', width: 50, align: 'center', valign: 'middle', formatter: function(value, row, index) { return index + 1; }},
                { field: 'NamaFile', title: 'Keterangan' },
                { field: 'FileLampiran', title: 'File', formatter: function(value) {
                    if (!value) return '-';
                    var fileUrl = "{{ ENV('ASSET_FILE') }}netfile/InternalAudit/" + value;
                    return '<a href="javascript:void(0)" onclick="window.open(\'' + fileUrl + '\', \'_blank\')" style="color:#0066CC; text-decoration:underline;">' + value + '</a>';
                }}
            ]],
            filters: []
        });
        ==== END LAMPIRAN TEMUAN ==== */

        // Kunci / buka seluruh form Informasi Kondisi + Temuan. Saat readonly, panel Temuan masuk mode view
        // (bisa klik baris untuk melihat detail), tombol tambah/ubah/hapus disembunyikan lewat CSS .readonly-mode.
        function setHeaderReadonly(readonly) {
            $('#dlgInternalAudit').toggleClass('readonly-mode', readonly);
            $('#txtNoSuratTugas, #txtNoGaroon, #txtJudulKondisi').prop('readonly', readonly);
            $('#txtTanggalPemeriksaan, #txtTanggalUpload, #txtPeriodePemeriksaan, #cboJenisAudit, #cboApproval1, #cboApproval2, #cboDepartmentAudity, #cboListPic').prop('disabled', readonly);
            if ($.fn.iCheck) { $('#chkListPic').iCheck(readonly ? 'disable' : 'enable'); }
            $('#btnSaveInternalAudit').prop('disabled', readonly).css({ opacity: readonly ? 0.5 : 1, cursor: readonly ? 'not-allowed' : 'pointer' });

            if (readonly) {
                showTemuanReadonly({});
            } else if (fieldMenuMode === 'view') {
                resetFormTemuan();
            }
        }

        // Data baru (belum ada AuditId): siapa saja boleh. Data existing: hanya PIC Auditor di list
        // atau Approval 1 / Approval 2. Kunci dulu, buka kalau lolos pengecekan.
        function applyEditAccess(row) {
            if (!row.AuditId) {
                setHeaderReadonly(false);
                return;
            }

            var u = String(loginUserIdEdit || '').trim();
            var isApprover = !!u && (
                (row.ApproverUserId1 && String(row.ApproverUserId1).trim() === u) ||
                (row.ApproverUserId2 && String(row.ApproverUserId2).trim() === u)
            );
            if (isApprover) {
                setHeaderReadonly(false);
                return;
            }

            setHeaderReadonly(true);
            ajax({
                url      : '{{ url("audit/transactions/audit-list/get-pic-list-array") }}',
                postData : { AuditId: row.AuditId },
                blockId  : 'dlgInternalAudit',
                success  : function(ret) {
                    if (String($('#hdnAuditId').val()) !== String(row.AuditId)) return; // form sudah pindah ke data lain
                    var list = (ret && ret.data) ? ret.data : [];
                    var arrNik = $.map(list, function(item){ return String(item.NikId); });
                    var isPic = !!loginNikIdEdit && arrNik.indexOf(String(loginNikIdEdit)) !== -1;
                    if (isPic) {
                        setHeaderReadonly(false);
                    } else {
                        alertBoxAuto({ id: 'frmAuditAlert', msg: 'Anda hanya dapat melihat data ini. Perubahan hanya dapat dilakukan oleh PIC Auditor, Approval 1, atau Approval 2.', mode: 'warning' });
                    }
                }
            });
        }

        // Reset & load ketika buka form Kondisi (add / edit / view)
        window.showInternalAuditDetail = function(row, isEdit, isView) {
            row = row || {};
            temuanList = [];
            renderTemuanTemp();
            resetFormTemuan();

            $('#hdnAuditId').val(row.AuditId || '');
            $('#txtNoSuratTugas').val(row.NoSuratTugas || '');
            $('#txtNoGaroon').val(row.NoGaroon || '');
            setPeriode(row.PeriodeStart, row.PeriodeEnd);
            setTanggalPemeriksaan(row.TanggalPemeriksaan);
            setTanggalUpload(row.TanggalUpload);
            // Tanggal Upload hanya tampil kalau data sudah tersimpan (mode edit), tidak muncul di mode simpan baru
            $('#grpTanggalUpload').toggle(!!row.AuditId);
            $('#txtJudulKondisi').val(row.JudulKondisi || '');
            $('#cboDepartmentAudity').val(row.DepartmentAudity || '').trigger('change');
            jenisAuditLoading = true;
            $('#cboJenisAudit').val(row.JenisAudit || '').trigger('change');
            jenisAuditLoading = false;
            prevJenisAudit = $('#cboJenisAudit').val() || '';
            applyJenisAuditTindakLanjutUI();
            $('#cboApproval1').val(row.Approval1 || '').trigger('change');
            $('#cboApproval2').val(row.Approval2 || '').trigger('change');
            applyEditAccess(row);
            toggleDepartmentAudity();

            if (row.AuditId) {
                $('#wrapTemuanDb').show();
                setTimeout(function() {
                    if ($('#grdTemuanDb').data('datagrid')) {
                        $('#grdTemuanDb').datagrid('resize');
                    }
                    gridFilterData('grdTemuanDb', [{field: 'AuditId', value: row.AuditId}], {AuditId: row.AuditId});
                }, 100);

                setTimeout(function() {
                    if ($('#cboListPic').length === 0) return;
                    ajax({
                        url      : '{{ url("audit/transactions/audit-list/get-pic-list-array") }}',
                        postData : { AuditId: row.AuditId },
                        blockId  : 'dlgInternalAudit',
                        success  : function(ret) {
                            var list = (ret && ret.data) ? ret.data : [];
                            $.each(list, function(i, item) {
                                if ($('#cboListPic option[value="' + item.NikId + '"]').length === 0) {
                                    $('#cboListPic').append(new Option(item.Name, item.NikId, true, true));
                                }
                            });
                            var arrPic = $.map(list, function(item){ return item.NikId; });
                            $('#cboListPic').val(arrPic).trigger('change');
                        }
                    });
                }, 1000);
            } else {
                $('#wrapTemuanDb').hide();
                if ($('#grdTemuanDb').data('datagrid')) {
                    $('#grdTemuanDb').datagrid('loadData', { total: 0, rows: [] });
                }
                $('#cboListPic').val(null).trigger('change');
            }

            $('#frmAuditHeader').hide();
            $('#dlgInternalAudit').show();

            initTemuanEditor(function() {
                if (fieldMenuMode === 'view') {
                    var edReadonly = tinymce.get('txtTemuanEditor');
                    if (edReadonly) edReadonly.hide();
                    return;
                }
                var editor = tinymce.get('txtTemuanEditor');
                if (activeTemuanField === 'Kerugian') {
                    editor.hide();
                    $('#wrapKerugianInline').show();
                } else {
                    $('#wrapKerugianInline').hide();    
                    editor.show();
                    editor.setContent(textToEditorHtml($(temuanFieldMap[activeTemuanField]).val()));
                    if (editor.getBody()) { editor.getBody().scrollTop = 0; }
                }
            });

            setTimeout(function() {
                if ($('#grdTemuanTemp').data('datagrid')) {
                    $('#grdTemuanTemp').datagrid('resize');
                }
            }, 100);
        };

        $('#btnBackInternalAudit').click(function(){
            $('#dlgInternalAudit').hide();
            $('#frmAuditHeader').show();
            $('#grdInternalAudit').datagrid('reload');
        });

        $('#btnSaveInternalAudit').click(function(){
            saveInternalAudit();
        });

        function saveInternalAudit() {
            var judulKondisi = $('#txtJudulKondisi').val();
            if (!judulKondisi) {
                alertBoxAuto({ id: 'frmAuditAlert', msg: 'Judul Pemeriksaan wajib diisi', mode: 'warning' });
                return;
            }

            var jenisAudit = $('#cboJenisAudit').val();
            if (!jenisAudit) {
                alertBoxAuto({ id: 'frmAuditAlert', msg: 'Jenis Audit wajib diisi', mode: 'warning' });
                return;
            }

            if (periodeStart && periodeEnd && periodeEnd <= periodeStart) {
                alertBoxAuto({ id: 'frmAuditAlert', msg: 'Tanggal selesai Periode Pemeriksaan harus setelah tanggal mulai', mode: 'warning' });
                return;
            }

            var auditId = $('#hdnAuditId').val();
            var url = auditId
                ? '{{ url("audit/transactions/audit-list/update") }}'
                : '{{ url("audit/transactions/audit-list/save") }}';

            var arrListPic = [];
            $('#cboListPic').select2('data').forEach(function(item){
                arrListPic.push(item.id);
            });

            ajax({
                url      : url,
                postData : {
                    AuditId: auditId,
                    JudulKondisi: judulKondisi,
                    JenisAudit: jenisAudit,
                    DepartmentAudity: $('#cboDepartmentAudity').val(),
                    Approval1: $('#cboApproval1').val(),
                    Approval2: $('#cboApproval2').val(),
                    ListPic: JSON.stringify(arrListPic),
                    NoSuratTugas: $('#txtNoSuratTugas').val(),
                    NoGaroon: $('#txtNoGaroon').val(),
                    PeriodeStart: periodeStart,
                    PeriodeEnd: periodeEnd,
                    TanggalPemeriksaan: tglPemeriksaan,
                    TanggalUpload: tglUpload
                },
                blockId  : 'dlgInternalAudit',
                alertId  : 'frmAuditAlert',
                success  : function(ret) {
                    if (!ret.result) {
                        alertBoxAuto({ id: 'frmAuditAlert', msg: ret.msg, mode: 'error' });
                        return;
                    }

                    var savedAuditId = ret.AuditId || auditId;

                    if (temuanList.length === 0) {
                        finishSave();
                        return;
                    }

                    saveTemuanSequential(0, savedAuditId);
                }
            });

            // Simpan Temuan baru satu-per-satu pakai helper ajax() yang sama dengan update-temuan.
            // Sebelumnya pakai $.ajax mentah + $.when(...).always() yang SELALU lanjut ke "berhasil
            // disimpan" walau salah satu request gagal di background - itu penyebab Total Temuan
            // tetap 0 & Created By kosong walau baris di grid sudah ditambahkan sebelum Save.
            function saveTemuanSequential(index, savedAuditId) {
                if (index >= temuanList.length) {
                    finishSave();
                    return;
                }

                var item = temuanList[index];

                ajax({
                    url      : '{{ url("audit/transactions/audit-list/save-temuan") }}',
                    postData : {
                        AuditId: savedAuditId,
                        JudulTemuan: item.JudulTemuan,
                        DetailTemuan: item.DetailTemuan,
                        IndikasiAwal: item.IndikasiAwal,
                        Resiko: item.Resiko,
                        Kerugian: item.Kerugian,
                        PeraturanSOP: item.PeraturanSOP,
                        SanksiKaryawan: item.SanksiKaryawan,
                        SanksiAtasan: item.SanksiAtasan,
                        RekomendasiAuditor: item.RekomendasiAuditor,
                        PengembalianKerugian: item.PengembalianKerugian,
                        PerbaikanList: JSON.stringify(item.PerbaikanList || [])
                    },
                    blockId  : 'dlgInternalAudit',
                    alertId  : 'frmAuditAlert',
                    success  : function(retTemuan) {
                        if (!retTemuan.result) {
                            alertBoxAuto({ id: 'frmAuditAlert', msg: 'Gagal menyimpan Temuan "' + (item.JudulTemuan || '') + '": ' + retTemuan.msg, mode: 'error' });
                            return;
                        }
                        saveTemuanSequential(index + 1, savedAuditId);
                    }
                });
            }

            function finishSave() {
                temuanList = [];
                alertBoxAuto({ msg: 'Data berhasil disimpan!', mode: 'success' });
                setTimeout(function() {
                    $('#dlgInternalAudit').hide();
                    $('#frmAuditHeader').show();
                    $('#grdInternalAudit').datagrid('reload');
                }, 1000);
            }
        }

});
</script>

<div id="dlgInternalAudit" style="display: none;">
    <div class="box">

        <div style="position: absolute; top: 10px; left: 10px; z-index: 100; display: flex; gap: 0;">
            <button type="button" id="btnBackInternalAudit" style="background-color: white; color: #0066CC; border: 1px solid #ccc; padding: 6px 12px; border-radius: 3px 0 0 3px; cursor: pointer; margin: 0;">
                <i class="fa fa-arrow-circle-o-left text-blue"></i>&nbsp;Back
            </button>
            <button type="button" id="btnSaveInternalAudit" style="background-color: white; color: #0066CC; border: 1px solid #ccc; padding: 6px 12px; border-radius: 0 3px 3px 0; cursor: pointer; margin: 0; border-left: 0;">
                <i class="fa fa-save" style="color: #0066CC;"></i>&nbsp;Save
            </button>
        </div>

        <div class="box-header" style="padding-top: 50px;"></div>

        <div class="modal-body">
            <div id="frmAuditAlert"></div>

            <div class="form-horizontal">
                <input type="hidden" id="hdnAuditId" value=""/>


                <div class="frame">
                    <div class="frame-header"><strong>Informasi Kondisi</strong></div><br>
                    <div class="row">

                        {{-- Kolom kiri --}}
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="col-sm-4">No Surat Tugas</label>
                                <div class="col-sm-8">
                                    <input type="text" id="txtNoSuratTugas" placeholder="No Surat Tugas..."/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4">No Garoon</label>
                                <div class="col-sm-8">
                                    <input type="text" id="txtNoGaroon" placeholder="No Garoon..."/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 mandatory">Judul Pemeriksaan</label>
                                <div class="col-sm-8">
                                    <input type="text" id="txtJudulKondisi" placeholder="Judul Pemeriksaan..."/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 mandatory">Jenis Audit</label>
                                <div class="col-sm-8">
                                    <select id="cboJenisAudit" name="JenisAudit" formatter="combo-array" style="display:none;">
                                        <option value="Pemeriksaan Reguler">Pemeriksaan Reguler</option>
                                        <option value="Fraud / Spesial Audit">Fraud / Spesial Audit</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4" id="lblListPic" style="display:none;">
                                    <label>
                                    PIC Auditor
                                        <input id="chkListPic" type="checkbox" formatter="cr-box">
                                    </label>
                                </label>
                                <div class="col-sm-8" id="wrapListPic" style="display:none;">
                                    <select id="cboListPic" name="ListPic" formatter="combo-ajax"></select>
                                </div>
                            </div>
                        </div>

                        {{-- Kolom kanan --}}
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="col-sm-4">Periode Pemeriksaan</label>
                                <div class="col-sm-8">
                                    <div class="input-group">
                                        <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                        <input type="text" id="txtPeriodePemeriksaan" placeholder="Periode Pemeriksaan..." readonly/>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4">Tanggal Pemeriksaan</label>
                                <div class="col-sm-8" id="wrapTanggalPemeriksaan">
                                    <div class="input-group">
                                        <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                        <input type="text" id="txtTanggalPemeriksaan" placeholder="Tanggal Pemeriksaan..." readonly/>
                                    </div>
                                </div>
                            </div>
                            {{-- Tanggal Upload: hanya tampil di mode edit (data sudah tersimpan), diatur lewat JS di showInternalAuditDetail --}}
                            <div class="form-group" id="grpTanggalUpload" style="display:none;">
                                <label class="col-sm-4">Tanggal Upload</label>
                                <div class="col-sm-8" id="wrapTanggalUpload">
                                    <div class="input-group">
                                        <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                        <input type="text" id="txtTanggalUpload" placeholder="Tanggal Upload..." readonly/>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4">Approval 1</label>
                                <div class="col-sm-8">
                                    <select id="cboApproval1" name="Approval1" formatter="combo-ajax"></select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4" id="lblApproval2">Approval 2</label>
                                <div class="col-sm-8">
                                    <select id="cboApproval2" name="Approval2" formatter="combo-ajax"></select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 mandatory" id="lblDepartmentAudity" style="display:none;">Department Auditee</label>
                                <div class="col-sm-8" id="wrapDepartmentAudity" style="display:none;">
                                    <select id="cboDepartmentAudity" name="DepartmentAudity" formatter="combo-ajax"></select>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Temuan tersimpan di DB (mode edit/view) --}}
                <div class="frame" id="wrapTemuanDb" style="display:none;">
                    <div class="frame-header"><strong>Daftar Temuan Tersimpan</strong></div>
                    <div class="row">
                        <div class="col-md-12">
                            <div id="grdTemuanDb"></div>
                        </div>
                    </div>
                </div>

                {{-- Tambah Temuan --}}
                <div class="frame" id="frameTambahTemuan">
                    <div class="frame-header"><strong>Tambah Temuan</strong></div>
                    <div id="frmTemuanAlert"></div>
                    {{-- Temuan baru yang sudah ditambahkan (belum di-save) --}}
                    <div class="row">
                        <div class="col-sm-12">
                            <div id="grdTemuanTemp"></div>
                        </div>
                    </div>
                    <!-- <hr style="border: none; border-top: 1px solid #ccc;"> -->              
                    <div class="temuan-field-wrap">
                        <div class="temuan-field-menu" id="grpTemuanFieldMenu">
                            <button type="button" class="temuan-field-btn active" data-field="JudulTemuan">Judul Temuan <span class="text-danger">*</span><span class="field-filled-indicator">★</span></button>
                            <button type="button" class="temuan-field-btn" data-field="DetailTemuan">Detail Temuan<span class="field-filled-indicator">★</span></button>
                            <button type="button" class="temuan-field-btn" data-field="IndikasiAwal">Indikasi Awal & Bukti / Penyebab<span class="field-filled-indicator">★</span></button>
                            <button type="button" class="temuan-field-btn" data-field="Resiko">Resiko<span class="field-filled-indicator">★</span></button>
                            <button type="button" class="temuan-field-btn" data-field="Kerugian">Kerugian Perusahaan<span class="field-filled-indicator">★</span></button>
                            <button type="button" class="temuan-field-btn" data-field="PeraturanSOP">Peraturan / SOP Perusahaan yang Dilanggar<span class="field-filled-indicator">★</span></button>
                            <button type="button" class="temuan-field-btn" data-field="SanksiKaryawan">Jenis Sanksi Berdasar PP & SOP (Karyawan)<span class="field-filled-indicator">★</span></button>
                            <button type="button" class="temuan-field-btn" data-field="SanksiAtasan">Jenis Sanksi Berdasar PP & SOP (Atasan)<span class="field-filled-indicator">★</span></button>
                            <button type="button" class="temuan-field-btn" data-field="TindakLanjut"><span class="tl-label">Tindak Lanjut / Perbaikan</span><span class="field-filled-indicator">★</span></button>
                            <button type="button" class="temuan-field-btn" data-field="RekomendasiAuditor">Rekomendasi Auditor<span class="field-filled-indicator">★</span></button>
                            <button type="button" class="temuan-field-btn" data-field="PengembalianKerugian">Pengembalian Kerugian Perusahaan<span class="field-filled-indicator">★</span></button>                            
                        </div>
                        <div class="temuan-field-editor">
                            <textarea id="txtTemuanEditor"></textarea>

                            <div id="viewerTemuanField"></div>

                            <div id="wrapKerugianInline" style="display:none;">
                                <div class="input-group">
                                    <span class="input-group-addon">Rp</span>
                                    <input type="text" id="txtKerugian" formatter="money"/>
                                </div>
                            </div>

                            <div id="wrapTindakLanjutPerbaikan" style="display:none; padding:10px;">
                                <div id="wrapPerbaikanForm">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label>Action</label>
                                            <select id="cboActionPerbaikan" formatter="combo-array" style="display:none;">
                                                <option value="Corrective Action">Corrective Action</option>
                                                <option value="Preventive Action">Preventive Action</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label>Deadline</label>
                                            <input type="text" id="txtDeadlinePerbaikan" formatter="date"/>
                                        </div>
                                        <div class="col-md-3">
                                            <label>Approval Auditee</label>
                                            <select id="cboApprovalAuditee" name="ApprovalAuditee" formatter="combo-ajax"></select>
                                        </div>
                                        <div class="col-md-3">
                                            <label>PIC Auditee</label>
                                            <select id="cboListPicPerbaikan" formatter="combo-ajax" multiple="multiple"></select>
                                        </div>
                                    </div>
                                    <div style="margin-top:10px;">
                                        <label>Detail Tindak Lanjut / Perbaikan</label>
                                        <textarea id="txtDetailPerbaikan"></textarea>
                                    </div>
                                    <div style="display:flex; justify-content:flex-end; margin-top:8px;">
                                        <button type="button" id="btnAddPerbaikan" style="background-color: white; color: #0066CC; border: 1px solid #ccc; padding: 6px 12px; border-radius: 3px; cursor: pointer;">
                                            <i class="fa fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div id="tblPerbaikanTemp" style="margin-top:10px;"></div>

                                {{-- Khusus Jenis Audit = Fraud / Spesial Audit: hanya 1 Deadline + 1 Catatan per Temuan --}}
                                <div id="wrapDeadlineFraud" style="display:none;">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label>Deadline <span class="text-danger">*</span></label>
                                            <input type="text" id="txtDeadlineFraud" formatter="date"/>
                                        </div>
                                    </div>
                                    <div style="margin-top:10px;">
                                        <label>Detail Tindak Lanjut / Perbaikan</label>
                                        <textarea id="txtCatatanFraud"></textarea>
                                    </div>
                                </div>
                            </div>

                            {{-- Penyimpanan internal value asli tiap field, id dipertahankan agar kompatibel dgn JS lain --}}
                            <input type="hidden" id="txtJudulTemuan" value=""/>
                            <input type="hidden" id="txtDetailTemuan" value=""/>
                            <input type="hidden" id="txtIndikasiAwal" value=""/>
                            <input type="hidden" id="txtResiko" value=""/>
                            <input type="hidden" id="txtPeraturanSOP" value=""/>
                            <input type="hidden" id="txtSanksiKaryawan" value=""/>
                            <input type="hidden" id="txtSanksiAtasan" value=""/>
                            <input type="hidden" id="txtRekomendasiAuditor" value=""/>
                            <input type="hidden" id="txtPengembalianKerugian" value=""/>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; margin-top:10px;">
                        <button type="button" id="btnCancelTemuan" style="display:none; background-color: white; color: #28a745; border: 1px solid #28a745; padding: 6px 12px; border-radius: 3px 0 0 3px; cursor: pointer; margin: 0;">
                            <i class="fa fa-times" style="color: #28a745;"></i>&nbsp;Cancel
                        </button>
                        <button type="button" id="btnClearTemuan" style="display:none; background-color: white; color: #28a745; border: 1px solid #28a745; padding: 6px 12px; border-radius: 3px 0 0 3px; cursor: pointer; margin: 0;">
                            <i class="fa fa-refresh" style="color: #28a745;"></i>&nbsp;Clear
                        </button>
                        <button type="button" id="btnAddTemuan" style="background-color: white; color: #28a745; border: 1px solid #28a745; padding: 6px 12px; border-radius: 3px; cursor: pointer; margin: 0;">
                            <i class="fa fa-plus" style="color: #28a745;"></i>&nbsp;Tambah Temuan
                        </button>
                    </div>

                </div>

                {{-- Lampiran Temuan --}}
                <!-- ==== LAMPIRAN TEMUAN DINONAKTIFKAN - uncomment untuk restore ====
                <div class="frame">
                    <div class="frame-header"><strong>Lampiran Temuan</strong></div><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="col-sm-4">Nama File</label>
                                <div class="col-sm-8"><input type="text" id="txtNamaFileLampiranTemuan" placeholder="Keterangan file..."/></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="col-sm-4">File</label>
                                <div class="col-sm-8">
                                    <div class="input-group">
                                        <input type="file" id="txtFileLampiranTemuan" name="txtFileLampiranTemuan"/>
                                        <input type="hidden" id="hdnFileLampiranTemuanUploaded" value=""/>
                                        <span class="input-group-btn" style="vertical-align: top;">
                                            <button type="button" id="btnAddLampiranTemuan" style="background-color: white; color: #0066CC; border: 1px solid #ccc; padding: 6px 12px; border-radius: 3px; cursor: pointer; margin-top: 0; vertical-align: top;">
                                                <i class="fa fa-plus" style="color: #0066CC;"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-12">
                            <div id="tblLampiranTemuanTemp"></div>
                        </div>
                    </div>
                </div>
                ==== END LAMPIRAN TEMUAN ==== -->

            </div>
        </div>

    </div>
</div>