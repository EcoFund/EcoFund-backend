<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $table = 'withdrawals';
    protected $primaryKey = 'id_withdraw';

    protected $fillable = [
        'id_campaign',
        'jumlah',
        'nama_bank',
        'nomor_rekening',
        'atas_nama',
        'status',
        'catatan_admin',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'id_campaign', 'id_campaign');
    }
}
