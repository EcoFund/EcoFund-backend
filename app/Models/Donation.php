<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    protected $table = 'donasi';

    protected $primaryKey = 'id_donasi';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id_campaign',
        'nama_donatur',
        'jumlah',
        'is_anonymous',
        'status',
        'payment_url',
        'xendit_invoice_id',
    ];

    protected $casts = [
        'jumlah' => 'integer',
        'is_anonymous' => 'boolean',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'id_campaign', 'id_campaign');
    }
}
