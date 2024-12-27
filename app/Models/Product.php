<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category_id',
        'description',
        'price',
        'sku',
        'color_code',
        'retailer_id',
        'mode_type',
        'status',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function productImages()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function sizeVariants()
    {
        return $this->hasMany(SizeVariant::class);
    }

    public function colorVariants()
    {
        return $this->hasMany(ColorVariant::class, 'primary_product_id');
    }
}