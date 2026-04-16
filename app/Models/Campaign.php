<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Campaign extends Model
{
    protected $fillable = [
        'user_id', 'title', 'slug', 'description',
        'category', 'goal_amount', 'deadline', 'image', 'status',
    ];
 
    protected $casts = ['deadline' => 'date', 'goal_amount' => 'decimal:2'];
 
    public function user()       { return $this->belongsTo(User::class); }
    public function donations()  { return $this->hasMany(Donation::class); }
 
    // Accessor: persentase terkumpul
    public function getPercentageAttribute(): int
    {
        $raised = $this->donations()->where('status', 'paid')->sum('amount');
        return $this->goal_amount > 0
            ? (int) min(100, round(($raised / $this->goal_amount) * 100))
            : 0;
    }
}