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
use App\Models\Spd\Master\MstKaryawan;


class InternalAuditController extends Controller
{
    public function index(Request $req) {
        $data['isFullPage'] = $this->getPageType($req);
        $data['loginUserId'] = $this->editBy;
        $data['loginUserName'] = \DB::table('SecUser')->where('UserId', $this->editBy)->value('EmpName');
        $data['loginNikId'] = $this->resolveLoginNikId();
        return view('audit/transactions/internal-audit/index')->with($data)->render();
    }

    public function getList(Request $req) {
        $qry = TrnInternalAuditHdr::select(
                \DB::raw('"TrnInternalAuditHdr"."AuditId" as "AuditId"'),
                \DB::raw('"TrnInternalAuditHdr"."JudulKondisi" as "JudulKondisi"'),
                \DB::raw('"TrnInternalAuditHdr"."JenisAudit" as "JenisAudit"'),
                \DB::raw('"TrnInternalAuditHdr"."DepartmentAudity" as "DepartmentAudity"'),
                // Cancelled ditandai murni lewat FlagApproval1 & FlagApproval2 = 3 (keduanya),
                // BUKAN dari kolom Status literal - supaya IT bisa memaksa keluar dari Cancelled
                // setelah lewat batas 3 hari cukup dengan UPDATE FlagApproval1/2 dari 3 ke 0 lewat
                // query manual, tanpa perlu sentuh kolom Status atau CancelledBy sama sekali.
                \DB::raw('CASE
                    WHEN COALESCE("TrnInternalAuditHdr"."FlagApproval1", 0) = 3 AND COALESCE("TrnInternalAuditHdr"."FlagApproval2", 0) = 3 THEN \'Cancelled\'
                    WHEN COALESCE("TrnInternalAuditHdr"."FlagApproval1", 0) = 4 AND COALESCE("TrnInternalAuditHdr"."FlagApproval2", 0) = 4 THEN \'CancelPending\'
                    WHEN COALESCE("TrnInternalAuditHdr"."FlagApproval1", 0) = 1 AND COALESCE("TrnInternalAuditHdr"."FlagApproval2", 0) = 1 THEN \'Completed\'
                    ELSE \'In Progress\'
                END as "Status"'),
                \DB::raw('"MstDepartemen"."DeptName" as "DepartmentAudityName"'),
                \DB::raw('"TrnInternalAuditHdr"."CreatedAt" as "CreatedAt"'),
                \DB::raw('"TrnInternalAuditHdr"."UpdatedAt" as "UpdatedAt"'),
                \DB::raw('COALESCE(canceluser."EmpName", \'\') as "CancelledByName"'),
                \DB::raw('COALESCE(dtl."JumlahTemuan", 0) as "JumlahTemuan"'),
                \DB::raw('COALESCE(progagg."TotalProgress", 0) as "TotalProgress"'),
                \DB::raw('dtl."CreatedByNames" as "CreatedByNames"'),
                \DB::raw('"TrnInternalAuditHdr"."Approval1" as "Approval1"'),
                \DB::raw('"TrnInternalAuditHdr"."FlagApproval1" as "FlagApproval1"'),
                \DB::raw('COALESCE(secuser1."EmpName", \'\') as "ApproverName1"'),
                \DB::raw('COALESCE(mstapprover1."UserId", \'\') as "ApproverUserId1"'),
                \DB::raw('"TrnInternalAuditHdr"."Approval2" as "Approval2"'),
                \DB::raw('"TrnInternalAuditHdr"."FlagApproval2" as "FlagApproval2"'),
                \DB::raw('COALESCE(secuser2."EmpName", \'\') as "ApproverName2"'),
                \DB::raw('COALESCE(mstapprover2."UserId", \'\') as "ApproverUserId2"'),
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
            // Total Progress dihitung dari level Tindak Lanjut (TrnInternalAuditPerbaikan), BUKAN level
            // Temuan (DtlId) - 1 Temuan bisa punya banyak Tindak Lanjut, masing-masing dengan histori
            // progress sendiri. Ambil progress Approved TERAKHIR per PerbaikanId, lalu rata-ratakan
            // SEMUA Tindak Lanjut yang ada di bawah 1 AuditId (flat, lintas Temuan).
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
            ->leftJoin('SecUser as canceluser', 'canceluser.UserId', '=', 'TrnInternalAuditHdr.CancelledBy')
            ->orderByRaw('COALESCE("TrnInternalAuditHdr"."UpdatedAt", "TrnInternalAuditHdr"."CreatedAt") DESC NULLS LAST');

        if (!empty($req->JudulKondisi)) {
            $qry->where('TrnInternalAuditHdr.JudulKondisi', 'like', '%' . $req->JudulKondisi . '%');
        }

        if (!empty($req->NotifAuditId)) {
            $qry->where('TrnInternalAuditHdr.AuditId', $req->NotifAuditId);
        }

        return $this->grid->load($qry);
    }

    // Status cancel disimpan di FlagApproval1 & FlagApproval2 (tanpa kolom baru):
    //   4 & 4 = pengajuan Cancelled, menunggu konfirmasi Approval 1/2 (badge kuning)
    //   3 & 3 = Cancelled final (badge merah)
    private function isCancelPending($hdr) {
        return $hdr && (int) $hdr->FlagApproval1 === 4 && (int) $hdr->FlagApproval2 === 4;
    }

    private function isCancelFinal($hdr) {
        return $hdr && (int) $hdr->FlagApproval1 === 3 && (int) $hdr->FlagApproval2 === 3;
    }

    private function isCancelLocked($hdr) {
        return $this->isCancelPending($hdr) || $this->isCancelFinal($hdr);
    }

    private function cancelLockedMsg($hdr) {
        return $this->isCancelPending($hdr)
            ? 'Pemeriksaan ini sedang menunggu approval pembatalan'
            : 'Pemeriksaan telah dibatalkan dan tidak dapat diproses lebih lanjut';
    }

    // Login user = Approver 1 atau Approver 2 pada pemeriksaan ini
    private function isLoginApproverOf($hdr) {
        $approverIds = array_values(array_filter([$hdr->Approval1, $hdr->Approval2]));
        if (empty($approverIds)) return false;

        return \DB::table('MstApprover')
            ->whereIn('ApproverId', $approverIds)
            ->where('UserId', $this->editBy)
            ->exists();
    }

    // Guard umum untuk aksi ubah data: data harus ada, tidak sedang/sudah Cancelled,
    // dan login user = PIC Auditor / Approver 1 / Approver 2. Return pesan error atau null kalau lolos.
    private function guardWriteAudit($hdr) {
        if (!$hdr) return 'Data tidak ditemukan';
        if ($this->isCancelLocked($hdr)) return $this->cancelLockedMsg($hdr);
        if (!$this->canActOnAudit($hdr->AuditId)) return 'Anda bukan PIC Auditor atau Approver pada pemeriksaan ini';
        return null;
    }

    // PENGAJUAN cancel (PIC Auditor / Approval 1 / Approval 2): flag jadi 4 & 4 = menunggu konfirmasi.
    // CancelledBy & UpdatedAt dipakai sebagai nama + tanggal pengaju.
    public function cancelAudit(Request $req) {
        try {
            $data = TrnInternalAuditHdr::find($req->AuditId);
            if (!$data) {
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan']);
            }

            if (!$this->canActOnAudit($data->AuditId)) {
                return response()->json(['result' => false, 'msg' => 'Anda bukan PIC Auditor atau Approver pada pemeriksaan ini']);
            }

            if ($this->isCancelLocked($data)) {
                return response()->json(['result' => false, 'msg' => $this->cancelLockedMsg($data)]);
            }

            if (empty($data->Approval1) && empty($data->Approval2)) {
                return response()->json(['result' => false, 'msg' => 'Approval 1 / Approval 2 belum diisi, pembatalan tidak dapat diajukan']);
            }

            $data->FlagApproval1 = 4;
            $data->FlagApproval2 = 4;
            $data->Status        = 'In Progress';
            $data->CancelledBy   = $this->editBy;
            $data->UpdatedAt     = now();
            $data->save();

            return response()->json(['result' => true, 'Status' => 'CancelPending']);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    // YES - Approval 1/2 menyetujui pembatalan: flag 3 & 3 = Cancelled final.
    // CancelledBy & UpdatedAt sengaja tidak diubah (tetap nama + tanggal pengaju).
    public function approveCancel(Request $req) {
        try {
            $data = TrnInternalAuditHdr::find($req->AuditId);
            if (!$data) {
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan']);
            }

            if (!$this->isCancelPending($data)) {
                return response()->json(['result' => false, 'msg' => 'Pengajuan pembatalan tidak ditemukan atau sudah diproses']);
            }

            if (!$this->isLoginApproverOf($data)) {
                return response()->json(['result' => false, 'msg' => 'Hanya Approval 1 atau Approval 2 yang dapat memproses pembatalan ini']);
            }

            $data->FlagApproval1 = 3;
            $data->FlagApproval2 = 3;
            $data->Status        = 'Cancelled';
            $data->save();

            return response()->json(['result' => true, 'Status' => 'Cancelled']);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    // NO - Approval 1/2 menolak pembatalan: kembali ke In Progress (flag 0 & 0), pengaju dikosongkan.
    public function rejectCancel(Request $req) {
        try {
            $data = TrnInternalAuditHdr::find($req->AuditId);
            if (!$data) {
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan']);
            }

            if (!$this->isCancelPending($data)) {
                return response()->json(['result' => false, 'msg' => 'Pengajuan pembatalan tidak ditemukan atau sudah diproses']);
            }

            if (!$this->isLoginApproverOf($data)) {
                return response()->json(['result' => false, 'msg' => 'Hanya Approval 1 atau Approval 2 yang dapat memproses pembatalan ini']);
            }

            $data->FlagApproval1 = 0;
            $data->FlagApproval2 = 0;
            $data->Status        = 'In Progress';
            $data->CancelledBy   = null;
            $data->UpdatedAt     = now();
            $data->save();

            return response()->json(['result' => true, 'Status' => 'In Progress']);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    // Boleh beraktivitas di pemeriksaan ini jika login user = PIC Auditor (Model 'Informasi Kondisi')
    // ATAU Approver 1 / Approver 2 pada pemeriksaan tersebut.
    private function canActOnAudit($auditId) {
        $hdr = TrnInternalAuditHdr::find($auditId);
        if (!$hdr) return false;

        $nikId = $this->resolveLoginNikId();
        if ($nikId !== '' && TrnInternalAuditPic::where('AuditId', $auditId)
                ->where('Model', 'Informasi Kondisi')
                ->where('NikId', $nikId)
                ->exists()) {
            return true;
        }

        $approverIds = array_values(array_filter([$hdr->Approval1, $hdr->Approval2]));
        if (empty($approverIds)) return false;

        return \DB::table('MstApprover')
            ->whereIn('ApproverId', $approverIds)
            ->where('UserId', $this->editBy)
            ->exists();
    }

    // Resolve NikId (short format) untuk user yang sedang login, dipakai buat filter dropdown
    // Tindak Lanjut berdasarkan List PIC. Logic sama dengan yang dipakai StatusPenyelesaianController.
    private function resolveLoginNikId() {
        $loginUser = \DB::table('SecUser')->where('UserId', $this->editBy)->first();

        $loginKaryawan = null;
        if ($loginUser && !empty($loginUser->NikId)) {
            $loginKaryawan = \DB::table('MstKaryawan')->where('NikFull', $loginUser->NikId)->first();
        }

        // Fallback: SecUser.NikId kadang keisi format pendek yang match langsung ke
        // MstKaryawan.NikId (bukan ke NikFull).
        if (!$loginKaryawan && $loginUser && !empty($loginUser->NikId)) {
            $loginKaryawan = \DB::table('MstKaryawan')->where('NikId', $loginUser->NikId)->first();
        }

        if (!$loginKaryawan && $loginUser && !empty($loginUser->EmpName)) {
            $candidates = \DB::table('MstKaryawan')->where('Name', $loginUser->EmpName)->get();
            if ($candidates->count() === 1) {
                $loginKaryawan = $candidates->first();
            } else {
                $partialCandidates = \DB::table('MstKaryawan')
                    ->where('Name', 'ILIKE', '%' . $loginUser->EmpName . '%')
                    ->orWhereRaw('? ILIKE (\'%\' || "Name" || \'%\')', [$loginUser->EmpName])
                    ->get();

                if ($partialCandidates->count() === 1) {
                    $loginKaryawan = $partialCandidates->first();
                }
            }
        }

        return $loginKaryawan ? $loginKaryawan->NikId : '';
    }

    // Dropdown "Pilih Tindak Lanjut" untuk form Progress Temuan (detail-modal.blade.php).
    // Ranahnya Auditor - tampilkan SEMUA entry Perbaikan milik Temuan ini, tanpa filter PIC
    // (beda dengan StatusPenyelesaianController::getPerbaikanDropdown yang ranahnya Auditee
    // dan memang harus dibatasi PIC login).
    public function getPerbaikanDropdown(Request $req) {
        $data = \DB::table('TrnInternalAuditPerbaikan')
            ->where('DtlId', $req->DtlId)
            ->orderBy('Deadline')
            ->get(['PerbaikanId', 'Action', 'DetailPerbaikan', 'Deadline']);

        $result = [];
        foreach ($data as $item) {
            $label = $item->Action ? ('[' . $item->Action . '] ') : '';
            $label .= strip_tags($item->DetailPerbaikan ?: '-');
            $result[] = ['PerbaikanId' => $item->PerbaikanId, 'Label' => $label];
        }

        return response()->json(['result' => true, 'data' => $result]);
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

    // Histori gabungan SEMUA entry Perbaikan (Tindak Lanjut) milik 1 Temuan (DtlId), dipakai
    // buat isi panel Auditor/Auditee di atas grid saat row Temuan diklik - beda dengan
    // getProgressList() di atas yang scoped ke 1 PerbaikanId saja (dipakai buat prefill input
    // Progress baru sesuai Tindak Lanjut yang sedang dipilih di dropdown).
    public function getProgressListByDtl(Request $req) {
        $dtlId = $req->DtlId;

        $progressSelect = [
            \DB::raw('"TrnInternalAuditProgress"."ProgressId" as "ItemId"'),
            \DB::raw('"TrnInternalAuditProgress"."PerbaikanId" as "PerbaikanId"'),
            \DB::raw('"TrnInternalAuditProgress"."Progress" as "Progress"'),
            \DB::raw('"TrnInternalAuditProgress"."Keterangan" as "Keterangan"'),
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
            \DB::raw('"TrnInternalAuditLampiran"."CreatedAt" as "CreatedAt"'),
            \DB::raw('su."EmpName" as "CreatedByName"'),
            \DB::raw('pb."Action" as "PerbaikanAction"'),
            \DB::raw('pb."DetailPerbaikan" as "PerbaikanDetail"'),
            \DB::raw('\'Lampiran\' as "ItemType"'),
            \DB::raw('"TrnInternalAuditLampiran"."FileLampiran" as "FileLampiran"'),
        ];

        $auditor = TrnInternalAuditProgress::select($progressSelect)
            ->leftJoin('SecUser as su', 'su.UserId', '=', 'TrnInternalAuditProgress.CreatedBy')
            ->leftJoin('TrnInternalAuditPerbaikan as pb', 'pb.PerbaikanId', '=', 'TrnInternalAuditProgress.PerbaikanId')
            ->where('TrnInternalAuditProgress.DtlId', $dtlId)
            ->where('AuditRole', 'Auditor')
            ->get()
            ->concat(
                TrnInternalAuditLampiran::select($lampiranSelect)
                    ->leftJoin('SecUser as su', 'su.UserId', '=', 'TrnInternalAuditLampiran.CreatedBy')
                    ->leftJoin('TrnInternalAuditPerbaikan as pb', 'pb.PerbaikanId', '=', 'TrnInternalAuditLampiran.PerbaikanId')
                    ->where('TrnInternalAuditLampiran.DtlId', $dtlId)
                    ->where('AuditRole', 'Auditor')
                    ->get()
            )
            ->sortByDesc('CreatedAt')->values();

        // Sisi Auditor cuma boleh lihat progress/lampiran Auditee yang sudah Approved - yang masih
        // Pending/Rejected disembunyikan total (belum "sah" secara alur approval).
        $auditee = TrnInternalAuditProgress::select($progressSelect)
            ->leftJoin('SecUser as su', 'su.UserId', '=', 'TrnInternalAuditProgress.CreatedBy')
            ->leftJoin('TrnInternalAuditPerbaikan as pb', 'pb.PerbaikanId', '=', 'TrnInternalAuditProgress.PerbaikanId')
            ->where('TrnInternalAuditProgress.DtlId', $dtlId)
            ->where('AuditRole', 'Auditee')
            ->where('TrnInternalAuditProgress.ApprovalStatus', 'Approved')
            ->get()
            ->concat(
                TrnInternalAuditLampiran::select($lampiranSelect)
                    ->leftJoin('SecUser as su', 'su.UserId', '=', 'TrnInternalAuditLampiran.CreatedBy')
                    ->leftJoin('TrnInternalAuditPerbaikan as pb', 'pb.PerbaikanId', '=', 'TrnInternalAuditLampiran.PerbaikanId')
                    ->where('TrnInternalAuditLampiran.DtlId', $dtlId)
                    ->where('AuditRole', 'Auditee')
                    ->where('TrnInternalAuditLampiran.ApprovalStatus', 'Approved')
                    ->get()
            )
            ->sortByDesc('CreatedAt')->values();

        // Progress terakhir per entry Perbaikan (yang Approved saja) - dikirim sekali di sini
        // supaya JS bisa prefill field Progress (%) murni client-side saat dropdown diganti,
        // tanpa perlu ajax lagi tiap kali user pilih item dropdown.
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

    public function saveProgress(Request $req) {
        try {
            \DB::beginTransaction();

            $perbaikan = TrnInternalAuditPerbaikan::find($req->PerbaikanId);
            if (!$perbaikan) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Tindak Lanjut / Perbaikan tidak ditemukan']);
            }

            $dtl = TrnInternalAuditDtl::find($perbaikan->DtlId);
            if (!$dtl) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan']);
            }

            if (!$this->canActOnAudit($dtl->AuditId)) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Anda bukan PIC Auditor atau Approver pada pemeriksaan ini']);
            }

            $hdr = TrnInternalAuditHdr::find($dtl->AuditId);

            if ($hdr && $this->isCancelLocked($hdr)) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => $this->cancelLockedMsg($hdr)]);
            }

            if ($hdr && (int) $hdr->FlagApproval1 === 1 && (int) $hdr->FlagApproval2 === 1) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Status Kondisi sudah Completed, ubah salah satu Workflow ke In Progress terlebih dahulu untuk menambahkan progress']);
            }
            $isReguler = $hdr && $hdr->JenisAudit === 'Pemeriksaan Reguler';

            $hasKeterangan = !empty(trim((string) $req->Keterangan));

            if ($hasKeterangan) {
                // Progress ditentukan Auditor (berlaku untuk Reguler maupun Fraud)
                $progressValue = $req->Progress ?: 0;

                $data = new TrnInternalAuditProgress();
                $data->AuditId     = $dtl->AuditId;
                $data->DtlId       = $dtl->DtlId;
                $data->PerbaikanId = $perbaikan->PerbaikanId;
                $data->AuditRole   = 'Auditor';
                $data->Progress    = $progressValue;
                $data->Keterangan  = $req->Keterangan;
                $data->ApprovalStatus = 'Approved';
                $data->CreatedBy   = $this->editBy;
                $data->CreatedAt   = now();
                $data->save();

                \DB::table('TrnInternalAuditProgress')
                    ->where('ProgressId', $data->ProgressId)
                    ->update(['CreatedAt' => \DB::raw('now()')]);
            }

            // Lampiran sekarang berlaku untuk semua Jenis Audit, dikaitkan ke PerbaikanId yang sedang dipilih
            $destPath = "netfile/InternalAudit";

            $deletedIds = json_decode($req->DeletedLampiranIds, true) ?: [];
            if (!empty($deletedIds)) {
                $toDelete = TrnInternalAuditLampiran::whereIn('LampiranId', $deletedIds)
                    ->where('PerbaikanId', $perbaikan->PerbaikanId)
                    ->get();

                foreach ($toDelete as $lp) {
                    if (!empty($lp->FileLampiran)) {
                        $this->libs->removeFile($lp->FileLampiran, $destPath);
                    }
                    $lp->delete();
                }
            }

            $namaFileList     = json_decode($req->NamaFileList, true) ?: [];
            $fileLampiranList = json_decode($req->FileLampiranList, true) ?: [];

            if (!empty($fileLampiranList)) {
                $temp = "upload/temp";

                foreach ($fileLampiranList as $i => $file) {
                    if (!empty($file)) {
                        $this->libs->moveFile($file, $temp, $destPath);

                        $lampiran = new TrnInternalAuditLampiran();
                        $lampiran->DtlId        = $dtl->DtlId;
                        $lampiran->PerbaikanId  = $perbaikan->PerbaikanId;
                        $lampiran->NamaFile     = $namaFileList[$i] ?? '';
                        $lampiran->FileLampiran = $file;
                        $lampiran->AuditRole    = 'Auditor';
                        $lampiran->ApprovalStatus = 'Approved';
                        $lampiran->CreatedBy    = $this->editBy;
                        $lampiran->CreatedAt    = now();
                        $lampiran->save();
                    }
                }
            }

            // Sama seperti getList() - agregasi progress harus per PerbaikanId (Tindak Lanjut)
            // dulu, baru dirata-ratakan lintas Temuan dalam 1 AuditId.
            $totalProgress = \DB::table('TrnInternalAuditPerbaikan as pb')
                ->join('TrnInternalAuditDtl as dtl', 'dtl.DtlId', '=', 'pb.DtlId')
                ->leftJoin(\DB::raw('(
                    SELECT DISTINCT ON ("PerbaikanId") "PerbaikanId", "Progress"
                    FROM "TrnInternalAuditProgress"
                    WHERE "ApprovalStatus" = \'Approved\'
                    ORDER BY "PerbaikanId", "CreatedAt" DESC, "ProgressId" DESC
                ) as lp'), 'lp.PerbaikanId', '=', 'pb.PerbaikanId')
                ->where('dtl.AuditId', $dtl->AuditId)
                ->selectRaw('ROUND(AVG(COALESCE(lp."Progress", 0))) as total')
                ->value('total');

            \DB::commit();
            return response()->json(['result' => true, 'TotalProgress' => $totalProgress ?: 0]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }    

    public function save(Request $req) {
        try {
            $data = new TrnInternalAuditHdr();
            $data->JudulKondisi = $req->JudulKondisi;
            $data->JenisAudit   = $req->JenisAudit;
            $data->DepartmentAudity = $req->DepartmentAudity;
            $data->Status            = 'In Progress';
            $data->Approval1         = $req->Approval1 ?: null;
            $data->Approval2         = $req->Approval2 ?: null;
            $data->NoSuratTugas         = $req->NoSuratTugas ?: null;
            $data->NoGaroon             = $req->NoGaroon ?: null;
            $periodeError = $this->validatePeriode($req);
            if ($periodeError) {
                return response()->json(['result' => false, 'msg' => $periodeError]);
            }
            $data->PeriodeStart         = $req->PeriodeStart ?: null;
            $data->PeriodeEnd           = $req->PeriodeEnd ?: null;
            $data->TanggalPemeriksaan   = $req->TanggalPemeriksaan ?: null;
            $data->TanggalUpload        = $req->TanggalUpload ?: null;
            $data->CreatedAt        = now();
            $data->save();

            $this->savePic($data->AuditId, $req->ListPic);

            return response()->json(['result' => true, 'AuditId' => $data->AuditId]);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function update(Request $req) {
        try {
            $data = TrnInternalAuditHdr::find($req->AuditId);
            if (!$data) {
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan']);
            }

            $guardMsg = $this->guardWriteAudit($data);
            if ($guardMsg) {
                return response()->json(['result' => false, 'msg' => $guardMsg]);
            }

            $data->JudulKondisi = $req->JudulKondisi;
            $data->JenisAudit   = $req->JenisAudit;
            $data->DepartmentAudity  = $req->DepartmentAudity;
            $data->Approval1         = $req->Approval1 ?: null;
            $data->Approval2         = $req->Approval2 ?: null;
            $data->NoSuratTugas         = $req->NoSuratTugas ?: null;
            $data->NoGaroon             = $req->NoGaroon ?: null;
            $periodeError = $this->validatePeriode($req);
            if ($periodeError) {
                return response()->json(['result' => false, 'msg' => $periodeError]);
            }
            $data->PeriodeStart         = $req->PeriodeStart ?: null;
            $data->PeriodeEnd           = $req->PeriodeEnd ?: null;
            $data->TanggalPemeriksaan   = $req->TanggalPemeriksaan ?: null;
            $data->TanggalUpload        = $req->TanggalUpload ?: null;
            // Catatan: FlagApproval1/2 & Status sengaja TIDAK direset di sini walau approver diganti.
            // Kalau nanti perlu reset approval saat approver diganti, tambahkan logic di sini.
            $data->UpdatedAt         = now();
            $data->save();

            $this->savePic($data->AuditId, $req->ListPic);

            return response()->json(['result' => true]);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    // PeriodeStart/PeriodeEnd (format Y-m-d). End boleh kosong, tapi kalau diisi harus SETELAH Start (tidak boleh sama).
    private function validatePeriode(Request $req) {
        $start = trim((string) $req->PeriodeStart);
        $end   = trim((string) $req->PeriodeEnd);

        if ($start === '' && $end === '') return null;
        if ($start === '') return 'Tanggal mulai Periode Pemeriksaan wajib diisi';

        $ds = \DateTime::createFromFormat('Y-m-d', $start);
        if (!$ds || $ds->format('Y-m-d') !== $start) {
            return 'Format tanggal mulai Periode Pemeriksaan tidak valid';
        }

        if ($end !== '') {
            $de = \DateTime::createFromFormat('Y-m-d', $end);
            if (!$de || $de->format('Y-m-d') !== $end) {
                return 'Format tanggal selesai Periode Pemeriksaan tidak valid';
            }
            if ($end <= $start) {
                return 'Tanggal selesai Periode Pemeriksaan harus setelah tanggal mulai';
            }
        }

        return null;
    }

    private function savePic($auditId, $picListJson) {
        // Auto-grant/revoke privilege Status Penyelesaian TIDAK lagi dari PIC di sini
        // (Informasi Kondisi) - sudah dipindah ke PIC Auditee per Tindak Lanjut, lihat
        // savePerbaikanList(). Fungsi ini sekarang murni simpan List PIC Auditor saja.
        TrnInternalAuditPic::where('AuditId', $auditId)->delete();
        $arrNikId = json_decode($picListJson, true) ?: [];

        foreach ($arrNikId as $nikId) {
            if (empty($nikId)) continue;
            $pic = new TrnInternalAuditPic();
            $pic->AuditId   = $auditId;
            $pic->Model     = 'Informasi Kondisi';
            $pic->NikId     = $nikId;
            $pic->CreatedAt = now();
            $pic->CreatedBy = $this->editBy;
            $pic->save();
        }
    }

    // Resolve NikId (List PIC) -> SecUser (UserId & RoleCode).
    // Jalur utama: MstKaryawan.NikFull -> SecUser.NikId.
    // Fallback: cocokkan lewat EmpName kalau NikFull tidak match (data SecUser.NikId legacy kadang tidak
    // konsisten) - fallback ini hanya dipakai kalau hasilnya persis 1 kandidat unik.
    private function resolveUserByNikId($nikId) {
        $karyawan = \DB::table('MstKaryawan')->where('NikId', $nikId)->first();
        if (!$karyawan) {
            \Log::warning("StatusPenyelesaian Access: NikId PIC '{$nikId}' tidak ditemukan di MstKaryawan, dilewati.");
            return null;
        }

        $user = null;
        if (!empty($karyawan->NikFull)) {
            $user = \DB::table('SecUser')->where('NikId', $karyawan->NikFull)->first();
        }

        if (!$user && !empty($karyawan->Name)) {
            $candidates = \DB::table('SecUser')->where('EmpName', $karyawan->Name)->get();
            if ($candidates->count() === 1) {
                $user = $candidates->first();
            } else {
                \Log::warning("StatusPenyelesaian Access: gagal resolve UserId untuk NikId '{$nikId}' ({$karyawan->Name}) - NikFull '{$karyawan->NikFull}' tidak match SecUser, fallback by Name ditemukan {$candidates->count()} kandidat (butuh tepat 1).");
            }
        }

        if (!$user) {
            \Log::warning("StatusPenyelesaian Access: UserId tidak ditemukan untuk NikId PIC '{$nikId}', dilewati.");
            return null;
        }

        return $user;
    }

    // Auto-grant akses menu Status Penyelesaian (IA0021) untuk NikId yang ditambahkan ke List PIC,
    // TAPI hanya kalau user tsb BELUM punya akses ke Audit List (IA0020) MAUPUN Status Penyelesaian (IA0021)
    // sama sekali (baik lewat Role/SecRule maupun lewat direct privilege/SecUserMenu).
    // Kalau salah satu dari dua menu itu sudah bisa diakses (dari sumber manapun), skip - tidak insert apa-apa.
    private function autoGrantStatusPenyelesaianAccess($arrNikId) {
        $menuCodesToCheck = ['IA0020', 'IA0021']; // audit-list, status-penyelesaian
        $targetMenuCode   = 'IA0021';             // yang akan di-insert kalau belum ada akses sama sekali

        foreach ($arrNikId as $nikId) {
            if (empty($nikId)) continue;

            $user = $this->resolveUserByNikId($nikId);
            if (!$user) continue;

            $hasAccess = false;
            foreach ($menuCodesToCheck as $menuCode) {
                $viaRole = \DB::table('SecRule')
                    ->where('RoleCode', $user->RoleCode)
                    ->where('MenuCode', $menuCode)
                    ->exists();

                if ($viaRole) { $hasAccess = true; break; }

                $viaUserMenu = \DB::table('SecUserMenu')
                    ->where('UserId', $user->UserId)
                    ->where('MenuCode', $menuCode)
                    ->exists();

                if ($viaUserMenu) { $hasAccess = true; break; }
            }

            if ($hasAccess) continue;

            $alreadyExists = \DB::table('SecUserMenu')
                ->where('UserId', $user->UserId)
                ->where('MenuCode', $targetMenuCode)
                ->where('ObjCode', 'SHOW')
                ->where('MenuObjCode', 'PAGE')
                ->exists();

            if (!$alreadyExists) {
                \DB::table('SecUserMenu')->insert([
                    'UserId'      => $user->UserId,
                    'MenuCode'    => $targetMenuCode,
                    'MenuObjCode' => 'PAGE',
                    'ObjCode'     => 'SHOW',
                    'RuleAccess'  => 1,
                ]);
            }
        }
    }

    // Auto-revoke akses menu Status Penyelesaian (IA0021) untuk NikId yang DIHILANGKAN dari List
    // PIC Auditee (Model='Tindak Lanjut'), TAPI hanya kalau NikId tsb sudah TIDAK jadi PIC
    // Auditee di Tindak Lanjut manapun lagi (lintas Temuan/Audit). Kalau masih jadi PIC di
    // Tindak Lanjut lain, privilege tetap dipertahankan.
    private function autoRevokeStatusPenyelesaianAccessByPic($removedNikIds) {
        $targetMenuCode = 'IA0021';

        foreach ($removedNikIds as $nikId) {
            if (empty($nikId)) continue;

            $stillPicElsewhere = \DB::table('TrnInternalAuditPic')
                ->where('NikId', $nikId)
                ->where('Model', 'Tindak Lanjut')
                ->exists();

            if ($stillPicElsewhere) continue;

            $user = $this->resolveUserByNikId($nikId);
            if (!$user) continue;

            \DB::table('SecUserMenu')
                ->where('UserId', $user->UserId)
                ->where('MenuCode', $targetMenuCode)
                ->where('ObjCode', 'SHOW')
                ->where('MenuObjCode', 'PAGE')
                ->delete();
        }
    }

    public function getPicListArray(Request $req) {
        $data = TrnInternalAuditPic::select(
                \DB::raw('"TrnInternalAuditPic"."NikId" as "NikId"'),
                \DB::raw('COALESCE("MstKaryawan"."Name", \'\') as "Name"')
            )
            ->leftJoin('MstKaryawan', 'MstKaryawan.NikId', '=', 'TrnInternalAuditPic.NikId')
            ->where('AuditId', $req->AuditId)
            ->where('Model', 'Informasi Kondisi')
            ->get();

        return response()->json(['result' => true, 'data' => $data]);
    }

    // Approval Auditee di Tindak Lanjut/Perbaikan: SEMUA approver (tanpa filter ApproverFor/TypeApprover
    // seperti cboApproval1/cboApproval2), tapi nama yang sama hanya tampil 1x
    public function getAllApprover(Request $req) {
        $data = \DB::table('MstApprover')
            ->select(
                \DB::raw('MIN("MstApprover"."ApproverId") as "id"'),
                \DB::raw('COALESCE("SecUser"."EmpName", \'\') as "text"')
            )
            ->leftJoin('SecUser', 'SecUser.UserId', '=', 'MstApprover.UserId')
            ->groupBy('SecUser.EmpName')
            ->orderBy('SecUser.EmpName')
            ->get();

        return response()->json($data);
    }

    // Khusus Internal Audit: List PIC unfiltered department, tidak pakai services/combo/subOrdinate (shared)
    public function getSubOrdinateAll(Request $req) {
        $sql = MstKaryawan::select([
                'NikId as id', 'Name as text'
            ])
            ->where(["Status" => '1'])
            ->orderBy("Name");

        return $this->libs->renderDataCombo($sql);
    }

    public function updateStatusHdr(Request $req) {
        try {
            $data = TrnInternalAuditHdr::find($req->AuditId);
            if (!$data) {
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan']);
            }
            $data->Status    = $req->Status;
            $data->UpdatedAt = now();
            $data->save();

            return response()->json(['result' => true, 'Status' => $data->Status]);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    // Simpan Workflow 1 / Workflow 2 (FlagApproval1/2), sekaligus auto-set Status jadi
    // Completed kalau kedua flag sudah Approved. Dipanggil dari tombol Update Progress
    // di detail-modal.blade.php.
    public function updateWorkflow(Request $req) {
        try {
            \DB::beginTransaction();

            $hdr = TrnInternalAuditHdr::find($req->AuditId);
            if (!$hdr) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan']);
            }

            if ($this->isCancelLocked($hdr)) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => $this->cancelLockedMsg($hdr)]);
            }

            $loginUserId = $this->editBy;

            if ($req->has('FlagApproval1') && (int) $req->FlagApproval1 !== (int) $hdr->FlagApproval1) {
                $approver1 = \DB::table('MstApprover')->where('ApproverId', $hdr->Approval1)->first();
                if (!$approver1 || (string) $approver1->UserId !== (string) $loginUserId) {
                    \DB::rollBack();
                    return response()->json(['result' => false, 'msg' => 'Anda bukan Approver 1 untuk data ini']);
                }
                $hdr->FlagApproval1 = (int) $req->FlagApproval1;
            }

            if ($req->has('FlagApproval2') && (int) $req->FlagApproval2 !== (int) $hdr->FlagApproval2) {
                if ((int) $hdr->FlagApproval1 !== 1) {
                    \DB::rollBack();
                    return response()->json(['result' => false, 'msg' => 'Workflow 1 harus Approved terlebih dahulu']);
                }
                $approver2 = \DB::table('MstApprover')->where('ApproverId', $hdr->Approval2)->first();
                if (!$approver2 || (string) $approver2->UserId !== (string) $loginUserId) {
                    \DB::rollBack();
                    return response()->json(['result' => false, 'msg' => 'Anda bukan Approver 2 untuk data ini']);
                }
                $hdr->FlagApproval2 = (int) $req->FlagApproval2;
            }

            // Status murni turunan dari kedua flag saat ini — jadi selalu konsisten
            // walau salah satu flag pernah diubah manual lewat DB.
            $hdr->Status = ((int) $hdr->FlagApproval1 === 1 && (int) $hdr->FlagApproval2 === 1) ? 'Completed' : 'In Progress';

            $hdr->UpdatedAt = now();
            $hdr->save();

            \DB::commit();
            return response()->json([
                'result'        => true,
                'Status'        => $hdr->Status,
                'FlagApproval1' => (int) $hdr->FlagApproval1,
                'FlagApproval2' => (int) $hdr->FlagApproval2,
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
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
                \DB::raw('COALESCE(lp."JumlahLampiran", 0) as "JumlahLampiran"'),
                \DB::raw('COALESCE(pb."JumlahPerbaikan", 0) as "JumlahPerbaikan"'),
                \DB::raw('pb."DeadlineList" as "DeadlineList"'),
                \DB::raw('picpb."PicAuditeeList" as "PicAuditeeList"'),
                \DB::raw('progpb."ProgressList" as "ProgressList"'),
                \DB::raw('COALESCE(lprog."Progress", 0) as "Progress"')
            )
            ->leftJoin(\DB::raw('(
                SELECT "DtlId", COUNT(*) as "JumlahLampiran"
                FROM "TrnInternalAuditLampiran"
                GROUP BY "DtlId"
            ) as lp'), 'lp.DtlId', '=', 'TrnInternalAuditDtl.DtlId')
            ->leftJoin(\DB::raw('(
                SELECT "DtlId", COUNT(*) as "JumlahPerbaikan", STRING_AGG("Deadline"::text, \'|\' ORDER BY "Deadline") as "DeadlineList"
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

    // Union PIC (Model = 'Tindak Lanjut') dari semua entry Perbaikan milik satu Temuan (DtlId)
    public function getPerbaikanListArray(Request $req) {
        $list = \DB::table('TrnInternalAuditPerbaikan')
            ->where('DtlId', $req->DtlId)
            ->orderBy('PerbaikanId')
            ->get();

        $data = [];
        foreach ($list as $item) {
            $pics = \DB::table('TrnInternalAuditPic')
                ->select(
                    \DB::raw('"TrnInternalAuditPic"."NikId" as "NikId"'),
                    \DB::raw('COALESCE("MstKaryawan"."Name", \'\') as "Name"')
                )
                ->leftJoin('MstKaryawan', 'MstKaryawan.NikId', '=', 'TrnInternalAuditPic.NikId')
                ->where('PerbaikanId', $item->PerbaikanId)
                ->where('Model', 'Tindak Lanjut')
                ->get();

            $approvalAuditeeName = '';
            if (!empty($item->ApprovalAuditee)) {
                $approvalAuditeeName = \DB::table('MstApprover')
                    ->leftJoin('SecUser', 'SecUser.UserId', '=', 'MstApprover.UserId')
                    ->where('MstApprover.ApproverId', $item->ApprovalAuditee)
                    ->value('SecUser.EmpName') ?? '';
            }

            $data[] = [
                'PerbaikanId'          => $item->PerbaikanId,
                'Action'               => $item->Action,
                'Deadline'             => $item->Deadline,
                'DetailPerbaikan'      => $item->DetailPerbaikan,
                'ApprovalAuditee'      => $item->ApprovalAuditee,
                'ApprovalAuditeeName'  => $approvalAuditeeName,
                'ListPic'              => $pics,
            ];
        }

        return response()->json(['result' => true, 'data' => $data]);
    }

    public function saveTemuan(Request $req) {
        try {
            \DB::beginTransaction();

            $allowedTags = '<strong><b><em><i><u><br>';

            $hdrGuard = TrnInternalAuditHdr::find($req->AuditId);
            if ($hdrGuard && $this->isCancelLocked($hdrGuard)) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => $this->cancelLockedMsg($hdrGuard)]);
            }

            $data = new TrnInternalAuditDtl();
            $data->AuditId              = $req->AuditId;
            $data->JudulTemuan          = strip_tags($req->JudulTemuan, $allowedTags);
            $data->DetailTemuan         = strip_tags($req->DetailTemuan, $allowedTags);
            $data->IndikasiAwal         = strip_tags($req->IndikasiAwal, $allowedTags);
            $data->Resiko               = strip_tags($req->Resiko, $allowedTags);
            $data->Kerugian             = $req->Kerugian ?: 0;
            $data->PeraturanSOP         = strip_tags($req->PeraturanSOP, $allowedTags);
            $data->SanksiKaryawan       = strip_tags($req->SanksiKaryawan, $allowedTags);
            $data->SanksiAtasan         = strip_tags($req->SanksiAtasan, $allowedTags);
            $data->RekomendasiAuditor   = strip_tags($req->RekomendasiAuditor, $allowedTags);
            $data->PengembalianKerugian = strip_tags($req->PengembalianKerugian, $allowedTags);
            $data->CreatedAt            = now();
            $data->CreatedBy            = $this->editBy;
            $data->save();

            $this->savePerbaikanList($data->DtlId, $req->PerbaikanList, $allowedTags);

            // Lampiran (bisa lebih dari satu per temuan)
            $namaFileList     = json_decode($req->NamaFileList, true) ?: [];
            $fileLampiranList = json_decode($req->FileLampiranList, true) ?: [];

            if (!empty($fileLampiranList)) {
                $temp     = "upload/temp";
                $destPath = "netfile/InternalAudit";

                foreach ($fileLampiranList as $i => $file) {
                    if (!empty($file)) {
                        $this->libs->moveFile($file, $temp, $destPath);

                        $lampiran = new TrnInternalAuditLampiran();
                        $lampiran->DtlId        = $data->DtlId;
                        $lampiran->NamaFile     = $namaFileList[$i] ?? '';
                        $lampiran->FileLampiran = $file;
                        $lampiran->CreatedAt    = now();
                        $lampiran->save();
                    }
                }
            }

            \DB::commit();
            return response()->json(['result' => true, 'DtlId' => $data->DtlId]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    // PerbaikanList (JSON string dari JS): array of {PerbaikanId?, Action, Deadline, DetailPerbaikan, ListPic:[{NikId,Name}]}
    // Diff-based per DtlId (bukan wipe & reinsert lagi) - dipakai saat save (baru) maupun update
    // (edit existing Dtl). Entry yang punya PerbaikanId di-UPDATE di tempat (PerbaikanId-nya
    // dipertahankan), bukan dihapus lalu dibuat ulang - supaya histori TrnInternalAuditProgress
    // yang sudah terikat ke PerbaikanId itu (via FK) tidak bentrok waktu disimpan ulang.
    // Entry lama yang sudah tidak ada di list baru (dihapus user) baru di-delete, TAPI hanya
    // kalau belum ada histori Progress-nya - kalau sudah ada, dipertahankan diam-diam (silent-
    // keep) supaya histori progress-nya tidak hilang / FK tidak bentrok.
    private function savePerbaikanList($dtlId, $perbaikanListJson, $allowedTags) {
        $perbaikanList = json_decode($perbaikanListJson, true) ?: [];

        $keepIds = [];
        foreach ($perbaikanList as $item) {
            if (!empty($item['PerbaikanId'])) {
                $keepIds[] = $item['PerbaikanId'];
            }
        }

        $oldPerbaikanIds = \DB::table('TrnInternalAuditPerbaikan')->where('DtlId', $dtlId)->pluck('PerbaikanId')->toArray();
        $idsToDelete = array_diff($oldPerbaikanIds, $keepIds);

        // NikId PIC Auditee (Model='Tindak Lanjut') lama SEBELUM ada perubahan apapun di Temuan
        // ini - dipakai buat deteksi siapa yang dihapus dari List PIC (auto-revoke privilege
        // Status Penyelesaian IA0021).
        $oldNikIds = \DB::table('TrnInternalAuditPic')
            ->whereIn('PerbaikanId', $oldPerbaikanIds)
            ->where('Model', 'Tindak Lanjut')
            ->pluck('NikId')
            ->toArray();

        if (!empty($idsToDelete)) {
            $idsWithProgress = \DB::table('TrnInternalAuditProgress')
                ->whereIn('PerbaikanId', $idsToDelete)
                ->distinct()
                ->pluck('PerbaikanId')
                ->toArray();

            $safeToDelete = array_diff($idsToDelete, $idsWithProgress);

            if (!empty($safeToDelete)) {
                \DB::table('TrnInternalAuditPic')->whereIn('PerbaikanId', $safeToDelete)->where('Model', 'Tindak Lanjut')->delete();
                \DB::table('TrnInternalAuditPerbaikan')->whereIn('PerbaikanId', $safeToDelete)->delete();
            }
        }

        $newNikIds = [];

        foreach ($perbaikanList as $item) {
            if (!empty($item['PerbaikanId'])) {
                $perbaikan = TrnInternalAuditPerbaikan::find($item['PerbaikanId']);
                if ($perbaikan) {
                    $perbaikan->Action          = $item['Action'] ?? '';
                    $perbaikan->Deadline        = !empty($item['Deadline']) ? $item['Deadline'] : null;
                    $perbaikan->DetailPerbaikan = strip_tags($item['DetailPerbaikan'] ?? '', $allowedTags);
                    $perbaikan->ApprovalAuditee = $item['ApprovalAuditee'] ?? null;
                    $perbaikan->save();

                    \DB::table('TrnInternalAuditPic')->where('PerbaikanId', $perbaikan->PerbaikanId)->where('Model', 'Tindak Lanjut')->delete();
                    foreach (($item['ListPic'] ?? []) as $pic) {
                        if (empty($pic['NikId'])) continue;
                        \DB::table('TrnInternalAuditPic')->insert([
                            'PerbaikanId' => $perbaikan->PerbaikanId,
                            'Model'       => 'Tindak Lanjut',
                            'NikId'       => $pic['NikId'],
                            'CreatedAt'   => now(),
                            'CreatedBy'   => $this->editBy,
                        ]);
                        $newNikIds[] = $pic['NikId'];
                    }
                    continue;
                }
            }

            $perbaikan = new TrnInternalAuditPerbaikan();
            $perbaikan->DtlId           = $dtlId;
            $perbaikan->Action          = $item['Action'] ?? '';
            $perbaikan->Deadline        = !empty($item['Deadline']) ? $item['Deadline'] : null;
            $perbaikan->DetailPerbaikan = strip_tags($item['DetailPerbaikan'] ?? '', $allowedTags);
            $perbaikan->ApprovalAuditee = $item['ApprovalAuditee'] ?? null;
            $perbaikan->CreatedAt       = now();
            $perbaikan->CreatedBy       = $this->editBy;
            $perbaikan->save();

            foreach (($item['ListPic'] ?? []) as $pic) {
                if (empty($pic['NikId'])) continue;
                \DB::table('TrnInternalAuditPic')->insert([
                    'PerbaikanId' => $perbaikan->PerbaikanId,
                    'Model'       => 'Tindak Lanjut',
                    'NikId'       => $pic['NikId'],
                    'CreatedAt'   => now(),
                    'CreatedBy'   => $this->editBy,
                ]);
                $newNikIds[] = $pic['NikId'];
            }
        }

        // Auto-grant privilege menu Status Penyelesaian (IA0021) buat PIC Auditee baru yang
        // muncul di Tindak Lanjut manapun pada Temuan ini.
        $this->autoGrantStatusPenyelesaianAccess(array_unique($newNikIds));

        // Auto-revoke untuk NikId yang hilang dari List PIC di Temuan ini - tapi hanya kalau
        // NikId itu sudah tidak jadi PIC Auditee di Tindak Lanjut manapun lagi (lintas
        // Temuan/Audit), bukan cuma dicek di Temuan ini saja.
        $removedNikIds = array_diff($oldNikIds, $newNikIds);
        if (!empty($removedNikIds)) {
            $this->autoRevokeStatusPenyelesaianAccessByPic($removedNikIds);
        }
    }

    public function deleteTemuan(Request $req) {
        try {
            \DB::beginTransaction();

            $destPath = "netfile/InternalAudit";
            $dtlGuard = TrnInternalAuditDtl::find($req->DtlId);
            $guardMsg = $this->guardWriteAudit($dtlGuard ? TrnInternalAuditHdr::find($dtlGuard->AuditId) : null);
            if ($guardMsg) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => $guardMsg]);
            }

            $lampiranList = TrnInternalAuditLampiran::where('DtlId', $req->DtlId)->get();

            foreach ($lampiranList as $lp) {
                if (!empty($lp->FileLampiran)) {
                    $this->libs->removeFile($lp->FileLampiran, $destPath);
                }
                $lp->delete();
            }

            $perbaikanIds = \DB::table('TrnInternalAuditPerbaikan')->where('DtlId', $req->DtlId)->pluck('PerbaikanId');
            if ($perbaikanIds->count() > 0) {
                \DB::table('TrnInternalAuditPic')->whereIn('PerbaikanId', $perbaikanIds)->where('Model', 'Tindak Lanjut')->delete();
                \DB::table('TrnInternalAuditPerbaikan')->whereIn('PerbaikanId', $perbaikanIds)->delete();
            }

            $data = TrnInternalAuditDtl::find($req->DtlId);
            if ($data) {
                $data->delete();
            }

            \DB::commit();
            return response()->json(['result' => true]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function getLampiranList(Request $req) {
        $qry = TrnInternalAuditLampiran::select('LampiranId', 'DtlId', 'NamaFile', 'FileLampiran', 'CreatedAt')
            ->where('DtlId', $req->DtlId);

        return $this->grid->load($qry);
    }

    // Dipakai JS mode edit: butuh array polos (bukan format grid) untuk isi ulang lampiranTemuanTemp
    // Sekarang difilter by PerbaikanId (bukan DtlId lagi) - lampiran terkait ke entry Tindak Lanjut tertentu
    public function getLampiranListArray(Request $req) {
        // Auditor cuma boleh lihat lampiran yang sudah Approved (termasuk miliknya sendiri, yang
        // otomatis Approved saat diupload) - lampiran Auditee yang masih Pending/Rejected
        // disembunyikan sampai disetujui, sama seperti aturan Progress.
        $data = TrnInternalAuditLampiran::select(
                \DB::raw('"TrnInternalAuditLampiran"."LampiranId" as "LampiranId"'),
                \DB::raw('"TrnInternalAuditLampiran"."DtlId" as "DtlId"'),
                \DB::raw('"TrnInternalAuditLampiran"."PerbaikanId" as "PerbaikanId"'),
                \DB::raw('"TrnInternalAuditLampiran"."NamaFile" as "NamaFile"'),
                \DB::raw('"TrnInternalAuditLampiran"."FileLampiran" as "FileLampiran"'),
                \DB::raw('"TrnInternalAuditLampiran"."CreatedAt" as "CreatedAt"'),
                \DB::raw('"TrnInternalAuditLampiran"."AuditRole" as "AuditRole"'),
                \DB::raw('COALESCE(su."EmpName", \'\') as "CreatedByName"')
            )
            ->leftJoin('SecUser as su', 'su.UserId', '=', 'TrnInternalAuditLampiran.CreatedBy')
            ->where('PerbaikanId', $req->PerbaikanId)
            ->where('ApprovalStatus', 'Approved')
            ->orderBy('LampiranId')
            ->get();

        return response()->json(['result' => true, 'data' => $data]);
    }

    public function updateTemuan(Request $req) {
        try {
            \DB::beginTransaction();

            $data = TrnInternalAuditDtl::find($req->DtlId);
            $guardMsg = $this->guardWriteAudit($data ? TrnInternalAuditHdr::find($data->AuditId) : null);
            if ($guardMsg) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => $guardMsg]);
            }
            if (!$data) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan']);
            }

            $allowedTags = '<strong><b><em><i><u><br>';

            $data->JudulTemuan          = strip_tags($req->JudulTemuan, $allowedTags);
            $data->DetailTemuan         = strip_tags($req->DetailTemuan, $allowedTags);
            $data->IndikasiAwal         = strip_tags($req->IndikasiAwal, $allowedTags);
            $data->Resiko               = strip_tags($req->Resiko, $allowedTags);
            $data->Kerugian             = $req->Kerugian ?: 0;
            $data->PeraturanSOP         = strip_tags($req->PeraturanSOP, $allowedTags);
            $data->SanksiKaryawan       = strip_tags($req->SanksiKaryawan, $allowedTags);
            $data->SanksiAtasan         = strip_tags($req->SanksiAtasan, $allowedTags);
            $data->RekomendasiAuditor   = strip_tags($req->RekomendasiAuditor, $allowedTags);
            $data->PengembalianKerugian = strip_tags($req->PengembalianKerugian, $allowedTags);
            $data->save();

            $this->savePerbaikanList($data->DtlId, $req->PerbaikanList, $allowedTags);

            $destPath = "netfile/InternalAudit";

            // Hapus lampiran lama yang di-uncheck user di mode edit
            $deletedIds = json_decode($req->DeletedLampiranIds, true) ?: [];
            if (!empty($deletedIds)) {
                $toDelete = TrnInternalAuditLampiran::whereIn('LampiranId', $deletedIds)
                    ->where('DtlId', $data->DtlId)
                    ->get();

                foreach ($toDelete as $lp) {
                    if (!empty($lp->FileLampiran)) {
                        $this->libs->removeFile($lp->FileLampiran, $destPath);
                    }
                    $lp->delete();
                }
            }

            // Tambah lampiran baru yang ditambahkan user saat mode edit
            $namaFileList     = json_decode($req->NamaFileList, true) ?: [];
            $fileLampiranList = json_decode($req->FileLampiranList, true) ?: [];

            if (!empty($fileLampiranList)) {
                $temp = "upload/temp";

                foreach ($fileLampiranList as $i => $file) {
                    if (!empty($file)) {
                        $this->libs->moveFile($file, $temp, $destPath);

                        $lampiran = new TrnInternalAuditLampiran();
                        $lampiran->DtlId        = $data->DtlId;
                        $lampiran->NamaFile     = $namaFileList[$i] ?? '';
                        $lampiran->FileLampiran = $file;
                        $lampiran->CreatedAt    = now();
                        $lampiran->save();
                    }
                }
            }

            \DB::commit();
            return response()->json(['result' => true]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }    

    public function saveLampiran(Request $req) {
        try {
            $temp     = "upload/temp";
            $destPath = "netfile/InternalAudit";

            if (!empty($req->FileLampiran)) {
                $this->libs->moveFile($req->FileLampiran, $temp, $destPath);
            }

            $data = new TrnInternalAuditLampiran();
            $data->DtlId        = $req->DtlId;
            $data->NamaFile     = $req->NamaFile;
            $data->FileLampiran = $req->FileLampiran;
            $data->CreatedAt    = now();
            $data->save();

            return response()->json(['result' => true]);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function deleteLampiran(Request $req) {
        try {
            $destPath = "netfile/InternalAudit";
            $data = TrnInternalAuditLampiran::find($req->LampiranId);
            if (!$data) {
                return response()->json(['result' => false, 'msg' => 'Data tidak ditemukan']);
            }

            if (!empty($data->FileLampiran)) {
                $this->libs->removeFile($data->FileLampiran, $destPath);
            }

            $data->delete();

            return response()->json(['result' => true]);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => $e->getMessage()]);
        }
    }
}