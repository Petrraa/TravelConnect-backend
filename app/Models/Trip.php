<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Trip extends Model
{
    protected $fillable = [
    'user_id',
    'title',
    'desription',
    'destination',
    'description',
    'start_date',
    'end_date',
    'budget',
    'travel_style',
    'pace',
    'is_public',
    'image'
];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function days()
    {
        return $this->hasMany(ItineraryDay::class);
    }
    public function posts()
    {
        return $this->hasMany(\App\Models\TripPost::class);
    }
    public function aiGenerations()
    {
        return $this->hasMany(\App\Models\AiGeneration::class);
    }
    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function images()
    {
        return $this->hasMany(TripImage::class);
    }


}
