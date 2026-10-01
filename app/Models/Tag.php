<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'display_order',
        'is_active',
    ];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class)->withTimestamps();
    }

    public function users(): BelongsToMany
    {   
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
