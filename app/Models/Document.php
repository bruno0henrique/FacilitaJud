<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $fillable = ['office_id', 'legal_case_id', 'name', 'path', 'mime', 'size', 'contents'];

    protected $hidden = ['contents', 'path'];
}
