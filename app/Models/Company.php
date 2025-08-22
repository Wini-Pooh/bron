<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Traits\HasCalendarSettings;

class Company extends Model
{
    use HasFactory, HasCalendarSettings;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'specialty',
        'phone',
        'email',
        'address',
        'website',
        'avatar',
        'total_clients',
        'total_specialists',
        'years_experience',
        'satisfaction_rate',
        'is_active',
        'settings',
        'telegram_bot_token',
        'telegram_bot_username',
        'telegram_notifications_enabled',
        'telegram_chat_id',
    ];

    protected $casts = [
        'satisfaction_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'settings' => 'json',
        'telegram_notifications_enabled' => 'boolean',
    ];

    /**
     * Get the user that owns the company.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($company) {
            if (empty($company->slug)) {
                $company->slug = Str::slug($company->name);
            }
        });
    }

    public function getAppointmentsForDate($date)
    {
        // Добавим логирование для отладки
        \Illuminate\Support\Facades\Log::info('Запрос записей для даты ' . $date, [
            'company_id' => $this->id,
            'company_name' => $this->name
        ]);
        
        $appointments = $this->appointments()
            ->where('appointment_date', $date)
            ->where('status', '!=', 'cancelled')
            ->with('service')
            ->orderBy('appointment_time')
            ->get();
            
        // Добавляем форматированные поля к каждой записи
        $appointments->transform(function ($appointment) {
            $appointment->formatted_date = $appointment->appointment_date->format('Y-m-d');
            $appointment->formatted_time = $appointment->getFormattedTimeAttribute();
            $appointment->formatted_created_at = $appointment->created_at->format('d.m.Y H:i');
            return $appointment;
        });
        
        return $appointments;
    }

    /**
     * Проверяет, настроен ли Telegram-бот для компании
     */
    public function hasTelegramBot()
    {
        return !empty($this->telegram_bot_token) && $this->telegram_notifications_enabled;
    }

    /**
     * Получает маскированный токен бота для отображения
     */
    public function getMaskedBotToken()
    {
        if (empty($this->telegram_bot_token)) {
            return null;
        }
        
        $token = $this->telegram_bot_token;
        if (strlen($token) > 10) {
            return substr($token, 0, 8) . '...' . substr($token, -4);
        }
        
        return $token;
    }

    /**
     * Проверяет валидность токена Telegram-бота
     */
    public function validateTelegramBot()
    {
        if (empty($this->telegram_bot_token)) {
            return false;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::get("https://api.telegram.org/bot{$this->telegram_bot_token}/getMe");
            return $response->successful() && $response->json('ok', false);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Ошибка валидации Telegram-бота', [
                'company_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
