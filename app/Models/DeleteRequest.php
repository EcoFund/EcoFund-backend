<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeleteRequest extends Model
{
    protected $table = 'delete_requests';
    

    protected $fillable = [
        'id_campaign', 'id_user', 'alasan', 'status',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'id_campaign', 'id_campaign');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
