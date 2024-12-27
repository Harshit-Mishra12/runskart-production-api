<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;



class ColorVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'primary_product_id',
        'secondary_product_id',
    ];

    public function primaryProduct()
    {
        return $this->belongsTo(Product::class, 'primary_product_id');
    }

    public function secondaryProduct()
    {
        return $this->belongsTo(Product::class, 'secondary_product_id');
    }
}