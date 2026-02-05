<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripPost extends Model
{
    protected $fillable = [
        'user_id',
        'trip_id',
        'caption',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class, 'trip_post_id');
    }

}
