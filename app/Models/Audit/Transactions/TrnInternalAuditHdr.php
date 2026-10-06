<?php
namespace App\Models\Audit\Transactions;

use Illuminate\Database\Eloquent\Model;

class TrnInternalAuditHdr extends Model
{
    protected $table      = 'TrnInternalAuditHdr';
    protected $primaryKey = 'AuditId';
    public $timestamps    = false;

    protected $fillable = [
        'JudulKondisi',
        'JenisAudit',
        'DepartmentAudity',
        'Status',
        'Approval1',
        'FlagApproval1',
        'Approval2',
        'FlagApproval2',
        'NoSuratTugas',
        'NoGaroon',
        'PeriodeStart',
        'PeriodeEnd',
        'TanggalPemeriksaan',
        'TanggalUpload',
        'CancelledBy',
    ];
}