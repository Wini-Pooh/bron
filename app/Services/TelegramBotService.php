<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Company;
use App\Models\Appointment;
use Carbon\Carbon;

class TelegramBotService
{
    /**
     * Отправляет сообщение через Telegram-бот
     */
    public function sendMessage($company, $chatId, $message, $options = [])
    {
        if (!$company->telegram_bot_token) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$company->telegram_bot_token}/sendMessage";
        
        $data = array_merge([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ], $options);

        try {
            $response = Http::post($url, $data);
            
            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Ошибка отправки Telegram сообщения', [
                    'response' => $response->body(),
                    'data' => $data
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Исключение при отправке Telegram сообщения', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Редактирует существующее сообщение
     */
    public function editMessage($company, $chatId, $messageId, $message, $options = [])
    {
        if (!$company->telegram_bot_token) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$company->telegram_bot_token}/editMessageText";
        
        $data = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ], $options);

        try {
            $response = Http::post($url, $data);
            return $response->successful() ? $response->json() : false;
        } catch (\Exception $e) {
            Log::error('Ошибка редактирования Telegram сообщения', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Отвечает на callback query
     */
    public function answerCallbackQuery($company, $callbackQueryId, $text = null)
    {
        if (!$company->telegram_bot_token) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$company->telegram_bot_token}/answerCallbackQuery";
        
        $data = ['callback_query_id' => $callbackQueryId];
        if ($text) {
            $data['text'] = $text;
        }

        try {
            Http::post($url, $data);
            return true;
        } catch (\Exception $e) {
            Log::error('Ошибка ответа на callback query', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Создает клавиатуру с датами
     */
    public function createDateKeyboard($company, $daysAhead = null)
    {
        $settings = $company->getCalendarSettings();
        $daysAhead = $daysAhead ?? $settings['appointment_days_ahead'];
        
        $keyboard = [];
        $today = Carbon::now();
        $row = [];
        
        for ($i = 0; $i < $daysAhead; $i++) {
            $date = $today->copy()->addDays($i);
            
            // Проверяем рабочие дни
            $dayOfWeek = strtolower($date->format('l'));
            if (!in_array($dayOfWeek, $settings['work_days'])) {
                continue;
            }
            
            // Проверяем праздники
            $dateString = $date->format('Y-m-d');
            if (in_array($dateString, $settings['holidays'])) {
                continue;
            }
            
            $formattedDate = $date->format('d.m');
            $dayName = $this->getDayName($date);
            
            $row[] = [
                'text' => "{$formattedDate} ({$dayName})",
                'callback_data' => "select_date:{$dateString}"
            ];
            
            // По 2 кнопки в ряд
            if (count($row) == 2) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        
        // Добавляем последний ряд если есть
        if (!empty($row)) {
            $keyboard[] = $row;
        }
        
        return $keyboard;
    }

    /**
     * Создает клавиатуру с временными слотами
     */
    public function createTimeKeyboard($date, $slots)
    {
        $keyboard = [];
        $row = [];
        
        foreach ($slots as $slot) {
            $row[] = [
                'text' => $slot['time'],
                'callback_data' => "select_time:{$date}:{$slot['time']}"
            ];
            
            // По 3 кнопки в ряд
            if (count($row) == 3) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        
        // Добавляем последний ряд если есть
        if (!empty($row)) {
            $keyboard[] = $row;
        }
        
        // Кнопка "Назад"
        $keyboard[] = [
            ['text' => '← Выбрать другую дату', 'callback_data' => 'select_date_back']
        ];
        
        return $keyboard;
    }

    /**
     * Создает клавиатуру с услугами
     */
    public function createServiceKeyboard($date, $time, $services)
    {
        $keyboard = [];
        
        foreach ($services as $service) {
            $text = $service->name;
            if ($service->price > 0) {
                $text .= " - {$service->formatted_price}";
            }
            
            $keyboard[] = [
                [
                    'text' => $text,
                    'callback_data' => "select_service:{$date}:{$time}:{$service->id}"
                ]
            ];
        }
        
        // Кнопка "Назад"
        $keyboard[] = [
            ['text' => '← Выбрать другое время', 'callback_data' => "select_date:{$date}"]
        ];
        
        return $keyboard;
    }

    /**
     * Получает доступные временные слоты для даты
     */
    public function getAvailableTimeSlots($company, $date)
    {
        $settings = $company->getCalendarSettings();
        $existingAppointments = $company->appointments()
            ->where('appointment_date', $date)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->keyBy('appointment_time');

        $slots = [];
        $startTime = Carbon::createFromFormat('H:i', $settings['work_start_time']);
        $endTime = Carbon::createFromFormat('H:i', $settings['work_end_time']);
        $interval = $settings['appointment_interval'];
        $maxPerSlot = $settings['max_appointments_per_slot'];

        $currentTime = $startTime->copy();
        
        while ($currentTime->lt($endTime)) {
            $timeString = $currentTime->format('H:i');
            
            // Проверяем перерывы
            $isBreak = false;
            foreach ($settings['break_times'] as $breakTime) {
                $breakStart = Carbon::createFromFormat('H:i', $breakTime['start']);
                $breakEnd = Carbon::createFromFormat('H:i', $breakTime['end']);
                
                if ($currentTime->gte($breakStart) && $currentTime->lt($breakEnd)) {
                    $isBreak = true;
                    break;
                }
            }
            
            if (!$isBreak) {
                // Считаем количество записей на это время
                $appointmentsCount = $existingAppointments->filter(function ($appointment) use ($timeString) {
                    return $appointment->appointment_time === $timeString;
                })->count();
                
                if ($appointmentsCount < $maxPerSlot) {
                    $slots[] = [
                        'time' => $timeString,
                        'available_slots' => $maxPerSlot - $appointmentsCount
                    ];
                }
            }
            
            $currentTime->addMinutes($interval);
        }
        
        return $slots;
    }

    /**
     * Устанавливает webhook для бота
     */
    public function setWebhook($company, $webhookUrl)
    {
        if (!$company->telegram_bot_token) {
            Log::error('Попытка установить webhook без токена', [
                'company_id' => $company->id
            ]);
            return false;
        }

        $url = "https://api.telegram.org/bot{$company->telegram_bot_token}/setWebhook";
        
        Log::info('Установка webhook', [
            'company_id' => $company->id,
            'webhook_url' => $webhookUrl,
            'api_url' => $url
        ]);
        
        try {
            $response = Http::post($url, [
                'url' => $webhookUrl,
                'allowed_updates' => ['message', 'callback_query']
            ]);
            
            $result = $response->json();
            
            Log::info('Ответ от Telegram API при установке webhook', [
                'company_id' => $company->id,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'response' => $result
            ]);
            
            return $response->successful() ? $result : false;
        } catch (\Exception $e) {
            Log::error('Ошибка установки webhook', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
                'webhook_url' => $webhookUrl
            ]);
            return false;
        }
    }

    /**
     * Удаляет webhook для бота
     */
    public function deleteWebhook($company)
    {
        if (!$company->telegram_bot_token) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$company->telegram_bot_token}/deleteWebhook";
        
        try {
            $response = Http::post($url);
            return $response->successful() ? $response->json() : false;
        } catch (\Exception $e) {
            Log::error('Ошибка удаления webhook', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Получает информацию о webhook
     */
    public function getWebhookInfo($company)
    {
        if (!$company->telegram_bot_token) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$company->telegram_bot_token}/getWebhookInfo";
        
        try {
            $response = Http::get($url);
            return $response->successful() ? $response->json() : false;
        } catch (\Exception $e) {
            Log::error('Ошибка получения информации webhook', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Возвращает название дня недели на русском
     */
    private function getDayName($date)
    {
        $days = [
            'Monday' => 'Пн',
            'Tuesday' => 'Вт', 
            'Wednesday' => 'Ср',
            'Thursday' => 'Чт',
            'Friday' => 'Пт',
            'Saturday' => 'Сб',
            'Sunday' => 'Вс'
        ];
        
        return $days[$date->format('l')] ?? '';
    }

    /**
     * Отправляет уведомление о новой записи
     */
    public function sendNewAppointmentNotification(Appointment $appointment)
    {
        $company = $appointment->company;
        
        if (!$company->hasTelegramBot() || !$company->telegram_notifications_enabled) {
            return false;
        }

        $message = "🔔 <b>Новая запись!</b>\n\n";
        $message .= "👤 <b>Клиент:</b> {$appointment->client_name}\n";
        $message .= "📞 <b>Телефон:</b> {$appointment->client_phone}\n";
        if ($appointment->client_email) {
            $message .= "📧 <b>Email:</b> {$appointment->client_email}\n";
        }
        $message .= "📅 <b>Дата:</b> {$appointment->formatted_date}\n";
        $message .= "🕐 <b>Время:</b> {$appointment->formatted_time}\n";
        $message .= "💼 <b>Услуга:</b> {$appointment->service->name}\n";
        if ($appointment->notes) {
            $message .= "📝 <b>Примечания:</b> {$appointment->notes}\n";
        }
        $message .= "\n📋 <b>Номер записи:</b> #{$appointment->id}";

        return $this->sendMessage($company, $company->telegram_chat_id, $message);
    }

    /**
     * Отправляет уведомление об отмене записи
     */
    public function sendCancelledAppointmentNotification(Appointment $appointment)
    {
        $company = $appointment->company;
        
        if (!$company->hasTelegramBot() || !$company->telegram_notifications_enabled) {
            return false;
        }

        $message = "❌ <b>Запись отменена</b>\n\n";
        $message .= "👤 <b>Клиент:</b> {$appointment->client_name}\n";
        $message .= "📅 <b>Дата:</b> {$appointment->formatted_date}\n";
        $message .= "🕐 <b>Время:</b> {$appointment->formatted_time}\n";
        $message .= "💼 <b>Услуга:</b> {$appointment->service->name}\n";
        $message .= "\n📋 <b>Номер записи:</b> #{$appointment->id}";

        return $this->sendMessage($company, $company->telegram_chat_id, $message);
    }

    /**
     * Отправляет уведомление о переносе записи
     */
    public function sendRescheduledAppointmentNotification(Appointment $appointment, $oldDate, $oldTime)
    {
        $company = $appointment->company;
        
        if (!$company->hasTelegramBot() || !$company->telegram_notifications_enabled) {
            return false;
        }

        $message = "🔄 <b>Запись перенесена</b>\n\n";
        $message .= "👤 <b>Клиент:</b> {$appointment->client_name}\n";
        $message .= "📅 <b>Старая дата:</b> {$oldDate} в {$oldTime}\n";
        $message .= "📅 <b>Новая дата:</b> {$appointment->formatted_date} в {$appointment->formatted_time}\n";
        $message .= "💼 <b>Услуга:</b> {$appointment->service->name}\n";
        $message .= "\n📋 <b>Номер записи:</b> #{$appointment->id}";

        return $this->sendMessage($company, $company->telegram_chat_id, $message);
    }
}
