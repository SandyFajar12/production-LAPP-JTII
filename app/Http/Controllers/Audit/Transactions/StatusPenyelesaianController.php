<?php

namespace App\Http\Controllers\Audit\Transactions;

use Symfony\Component\HttpFoundation\Request;
use App\Http\Controllers\Controller;
use App\Models\Audit\Transactions\TrnInternalAuditHdr;
use App\Models\Audit\Transactions\TrnInternalAuditDtl;
use App\Models\Audit\Transactions\TrnInternalAuditLampiran;
use App\Models\Audit\Transactions\TrnInternalAuditProgress;
use App\Models\Audit\Transactions\TrnInternalAuditPic;
use App\Models\Audit\Transactions\TrnInternalAuditPerbaikan;

class StatusPenyelesaianController extends Controller
{
    public function index(Request $req) {
        $data['isFullPage'] = $this->getPageType($req);
        $data['loginUserDeptId'] = \Auth::user() ? \Auth::user()->DeptId : '';
        $data['loginNikId'] = $this->resolveLoginNikId();
        $data['loginUserName'] = \DB::table('SecUser')->where('UserId', $this->editBy)->value('EmpName');

        return view('audit/transactions/status-penyelesaian/index')->with($data)->render();
    }

    // Resolve NikId (short format) untuk user yang sedang login (short, sama format dengan
    // TrnInternalAuditPic.NikId). Jalur utama: SecUser.NikId (harusnya berisi NikFull) ->
    // MstKaryawan.NikFull -> MstKaryawan.NikId. Fallback: cocokkan lewat nama (exact, lalu
    // partial dua arah) - hanya dipakai kalau hasilnya persis 1 kandidat unik.
    private function resolveLoginNikId() {
        $loginUser = \DB::table('SecUser')->where('UserId', $this->editBy)->first();

        $loginKaryawan = null;
        if ($loginUser && !empty($loginUser->NikId)) {
            $loginKaryawan = \DB::table('MstKaryawan')->where('NikFull', $loginUser->NikId)->first();
        }

        // Fallback: SecUser.NikId kadang keisi format pendek yang match langsung ke
        // MstKaryawan.NikId (bukan ke NikFull) - kasus Maria: SecUser.NikId='1314' ==
        // MstKaryawan.NikId='1314', sementara NikFull-nya beda format ('20220012.314').
        if (!$loginKaryawan && $loginUser && !empty($loginUser->NikId)) {
            $loginKaryawan = \DB::table('MstKaryawan')->where('NikId', $loginUser->NikId)->first();
        }

        if (!$loginKaryawan && $loginUser && !empty($loginUser->EmpName)) {
            $candidates = \DB::table('MstKaryawan')->where('Name', $loginUser->EmpName)->get();
            if ($candidates->count() === 1) {
                $loginKaryawan = $candidates->first();
            } else {
                \Log::warning("StatusPenyelesaian: fallback exact-name untuk UserId '{$this->editBy}' (EmpName '{$loginUser->EmpName}') ditemukan {$candidates->count()} kandidat (butuh tepat 1), coba fallback partial-name.");

                $partialCandidates = \DB::table('MstKaryawan')
                    ->where('Name', 'ILIKE', '%' . $loginUser->EmpName . '%')
                    ->orWhereRaw('? ILIKE (\'%\' || "Name" || \'%\')', [$loginUser->EmpName])
                    ->get();

                if ($partialCandidates->count() === 1) {
                    $loginKaryawan = $partialCandidates->first();
                } else {
                    \Log::warning("StatusPenyelesaian: fallback partial-name untuk UserId '{$this->editBy}' (EmpName '{$loginUser->EmpName}') ditemukan {$partialCandidates->count()} kandidat (butuh tepat 1).");
                }
            }
        }

        return $loginKaryawan ? $loginKaryawan->NikId : '';
    }

    // Dropdown "Pilih Tindak Lanjut" untuk form Progress Temuan (status-penyelesaian/modal.blade.php).
    // Hanya menampilkan entry Perbaikan yang login user-nya (Auditee) termasuk PIC (Model = 'Tindak Lanjut').
    // Dropdown sekarang SELALU tampilkan SEMUA entry Tindak Lanjut milik Temuan ini (tidak lagi
    // difilter berdasar PIC login) - supaya user yang bukan PIC di sebagian entry tetap bisa
    // melihat progress entry lain. Akses EDIT (readonly/enable form) ditentukan per-item lewat
    // flag IsPic yang dikirim di sini, dicek ulang di frontend tiap dropdown diganti.
    public function getPerbaikanDropdown(Request $req) {
        $loginNikId  = $this->resolveLoginNikId();
        $loginUserId = $this->editBy;

        $data = \DB::table('TrnInternalAuditPerbaikan')
            ->where('DtlId', $req->DtlId)
            ->orderBy('Deadline')
            ->get(['PerbaikanId', 'Action', 'DetailPerbaikan', 'Deadline', 'ApprovalAuditee']);

        $picPerbaikanIds = \DB::table('TrnInternalAuditPic')
            ->where('Model', 'Tindak Lanjut')
            ->where('NikId', $loginNikId)
            ->pluck('PerbaikanId')
            ->map(function($id){ return (string) $id; })
            ->toArray();

        $result = [];
        foreach ($data as $item) {
            $label = $item->Action ? ('[' . $item->Action . '] ') : '';
            $label .= strip_tags($item->DetailPerbaikan ?: '-');

            $result[] = [
                'PerbaikanId'       => $item->PerbaikanId,
                'Label'             => $label,
                'IsPic'             => in_array((string) $item->PerbaikanId, $picPerbaikanIds),
                'IsApprovalAuditee' => $this->isLoginApprovalAuditee($item->ApprovalAuditee),
            ];
        }

        return response()->json(['result' => true, 'data' => $result]);
    }

    // Cocokkan login user (UserId) dengan ApprovalAuditee (ApproverId di TrnInternalAuditPerbaikan)
    private function isLoginApprovalAuditee($approverId) {
        if (empty($approverId)) return false;
        $approver = \DB::table('MstApprover')->where('ApproverId', $approverId)->first();
        return $approver && (string) $approver->UserId === (string) $this->editBy;
    }

    // Total Progress cuma dihitung dari entry berstatus Approved (Pending/Rejected diabaikan).
    // Agregasi WAJIB per PerbaikanId (Tindak Lanjut) dulu - bukan per DtlId (Temuan) - karena
    // 1 Temuan bisa punya lebih dari 1 Tindak Lanjut, masing-masing dengan progress sendiri.
    private function calcTotalProgress($auditId) {
        return \DB::table('TrnInternalAuditPerbaikan as pb')
            ->join('TrnInternalAuditDtl as dtl', 'dtl.DtlId', '=', 'pb.DtlId')
            ->leftJoin(\DB::raw('(
                SELECT DISTINCT ON ("PerbaikanId") "PerbaikanId", "Progress"
                FROM "TrnInternalAuditProgress"
                WHERE "ApprovalStatus" = \'Approved\'
                ORDER BY "PerbaikanId", "CreatedAt" DESC, "ProgressId" DESC
            ) as lp'), 'lp.PerbaikanId', '=', 'pb.PerbaikanId')
            ->where('dtl.AuditId', $auditId)
            ->selectRaw('ROUND(AVG(COALESCE(lp."Progress", 0))) as total')
            ->value('total') ?: 0;
    }

    // Level 2 — union PIC (Model='Tindak Lanjut') dari semua entry Perbaikan milik 1 Temuan (DtlId)
    public function getPicListArray(Request $req) {
        $data = \DB::table('TrnInternalAuditPic')
            ->select(
                \DB::raw('DISTINCT "TrnInternalAuditPic"."NikId" as "NikId"'),
                \DB::raw('COALESCE("MstKaryawan"."Name", \'\') as "Name"')
            )
            ->leftJoin('MstKaryawan', 'MstKaryawan.NikId', '=', 'TrnInternalAuditPic.NikId')
            ->whereIn('PerbaikanId', function($q) use ($req) {
                $q->select('PerbaikanId')
                  ->from('TrnInternalAuditPerbaikan')
                  ->where('DtlId', $req->DtlId);
            })
            ->where('Model', 'Tindak Lanjut')
            ->get();

        return response()->json(['result' => true, 'data' => $data]);
    }

    // Level 1 — union PIC (Model='Tindak Lanjut') dari semua Temuan & entry Perbaikan di bawah 1 AuditId
    public function getPicListArrayAudit(Request $req) {
        $data = \DB::table('TrnInternalAuditPic')
            ->select(
                \DB::raw('DISTINCT "TrnInternalAuditPic"."NikId" as "NikId"'),
                \DB::raw('COALESCE("MstKaryawan"."Name", \'\') as "Name"')
            )
            ->leftJoin('MstKaryawan', 'MstKaryawan.NikId', '=', 'TrnInternalAuditPic.NikId')
            ->whereIn('PerbaikanId', function($q) use ($req) {
                $q->select('PerbaikanId')
                  ->from('TrnInternalAuditPerbaikan')
                  ->whereIn('DtlId', function($q2) use ($req) {
                      $q2->select('DtlId')
                         ->from('TrnInternalAuditDtl')
                         ->where('AuditId', $req->AuditId);
                  });
            })
            ->where('Model', 'Tindak Lanjut')
            ->get();

        // Selain PIC Auditee, akses juga terbuka kalau login user = ApprovalAuditee di
        // salah satu entry Tindak Lanjut milik AuditId ini (Approval Auditee memang tidak
        // selalu tercatat sebagai PIC, tapi tetap perlu bisa buka Status Penyelesaian).
        $isApprovalAuditee = \DB::table('TrnInternalAuditPerbaikan')
            ->whereIn('DtlId', function($q) use ($req) {
                $q->select('DtlId')
                  ->from('TrnInternalAuditDtl')
                  ->where('AuditId', $req->AuditId);
            })
            ->whereNotNull('ApprovalAuditee')
            ->whereIn('ApprovalAuditee', function($q) {
                $q->select('ApproverId')
                  ->from('MstApprover')
                  ->where('UserId', $this->editBy);
            })
            ->exists();

        return response()->json(['result' => true, 'data' => $data, 'IsApprovalAuditee' => $isApprovalAuditee]);
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
                \DB::raw('COALESCE(progagg."TotalProgress", 0) as "TotalProgress"'),
                \DB::raw('dtl."CreatedByNames" as "CreatedByNames"'),
                \DB::raw('"TrnInternalAuditHdr"."FlagApproval1" as "FlagApproval1"'),
                \DB::raw('COALESCE(secuser1."EmpName", \'\') as "ApproverName1"'),
                \DB::raw('"TrnInternalAuditHdr"."FlagApproval2" as "FlagApproval2"'),
                \DB::raw('COALESCE(secuser2."EmpName", \'\') as "ApproverName2"'),
                \DB::raw('"TrnInternalAuditHdr"."NoSuratTugas" as "NoSuratTugas"'),
                \DB::raw('"TrnInternalAuditHdr"."NoGaroon" as "NoGaroon"'),
                \DB::raw('"TrnInternalAuditHdr"."PeriodeStart" as "PeriodeStart"'),
                \DB::raw('"TrnInternalAuditHdr"."PeriodeEnd" as "PeriodeEnd"'),
                \DB::raw('"TrnInternalAuditHdr"."TanggalPemeriksaan" as "TanggalPemeriksaan"'),
                \DB::raw('"TrnInternalAuditHdr"."TanggalUpload" as "TanggalUpload"')
            )
            ->leftJoin(\DB::raw('(
                SELECT dtl."AuditId",
                       COUNT(*) as "JumlahTemuan",
                       STRING_AGG(DISTINCT su."EmpName", \', \') as "CreatedByNames"
                FROM "TrnInternalAuditDtl" dtl
                LEFT JOIN "SecUser" su ON su."UserId" = dtl."CreatedBy"
                GROUP BY dtl."AuditId"
            ) as dtl'), 'dtl.AuditId', '=', 'TrnInternalAuditHdr.AuditId')
            // Sama seperti InternalAuditController::getList() - Total Progress dihitung dari level
            // Tindak Lanjut (PerbaikanId), lalu dirata-ratakan lintas Temuan dalam 1 AuditId.
            ->leftJoin(\DB::raw('(
                SELECT dtl2."AuditId", ROUND(AVG(COALESCE(lp."Progress", 0))) as "TotalProgress"
                FROM "TrnInternalAuditPerbaikan" pb
                INNER JOIN "TrnInternalAuditDtl" dtl2 ON dtl2."DtlId" = pb."DtlId"
                LEFT JOIN (
                    SELECT DISTINCT ON ("PerbaikanId") "PerbaikanId", "Progress"
                    FROM "TrnInternalAuditProgress"
                    WHERE "ApprovalStatus" = \'Approved\'
                    ORDER BY "PerbaikanId", "CreatedAt" DESC, "ProgressId" DESC
                ) lp ON lp."PerbaikanId" = pb."PerbaikanId"
                GROUP BY dtl2."AuditId"
            ) as progagg'), 'progagg.AuditId', '=', 'TrnInternalAuditHdr.AuditId')
            ->leftJoin('MstDepartemen', 'MstDepartemen.DeptId', '=', 'TrnInternalAuditHdr.DepartmentAudity')
            ->leftJoin('MstApprover as mstapprover1', 'mstapprover1.ApproverId', '=', 'TrnInternalAuditHdr.Approval1')
            ->leftJoin('SecUser as secuser1', 'secuser1.UserId', '=', 'mstapprover1.UserId')
            ->leftJoin('MstApprover as mstapprover2', 'mstapprover2.ApproverId', '=', 'TrnInternalAuditHdr.Approval2')
            ->leftJoin('SecUser as secuser2', 'secuser2.UserId', '=', 'mstapprover2.UserId')
            ->orderByRaw('COALESCE("TrnInternalAuditHdr"."UpdatedAt", "TrnInternalAuditHdr"."CreatedAt") DESC NULLS LAST');

        if (!empty($req->JudulKondisi)) {
            $qry->where('TrnInternalAuditHdr.JudulKondisi', 'like', '%' . $req->JudulKondisi . '%');
        }

        if (!empty($req->NotifAuditId)) {
            $qry->where('TrnInternalAuditHdr.AuditId', $req->NotifAuditId);
        }

        return $this->grid->load($qry);
    }

    public function getTemuanList(Request $req) {
        $qry = TrnInternalAuditDtl::select(
                \DB::raw('"TrnInternalAuditDtl"."DtlId" as "DtlId"'),
                \DB::raw('"TrnInternalAuditDtl"."AuditId" as "AuditId"'),
                \DB::raw('"TrnInternalAuditDtl"."JudulTemuan" as "JudulTemuan"'),
                \DB::raw('"TrnInternalAuditDtl"."DetailTemuan" as "DetailTemuan"'),
                \DB::raw('"TrnInternalAuditDtl"."IndikasiAwal" as "IndikasiAwal"'),
                \DB::raw('"TrnInternalAuditDtl"."Resiko" as "Resiko"'),
                \DB::raw('"TrnInternalAuditDtl"."Kerugian" as "Kerugian"'),
                \DB::raw('"TrnInternalAuditDtl"."PeraturanSOP" as "PeraturanSOP"'),
                \DB::raw('"TrnInternalAuditDtl"."SanksiKaryawan" as "SanksiKaryawan"'),
                \DB::raw('"TrnInternalAuditDtl"."SanksiAtasan" as "SanksiAtasan"'),
                \DB::raw('"TrnInternalAuditDtl"."RekomendasiAuditor" as "RekomendasiAuditor"'),
                \DB::raw('"TrnInternalAuditDtl"."PengembalianKerugian" as "PengembalianKerugian"'),
                \DB::raw('"TrnInternalAuditDtl"."CreatedAt" as "CreatedAt"'),
                \DB::raw('pb."DeadlineList" as "DeadlineList"'),
                \DB::raw('picpb."PicAuditeeList" as "PicAuditeeList"'),
                \DB::raw('progpb."ProgressList" as "ProgressList"'),
                \DB::raw('COALESCE(lprog."Progress", 0) as "Progress"')
            )
            ->leftJoin(\DB::raw('(
                SELECT "DtlId", STRING_AGG("Deadline"::text, \'|\' ORDER BY "Deadline") as "DeadlineList"
                FROM "TrnInternalAuditPerbaikan"
                GROUP BY "DtlId"
            ) as pb'), 'pb.DtlId', '=', 'TrnInternalAuditDtl.DtlId')
            ->leftJoin(\DB::raw('(
                SELECT pb2."DtlId", STRING_AGG(pb2."PicNames", \'|\' ORDER BY pb2."Deadline") as "PicAuditeeList"
                FROM (
                    SELECT pb3."PerbaikanId", pb3."DtlId", pb3."Deadline",
                           COALESCE(STRING_AGG(mk."Name", \', \'), \'-\') as "PicNames"
                    FROM "TrnInternalAuditPerbaikan" pb3
                    LEFT JOIN "TrnInternalAuditPic" pic ON pic."PerbaikanId" = pb3."PerbaikanId" AND pic."Model" = \'Tindak Lanjut\'
                    LEFT JOIN "MstKaryawan" mk ON mk."NikId" = pic."NikId"
                    GROUP BY pb3."PerbaikanId", pb3."DtlId", pb3."Deadline"
                ) pb2
                GROUP BY pb2."DtlId"
            ) as picpb'), 'picpb.DtlId', '=', 'TrnInternalAuditDtl.DtlId')
            ->leftJoin(\DB::raw('(
                SELECT pg2."DtlId", STRING_AGG(pg2."ProgressVal"::text, \'|\' ORDER BY pg2."Deadline") as "ProgressList"
                FROM (
                    SELECT pb4."PerbaikanId", pb4."DtlId", pb4."Deadline",
                           COALESCE(lprogpb."Progress", 0) as "ProgressVal"
                    FROM "TrnInternalAuditPerbaikan" pb4
                    LEFT JOIN (
                        SELECT DISTINCT ON ("PerbaikanId") "PerbaikanId", "Progress"
                        FROM "TrnInternalAuditProgress"
                        WHERE "PerbaikanId" IS NOT NULL AND "ApprovalStatus" = \'Approved\'
                        ORDER BY "PerbaikanId", "CreatedAt" DESC, "ProgressId" DESC
                    ) lprogpb ON lprogpb."PerbaikanId" = pb4."PerbaikanId"
                ) pg2
                GROUP BY pg2."DtlId"
            ) as progpb'), 'progpb.DtlId', '=', 'TrnInternalAuditDtl.DtlId')
            ->leftJoin(\DB::raw('(
                SELECT DISTINCT ON ("DtlId") "DtlId", "Progress"
                FROM "TrnInternalAuditProgress"
                WHERE "ApprovalStatus" = \'Approved\'
                ORDER BY "DtlId", "CreatedAt" DESC, "ProgressId" DESC
            ) as lprog'), 'lprog.DtlId', '=', 'TrnInternalAuditDtl.DtlId')
            ->where('TrnInternalAuditDtl.AuditId', $req->AuditId)
            ->orderBy('TrnInternalAuditDtl.DtlId');

        return $this->grid->load($qry);
    }

    // Dipakai JS saat dropdown Tindak Lanjut dipilih: butuh array polos untuk isi ulang lampiranTempSP
    // Sekarang difilter by PerbaikanId (bukan DtlId lagi)
    public function getLampiranListArray(Request $req) {
        $data = TrnInternalAuditLampiran::select(
                \DB::raw('"TrnInternalAuditLampiran"."LampiranId" as "LampiranId"'),
                \DB::raw('"TrnInternalAuditLampiran"."DtlId" as "DtlId"'),
                \DB::raw('"TrnInternalAuditLampiran"."PerbaikanId" as "PerbaikanId"'),
                \DB::raw('"TrnInternalAuditLampiran"."NamaFile" as "NamaFile"'),
                \DB::raw('"TrnInternalAuditLampiran"."FileLampiran" as "FileLampiran"'),
                \DB::raw('"TrnInternalAuditLampiran"."CreatedAt" as "CreatedAt"'),
                \DB::raw('"TrnInternalAuditLampiran"."AuditRole" as "AuditRole"'),
                \DB::raw('"TrnInternalAuditLampiran"."ApprovalStatus" as "ApprovalStatus"'),
                \DB::raw('"TrnInternalAuditLampiran"."ApprovalNote" as "ApprovalNote"'),
                \DB::raw('COALESCE(su."EmpName", \'\') as "CreatedByName"')
            )
            ->leftJoin('SecUser as su', 'su.UserId', '=', 'TrnInternalAuditLampiran.CreatedBy')
            ->where('PerbaikanId', $req->PerbaikanId)
            ->orderBy('LampiranId')
            ->get();

        return response()->json(['result' => true, 'data' => $data]);
    }

    public function getProgressList(Request $req) {
        $perbaikanId = $req->PerbaikanId;

        $auditor = TrnInternalAuditProgress::select(
                \DB::raw('"TrnInternalAuditProgress"."ProgressId" as "ProgressId"'),
                \DB::raw('"TrnInternalAuditProgress"."Progress" as "Progress"'),
                \DB::raw('"TrnInternalAuditProgress"."Keterangan" as "Keterangan"'),
                \DB::raw('"TrnInternalAuditProgress"."CreatedAt" as "CreatedAt"'),
                \DB::raw('su."EmpName" as "CreatedByName"'),
                \DB::raw('pb."Action" as "PerbaikanAction"'),
                \DB::raw('pb."DetailPerbaikan" as "PerbaikanDetail"')
            )
            ->leftJoin('SecUser as su', 'su.UserId', '=', 'TrnInternalAuditProgress.CreatedBy')
            ->leftJoin('TrnInternalAuditPerbaikan as pb', 'pb.PerbaikanId', '=', 'TrnInternalAuditProgress.PerbaikanId')
            ->where('TrnInternalAuditProgress.PerbaikanId', $perbaikanId)
            ->where('AuditRole', 'Auditor')
            ->orderBy('TrnInternalAuditProgress.CreatedAt', 'desc')
            ->get();

        $auditee = TrnInternalAuditProgress::select(
                \DB::raw('"TrnInternalAuditProgress"."ProgressId" as "ProgressId"'),
                \DB::raw('"TrnInternalAuditProgress"."Progress" as "Progress"'),
                \DB::raw('"TrnInternalAuditProgress"."Keterangan" as "Keterangan"'),
                \DB::raw('"TrnInternalAuditProgress"."CreatedAt" as "CreatedAt"'),
                \DB::raw('su."EmpName" as "CreatedByName"'),
                \DB::raw('pb."Action" as "PerbaikanAction"'),
                \DB::raw('pb."DetailPerbaikan" as "PerbaikanDetail"')
            )
            ->leftJoin('SecUser as su', 'su.UserId', '=', 'TrnInternalAuditProgress.CreatedBy')
            ->leftJoin('TrnInternalAuditPerbaikan as pb', 'pb.PerbaikanId', '=', 'TrnInternalAuditProgress.PerbaikanId')
            ->where('TrnInternalAuditProgress.PerbaikanId', $perbaikanId)
            ->where('AuditRole', 'Auditee')
            ->orderBy('TrnInternalAuditProgress.CreatedAt', 'desc')
            ->get();

        $latest = TrnInternalAuditProgress::where('PerbaikanId', $perbaikanId)
            ->orderBy('CreatedAt', 'desc')
            ->orderBy('ProgressId', 'desc')
            ->first();

        return response()->json([
            'result'         => true,
            'auditor'        => $auditor,
            'auditee'        => $auditee,
            'latestProgress' => $latest ? $latest->Progress : 0,
        ]);
    }

    // Histori gabungan SEMUA entry Perbaikan (Tindak Lanjut) milik 1 Temuan (DtlId) - sama
    // seperti versi Auditor di InternalAuditController, dipakai buat isi panel Auditor/Auditee
    // saat row Temuan di grdTemuanDbSP diklik.
    public function getProgressListByDtl(Request $req) {
        $dtlId = $req->DtlId;

        $progressSelect = [
            \DB::raw('"TrnInternalAuditProgress"."ProgressId" as "ItemId"'),
            \DB::raw('"TrnInternalAuditProgress"."PerbaikanId" as "PerbaikanId"'),
            \DB::raw('"TrnInternalAuditProgress"."Progress" as "Progress"'),
            \DB::raw('"TrnInternalAuditProgress"."Keterangan" as "Keterangan"'),
            \DB::raw('"TrnInternalAuditProgress"."ApprovalStatus" as "ApprovalStatus"'),
            \DB::raw('"TrnInternalAuditProgress"."ApprovalNote" as "ApprovalNote"'),
            \DB::raw('"TrnInternalAuditProgress"."CreatedAt" as "CreatedAt"'),
            \DB::raw('su."EmpName" as "CreatedByName"'),
            \DB::raw('pb."Action" as "PerbaikanAction"'),
            \DB::raw('pb."DetailPerbaikan" as "PerbaikanDetail"'),
            \DB::raw('\'Progress\' as "ItemType"'),
            \DB::raw('NULL as "FileLampiran"'),
        ];

        $lampiranSelect = [
            \DB::raw('"TrnInternalAuditLampiran"."LampiranId" as "ItemId"'),
            \DB::raw('"TrnInternalAuditLampiran"."PerbaikanId" as "PerbaikanId"'),
            \DB::raw('NULL as "Progress"'),
            \DB::raw('"TrnInternalAuditLampiran"."NamaFile" as "Keterangan"'),
            \DB::raw('"TrnInternalAuditLampiran"."ApprovalStatus" as "ApprovalStatus"'),
            \DB::raw('"TrnInternalAuditLampiran"."ApprovalNote" as "ApprovalNote"'),
            \DB::raw('"TrnInternalAuditLampiran"."CreatedAt" as "CreatedAt"'),
            \DB::raw('su."EmpName" as "CreatedByName"'),
            \DB::raw('pb."Action" as "PerbaikanAction"'),
            \DB::raw('pb."DetailPerbaikan" as "PerbaikanDetail"'),
            \DB::raw('\'Lampiran\' as "ItemType"'),
            \DB::raw('"TrnInternalAuditLampiran"."FileLampiran" as "FileLampiran"'),
        ];

        $auditorProgress = TrnInternalAuditProgress::select($progressSelect)
            ->leftJoin('SecUser as su', 'su.UserId', '=', 'TrnInternalAuditProgress.CreatedBy')
            ->leftJoin('TrnInternalAuditPerbaikan as pb', 'pb.PerbaikanId', '=', 'TrnInternalAuditProgress.PerbaikanId')
            ->where('TrnInternalAuditProgress.DtlId', $dtlId)
            ->where('AuditRole', 'Auditor')
            ->get();

        $auditorLampiran = TrnInternalAuditLampiran::select($lampiranSelect)
            ->leftJoin('SecUser as su', 'su.UserId', '=', 'TrnInternalAuditLampiran.CreatedBy')
            ->leftJoin('TrnInternalAuditPerbaikan as pb', 'pb.PerbaikanId', '=', 'TrnInternalAuditLampiran.PerbaikanId')
            ->where('TrnInternalAuditLampiran.DtlId', $dtlId)
            ->where('AuditRole', 'Auditor')
            ->get();

        // Auditee: SEMUA status (Pending/Approved/Rejected) ikut ditampilkan di sisi Auditee sendiri
        // (biar dia tau status progress/lampiran yang dia input), beda dengan sisi Auditor yang
        // cuma boleh lihat yang Approved.
        $auditeeProgress = TrnInternalAuditProgress::select($progressSelect)
            ->leftJoin('SecUser as su', 'su.UserId', '=', 'TrnInternalAuditProgress.CreatedBy')
            ->leftJoin('TrnInternalAuditPerbaikan as pb', 'pb.PerbaikanId', '=', 'TrnInternalAuditProgress.PerbaikanId')
            ->where('TrnInternalAuditProgress.DtlId', $dtlId)
            ->where('AuditRole', 'Auditee')
            ->get();

        $auditeeLampiran = TrnInternalAuditLampiran::select($lampiranSelect)
            ->leftJoin('SecUser as su', 'su.UserId', '=', 'TrnInternalAuditLampiran.CreatedBy')
            ->leftJoin('TrnInternalAuditPerbaikan as pb', 'pb.PerbaikanId', '=', 'TrnInternalAuditLampiran.PerbaikanId')
            ->where('TrnInternalAuditLampiran.DtlId', $dtlId)
            ->where('AuditRole', 'Auditee')
            ->get();

        $auditor = $auditorProgress->concat($auditorLampiran)->sortByDesc('CreatedAt')->values();
        $auditee = $auditeeProgress->concat($auditeeLampiran)->sortByDesc('CreatedAt')->values();

        // Progress terakhir per entry Perbaikan (yang Approved saja) - dikirim sekali di
        // sini supaya JS bisa prefill field Progress (%) murni client-side tanpa ajax tambahan.
        $latestRows = \DB::table('TrnInternalAuditPerbaikan as pb')
            ->select('pb.PerbaikanId', 'lp.Progress')
            ->leftJoin(\DB::raw('(
                SELECT DISTINCT ON ("PerbaikanId") "PerbaikanId", "Progress"
                FROM "TrnInternalAuditProgress"
                WHERE "ApprovalStatus" = \'Approved\'
                ORDER BY "PerbaikanId", "CreatedAt" DESC, "ProgressId" DESC
            ) as lp'), 'lp.PerbaikanId', '=', 'pb.PerbaikanId')
            ->where('pb.DtlId', $dtlId)
            ->get();

        $latestByPerbaikan = [];
        foreach ($latestRows as $row) {
            $latestByPerbaikan[$row->PerbaikanId] = $row->Progress ?: 0;
        }

        return response()->json([
            'result'            => true,
            'auditor'           => $auditor,
            'auditee'           => $auditee,
            'latestByPerbaikan' => $latestByPerbaikan,
        ]);
    }

    // Update Progress & Keterangan (TrnInternalAuditDtl) + tambah/hapus Lampiran (TrnInternalAuditLampiran)
    public function updateStatus(Request $req) {
        try {
            \DB::beginTransaction();

            $dtl = TrnInternalAuditDtl::find($req->DtlId);
            if (!$dtl) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan']);
            }

            $hdr = TrnInternalAuditHdr::find($dtl->AuditId);
            if ($hdr && (int) $hdr->FlagApproval1 === 1 && (int) $hdr->FlagApproval2 === 1) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Status Kondisi sudah Completed, hubungi Auditor untuk mengubah Workflow terlebih dahulu']);
            }

            // Progress hanya dianggap "diisi" kalau Keterangan ada isinya - dropdown Progress
            // sudah otomatis ter-prefill dari update sebelumnya (lihat handler change.perbaikanSP
            // di frontend), jadi nilai dropdown Progress sendiri TIDAK bisa dipakai buat mendeteksi
            // apakah user memang berniat submit Progress baru atau cuma menambahkan Lampiran saja.
            $hasProgressInput = !empty(trim((string) $req->Keterangan));
            $isReguler = $hdr && $hdr->JenisAudit === 'Pemeriksaan Reguler';

            // Approval Auditee yang input sendiri tidak perlu approval lagi (langsung Approved)
            $perbaikanInput = !empty($req->PerbaikanId) ? TrnInternalAuditPerbaikan::find($req->PerbaikanId) : null;
            $isSelfApprover = $isReguler && $perbaikanInput && $this->isLoginApprovalAuditee($perbaikanInput->ApprovalAuditee);
            $needApproval   = $isReguler && !$isSelfApprover;

            // Reguler: nilai Progress ditentukan Auditor. Entry Auditee hanya membawa Keterangan/Lampiran,
            // jadi nilainya mengikuti progress Approved terakhir (carry-forward) supaya Total Progress tidak berubah.
            $progressValue = $req->Progress ?: 0;
            if ($isReguler && !empty($req->PerbaikanId)) {
                $latestApproved = TrnInternalAuditProgress::where('PerbaikanId', $req->PerbaikanId)
                    ->where('ApprovalStatus', 'Approved')
                    ->orderBy('CreatedAt', 'desc')
                    ->orderBy('ProgressId', 'desc')
                    ->first();
                $progressValue = $latestApproved ? $latestApproved->Progress : 0;
            }

            // Reguler: baik Progress maupun Lampiran baru dari Auditee sama-sama butuh approval -
            // tidak boleh numpuk (input Progress ATAUPUN upload Lampiran baru) selama masih ada
            // entry Pending (Progress atau Lampiran) untuk PerbaikanId yang sama.
            $hasPendingApproval = false;
            if ($isReguler && !empty($req->PerbaikanId)) {
                $hasPendingApproval = TrnInternalAuditProgress::where('PerbaikanId', $req->PerbaikanId)
                        ->where('AuditRole', 'Auditee')
                        ->where('ApprovalStatus', 'Pending')
                        ->exists()
                    || TrnInternalAuditLampiran::where('PerbaikanId', $req->PerbaikanId)
                        ->where('AuditRole', 'Auditee')
                        ->where('ApprovalStatus', 'Pending')
                        ->exists();
            }

            if ($hasProgressInput) {
                if (empty($req->PerbaikanId)) {
                    \DB::rollBack();
                    return response()->json(['result' => false, 'msg' => 'Pilih Tindak Lanjut yang mau diupdate terlebih dahulu']);
                }

                if ($isReguler && $hasPendingApproval) {
                    \DB::rollBack();
                    return response()->json(['result' => false, 'msg' => 'Masih ada progress/lampiran yang menunggu approval untuk Tindak Lanjut ini']);
                }

                // Reguler: PIC yang update dianggap sisi Auditee, progress-nya berstatus Pending
                // dulu sampai di-approve/reject oleh Approval Auditee. Fraud/Spesial: sifatnya
                // lintas pihak (bisa siapa saja, mis. HR), disimpan sebagai Auditor & langsung
                // Approved (tidak butuh approval) - tampil di satu kolom sama seperti
                // internal-audit/detail-modal.blade.php.
                $progress = new TrnInternalAuditProgress();
                $progress->AuditId        = $dtl->AuditId;
                $progress->DtlId          = $req->DtlId;
                $progress->PerbaikanId    = $req->PerbaikanId;
                $progress->AuditRole      = $isReguler ? 'Auditee' : 'Auditor';
                $progress->Progress       = $progressValue;
                $progress->Keterangan     = $req->Keterangan;
                $progress->ApprovalStatus = $needApproval ? 'Pending' : 'Approved';
                if ($isSelfApprover) {
                    $progress->ApprovedBy = $this->editBy;
                    $progress->ApprovedAt = now();
                }
                $progress->CreatedBy      = $this->editBy;
                $progress->CreatedAt      = now();
                $progress->save();

                \DB::table('TrnInternalAuditProgress')
                    ->where('ProgressId', $progress->ProgressId)
                    ->update(['CreatedAt' => \DB::raw('now()')]);
            }

            $destPath = "netfile/InternalAudit";

            // Hapus lampiran lama yang di-uncheck user - sekarang difilter by PerbaikanId
            $deletedIds = json_decode($req->DeletedLampiranIds, true) ?: [];
            if (!empty($deletedIds)) {
                $toDelete = TrnInternalAuditLampiran::whereIn('LampiranId', $deletedIds)
                    ->where('PerbaikanId', $req->PerbaikanId)
                    ->get();

                foreach ($toDelete as $lp) {
                    if (!empty($lp->FileLampiran)) {
                        $this->libs->removeFile($lp->FileLampiran, $destPath);
                    }
                    $lp->delete();
                }
            }

            // Tambah lampiran baru - wajib ada PerbaikanId
            $namaFileList     = json_decode($req->NamaFileList, true) ?: [];
            $fileLampiranList = json_decode($req->FileLampiranList, true) ?: [];

            if (!empty($fileLampiranList)) {
                if (empty($req->PerbaikanId)) {
                    \DB::rollBack();
                    return response()->json(['result' => false, 'msg' => 'Pilih Tindak Lanjut yang mau diupdate terlebih dahulu']);
                }

                if ($isReguler && $hasPendingApproval) {
                    \DB::rollBack();
                    return response()->json(['result' => false, 'msg' => 'Masih ada progress/lampiran yang menunggu approval untuk Tindak Lanjut ini']);
                }

                // Lampiran baru dari PIC Auditee butuh approval Approval Auditee dulu untuk Jenis
                // Audit Reguler - sama seperti Progress. Fraud/Spesial: langsung Approved & dicatat
                // sebagai AuditRole 'Auditor' (tampil di kolom tunggal), sama pola dengan Progress.
                $temp = "upload/temp";

                foreach ($fileLampiranList as $i => $file) {
                    if (!empty($file)) {
                        $this->libs->moveFile($file, $temp, $destPath);

                        $lampiran = new TrnInternalAuditLampiran();
                        $lampiran->DtlId          = $dtl->DtlId;
                        $lampiran->PerbaikanId    = $req->PerbaikanId;
                        $lampiran->NamaFile       = $namaFileList[$i] ?? '';
                        $lampiran->FileLampiran   = $file;
                        $lampiran->AuditRole      = $isReguler ? 'Auditee' : 'Auditor';
                        $lampiran->ApprovalStatus = $needApproval ? 'Pending' : 'Approved';
                        if ($isSelfApprover) {
                            $lampiran->ApprovedBy = $this->editBy;
                            $lampiran->ApprovedAt = now();
                        }
                        $lampiran->CreatedBy      = $this->editBy;
                        $lampiran->CreatedAt      = now();
                        $lampiran->save();
                    }
                }
            }

            $totalProgress = $this->calcTotalProgress($dtl->AuditId);

            \DB::commit();
            return response()->json(['result' => true, 'TotalProgress' => $totalProgress]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    // Approve SEKALIGUS semua entry Pending (Progress + Lampiran) milik Auditee untuk 1 PerbaikanId.
    // Dipakai card "Menunggu Approval Anda": 1 klik memproses seluruh paket yang dikirim PIC Auditee.
    // Backend menolak pengiriman baru selama masih ada Pending di PerbaikanId yang sama, jadi semua
    // entry Pending di sini pasti berasal dari 1 pengiriman.
    public function approvePending(Request $req) {
        try {
            \DB::beginTransaction();

            $perbaikan = TrnInternalAuditPerbaikan::find($req->PerbaikanId);
            if (!$perbaikan || !$this->isLoginApprovalAuditee($perbaikan->ApprovalAuditee)) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Anda bukan Approval Auditee untuk Tindak Lanjut ini']);
            }

            $progressList = TrnInternalAuditProgress::where('PerbaikanId', $perbaikan->PerbaikanId)
                ->where('AuditRole', 'Auditee')
                ->where('ApprovalStatus', 'Pending')
                ->get();

            $lampiranList = TrnInternalAuditLampiran::where('PerbaikanId', $perbaikan->PerbaikanId)
                ->where('AuditRole', 'Auditee')
                ->where('ApprovalStatus', 'Pending')
                ->get();

            if ($progressList->isEmpty() && $lampiranList->isEmpty()) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan atau sudah diproses']);
            }

            foreach ($progressList as $progress) {
                $progress->ApprovalStatus = 'Approved';
                $progress->ApprovedBy     = $this->editBy;
                $progress->ApprovedAt     = now();
                $progress->save();
            }

            foreach ($lampiranList as $lampiran) {
                $lampiran->ApprovalStatus = 'Approved';
                $lampiran->ApprovedBy     = $this->editBy;
                $lampiran->ApprovedAt     = now();
                $lampiran->save();
            }

            $dtl = TrnInternalAuditDtl::find($perbaikan->DtlId);
            $totalProgress = $dtl ? $this->calcTotalProgress($dtl->AuditId) : 0;

            \DB::commit();
            return response()->json(['result' => true, 'TotalProgress' => $totalProgress]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    // Reject SEKALIGUS semua entry Pending (Progress + Lampiran) milik Auditee untuk 1 PerbaikanId.
    // Catatan penolakan wajib diisi dan berlaku untuk seluruh entry.
    public function rejectPending(Request $req) {
        try {
            \DB::beginTransaction();

            if (empty(trim((string) $req->Note))) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Catatan penolakan wajib diisi']);
            }

            $perbaikan = TrnInternalAuditPerbaikan::find($req->PerbaikanId);
            if (!$perbaikan || !$this->isLoginApprovalAuditee($perbaikan->ApprovalAuditee)) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Anda bukan Approval Auditee untuk Tindak Lanjut ini']);
            }

            $progressList = TrnInternalAuditProgress::where('PerbaikanId', $perbaikan->PerbaikanId)
                ->where('AuditRole', 'Auditee')
                ->where('ApprovalStatus', 'Pending')
                ->get();

            $lampiranList = TrnInternalAuditLampiran::where('PerbaikanId', $perbaikan->PerbaikanId)
                ->where('AuditRole', 'Auditee')
                ->where('ApprovalStatus', 'Pending')
                ->get();

            if ($progressList->isEmpty() && $lampiranList->isEmpty()) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan atau sudah diproses']);
            }

            foreach ($progressList as $progress) {
                $progress->ApprovalStatus = 'Rejected';
                $progress->ApprovalNote   = $req->Note;
                $progress->ApprovedBy     = $this->editBy;
                $progress->ApprovedAt     = now();
                $progress->save();
            }

            foreach ($lampiranList as $lampiran) {
                $lampiran->ApprovalStatus = 'Rejected';
                $lampiran->ApprovalNote   = $req->Note;
                $lampiran->ApprovedBy     = $this->editBy;
                $lampiran->ApprovedAt     = now();
                $lampiran->save();
            }

            \DB::commit();
            return response()->json(['result' => true]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    // Approve progress Pending milik Auditee - hanya boleh oleh user yang = ApprovalAuditee
    // untuk PerbaikanId terkait
    public function approveProgress(Request $req) {
        try {
            \DB::beginTransaction();

            $progress = TrnInternalAuditProgress::find($req->ProgressId);
            if (!$progress || $progress->AuditRole !== 'Auditee' || $progress->ApprovalStatus !== 'Pending') {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan atau sudah diproses']);
            }

            $perbaikan = TrnInternalAuditPerbaikan::find($progress->PerbaikanId);
            if (!$perbaikan || !$this->isLoginApprovalAuditee($perbaikan->ApprovalAuditee)) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Anda bukan Approval Auditee untuk Tindak Lanjut ini']);
            }

            $progress->ApprovalStatus = 'Approved';
            $progress->ApprovedBy     = $this->editBy;
            $progress->ApprovedAt     = now();
            $progress->save();

            $totalProgress = $this->calcTotalProgress($progress->AuditId);

            \DB::commit();
            return response()->json(['result' => true, 'TotalProgress' => $totalProgress]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    // Reject progress Pending milik Auditee - wajib sertakan Catatan Penolakan
    public function rejectProgress(Request $req) {
        try {
            \DB::beginTransaction();

            if (empty(trim((string) $req->Note))) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Catatan penolakan wajib diisi']);
            }

            $progress = TrnInternalAuditProgress::find($req->ProgressId);
            if (!$progress || $progress->AuditRole !== 'Auditee' || $progress->ApprovalStatus !== 'Pending') {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan atau sudah diproses']);
            }

            $perbaikan = TrnInternalAuditPerbaikan::find($progress->PerbaikanId);
            if (!$perbaikan || !$this->isLoginApprovalAuditee($perbaikan->ApprovalAuditee)) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Anda bukan Approval Auditee untuk Tindak Lanjut ini']);
            }

            $progress->ApprovalStatus = 'Rejected';
            $progress->ApprovalNote   = $req->Note;
            $progress->ApprovedBy     = $this->editBy;
            $progress->ApprovedAt     = now();
            $progress->save();

            \DB::commit();
            return response()->json(['result' => true]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    // Approve lampiran Pending milik Auditee - hanya boleh oleh user yang = ApprovalAuditee
    // untuk PerbaikanId terkait. Mirror approveProgress(), tapi TIDAK mempengaruhi TotalProgress.
    public function approveLampiran(Request $req) {
        try {
            \DB::beginTransaction();

            $lampiran = TrnInternalAuditLampiran::find($req->LampiranId);
            if (!$lampiran || $lampiran->AuditRole !== 'Auditee' || $lampiran->ApprovalStatus !== 'Pending') {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan atau sudah diproses']);
            }

            $perbaikan = TrnInternalAuditPerbaikan::find($lampiran->PerbaikanId);
            if (!$perbaikan || !$this->isLoginApprovalAuditee($perbaikan->ApprovalAuditee)) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Anda bukan Approval Auditee untuk Tindak Lanjut ini']);
            }

            $lampiran->ApprovalStatus = 'Approved';
            $lampiran->ApprovedBy     = $this->editBy;
            $lampiran->ApprovedAt     = now();
            $lampiran->save();

            \DB::commit();
            return response()->json(['result' => true]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    // Reject lampiran Pending milik Auditee - wajib sertakan Catatan Penolakan
    public function rejectLampiran(Request $req) {
        try {
            \DB::beginTransaction();

            if (empty(trim((string) $req->Note))) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Catatan penolakan wajib diisi']);
            }

            $lampiran = TrnInternalAuditLampiran::find($req->LampiranId);
            if (!$lampiran || $lampiran->AuditRole !== 'Auditee' || $lampiran->ApprovalStatus !== 'Pending') {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan atau sudah diproses']);
            }

            $perbaikan = TrnInternalAuditPerbaikan::find($lampiran->PerbaikanId);
            if (!$perbaikan || !$this->isLoginApprovalAuditee($perbaikan->ApprovalAuditee)) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Anda bukan Approval Auditee untuk Tindak Lanjut ini']);
            }

            $lampiran->ApprovalStatus = 'Rejected';
            $lampiran->ApprovalNote   = $req->Note;
            $lampiran->ApprovedBy     = $this->editBy;
            $lampiran->ApprovedAt     = now();
            $lampiran->save();

            \DB::commit();
            return response()->json(['result' => true]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }
}