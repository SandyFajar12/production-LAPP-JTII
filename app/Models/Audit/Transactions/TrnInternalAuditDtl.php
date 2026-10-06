<?php
namespace App\Models\Audit\Transactions;

use Illuminate\Database\Eloquent\Model;

class TrnInternalAuditDtl extends Model
{
    protected $table      = 'TrnInternalAuditDtl';
    protected $primaryKey = 'DtlId';
    public $timestamps    = false;

    protected $fillable = [
        'AuditId',
        'JudulTemuan',
        'DetailTemuan',
        'IndikasiAwal',
        'Resiko',
        'Kerugian',
        'PeraturanSOP',
        'SanksiKaryawan',
        'SanksiAtasan',
        'RekomendasiAuditor',
        'PengembalianKerugian',
        'CreatedBy',
    ];
}