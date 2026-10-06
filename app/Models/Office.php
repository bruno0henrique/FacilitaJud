<?php

namespace App\Models;

use Database\Factories\OfficeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    /** @use HasFactory<OfficeFactory> */
    use HasFactory;

    protected $fillable = ['name', 'display_name', 'reminders', 'is_demo'];
}
