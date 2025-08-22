<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Company;
use App\Models\Service;
use App\Models\Appointment;
use Carbon\Carbon;

class CompanyManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Показывает форму создания компании
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('company.create');
    }
    
    /**
     * Сохраняет новую компанию
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'specialty' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'website' => 'nullable|url|max:255',
            'years_experience' => 'nullable|integer|min:0|max:100',
            'total_clients' => 'nullable|integer|min:0',
            'total_specialists' => 'nullable|integer|min:0',
            'satisfaction_rate' => 'nullable|numeric|min:0|max:100',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        // Генерируем уникальный slug для компании
        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $count = 1;
        
        while (Company::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }
        
        // Создаем компанию
        $company = Company::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'specialty' => $request->specialty,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'website' => $request->website,
            'years_experience' => $request->years_experience,
            'total_clients' => $request->total_clients,
            'total_specialists' => $request->total_specialists,
            'satisfaction_rate' => $request->satisfaction_rate,
            'is_active' => true,
        ]);
        
        // Создаем стандартную услугу для компании
        Service::create([
            'company_id' => $company->id,
            'name' => 'Стандартная консультация',
            'description' => 'Консультация по базовым вопросам',
            'price' => 0,
            'duration_minutes' => 30,
            'type' => 'consultation',
            'is_active' => true,
        ]);
        
        return redirect()->route('company.show', $company->slug)
            ->with('success', 'Компания успешно создана!');
    }
    
    /**
     * Показывает форму редактирования компании
     *
     * @param string $slug
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function edit($slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return redirect()->route('home')
                ->with('error', 'У вас нет прав для редактирования этой компании');
        }
        
        return view('company.edit', compact('company'));
    }
    
    /**
     * Обновляет данные компании
     *
     * @param Request $request
     * @param string $slug
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return redirect()->route('home')
                ->with('error', 'У вас нет прав для редактирования этой компании');
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'specialty' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'website' => 'nullable|url|max:255',
            'years_experience' => 'nullable|integer|min:0|max:100',
            'total_clients' => 'nullable|integer|min:0',
            'total_specialists' => 'nullable|integer|min:0',
            'satisfaction_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|in:0,1',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        // Если имя изменилось, обновляем slug
        if ($request->name !== $company->name) {
            $slug = Str::slug($request->name);
            $originalSlug = $slug;
            $count = 1;
            
            while (Company::where('slug', $slug)->where('id', '!=', $company->id)->exists()) {
                $slug = $originalSlug . '-' . $count;
                $count++;
            }
            
            $company->slug = $slug;
        }
        
        // Обновляем данные компании
        $company->name = $request->name;
        $company->description = $request->description;
        $company->specialty = $request->specialty;
        $company->phone = $request->phone;
        $company->email = $request->email;
        $company->address = $request->address;
        $company->website = $request->website;
        $company->years_experience = $request->years_experience;
        $company->total_clients = $request->total_clients;
        $company->total_specialists = $request->total_specialists;
        $company->satisfaction_rate = $request->satisfaction_rate;
        $company->is_active = $request->input('is_active') == '1';
        $company->save();
        
        // Очищаем кэш компании, чтобы изменения сразу применились
        Cache::forget('company.' . $company->slug);
        
        return redirect()->route('company.show', $company->slug)
            ->with('success', 'Данные компании успешно обновлены!');
    }
    
    /**
     * Показывает все записи для компании
     *
     * @param string $slug
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function appointments($slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return redirect()->route('home')
                ->with('error', 'У вас нет прав для просмотра записей этой компании');
        }
        
        // Получаем записи за последние 30 дней и будущие записи
        $startDate = Carbon::now()->subDays(30);
        $appointments = Appointment::where('company_id', $company->id)
            ->where(function($query) use ($startDate) {
                $query->where('appointment_date', '>=', $startDate)
                      ->orWhere('appointment_date', '>=', Carbon::now());
            })
            ->with('service')
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->paginate(15);
        
        return view('company.appointments', compact('company', 'appointments'));
    }

    /**
     * Показывает страницу настроек компании
     *
     * @param string $slug
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function settings($slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return redirect()->route('home')
                ->with('error', 'У вас нет прав для изменения настроек этой компании');
        }
        
        return view('company.settings', compact('company'));
    }
    
    /**
     * Обновляет настройки компании
     *
     * @param Request $request
     * @param string $slug
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateSettings(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return redirect()->route('home')
                ->with('error', 'У вас нет прав для изменения настроек этой компании');
        }
        
        // Обрабатываем checkbox'ы правильно - если не отмечены, то false
        $emailNotifications = $request->has('email_notifications') ? true : false;
        $requireConfirmation = $request->has('require_confirmation') ? true : false;
        $workDays = $request->input('work_days', []);
        
        $validator = Validator::make($request->all(), [
            'work_start_time' => 'required|string|date_format:H:i',
            'work_end_time' => 'required|string|date_format:H:i|after:work_start_time',
            'appointment_interval' => 'required|integer|min:10|max:120',
            'appointment_days_ahead' => 'required|integer|min:1|max:90',
            'work_days' => 'nullable|array',
            'work_days.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'holidays' => 'nullable|string',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i|after_or_equal:break_start',
            'max_appointments_per_slot' => 'nullable|integer|min:1|max:10',
            // Telegram настройки
            'telegram_bot_token' => 'nullable|string|min:40|max:50',
            'telegram_bot_username' => 'nullable|string|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'telegram_notifications_enabled' => 'nullable|boolean',
            'telegram_chat_id' => 'nullable|string|max:50',
        ]);
        
        if ($validator->fails()) {
            Log::error('Ошибки валидации настроек компании', [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->all()
            ]);
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        // Подготавливаем настройки для сохранения
        $settings = [
            'work_start_time' => $request->work_start_time,
            'work_end_time' => $request->work_end_time,
            'appointment_interval' => (int) $request->appointment_interval,
            'appointment_days_ahead' => (int) $request->appointment_days_ahead,
            'work_days' => !empty($workDays) ? $workDays : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'email_notifications' => $emailNotifications,
            'require_confirmation' => $requireConfirmation,
            'holidays' => $this->parseHolidays($request->holidays),
            'break_times' => $this->parseBreakTimes($request->break_start, $request->break_end),
            'max_appointments_per_slot' => (int) $request->input('max_appointments_per_slot', 1),
        ];
        
        // Логируем данные для отладки
        Log::info('Обновление настроек компании ' . $company->slug, [
            'old_settings' => $company->settings,
            'new_settings' => $settings,
            'request_data' => $request->all(),
            'email_notifications' => $emailNotifications,
            'require_confirmation' => $requireConfirmation,
            'work_days' => $workDays,
            'holidays_raw' => $request->holidays,
            'holidays_parsed' => $this->parseHolidays($request->holidays),
            'break_start' => $request->break_start,
            'break_end' => $request->break_end,
            'break_times_parsed' => $this->parseBreakTimes($request->break_start, $request->break_end),
            'telegram_bot_token' => $request->input('telegram_bot_token') ? 'set' : 'empty',
            'telegram_bot_username' => $request->input('telegram_bot_username'),
            'telegram_notifications_enabled' => $request->has('telegram_notifications_enabled'),
            'telegram_chat_id' => $request->input('telegram_chat_id')
        ]);
        
        try {
            // Обновляем настройки компании
            $company->settings = $settings;
            
            // Обновляем Telegram настройки
            $company->telegram_bot_token = $request->input('telegram_bot_token');
            $company->telegram_bot_username = $request->input('telegram_bot_username');
            $company->telegram_notifications_enabled = $request->has('telegram_notifications_enabled') ? true : false;
            $company->telegram_chat_id = $request->input('telegram_chat_id');
            
            $saveResult = $company->save();
            
            Log::info('Результат сохранения настроек', [
                'save_result' => $saveResult,
                'company_id' => $company->id,
                'settings_after_save' => $company->fresh()->settings,
                'telegram_after_save' => [
                    'bot_token' => $company->fresh()->telegram_bot_token ? 'set' : 'empty',
                    'bot_username' => $company->fresh()->telegram_bot_username,
                    'notifications_enabled' => $company->fresh()->telegram_notifications_enabled,
                    'chat_id' => $company->fresh()->telegram_chat_id
                ]
            ]);
            
            // Очищаем кэш компании
            Cache::forget('company.' . $company->slug);
            
            return redirect()->route('company.settings', $company->slug)
                ->with('success', 'Настройки компании успешно обновлены!');
                
        } catch (\Exception $e) {
            Log::error('Ошибка при сохранении настроек компании', [
                'error' => $e->getMessage(),
                'company_id' => $company->id,
                'settings' => $settings
            ]);
            
            return redirect()->back()
                ->with('error', 'Произошла ошибка при сохранении настроек. Попробуйте еще раз.')
                ->withInput();
        }
    }

    /**
     * Парсит строку праздников в массив
     *
     * @param string|null $holidaysString
     * @return array
     */
    private function parseHolidays($holidaysString)
    {
        if (empty($holidaysString)) {
            return [];
        }

        $holidays = [];
        $parts = array_map('trim', explode(',', $holidaysString));
        
        foreach ($parts as $part) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $part) || preg_match('/^\d{2}-\d{2}$/', $part)) {
                $holidays[] = $part;
            }
        }

        return $holidays;
    }

    /**
     * Парсит время перерывов
     *
     * @param string|null $breakStart
     * @param string|null $breakEnd
     * @return array
     */
    private function parseBreakTimes($breakStart, $breakEnd)
    {
        if (empty($breakStart) || empty($breakEnd)) {
            return [];
        }

        return [
            [
                'start' => $breakStart,
                'end' => $breakEnd
            ]
        ];
    }

    /**
     * Отладочный метод для проверки настроек
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function debugSettings($slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (auth()->id() !== $company->user_id) {
            return response()->json([
                'error' => 'У вас нет прав для просмотра настроек этой компании'
            ], 403);
        }

        // Получаем настройки календаря так же, как в CompanyController
        $settings = $company->settings ?? [];
        $calendarSettings = [
            'work_start_time' => $settings['work_start_time'] ?? '09:00',
            'work_end_time' => $settings['work_end_time'] ?? '18:00',
            'appointment_interval' => (int)($settings['appointment_interval'] ?? 30),
            'appointment_days_ahead' => (int)($settings['appointment_days_ahead'] ?? 14),
            'work_days' => $settings['work_days'] ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'email_notifications' => (bool)($settings['email_notifications'] ?? true),
            'require_confirmation' => (bool)($settings['require_confirmation'] ?? false),
            'holidays' => $settings['holidays'] ?? [],
            'break_times' => $settings['break_times'] ?? [],
        ];

        $debugInfo = [
            'company_name' => $company->name,
            'company_slug' => $company->slug,
            'raw_settings' => $company->settings,
            'settings_type' => gettype($company->settings),
            'calendar_settings' => $calendarSettings,
            'database_settings_column' => $company->getAttributes()['settings'] ?? null,
        ];

        return response()->json($debugInfo, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
