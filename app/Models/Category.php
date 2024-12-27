<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_name',
        'media_url'
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function categorySizes()
    {
        return $this->hasMany(CategorySize::class);
    }
}