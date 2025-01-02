<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rules extends Model
{
    use HasFactory;

    protected $table = 'rules'; // Specify the table name

    protected $fillable = ['file_url', 'uploaded_at']; // Define which attributes are mass assignable
}
