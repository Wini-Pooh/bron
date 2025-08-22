<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Company;
use App\Models\Appointment;

class TelegramService
{
    /**
     * Отправляет сообщение через Telegram-бот компании
     */
    public function sendMessage(Company $company, string $message, array $options = [])
    {
        if (!$company->hasTelegramBot()) {
            Log::warning('Попытка отправки сообщения через неактивный Telegram-бот', [
                'company_id' => $company->id,
                'company_name' => $company->name
            ]);
            return false;
        }

        $chatId = $company->telegram_chat_id ?? $options['chat_id'] ?? null;
        
        if (!$chatId) {
            Log::warning('Не указан chat_id для отправки сообщения', [
                'company_id' => $company->id
            ]);
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$company->telegram_bot_token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $options['parse_mode'] ?? 'HTML',
                'disable_web_page_preview' => $options['disable_web_page_preview'] ?? true,
            ]);

            if ($response->successful()) {
                Log::info('Сообщение успешно отправлено через Telegram', [
                    'company_id' => $company->id,
                    'chat_id' => $chatId
                ]);
                return true;
            } else {
                Log::error('Ошибка отправки сообщения через Telegram', [
                    'company_id' => $company->id,
                    'response' => $response->json(),
                    'status' => $response->status()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Исключение при отправке сообщения через Telegram', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Отправляет уведомление о новой записи
     */
    public function sendNewAppointmentNotification(Appointment $appointment)
    {
        $company = $appointment->company;
        
        if (!$company->hasTelegramBot()) {
            return false;
        }

        $message = $this->formatNewAppointmentMessage($appointment);
        return $this->sendMessage($company, $message);
    }

    /**
     * Отправляет уведомление об отмене записи
     */
    public function sendCancelledAppointmentNotification(Appointment $appointment)
    {
        $company = $appointment->company;
        
        if (!$company->hasTelegramBot()) {
            return false;
        }

        $message = $this->formatCancelledAppointmentMessage($appointment);
        return $this->sendMessage($company, $message);
    }

    /**
     * Отправляет уведомление о переносе записи
     */
    public function sendRescheduledAppointmentNotification(Appointment $appointment, $oldDate, $oldTime)
    {
        $company = $appointment->company;
        
        if (!$company->hasTelegramBot()) {
            return false;
        }

        $message = $this->formatRescheduledAppointmentMessage($appointment, $oldDate, $oldTime);
        return $this->sendMessage($company, $message);
    }

    /**
     * Проверяет статус бота
     */
    public function getBotInfo(Company $company)
    {
        if (empty($company->telegram_bot_token)) {
            return null;
        }

        try {
            $response = Http::get("https://api.telegram.org/bot{$company->telegram_bot_token}/getMe");
            
            if ($response->successful()) {
                return $response->json('result');
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Ошибка получения информации о боте', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Форматирует сообщение о новой записи
     */
    private function formatNewAppointmentMessage(Appointment $appointment)
    {
        $message = "🆕 <b>Новая запись!</b>\n\n";
        $message .= "👤 <b>Клиент:</b> {$appointment->client_name}\n";
        $message .= "📞 <b>Телефон:</b> {$appointment->client_phone}\n";
        
        if ($appointment->client_email) {
            $message .= "📧 <b>Email:</b> {$appointment->client_email}\n";
        }
        
        $message .= "📅 <b>Дата:</b> {$appointment->formatted_date}\n";
        $message .= "🕐 <b>Время:</b> {$appointment->formatted_time}\n";
        $message .= "⚡ <b>Услуга:</b> {$appointment->service->name}\n";
        $message .= "⏱ <b>Длительность:</b> {$appointment->duration_minutes} мин\n";
        
        if ($appointment->notes) {
            $message .= "📝 <b>Примечания:</b> {$appointment->notes}\n";
        }
        
        $message .= "\n📊 <b>Статус:</b> {$appointment->status_text}";
        
        return $message;
    }

    /**
     * Форматирует сообщение об отмене записи
     */
    private function formatCancelledAppointmentMessage(Appointment $appointment)
    {
        $message = "❌ <b>Запись отменена</b>\n\n";
        $message .= "👤 <b>Клиент:</b> {$appointment->client_name}\n";
        $message .= "📞 <b>Телефон:</b> {$appointment->client_phone}\n";
        $message .= "📅 <b>Дата:</b> {$appointment->formatted_date}\n";
        $message .= "🕐 <b>Время:</b> {$appointment->formatted_time}\n";
        $message .= "⚡ <b>Услуга:</b> {$appointment->service->name}\n";
        
        return $message;
    }

    /**
     * Форматирует сообщение о переносе записи
     */
    private function formatRescheduledAppointmentMessage(Appointment $appointment, $oldDate, $oldTime)
    {
        $message = "🔄 <b>Запись перенесена</b>\n\n";
        $message .= "👤 <b>Клиент:</b> {$appointment->client_name}\n";
        $message .= "📞 <b>Телефон:</b> {$appointment->client_phone}\n";
        $message .= "⚡ <b>Услуга:</b> {$appointment->service->name}\n\n";
        
        $message .= "📅 <b>Было:</b> {$oldDate} в {$oldTime}\n";
        $message .= "📅 <b>Стало:</b> {$appointment->formatted_date} в {$appointment->formatted_time}\n";
        
        return $message;
    }

    /**
     * Тестирует отправку сообщения
     */
    public function testConnection(Company $company, $chatId = null)
    {
        $testMessage = "🤖 Тест подключения Telegram-бота\n\n";
        $testMessage .= "Компания: {$company->name}\n";
        $testMessage .= "Время: " . now()->format('d.m.Y H:i:s');
        
        $options = [];
        if ($chatId) {
            $options['chat_id'] = $chatId;
        }
        
        return $this->sendMessage($company, $testMessage, $options);
    }
}
