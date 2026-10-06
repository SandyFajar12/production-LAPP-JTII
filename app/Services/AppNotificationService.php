<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AppNotificationService
{
    /**
     * Sesuai App\Http\Controllers\Controller: $this->checkUser = Auth::User()->UserName.
     * Service ini pakai sumber yang identik supaya konsisten dengan seluruh
     * codebase, tanpa perlu lewat Controller apapun.
     */
    private function currentUserName()
    {
        $user = Auth::user();
        return $user ? $user->UserName : null;
    }

    /**
     * Dipakai untuk exclude-self notification (contoh: Internal Audit Fraud/Spesial
     * Audit di halaman Auditor). ASUMSI: $this->editBy pada Controller == Auth::user()->UserId
     * -> tolong dikonfirmasi & disesuaikan kalau ternyata beda.
     */
    private function currentUserId()
    {
        $user = Auth::user();
        return $user ? $user->UserId : null;
    }

    // =========================================================================
    // ============================ START: KJPP APPRAISAL ========================
    // Sumber data  : TrnKJPPAppraisalLampiran (Team: Appraisal/Lelang)
    // Status baca  : TrnNotifikasi (Module = KJPP_APPRAISAL, RefId = AppraisalId)
    // manggil langsung ke route generic yang manggil function di sini.
    // =========================================================================

    const KJPP_MODULE = 'KJPP_APPRAISAL';

    public function kjppAppraisalCount()
    {
        $userName = $this->currentUserName();
        if (!$userName) {
            return response()->json(['countNotif' => 0]);
        }

        $count = $this->kjppAppraisalNewItemsQuery($userName)->count();

        return response()->json(['countNotif' => $count]);
    }

    public function kjppAppraisalItems()
    {
        $userName = $this->currentUserName();
        if (!$userName) {
            return response()->json(['items' => []]);
        }

        $rows = $this->kjppAppraisalNewItemsQuery($userName)
            ->orderBy('lampiran_latest.CreatedAt', 'desc')
            ->limit(20)
            ->get();

        $items = $rows->map(function ($row) {
            $message = ($row->Team === 'Lelang')
                ? 'Lampiran Lelang diperbarui'
                : 'Lampiran Appraisal diperbarui';

            return [
                'AppraisalId' => $row->AppraisalId,
                'title'       => 'Appraisal Data',
                'message'     => $message,
                'reference'   => trim(($row->ItemNo ?: '-') . ' - ' . ($row->NamaDebitur ?: '-'), ' -'),
                'created_at'  => $row->CreatedAt,
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    public function kjppAppraisalMarkReadOne($appraisalId)
    {
        $userName = $this->currentUserName();
        if (!$userName || !$appraisalId) {
            return response()->json(['result' => false]);
        }

        DB::table('TrnNotifikasi')->updateOrInsert(
            ['UserId' => $userName, 'Module' => self::KJPP_MODULE, 'RefId' => $appraisalId],
            ['LastReadAt' => DB::raw('now()')]
        );

        return response()->json(['result' => true, 'AppraisalId' => $appraisalId]);
    }

    public function kjppAppraisalMarkReadMany()
    {
        $userName = $this->currentUserName();
        if (!$userName) {
            return response()->json(['ids' => []]);
        }

        $ids = $this->kjppAppraisalNewItemsQuery($userName)
            ->get(['lampiran_latest.AppraisalId'])
            ->pluck('AppraisalId')
            ->unique()
            ->values();

        $now = now();

        foreach ($ids as $id) {
            DB::table('TrnNotifikasi')->updateOrInsert(
                ['UserId' => $userName, 'Module' => self::KJPP_MODULE, 'RefId' => $id],
                ['LastReadAt' => DB::raw('now()')]
            );
        }

        return response()->json(['ids' => $ids]);
    }

    private function kjppAppraisalNewItemsQuery($userName)
    {
        $appraisalSrc = '
            (SELECT DISTINCT ON (la."AppraisalId")
                la."AppraisalId" as "AppraisalId",
                la."Team" as "Team",
                la."CreatedAt" as "CreatedAt"
            FROM "TrnKJPPAppraisalLampiran" la
            WHERE la."Team" = \'Appraisal\'
            ORDER BY la."AppraisalId", la."CreatedAt" DESC)
        ';

        $lelangSrc = '
            (SELECT DISTINCT ON (la."AppraisalId")
                ka."AppraisalId" as "AppraisalId",
                la."Team" as "Team",
                la."CreatedAt" as "CreatedAt"
            FROM "TrnKJPPAppraisalLampiran" la
            JOIN "TrnAuctionLelangHdr" h ON h."DataNo" = la."AppraisalId"
            JOIN "TrnAuctionLelangDtl" d ON d."LetterNo" = h."LetterNo"
            JOIN "TrnKJPPAppraisal" ka ON ka."DebiturNo" = d."NoDebitur" AND ka."SeqAgunan" = d."SeqAgunan"
            WHERE la."Team" = \'Lelang\'
            ORDER BY la."AppraisalId", la."CreatedAt" DESC, ka."AppraisalId")
        ';

        $sql = "
            SELECT DISTINCT ON (\"AppraisalId\")
                \"AppraisalId\", \"Team\", \"CreatedAt\"
            FROM ( $appraisalSrc UNION ALL $lelangSrc ) src
            ORDER BY \"AppraisalId\", \"CreatedAt\" DESC
        ";

        return DB::table(DB::raw("($sql) as lampiran_latest"))
            ->leftJoin('TrnKJPPAppraisal', 'TrnKJPPAppraisal.AppraisalId', '=', 'lampiran_latest.AppraisalId')
            ->leftJoin('TrnDebitur', 'TrnDebitur.DebiturNo', '=', 'TrnKJPPAppraisal.DebiturNo')
            ->leftJoin('TrnNotifikasi as notif', function ($join) use ($userName) {
                $join->on('notif.RefId', '=', 'lampiran_latest.AppraisalId')
                    ->where('notif.UserId', '=', $userName)
                    ->where('notif.Module', '=', self::KJPP_MODULE);
            })
            ->where(function ($q) {
                $q->whereNull('notif.LastReadAt')
                ->orWhereColumn('lampiran_latest.CreatedAt', '>', 'notif.LastReadAt');
            })
            ->select(
                'lampiran_latest.AppraisalId',
                'lampiran_latest.Team',
                'lampiran_latest.CreatedAt',
                'TrnDebitur.NamaDebitur',
                'TrnDebitur.ItemNo'
            );
    }

    // =========================================================================
    // ============================= END: KJPP APPRAISAL =========================
    // =========================================================================


    // =========================================================================
    // =========================== START: INTERNAL AUDIT ==========================
    // Sumber data  : TrnInternalAuditProgress (per update/komentar progress)
    // Status baca  : TrnNotifikasi (RefId = ProgressId)
    //
    // Dua konteks halaman, masing-masing punya aturan visibilitas & stream
    // baca terpisah (Module beda), karena "siapa notif siapa" berbeda:
    //
    // - INTERNAL_AUDIT_LIST  (audit/transactions/audit-list, sisi Auditor)
    //     > Pemeriksaan Reguler   : notif dari progress AuditRole='Auditee'
    //     > Fraud / Spesial Audit : notif dari progress AuditRole='Auditor',
    //       KECUALI progress yang dibuat oleh user yang login sendiri
    //
    // - INTERNAL_AUDIT_SP    (audit/transactions/status-penyelesaian, sisi Auditee)
    //     > Pemeriksaan Reguler   : notif dari progress AuditRole='Auditor'
    //     > (Fraud tidak relevan, halaman ini tidak diakses untuk Fraud)
    // =========================================================================

    const IA_LIST_MODULE = 'INTERNAL_AUDIT_LIST';
    const IA_SP_MODULE    = 'INTERNAL_AUDIT_SP';

    public function internalAuditListCount()
    {
        return $this->internalAuditCount(self::IA_LIST_MODULE);
    }

    public function internalAuditListItems()
    {
        return $this->internalAuditItems(self::IA_LIST_MODULE);
    }

    public function internalAuditListMarkReadOne($auditId)
    {
        return $this->internalAuditMarkReadOne($auditId, self::IA_LIST_MODULE);
    }

    public function internalAuditListMarkReadMany()
    {
        return $this->internalAuditMarkReadMany(self::IA_LIST_MODULE);
    }

    public function internalAuditSPCount()
    {
        return $this->internalAuditCount(self::IA_SP_MODULE);
    }

    public function internalAuditSPItems()
    {
        return $this->internalAuditItems(self::IA_SP_MODULE);
    }

    public function internalAuditSPMarkReadOne($auditId)
    {
        return $this->internalAuditMarkReadOne($auditId, self::IA_SP_MODULE);
    }

    public function internalAuditSPMarkReadMany()
    {
        return $this->internalAuditMarkReadMany(self::IA_SP_MODULE);
    }

    private function internalAuditCount($module)
    {
        $userName = $this->currentUserName();
        if (!$userName) {
            return response()->json(['countNotif' => 0]);
        }

        // Dihitung per AuditId (bukan per ProgressId), biar match sama jumlah
        // item yg tampil di dropdown -> 1 AuditId = 1 notif
        $count = $this->internalAuditNewItemsQuery($userName, $this->currentUserId(), $module)
            ->get(['dtl.AuditId'])
            ->pluck('AuditId')
            ->unique()
            ->count();

        return response()->json(['countNotif' => $count]);
    }

    private function internalAuditItems($module)
    {
        $userName = $this->currentUserName();
        if (!$userName) {
            return response()->json(['items' => []]);
        }

        $rows = $this->internalAuditNewItemsQuery($userName, $this->currentUserId(), $module)
            ->orderBy('prog.CreatedAt', 'desc')
            ->get();

        // 1 AuditId cuma 1 item di dropdown (ambil progress paling baru per AuditId),
        // krn klik notif di layout-main.blade.php cuma remove 1 DOM node -
        // kalau 1 AuditId punya banyak ProgressId, sisanya nyangkut sampai reopen.
        // Urutan tampilan dibalik: Keterangan Progress dulu (message), baru Judul
        // Pemeriksaan (reference) - Judul Pemeriksaan dibatasi lebih pendek (50 karakter)
        // karena cenderung panjang dan bikin numpuk di dropdown notifikasi yang sempit.
        $items = $rows->groupBy('AuditId')->map(function ($group) {
            $latest = $group->first();
            return [
                'AuditId'    => $latest->AuditId,
                'ProgressId' => $latest->ProgressId,
                'title'      => $latest->CreatedByName ?: '-',
                'message'    => $this->truncateText($latest->Keterangan),
                'reference'  => $this->truncateText($latest->JudulKondisi, 50),
                'created_at' => $latest->CreatedAt,
            ];
        })->sortByDesc('created_at')->take(20)->values();

        return response()->json(['items' => $items]);
    }

    private function internalAuditMarkReadOne($auditId, $module)
    {
        $userName = $this->currentUserName();
        if (!$userName || !$auditId) {
            \Log::info('IA_MARK_READ_FAIL: no username or auditId', ['userName' => $userName, 'auditId' => $auditId]);
            return response()->json(['result' => false]);
        }

        $progressIds = $this->internalAuditNewItemsQuery($userName, $this->currentUserId(), $module)
            ->where('dtl.AuditId', $auditId)
            ->get(['prog.ProgressId'])
            ->pluck('ProgressId')
            ->unique()
            ->values();

        \Log::info('IA_MARK_READ_DEBUG', [
            'userName'    => $userName,
            'module'      => $module,
            'auditId'     => $auditId,
            'progressIds' => $progressIds->toArray(),
        ]);

        foreach ($progressIds as $pid) {
            DB::table('TrnNotifikasi')->updateOrInsert(
                ['UserId' => $userName, 'Module' => $module, 'RefId' => $pid],
                ['LastReadAt' => DB::raw('now()')]
            );
        }

        return response()->json(['result' => true, 'AuditId' => $auditId]);
    }

    private function internalAuditMarkReadMany($module)
    {
        $userName = $this->currentUserName();
        if (!$userName) {
            return response()->json(['ids' => []]);
        }

        $rows = $this->internalAuditNewItemsQuery($userName, $this->currentUserId(), $module)
            ->get(['prog.ProgressId', 'dtl.AuditId']);

        foreach ($rows as $row) {
            DB::table('TrnNotifikasi')->updateOrInsert(
                ['UserId' => $userName, 'Module' => $module, 'RefId' => $row->ProgressId],
                ['LastReadAt' => DB::raw('now()')]
            );
        }

        $auditIds = $rows->pluck('AuditId')->unique()->values();

        return response()->json(['ids' => $auditIds]);
    }

    private function internalAuditNewItemsQuery($userName, $userId, $module)
    {
        $qry = DB::table('TrnInternalAuditProgress as prog')
            ->join('TrnInternalAuditDtl as dtl', 'dtl.DtlId', '=', 'prog.DtlId')
            ->join('TrnInternalAuditHdr as hdr', 'hdr.AuditId', '=', 'dtl.AuditId')
            ->leftJoin('SecUser as su', 'su.UserId', '=', 'prog.CreatedBy')
            ->leftJoin('TrnNotifikasi as notif', function ($join) use ($userName, $module) {
                $join->on('notif.RefId', '=', 'prog.ProgressId')
                    ->where('notif.UserId', '=', $userName)
                    ->where('notif.Module', '=', $module);
            })
            ->where(function ($q) {
                $q->whereNull('notif.LastReadAt')
                    ->orWhereColumn('prog.CreatedAt', '>', 'notif.LastReadAt');
            });

        if ($module === self::IA_LIST_MODULE) {
            // Halaman Auditor (audit-list)
            $qry->where(function ($q) use ($userId) {
                $q->where(function ($q2) {
                    $q2->where('hdr.JenisAudit', 'Pemeriksaan Reguler')
                        ->where('prog.AuditRole', 'Auditee');
                })->orWhere(function ($q2) use ($userId) {
                    $q2->where('hdr.JenisAudit', 'Fraud / Spesial Audit')
                        ->where('prog.AuditRole', 'Auditor')
                        ->where('prog.CreatedBy', '!=', $userId);
                });
            });
        } else {
            // Halaman Auditee (status-penyelesaian)
            $qry->where('hdr.JenisAudit', 'Pemeriksaan Reguler')
                ->where('prog.AuditRole', 'Auditor');
        }

        return $qry->select(
            'prog.ProgressId',
            'prog.CreatedAt',
            'prog.Keterangan',
            'dtl.AuditId',
            'hdr.JudulKondisi',
            'hdr.JenisAudit',
            DB::raw('COALESCE(su."EmpName", \'-\') as "CreatedByName"')
        );
    }

    private function truncateText($text, $limit = 100)
    {
        $text = (string) $text;
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return mb_substr($text, 0, $limit) . '...';
    }

    // =========================================================================
    // ============================ END: INTERNAL AUDIT ===========================
    // =========================================================================


    // =========================================================================
    // ================================ START: STPPL ============================
    // Sumber data  : TrnDataSuratHdr (SuratId='JS019') + TrnStpplDetail
    // Status baca  : TrnNotifikasi (Module = STPPL, RefId = DataNo)
    //
    // Dua arah, tertarget per user (berbeda dari KJPP yang global):
    //   - Approver (MstApprover.UserId = currentUserId, surat StatusApp='1' Diajukan)
    //   - Creator  (TrnDataSuratHdr.CreateBy = currentUserId, surat StatusApp='2'/'3')
    //
    // Dual-identifier (ikut pola INTERNAL AUDIT, BUKAN KJPP):
    //   - TrnNotifikasi.UserId menyimpan UserName (currentUserName) ? owner notif
    //   - MstApprover.UserId & TrnDataSuratHdr.CreateBy menyimpan UserId numerik
    //     (currentUserId) ? match kolom bisnis. Jangan tertukar.
    //
    // Event-time: TrnDataSuratHdr.StatusAppDate (timestamp, ditulis via DB::raw('now()')
    // di submit/saveApproval). Notif muncul saat StatusAppDate > notif.LastReadAt
    // atau belum pernah dibaca (LastReadAt IS NULL). Edit surat tidak menyentuh
    // StatusAppDate -> tidak memicu ulang notif.
    // =========================================================================

    const STPPL_MODULE = 'STPPL';

    public function stpplCount()
    {
        $userName = $this->currentUserName();
        if (!$userName) {
            return response()->json(['countNotif' => 0]);
        }

        // 1 DataNo = 1 baris (TrnStpplDetail 1:1 per DataNo) -> count() langsung,
        // tidak perlu dedup seperti IA per-AuditId.
        $count = $this->stpplNewItemsQuery($userName, $this->currentUserId())->count();

        return response()->json(['countNotif' => $count]);
    }

    public function stpplItems()
    {
        $userName = $this->currentUserName();
        if (!$userName) {
            return response()->json(['items' => []]);
        }

        $rows = $this->stpplNewItemsQuery($userName, $this->currentUserId())
            ->orderBy('h.StatusAppDate', 'desc')
            ->limit(20)
            ->get();

        $items = $rows->map(function ($row) {
            switch ($row->NotifType) {
                case 'DIAJUKAN':
                    $message = 'Surat diajukan untuk approval';
                    break;
                case 'APPROVED':
                    $message = 'Surat STPPl di-approve';
                    break;
                case 'DIKEMBALIKAN':
                    $message = 'Surat STPPl dikembalikan';
                    break;
                case 'CLOSE_REQUESTED':
                    $message = ($row->RequestType === 'COMPLETE')
                        ? 'Pengajuan penyelesaian surat STPPl'
                        : 'Pengajuan pembatalan surat STPPl';
                    break;
                case 'CLOSE_APPROVED_CANCEL':
                    $message = 'Pembatalan disetujui';
                    break;
                case 'CLOSE_APPROVED_COMPLETE':
                    $message = 'Penyelesaian disetujui';
                    break;
                case 'CLOSE_REJECTED':
                    $message = ($row->RequestType === 'COMPLETE')
                        ? 'Pengajuan penyelesaian ditolak (surat tetap Approved)'
                        : 'Pengajuan pembatalan ditolak (surat tetap Approved)';
                    break;
                default:
                    $message = 'Surat STPPl';
            }

            return [
                'DataNo'     => $row->DataNo,
                'title'      => 'STPPl',
                'message'    => $message,
                // Escape di sisi server: anRenderItems (layout-main) menyisipkan field item
                // via string-concat tanpa escaping; LetterNo/NamaDebiturOv adalah data user
                // yang di-decode (html_entity_decode) saat save -> potensi stored-XSS
                // creator->approver bila tidak di-escape di sini.
                'reference'  => htmlspecialchars(trim(($row->LetterNo ?: '-') . ' - ' . ($row->NamaDebiturOv ?: '-'), ' -'), ENT_QUOTES, 'UTF-8', false),
                'created_at' => $row->StatusAppDate,
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    public function stpplMarkReadOne($dataNo)
    {
        $userName = $this->currentUserName();
        // Validasi digit: RefId kolom integer ? nilai sampah via POST manual memicu PG
        // 22P02 (error 500). Jalur normal selalu mengirim DataNo numerik dari item bell.
        if (!$userName || !is_scalar($dataNo) || !ctype_digit((string)$dataNo)) {
            return response()->json(['result' => false]);
        }
        $dataNo = (int)$dataNo;

        // UserId = UserName (notif owner, konsisten KJPP/IA); RefId = DataNo numerik.
        DB::table('TrnNotifikasi')->updateOrInsert(
            ['UserId' => $userName, 'Module' => self::STPPL_MODULE, 'RefId' => $dataNo],
            ['LastReadAt' => DB::raw('now()')]
        );

        return response()->json(['result' => true, 'DataNo' => $dataNo]);
    }

    public function stpplMarkReadMany()
    {
        $userName = $this->currentUserName();
        if (!$userName) {
            return response()->json(['ids' => []]);
        }

        $dataNos = $this->stpplNewItemsQuery($userName, $this->currentUserId())
            ->get(['h.DataNo'])
            ->pluck('DataNo')
            ->unique()
            ->values();

        foreach ($dataNos as $dataNo) {
            DB::table('TrnNotifikasi')->updateOrInsert(
                ['UserId' => $userName, 'Module' => self::STPPL_MODULE, 'RefId' => $dataNo],
                ['LastReadAt' => DB::raw('now()')]
            );
        }

        return response()->json(['ids' => $dataNos]);
    }

    private function stpplNewItemsQuery($userName, $userId)
    {
        return DB::table('TrnDataSuratHdr as h')
            ->join('TrnStpplDetail as d', 'd.DataNo', '=', 'h.DataNo')
            ->leftJoin('MstApprover as ma', 'ma.ApproverId', '=', 'h.ApproverId')
            // Join permintaan penutupan terakhir per DataNo (DISTINCT ON pola existing).
            // Select wajib expose cr.RequestType dan cr.Decision ? dipakai mapping pesan.
            ->leftJoin(DB::raw('(SELECT DISTINCT ON ("DataNo") "DataNo","CloseReqId","RequestType","Decision" FROM "TrnStpplCloseReq" ORDER BY "DataNo", "CloseReqId" DESC) AS "cr"'),
                'cr.DataNo', '=', 'h.DataNo')
            // notif join binds UserName (notif owner); LEFT JOIN agar surat belum
            // dibaca tetap muncul (notif.LastReadAt IS NULL).
            ->leftJoin('TrnNotifikasi as notif', function ($join) use ($userName) {
                $join->on('notif.RefId', '=', 'h.DataNo')
                    ->where('notif.UserId', '=', $userName)
                    ->where('notif.Module', '=', self::STPPL_MODULE);
            })
            ->where('h.SuratId', 'JS019')
            // Match kolom bisnis pakai UserId (numerik), BUKAN UserName.
            // Approver arm: '1' DIAJUKAN (existing) + '6' CLOSE_REQUESTED.
            // Tidak ada arm superAdmin (keputusan user: cek manual via filter grid).
            ->where(function ($q) use ($userId) {
                $q->where(function ($q2) use ($userId) {
                    $q2->where('ma.UserId', $userId)
                        ->whereIn('h.StatusApp', ['1', '6']);
                })->orWhere(function ($q2) use ($userId) {
                    // Creator arm: '2','3' existing + '4','5' HANYA bila ada CloseReq
                    // (aliran baru; baris legacy direct-close tanpa CloseReq tidak muncul di bell).
                    $q2->where('h.CreateBy', $userId)
                        ->where(function ($q3) {
                            $q3->whereIn('h.StatusApp', ['2', '3'])
                               ->orWhere(function ($q4) {
                                   $q4->whereIn('h.StatusApp', ['4', '5'])
                                      ->whereNotNull('cr.CloseReqId');
                               });
                        });
                });
            })
            ->where(function ($q) {
                $q->whereNull('notif.LastReadAt')
                    ->orWhereColumn('h.StatusAppDate', '>', 'notif.LastReadAt');
            })
            ->select(
                'h.DataNo',
                'h.LetterNo',
                'd.NamaDebiturOv',
                'h.StatusApp',
                'h.StatusAppDate',
                'cr.RequestType',
                'cr.Decision'
            )
            // NotifType CASE diperluas:
            // '1' -> DIAJUKAN | '6' -> CLOSE_REQUESTED (sub-tipe via cr.RequestType)
            // '2' + cr.Decision=0 -> CLOSE_REJECTED | '2' tanpa cr -> APPROVED (existing)
            // '3' -> DIKEMBALIKAN | '4' -> CLOSE_APPROVED_CANCEL | '5' -> CLOSE_APPROVED_COMPLETE
            ->selectRaw('CASE ' .
                'WHEN "h"."StatusApp" = \'1\' THEN \'DIAJUKAN\' ' .
                'WHEN "h"."StatusApp" = \'6\' THEN \'CLOSE_REQUESTED\' ' .
                'WHEN "h"."StatusApp" = \'2\' AND "cr"."Decision" = 0 THEN \'CLOSE_REJECTED\' ' .
                'WHEN "h"."StatusApp" = \'2\' THEN \'APPROVED\' ' .
                'WHEN "h"."StatusApp" = \'3\' THEN \'DIKEMBALIKAN\' ' .
                'WHEN "h"."StatusApp" = \'4\' THEN \'CLOSE_APPROVED_CANCEL\' ' .
                'WHEN "h"."StatusApp" = \'5\' THEN \'CLOSE_APPROVED_COMPLETE\' ' .
                'END AS "NotifType"');
    }

    // =========================================================================
    // ================================= END: STPPL =============================
    // =========================================================================

    /**
     * =========================================================
     * TEMPLATE UNTUK MODUL LAIN
     * Kalau modul lain butuh notifikasi dengan pola sama, tinggal
     * tambah 1 blok baru di sini dengan format START/END seperti di atas
     * (const MODULE + method count/items/markReadOne/markReadMany +
     * private query builder), lalu daftarkan route-nya di
     * routes/web/app-notifications.php.
     * Tidak perlu sentuh controller modul yang bersangkutan sama sekali.
     * =========================================================
     */
}