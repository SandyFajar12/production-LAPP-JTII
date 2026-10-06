<?php
namespace App\Models\Audit\Transactions;

use Illuminate\Database\Eloquent\Model;

class TrnInternalAuditLampiran extends Model
{
    protected $table      = 'TrnInternalAuditLampiran';
    protected $primaryKey = 'LampiranId';
    public $timestamps    = false;

    protected $fillable = [
        'DtlId', 'PerbaikanId', 'NamaFile', 'FileLampiran', 'CreatedBy', 'AuditRole',
        'ApprovalStatus', 'ApprovalNote', 'ApprovedBy', 'ApprovedAt',
    ];
}