<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategorySize extends Model
{
    use HasFactory;

    protected $table = 'categories_sizes';

    protected $fillable = [
        'category_id',
        'size_id',
    ];
    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    // Define relationships if needed
}
