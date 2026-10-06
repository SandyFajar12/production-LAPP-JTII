<?php
namespace App\Models\Audit\Transactions;

use Illuminate\Database\Eloquent\Model;

class TrnInternalAuditProgress extends Model
{
    protected $table      = 'TrnInternalAuditProgress';
    protected $primaryKey = 'ProgressId';
    public $timestamps    = false;

    protected $fillable = [
        'AuditId',
        'DtlId',
        'PerbaikanId',
        'AuditRole',
        'Progress',
        'Keterangan',
        'ApprovalStatus',
        'ApprovalNote',
        'ApprovedBy',
        'ApprovedAt',
        'CreatedBy',
        'CreatedAt',
    ];
}