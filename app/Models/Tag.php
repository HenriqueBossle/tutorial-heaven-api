<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $primaryKey = 'id_tag';
    protected $fillable = 
    [        
        'name'
    ];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_tag', 'id_tag', 'id_article');
    }

    public function users(): BelongsToMany
    {   
        return $this->belongsToMany(User::class, 'user_tag', 'id_tag', 'id_user');
    }
}
