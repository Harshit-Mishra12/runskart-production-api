<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermsAndConditions extends Model
{
    use HasFactory;

    protected $table = 'terms_and_conditions'; // Specify the table name

    protected $fillable = ['file_url', 'uploaded_at']; // Define which attributes are mass assignable
}
