<?php
namespace App\Models\Audit\Transactions;

use Illuminate\Database\Eloquent\Model;

class TrnInternalAuditPerbaikan extends Model
{
    protected $table      = 'TrnInternalAuditPerbaikan';
    protected $primaryKey = 'PerbaikanId';
    public $timestamps    = false;

    protected $fillable = [
        'DtlId', 'Action', 'Deadline', 'DetailPerbaikan', 'ApprovalAuditee', 'CreatedAt', 'CreatedBy',
    ];
}