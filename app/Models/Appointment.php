<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'service_id',
        'client_name',
        'client_phone',
        'client_email',
        'appointment_date',
        'appointment_time',
        'duration_minutes',
        'status',
        'notes',
        'owner_notes',
    ];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function getFormattedTimeAttribute()
    {
        return is_string($this->appointment_time) 
            ? $this->appointment_time 
            : Carbon::parse($this->appointment_time)->format('H:i');
    }

    public function getFormattedDateAttribute()
    {
        return Carbon::parse($this->appointment_date)->format('d.m.Y');
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'confirmed' => 'badge bg-success',
            'cancelled' => 'badge bg-danger',
            'completed' => 'badge bg-primary',
            default => 'badge bg-secondary'
        };
    }

    public function getStatusTextAttribute()
    {
        return match($this->status) {
            'confirmed' => 'Подтверждена',
            'cancelled' => 'Отменена',
            'completed' => 'Выполнена',
            default => 'Неизвестно'
        };
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('appointment_date', $date);
    }

    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }
}
