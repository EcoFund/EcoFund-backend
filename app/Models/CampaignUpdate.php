<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignUpdate extends Model
{
<<<<<<< HEAD
     protected $table = 'campaign_updates';
    protected $primaryKey = 'id_update';

    protected $fillable = [
        'id_campaign', 'judul', 'deskripsi', 'gambar',
=======
    protected $table = 'campaign_updates';

    protected $primaryKey = 'id_update';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id_campaign',
        'judul',
        'deskripsi',
>>>>>>> 69639d6685384b02e0fdfa32d80d01c137f030ba
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'id_campaign', 'id_campaign');
    }
}
