<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'price',
        'duration_minutes',
        'type',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function getFormattedPriceAttribute()
    {
        return $this->price ? number_format((float)$this->price, 0, ',', ' ') . ' ₽' : 'Бесплатно';
    }

    public function getFormattedDurationAttribute()
    {
        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;
        
        $result = [];
        if ($hours > 0) {
            $result[] = $hours . ' ч';
        }
        if ($minutes > 0) {
            $result[] = $minutes . ' мин';
        }
        
        return implode(' ', $result);
    }
}
