<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    // Adicione esta propriedade liberando os campos para escrita em massa
    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'display_order',
        'is_active',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}