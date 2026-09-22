<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'rfid_id',
        'vehicle_type',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }
}