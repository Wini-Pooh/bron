<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Company;
use App\Models\Appointment;
use App\Services\TelegramBotService;
use Carbon\Carbon;

class TelegramWebhookController extends Controller
{
    protected $botService;

    public function __construct(TelegramBotService $botService)
    {
        $this->botService = $botService;
    }

    /**
     * Обработчик webhook от Telegram
     */
    public function handle(Request $request, $botToken)
    {
        try {
            // Найти компанию по токену бота
            $company = Company::where('telegram_bot_token', $botToken)->first();
            
            if (!$company) {
                Log::warning('Получен webhook для неизвестного бота', ['token' => $botToken]);
                return response('OK', 200);
            }

            $update = $request->all();
            Log::info('Получен Telegram webhook', [
                'company_id' => $company->id,
                'update' => $update
            ]);

            // Обработка callback query (нажатие кнопок)
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($company, $update['callback_query']);
            }
            
            // Обработка обычных сообщений
            if (isset($update['message'])) {
                $this->handleMessage($company, $update['message']);
            }

            return response('OK', 200);
            
        } catch (\Exception $e) {
            Log::error('Ошибка обработки Telegram webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response('Error', 500);
        }
    }

    /**
     * Обработка callback query (нажатие кнопок)
     */
    private function handleCallbackQuery($company, $callbackQuery)
    {
        $chatId = $callbackQuery['from']['id'];
        $messageId = $callbackQuery['message']['message_id'];
        $data = $callbackQuery['data'];

        // Парсим данные callback
        $parts = explode(':', $data);
        $action = $parts[0];

        switch ($action) {
            case 'select_date':
                $date = $parts[1];
                $this->showTimeSlots($company, $chatId, $messageId, $date);
                break;
                
            case 'select_time':
                $date = $parts[1];
                $time = $parts[2];
                $this->showServiceSelection($company, $chatId, $messageId, $date, $time);
                break;
                
            case 'select_service':
                $date = $parts[1];
                $time = $parts[2];
                $serviceId = $parts[3];
                $this->showContactForm($company, $chatId, $messageId, $date, $time, $serviceId);
                break;
                
            case 'confirm_booking':
                $this->processBooking($company, $callbackQuery);
                break;
                
            case 'cancel_booking':
                $this->cancelBooking($company, $chatId, $messageId);
                break;
        }

        // Отвечаем на callback query
        $this->botService->answerCallbackQuery($company, $callbackQuery['id']);
    }

    /**
     * Обработка обычных сообщений
     */
    private function handleMessage($company, $message)
    {
        $chatId = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        Log::info('Обработка сообщения от пользователя', [
            'company_id' => $company->id,
            'chat_id' => $chatId,
            'text' => $text
        ]);

        if ($text === '/start' || $text === '/book') {
            $this->showWelcomeMessage($company, $chatId);
        } elseif ($text === '/help') {
            $this->showHelpMessage($company, $chatId);
        } elseif ($text === '/cancel') {
            $this->showCancelOptions($company, $chatId);
        } else {
            // Проверяем, ожидается ли ввод контактных данных
            $this->handleContactInput($company, $chatId, $text);
        }
    }

    /**
     * Показывает приветственное сообщение с календарем
     */
    private function showWelcomeMessage($company, $chatId)
    {
        $message = "🏢 Добро пожаловать в {$company->name}!\n\n";
        $message .= "📅 Выберите удобную дату для записи:";

        $keyboard = $this->botService->createDateKeyboard($company);
        
        $this->botService->sendMessage($company, $chatId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает доступные временные слоты
     */
    private function showTimeSlots($company, $chatId, $messageId, $date)
    {
        $slots = $this->botService->getAvailableTimeSlots($company, $date);
        
        if (empty($slots)) {
            $message = "❌ На выбранную дату ({$date}) нет свободного времени.\n\nВыберите другую дату:";
            $keyboard = $this->botService->createDateKeyboard($company);
        } else {
            $message = "🕐 Выберите удобное время на {$date}:";
            $keyboard = $this->botService->createTimeKeyboard($date, $slots);
        }

        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает выбор услуги
     */
    private function showServiceSelection($company, $chatId, $messageId, $date, $time)
    {
        $services = $company->services()->where('is_active', true)->get();
        
        $message = "💼 Выберите услугу на {$date} в {$time}:";
        $keyboard = $this->botService->createServiceKeyboard($date, $time, $services);

        $this->botService->editMessage($company, $chatId, $messageId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Показывает форму для ввода контактов
     */
    private function showContactForm($company, $chatId, $messageId, $date, $time, $serviceId)
    {
        $service = $company->services()->find($serviceId);
        
        $message = "✍️ Данные записи:\n\n";
        $message .= "📅 Дата: {$date}\n";
        $message .= "🕐 Время: {$time}\n";
        $message .= "💼 Услуга: {$service->name}\n";
        $message .= "💰 Стоимость: {$service->formatted_price}\n\n";
        $message .= "Пожалуйста, отправьте ваши контактные данные в формате:\n";
        $message .= "Имя Фамилия\n+7 (XXX) XXX-XX-XX\nemail@example.com (необязательно)";

        // Сохраняем данные в сессии (можно использовать Redis или БД)
        cache()->put("booking_data_{$chatId}", [
            'date' => $date,
            'time' => $time,
            'service_id' => $serviceId,
            'step' => 'waiting_contact'
        ], 1800); // 30 минут

        $this->botService->editMessage($company, $chatId, $messageId, $message);
    }

    /**
     * Обрабатывает ввод контактных данных
     */
    private function handleContactInput($company, $chatId, $text)
    {
        $bookingData = cache()->get("booking_data_{$chatId}");
        
        if (!$bookingData || $bookingData['step'] !== 'waiting_contact') {
            return;
        }

        // Парсим контактные данные
        $lines = explode("\n", trim($text));
        $name = $lines[0] ?? '';
        $phone = $lines[1] ?? '';
        $email = $lines[2] ?? '';

        // Валидация
        if (empty($name) || empty($phone)) {
            $this->botService->sendMessage($company, $chatId, 
                "❌ Пожалуйста, укажите имя и телефон в правильном формате.");
            return;
        }

        // Сохраняем контактные данные
        $bookingData['name'] = $name;
        $bookingData['phone'] = $phone;
        $bookingData['email'] = $email;
        $bookingData['step'] = 'confirm';
        
        cache()->put("booking_data_{$chatId}", $bookingData, 1800);

        // Показываем подтверждение
        $this->showBookingConfirmation($company, $chatId, $bookingData);
    }

    /**
     * Показывает подтверждение записи
     */
    private function showBookingConfirmation($company, $chatId, $bookingData)
    {
        $service = $company->services()->find($bookingData['service_id']);
        
        $message = "✅ Подтвердите запись:\n\n";
        $message .= "👤 Клиент: {$bookingData['name']}\n";
        $message .= "📞 Телефон: {$bookingData['phone']}\n";
        if (!empty($bookingData['email'])) {
            $message .= "📧 Email: {$bookingData['email']}\n";
        }
        $message .= "📅 Дата: {$bookingData['date']}\n";
        $message .= "🕐 Время: {$bookingData['time']}\n";
        $message .= "💼 Услуга: {$service->name}\n";
        $message .= "💰 Стоимость: {$service->formatted_price}";

        $keyboard = [
            [
                ['text' => '✅ Подтвердить', 'callback_data' => 'confirm_booking'],
                ['text' => '❌ Отменить', 'callback_data' => 'cancel_booking']
            ]
        ];

        $this->botService->sendMessage($company, $chatId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * Обрабатывает подтверждение записи
     */
    private function processBooking($company, $callbackQuery)
    {
        $chatId = $callbackQuery['from']['id'];
        $bookingData = cache()->get("booking_data_{$chatId}");
        
        if (!$bookingData) {
            $this->botService->sendMessage($company, $chatId, 
                "❌ Данные записи устарели. Пожалуйста, начните заново с /start");
            return;
        }

        try {
            // Создаем запись
            $appointment = Appointment::create([
                'company_id' => $company->id,
                'service_id' => $bookingData['service_id'],
                'client_name' => $bookingData['name'],
                'client_phone' => $bookingData['phone'],
                'client_email' => $bookingData['email'],
                'appointment_date' => $bookingData['date'],
                'appointment_time' => $bookingData['time'],
                'duration_minutes' => $company->services()->find($bookingData['service_id'])->duration_minutes,
                'status' => 'pending',
                'notes' => 'Запись через Telegram-бот'
            ]);

            // Очищаем кэш
            cache()->forget("booking_data_{$chatId}");

            $message = "🎉 Запись успешно создана!\n\n";
            $message .= "📋 Номер записи: #{$appointment->id}\n";
            $message .= "📅 Дата: {$appointment->formatted_date}\n";
            $message .= "🕐 Время: {$appointment->formatted_time}\n\n";
            $message .= "📞 Мы свяжемся с вами для подтверждения.\n\n";
            $message .= "Для новой записи отправьте /start";

            $this->botService->editMessage($company, $chatId, $callbackQuery['message']['message_id'], $message);

            // Отправляем уведомление владельцу
            if ($company->telegram_notifications_enabled && $company->telegram_chat_id) {
                $ownerMessage = "🔔 Новая запись через Telegram-бот!\n\n";
                $ownerMessage .= "👤 Клиент: {$appointment->client_name}\n";
                $ownerMessage .= "📞 Телефон: {$appointment->client_phone}\n";
                $ownerMessage .= "📅 Дата: {$appointment->formatted_date}\n";
                $ownerMessage .= "🕐 Время: {$appointment->formatted_time}\n";
                $ownerMessage .= "💼 Услуга: {$appointment->service->name}";

                $this->botService->sendMessage($company, $company->telegram_chat_id, $ownerMessage);
            }

        } catch (\Exception $e) {
            Log::error('Ошибка создания записи через Telegram', [
                'error' => $e->getMessage(),
                'booking_data' => $bookingData
            ]);

            $this->botService->sendMessage($company, $chatId, 
                "❌ Произошла ошибка при создании записи. Пожалуйста, попробуйте позже или свяжитесь с нами напрямую.");
        }
    }

    /**
     * Отменяет процесс записи
     */
    private function cancelBooking($company, $chatId, $messageId)
    {
        cache()->forget("booking_data_{$chatId}");
        
        $message = "❌ Запись отменена.\n\nДля новой записи отправьте /start";
        
        $this->botService->editMessage($company, $chatId, $messageId, $message);
    }

    /**
     * Показывает справочную информацию
     */
    private function showHelpMessage($company, $chatId)
    {
        $message = "ℹ️ Справка по боту {$company->name}\n\n";
        $message .= "Доступные команды:\n";
        $message .= "/start или /book - Записаться на прием\n";
        $message .= "/help - Показать эту справку\n";
        $message .= "/cancel - Отменить текущую операцию\n\n";
        $message .= "📞 Контакты:\n";
        if ($company->phone) {
            $message .= "Телефон: {$company->phone}\n";
        }
        if ($company->email) {
            $message .= "Email: {$company->email}\n";
        }
        if ($company->address) {
            $message .= "Адрес: {$company->address}\n";
        }

        $this->botService->sendMessage($company, $chatId, $message);
    }

    /**
     * Показывает опции отмены записи
     */
    private function showCancelOptions($company, $chatId)
    {
        // Здесь можно добавить функционал отмены существующих записей
        $message = "Для отмены записи, пожалуйста, свяжитесь с нами:\n\n";
        if ($company->phone) {
            $message .= "📞 Телефон: {$company->phone}\n";
        }
        if ($company->email) {
            $message .= "📧 Email: {$company->email}";
        }

        $this->botService->sendMessage($company, $chatId, $message);
    }
}
