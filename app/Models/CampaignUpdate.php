<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignUpdate extends Model
{
    protected $table = 'campaign_updates';

    protected $primaryKey = 'id_update';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id_campaign',
        'judul',
        'deskripsi',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'id_campaign', 'id_campaign');
    }
}
