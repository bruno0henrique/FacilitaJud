<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = ['office_id', 'legal_case_id', 'title', 'kind', 'location', 'starts_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime'];
    }
}
