<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Company;
use App\Services\TelegramService;

class TelegramController extends Controller
{
    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->middleware('auth');
        $this->telegramService = $telegramService;
    }

    /**
     * Обновляет настройки Telegram-бота
     */
    public function updateSettings(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем права доступа
        if (auth()->id() !== $company->user_id) {
            return response()->json([
                'message' => 'У вас нет прав для изменения настроек этой компании'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'telegram_bot_token' => 'nullable|string|min:40|max:50',
            'telegram_bot_username' => 'nullable|string|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'telegram_notifications_enabled' => 'boolean',
            'telegram_chat_id' => 'nullable|string|max:50',
        ], [
            'telegram_bot_token.min' => 'Токен бота должен содержать минимум 40 символов',
            'telegram_chat_id.max' => 'ID чата не должен превышать 50 символов',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $company->update($request->only([
                'telegram_bot_token',
                'telegram_bot_username',
                'telegram_notifications_enabled',
                'telegram_chat_id'
            ]));

            return response()->json([
                'message' => 'Настройки Telegram успешно обновлены'
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Ошибка обновления настроек Telegram', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Произошла ошибка при сохранении настроек'
            ], 500);
        }
    }

    /**
     * Тестирует подключение к Telegram-боту
     */
    public function testConnection(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем права доступа
        if (auth()->id() !== $company->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет прав для тестирования бота этой компании'
            ], 403);
        }

        if (!$company->hasTelegramBot()) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram-бот не настроен для этой компании'
            ], 400);
        }

        $chatId = $request->input('chat_id', $company->telegram_chat_id);
        
        if (!$chatId) {
            return response()->json([
                'success' => false,
                'message' => 'Не указан ID чата для отправки тестового сообщения'
            ], 400);
        }

        try {
            $result = $this->telegramService->testConnection($company, $chatId);
            
            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Тестовое сообщение успешно отправлено!'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось отправить тестовое сообщение. Проверьте настройки бота и ID чата.'
                ], 400);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Ошибка тестирования Telegram-бота', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при тестировании подключения'
            ], 500);
        }
    }

    /**
     * Получает информацию о боте
     */
    public function getBotInfo(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем права доступа
        if (auth()->id() !== $company->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет прав для просмотра информации о боте этой компании'
            ], 403);
        }

        if (!$company->telegram_bot_token) {
            return response()->json([
                'success' => false,
                'message' => 'Токен бота не настроен'
            ], 400);
        }

        try {
            $botInfo = $this->telegramService->getBotInfo($company);
            
            if ($botInfo) {
                return response()->json([
                    'success' => true,
                    'data' => $botInfo
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось получить информацию о боте'
                ], 400);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Ошибка получения информации о боте', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении информации о боте'
            ], 500);
        }
    }

    /**
     * Устанавливает webhook для Telegram-бота
     */
    public function setWebhook(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем права доступа
        if (auth()->id() !== $company->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет прав для настройки webhook этой компании'
            ], 403);
        }

        if (!$company->hasTelegramBot()) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram-бот не настроен для этой компании'
            ], 400);
        }

        try {
            // Создаем URL для webhook
            $webhookUrl = route('telegram.webhook', ['botToken' => $company->telegram_bot_token]);
            
            // Создаем экземпляр TelegramBotService
            $botService = app(\App\Services\TelegramBotService::class);
            
            $result = $botService->setWebhook($company, $webhookUrl);
            
            if ($result && isset($result['ok']) && $result['ok']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook успешно установлен! Теперь бот может принимать сообщения от клиентов.',
                    'webhook_url' => $webhookUrl,
                    'bot_username' => '@' . ($company->telegram_bot_username ?: 'неизвестно'),
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось установить webhook',
                    'error' => $result['description'] ?? 'Неизвестная ошибка'
                ], 400);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Ошибка установки webhook', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при установке webhook'
            ], 500);
        }
    }

    /**
     * Получает информацию о webhook
     */
    public function getWebhookInfo(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем права доступа
        if (auth()->id() !== $company->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет прав для просмотра webhook этой компании'
            ], 403);
        }

        if (!$company->hasTelegramBot()) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram-бот не настроен для этой компании'
            ], 400);
        }

        try {
            $botService = app(\App\Services\TelegramBotService::class);
            $webhookInfo = $botService->getWebhookInfo($company);
            
            if ($webhookInfo && isset($webhookInfo['ok']) && $webhookInfo['ok']) {
                return response()->json([
                    'success' => true,
                    'data' => $webhookInfo['result']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось получить информацию о webhook'
                ], 400);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Ошибка получения информации о webhook', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении информации о webhook'
            ], 500);
        }
    }
}
