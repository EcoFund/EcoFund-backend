<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignImage extends Model
{
    protected $table = 'campaign_images';

    protected $primaryKey = 'id_image';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id_campaign',
        'image_url',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'id_campaign', 'id_campaign');
    }
}
