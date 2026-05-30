<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $table = 'campaigns';

    protected $primaryKey = 'id_campaign';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id_user',
        'kategori_id',
        'identities',
        'judul',
        'slug',
        'deskripsi',
        'target_donasi',
        'dana_terkumpul',
        'lokasi',
        'gambar',
        'payment_method',
        'supporting_document',
        'status',
        'reason',
        'tanggal_mulai',
        'tanggal_selesai',
    ];

    protected $casts = [
        'target_donasi' => 'integer',
        'dana_terkumpul' => 'integer',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function kategori()
    {
        return $this->belongsTo(Kategori::class, 'kategori_id', 'id_kategori');
    }

    public function donations()
    {
        return $this->hasMany(Donation::class, 'id_campaign', 'id_campaign');
    }

    public function deleteRequests()
    {
        return $this->hasMany(DeleteRequest::class, 'id_campaign', 'id_campaign');
    }

    public function images()
    {
        return $this->hasMany(CampaignImage::class, 'id_campaign', 'id_campaign');
    }

    public function getPercentageAttribute(): int
    {
        return $this->target_donasi > 0
            ? (int) min(100, round(($this->dana_terkumpul / $this->target_donasi) * 100))
            : 0;
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }
}
