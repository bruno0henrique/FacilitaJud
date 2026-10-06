<?php

namespace App\Models;

use Database\Factories\LegalCaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalCase extends Model
{
    /** @use HasFactory<LegalCaseFactory> */
    use HasFactory;

    protected $fillable = ['office_id', 'client_id', 'title', 'number', 'court', 'status', 'responsible', 'notes'];
}
