<?php
namespace App\Models\Audit\Transactions;

use Illuminate\Database\Eloquent\Model;

class TrnInternalAuditPic extends Model
{
    protected $table      = 'TrnInternalAuditPic';
    protected $primaryKey = 'PicId';
    public $timestamps    = false;

    protected $fillable = [
        'AuditId', 'PerbaikanId', 'Model', 'NikId', 'CreatedAt', 'CreatedBy',
    ];
}