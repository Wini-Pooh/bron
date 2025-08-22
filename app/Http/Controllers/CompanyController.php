<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use App\Models\Company;
use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\NewAppointmentNotification;
use App\Services\TelegramBotService;

class CompanyController extends Controller
{
    protected $telegramService;

    public function __construct(TelegramBotService $telegramService)
    {
        $this->telegramService = $telegramService;
    }
    /**
     * Показывает страницу компании
     *
     * @param string $slug
     * @return \Illuminate\View\View
     */
    public function show($slug)
    {
        $company = $this->getActiveCompany($slug);

        // Проверяем, является ли текущий пользователь владельцем компании
        $isOwner = auth()->check() && auth()->user()->id === $company->user_id;

        // Получаем настройки компании для календаря
        $calendarSettings = $this->getCalendarSettings($company);
        
        // Логируем настройки для отладки
        Log::info('Настройки календаря для компании ' . $company->slug, [
            'settings' => $calendarSettings,
            'raw_settings' => $company->settings
        ]);

        return view('company.show', compact('company', 'isOwner', 'calendarSettings'));
    }

    /**
     * Получает активную компанию по slug
     *
     * @param string $slug
     * @return \App\Models\Company
     */
    private function getActiveCompany($slug)
    {
        return Cache::remember('company.' . $slug, 60, function () use ($slug) {
            return Company::where('slug', $slug)
                ->where('is_active', true)
                ->firstOrFail();
        });
    }

    /**
     * Получает настройки календаря для компании
     *
     * @param \App\Models\Company $company
     * @return array
     */
    private function getCalendarSettings($company)
    {
        return $company->getCalendarSettings();
    }

    /**
     * Получает статистику записей для календаря
     *
     * @param string $slug
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMonthlyStats($slug, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'nullable|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Неверный формат месяца',
                'errors' => $validator->errors()
            ], 422);
        }

        $company = $this->getActiveCompany($slug);
        $month = $request->get('month', now()->format('Y-m'));
        
        // Получаем статистику по дням месяца
        $stats = $company->appointments()
            ->whereYear('appointment_date', '=', Carbon::parse($month)->year)
            ->whereMonth('appointment_date', '=', Carbon::parse($month)->month)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DATE(appointment_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        return response()->json([
            'stats' => $stats,
            'month' => $month
        ]);
    }

    /**
     * Получает список записей для компании в формате JSON
     *
     * @param string $slug
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAppointments($slug, Request $request)
    {
        // Валидация даты
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Неверный формат даты',
                'errors' => $validator->errors()
            ], 422);
        }

        $company = $this->getActiveCompany($slug);
        
        // Если дата не указана, используем текущую дату
        $requestDate = $request->get('date');
        $date = $requestDate ? $requestDate : now()->format('Y-m-d');
        
        // Проверяем, является ли текущий пользователь владельцем компании
        $isOwner = auth()->check() && auth()->user()->id === $company->user_id;
        
        // Получаем данные напрямую без кэширования
        $appointments = $company->getAppointmentsForDate($date);
        
        // Журналирование для отладки
        Log::info('Получены записи для даты ' . $date, [
            'company_id' => $company->id,
            'company_slug' => $company->slug,
            'is_owner' => $isOwner,
            'appointments_count' => $appointments->count(),
            'appointments' => $appointments->map(function($apt) {
                return [
                    'id' => $apt->id,
                    'client_name' => $apt->client_name,
                    'appointment_time' => $apt->appointment_time,
                    'formatted_time' => $apt->formatted_time,
                    'raw_time' => is_string($apt->appointment_time) ? $apt->appointment_time : $apt->appointment_time->toTimeString(),
                    'status' => $apt->status
                ];
            })->toArray()
        ]);
        
        $timeSlots = $this->generateTimeSlots($appointments, $date, $isOwner, $company);
        
        $response = [
            'appointments' => $isOwner ? $appointments : [],
            'timeSlots' => $timeSlots,
            'date' => $date,
            'isOwner' => $isOwner,
            'generated_at' => now()->toDateTimeString()
        ];
        
        return response()->json($response);
    }

    private function generateTimeSlots($appointments, $date, $isOwner = false, $company = null)
    {
        $slots = [];
        
        if (!$company) {
            return $slots;
        }

        // Получаем настройки компании
        $settings = $this->getCalendarSettings($company);
        
        // Преобразуем время начала и окончания работы
        $startTime = Carbon::parse($settings['work_start_time']);
        $endTime = Carbon::parse($settings['work_end_time']);
        $slotDuration = $settings['appointment_interval']; // минут
        $workDays = $settings['work_days'];
        $maxAppointmentsPerSlot = $settings['max_appointments_per_slot'] ?? 1;
        
        // Используем Carbon для работы с датами
        $now = Carbon::now();
        $selectedDate = Carbon::parse($date);
        $isToday = $selectedDate->isSameDay($now);
        
        // Проверяем, является ли день рабочим
        $dayOfWeek = strtolower($selectedDate->format('l'));
        $isWorkDay = in_array($dayOfWeek, $workDays);

        // Проверяем, не праздник ли это
        $isHoliday = $this->isHoliday($selectedDate, $settings['holidays']);

        // Если день не рабочий или праздник, возвращаем пустой массив
        if (!$isWorkDay || $isHoliday) {
            return [];
        }

        // Проверяем, не слишком ли далеко в будущем
        $maxDate = $now->copy()->addDays($settings['appointment_days_ahead']);
        if ($selectedDate->greaterThan($maxDate)) {
            return [];
        }

        $currentTime = $startTime->copy();
        
        while ($currentTime->lessThan($endTime)) {
            $timeString = $currentTime->format('H:i');
            
            // Проверяем, не время ли перерыва
            if ($this->isBreakTime($timeString, $settings['break_times'])) {
                $currentTime->addMinutes($slotDuration);
                continue;
            }
            
            // Создаем полную дату и время для сравнения
            $slotDateTime = Carbon::parse($date . ' ' . $timeString);
            $isPast = $slotDateTime->lessThan($now);
            
            // Получаем все записи на это время
            // Добавляем журналирование для отладки
            if ($timeString === '14:00') {
                Log::info('Проверка записей для времени 14:00', [
                    'date' => $date,
                    'all_appointments' => $appointments->toArray(),
                    'appointments_count' => $appointments->count()
                ]);
            }
            
            $slotAppointments = $appointments->filter(function($apt) use ($timeString) {
                // Нормализуем время записи (преобразуем из MySQL time в формат H:i)
                if ($apt->appointment_time instanceof \Carbon\Carbon) {
                    $aptTime = $apt->appointment_time->format('H:i');
                } else {
                    $aptTime = Carbon::parse($apt->appointment_time)->format('H:i');
                }
                
                // Журналирование для отладки
                if ($timeString === '14:00') {
                    Log::info('Сравнение времени записи', [
                        'apt_id' => $apt->id,
                        'apt_time' => $apt->appointment_time,
                        'formatted_time' => $apt->formatted_time,
                        'normalized_time' => $aptTime,
                        'slot_time' => $timeString,
                        'status' => $apt->status,
                        'matches' => $aptTime === $timeString && $apt->status !== 'cancelled'
                    ]);
                }
                
                return $aptTime === $timeString && $apt->status !== 'cancelled';
            });
            
            $appointmentCount = $slotAppointments->count();
            $isFullyBooked = $appointmentCount >= $maxAppointmentsPerSlot;
            
            $slot = [
                'time' => $timeString,
                'appointments' => [],
                'available' => !$isFullyBooked && !$isPast && $isWorkDay && !$isHoliday,
                'isPast' => $isPast,
                'isOwner' => $isOwner,
                'isWorkDay' => $isWorkDay,
                'isHoliday' => $isHoliday,
                'appointment_count' => $appointmentCount,
                'max_appointments' => $maxAppointmentsPerSlot,
                'multiple_bookings_enabled' => $maxAppointmentsPerSlot > 1
            ];

            // Для владельца показываем полную информацию о записях, даже если слот еще доступен
            if ($isOwner && $slotAppointments->count() > 0) {
                $slot['appointments'] = $slotAppointments->map(function($appointment) {
                    return [
                        'id' => $appointment->id,
                        'title' => $appointment->service->name ?? 'Услуга',
                        'client_name' => $appointment->client_name,
                        'client_phone' => $appointment->client_phone,
                        'client_email' => $appointment->client_email,
                        'appointment_date' => $appointment->formatted_date ?? $appointment->appointment_date->format('Y-m-d'),
                        'appointment_time' => $appointment->formatted_time ?? $appointment->getFormattedTimeAttribute(),
                        'duration' => $appointment->service->formatted_duration ?? '30 мин',
                        'price' => $appointment->service->formatted_price ?? null,
                        'type' => $appointment->service->type ?? 'default',
                        'status' => $appointment->status,
                        'status_text' => $appointment->status_text,
                        'notes' => $appointment->notes,
                        'owner_notes' => $appointment->owner_notes,
                        'created_at' => $appointment->formatted_created_at ?? $appointment->created_at->format('d.m.Y H:i')
                    ];
                })->values()->toArray(); // Добавляем values() для нормализации индексов массива
                
                // Журналирование для отладки слотов с записями
                Log::info("Слот {$timeString} имеет {$slotAppointments->count()} записей", [
                    'appointments' => $slot['appointments']
                ]);
            }
            // Для обычных пользователей показываем информацию о занятости только если слот полностью занят
            elseif (!$isOwner && $slotAppointments->count() > 0 && $isFullyBooked) {
                $slot['appointments'] = [
                    [
                        'title' => 'Занято',
                        'type' => 'fully_occupied'
                    ]
                ];
            }

            $slots[] = $slot;
            $currentTime->addMinutes($slotDuration);
        }
        
        // Журналирование для отладки
        Log::info('Сгенерированы временные слоты', [
            'date' => $date,
            'slots_count' => count($slots),
            'slots' => collect($slots)->map(function($slot) {
                return [
                    'time' => $slot['time'],
                    'appointments_count' => is_array($slot['appointments']) 
                        ? count($slot['appointments']) 
                        : (is_object($slot['appointments']) ? count((array)$slot['appointments']) : 0),
                    'available' => $slot['available'],
                    'appointment_count' => $slot['appointment_count'],
                    'appointments' => $slot['appointments'] // Добавляем для отладки
                ];
            })->toArray()
        ]);

        return $slots;
    }

    /**
     * Проверяет, является ли дата праздником
     *
     * @param Carbon $date
     * @param array $holidays
     * @return bool
     */
    private function isHoliday($date, $holidays)
    {
        if (empty($holidays)) {
            return false;
        }

        $dateString = $date->format('Y-m-d');
        $monthDay = $date->format('m-d'); // Для повторяющихся праздников
        
        return in_array($dateString, $holidays) || in_array($monthDay, $holidays);
    }

    /**
     * Проверяет, является ли время временем перерыва
     *
     * @param string $time
     * @param array $breakTimes
     * @return bool
     */
    private function isBreakTime($time, $breakTimes)
    {
        if (empty($breakTimes)) {
            return false;
        }

        foreach ($breakTimes as $breakTime) {
            if (isset($breakTime['start']) && isset($breakTime['end'])) {
                $start = Carbon::parse($breakTime['start']);
                $end = Carbon::parse($breakTime['end']);
                $currentTime = Carbon::parse($time);
                
                if ($currentTime->between($start, $end)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Создает новую запись к компании
     *
     * @param string $slug
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function createAppointment($slug, Request $request)
    {
        $company = $this->getActiveCompany($slug);
        
        // Добавляем логирование
        Log::info('Попытка создания записи', [
            'date' => $request->appointment_date,
            'time' => $request->appointment_time,
            'client_name' => $request->client_name,
            'client_phone' => $request->client_phone,
            'current_time' => Carbon::now()->toDateTimeString(),
        ]);

        // Расширенная валидация
        $validator = Validator::make($request->all(), [
            'client_name' => 'required|string|max:255',
            'client_phone' => 'required|string|max:20',
            'client_email' => 'nullable|email|max:255',
            'service_id' => 'nullable|exists:services,id',
            'appointment_date' => 'required|date_format:Y-m-d',
            'appointment_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:1000'
        ], [
            'client_name.required' => 'Имя клиента обязательно для заполнения',
            'client_name.max' => 'Имя клиента не должно превышать 255 символов',
            'client_phone.required' => 'Номер телефона обязателен для заполнения',
            'client_phone.max' => 'Номер телефона не должен превышать 20 символов',
            'client_email.email' => 'Введите корректный email адрес',
            'client_email.max' => 'Email не должен превышать 255 символов',
            'service_id.exists' => 'Выбранная услуга не существует',
            'appointment_date.required' => 'Дата записи обязательна для заполнения',
            'appointment_date.date_format' => 'Дата записи должна быть в формате ГГГГ-ММ-ДД',
            'appointment_time.required' => 'Время записи обязательно для заполнения',
            'appointment_time.date_format' => 'Время записи должно быть в формате ЧЧ:ММ',
            'notes.max' => 'Комментарий не должен превышать 1000 символов'
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Ошибки валидации',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Дополнительная проверка времени - не позволяем записываться в прошлое
        $appointmentDateTime = Carbon::parse($request->appointment_date . ' ' . $request->appointment_time);
        $now = Carbon::now();
        
        // Проверяем рабочие дни компании
        $settings = $company->settings ?? [];
        $workDays = $settings['work_days'] ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $appointmentDate = Carbon::parse($request->appointment_date);
        $dayOfWeek = strtolower($appointmentDate->format('l'));
        
        if (!in_array($dayOfWeek, $workDays)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Выбранный день не является рабочим',
                    'errors' => ['appointment_date' => ['Выбранный день не является рабочим']]
                ], 422);
            }
            
            return redirect()->back()
                ->withErrors(['appointment_date' => 'Выбранный день не является рабочим'])
                ->withInput();
        }
        
        // Проверяем рабочие часы
        $workStartTime = $settings['work_start_time'] ?? '09:00';
        $workEndTime = $settings['work_end_time'] ?? '18:00';
        $appointmentTime = Carbon::parse($request->appointment_time);
        $workStart = Carbon::parse($workStartTime);
        $workEnd = Carbon::parse($workEndTime);
        
        if ($appointmentTime->lessThan($workStart) || $appointmentTime->greaterThanOrEqualTo($workEnd)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Выбранное время не входит в рабочие часы',
                    'errors' => ['appointment_time' => ['Выбранное время не входит в рабочие часы']]
                ], 422);
            }
            
            return redirect()->back()
                ->withErrors(['appointment_time' => 'Выбранное время не входит в рабочие часы'])
                ->withInput();
        }
        
        // Учитываем часовой пояс пользователя, если он был передан
        if ($request->has('timezone_offset')) {
            $timezoneOffset = (int) $request->timezone_offset;
            $timezoneHours = floor(abs($timezoneOffset) / 60);
            $timezoneMinutes = abs($timezoneOffset) % 60;
            
            $timezoneString = ($timezoneOffset <= 0 ? '+' : '-') . 
                              str_pad($timezoneHours, 2, '0', STR_PAD_LEFT) . ':' . 
                              str_pad($timezoneMinutes, 2, '0', STR_PAD_LEFT);
            
            Log::info('Применяем смещение часового пояса', [
                'timezone_offset' => $timezoneOffset,
                'timezone_string' => $timezoneString,
                'appointment_before' => $appointmentDateTime->toDateTimeString(),
                'now' => $now->toDateTimeString(),
            ]);
            
            // Применяем часовой пояс пользователя
            $appointmentDateTime->setTimezone($timezoneString);
            
            Log::info('После применения часового пояса', [
                'appointment_after' => $appointmentDateTime->toDateTimeString(),
            ]);
        }
        
        // Логируем сравнение времен
        Log::info('Сравнение времени записи', [
            'appointment_datetime' => $appointmentDateTime->toDateTimeString(),
            'now' => $now->toDateTimeString(),
            'is_past' => $appointmentDateTime->lessThan($now)
        ]);
        
        // Используем строгое сравнение с учетом часовых поясов
        if ($appointmentDateTime->lessThan($now)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Нельзя записаться на прошедшее время',
                    'errors' => ['appointment_time' => ['Нельзя записаться на прошедшее время']]
                ], 422);
            }
            
            return redirect()->back()
                ->withErrors(['appointment_time' => 'Нельзя записаться на прошедшее время'])
                ->withInput();
        }

        // Проверяем доступность слота с учетом множественных записей
        $maxAppointmentsPerSlot = $settings['max_appointments_per_slot'] ?? 1;
        $existingAppointmentsCount = Appointment::where('company_id', $company->id)
            ->where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($existingAppointmentsCount >= $maxAppointmentsPerSlot) {
            // Логируем конфликт записей
            Log::info('Попытка записи на полностью занятое время', [
                'date' => $request->appointment_date,
                'time' => $request->appointment_time,
                'existing_appointments_count' => $existingAppointmentsCount,
                'max_appointments_per_slot' => $maxAppointmentsPerSlot
            ]);
            
            $errorMessage = $maxAppointmentsPerSlot > 1 
                ? "Все места на это время заняты ({$existingAppointmentsCount}/{$maxAppointmentsPerSlot})"
                : 'Это время уже занято';
            
            if ($request->expectsJson()) {
                // Очищаем кэш чтобы обновить данные расписания
                $this->clearAppointmentsCache($company->id, $request->appointment_date);
                
                return response()->json([
                    'error' => $errorMessage,
                    'errors' => ['appointment_time' => [$errorMessage]],
                    'slot_info' => [
                        'existing_count' => $existingAppointmentsCount,
                        'max_allowed' => $maxAppointmentsPerSlot,
                        'multiple_bookings_enabled' => $maxAppointmentsPerSlot > 1
                    ]
                ], 422);
            }
            
            return redirect()->back()
                ->withErrors(['appointment_time' => $errorMessage])
                ->withInput();
        }

        // Получаем выбранную услугу или первую активную услугу компании
        $service = null;
        if ($request->service_id) {
            $service = $company->services()->where('id', $request->service_id)
                ->where('is_active', true)
                ->first();
        }
        
        if (!$service) {
            $service = $company->services()->where('is_active', true)->first();
        }
        
        if (!$service) {
            // Если нет активных услуг, создаем стандартную услугу
            $service = Service::create([
                'company_id' => $company->id,
                'name' => 'Стандартная консультация',
                'duration_minutes' => 30,
                'is_active' => true,
            ]);
        }

        // Создаем запись
        $appointment = Appointment::create([
            'company_id' => $company->id,
            'service_id' => $service->id,
            'client_name' => $request->client_name,
            'client_phone' => $request->client_phone,
            'client_email' => $request->client_email,
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'duration_minutes' => $service->duration_minutes,
            'status' => 'confirmed',
            'notes' => $request->notes,
        ]);

        // Очищаем кэш для данного дня
        $this->clearAppointmentsCache($company->id, $request->appointment_date);

        // Отправляем уведомление владельцу компании
        $this->sendNewAppointmentNotification($appointment);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Запись успешно создана!',
                'appointment' => $appointment
            ]);
        }

        return redirect()->back()->with('success', 'Запись успешно создана!');
    }
    
    /**
     * Очищает кэш записей для указанной даты и компании
     *
     * @param int $companyId
     * @param string $date
     * @return void
     */
    private function clearAppointmentsCache($companyId, $date)
    {
        Cache::forget("appointments.{$companyId}.{$date}.0");
        Cache::forget("appointments.{$companyId}.{$date}.1");
    }
    
    /**
     * Отправляет уведомление о новой записи владельцу компании
     *
     * @param Appointment $appointment
     * @return void
     */
    private function sendNewAppointmentNotification($appointment)
    {
        $company = $appointment->company;
        $owner = $company->user;
        
        // Отправляем email уведомление
        if ($owner && $owner->email) {
            try {
                Mail::to($owner->email)->send(new NewAppointmentNotification($appointment));
            } catch (\Exception $e) {
                // Логируем ошибку, но не прерываем выполнение
                Log::error('Ошибка отправки email уведомления о новой записи: ' . $e->getMessage());
            }
        }
        
        // Отправляем Telegram уведомление
        try {
            $this->telegramService->sendNewAppointmentNotification($appointment);
        } catch (\Exception $e) {
            Log::error('Ошибка отправки Telegram уведомления о новой записи: ' . $e->getMessage());
        }
    }
    
    /**
     * Отменяет запись
     *
     * @param string $slug
     * @param int $appointmentId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function cancelAppointment($slug, $appointmentId, Request $request)
    {
        $company = $this->getActiveCompany($slug);
        
        // Проверяем, является ли текущий пользователь владельцем компании
        $isOwner = auth()->check() && auth()->user()->id === $company->user_id;
        
        if (!$isOwner) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Доступ запрещен',
                    'message' => 'Только владелец компании может отменять записи'
                ], 403);
            }
            
            return redirect()->back()->with('error', 'Доступ запрещен. Только владелец компании может отменять записи.');
        }
        
        $appointment = Appointment::where('id', $appointmentId)
            ->where('company_id', $company->id)
            ->firstOrFail();
            
        $appointment->status = 'cancelled';
        $appointment->save();
        
        // Отправляем уведомление об отмене
        try {
            $this->telegramService->sendCancelledAppointmentNotification($appointment);
        } catch (\Exception $e) {
            Log::error('Ошибка отправки Telegram уведомления об отмене записи: ' . $e->getMessage());
        }
        
        // Очищаем кэш для данного дня
        $this->clearAppointmentsCache($company->id, $appointment->appointment_date);
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Запись успешно отменена'
            ]);
        }
        
        return redirect()->back()->with('success', 'Запись успешно отменена');
    }
    
    /**
     * Изменяет статус записи на "завершена"
     *
     * @param string $slug
     * @param int $appointmentId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function completeAppointment($slug, $appointmentId, Request $request)
    {
        $company = $this->getActiveCompany($slug);
        
        // Проверяем, является ли текущий пользователь владельцем компании
        $isOwner = auth()->check() && auth()->user()->id === $company->user_id;
        
        if (!$isOwner) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Доступ запрещен',
                    'message' => 'Только владелец компании может изменять статус записей'
                ], 403);
            }
            
            return redirect()->back()->with('error', 'Доступ запрещен. Только владелец компании может изменять статус записей.');
        }
        
        $appointment = Appointment::where('id', $appointmentId)
            ->where('company_id', $company->id)
            ->firstOrFail();
            
        $appointment->status = 'completed';
        $appointment->save();
        
        // Очищаем кэш для данного дня
        $this->clearAppointmentsCache($company->id, $appointment->appointment_date);
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Запись отмечена как завершенная'
            ]);
        }
        
        return redirect()->back()->with('success', 'Запись отмечена как завершенная');
    }
    
    /**
     * Переносит запись на другую дату и время
     *
     * @param Request $request
     * @param string $slug
     * @param int $appointmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function rescheduleAppointment(Request $request, $slug, $appointmentId)
    {
        $company = $this->getActiveCompany($slug);
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (!auth()->check() || auth()->user()->id !== $company->user_id) {
            return response()->json([
                'success' => false,
                'error' => 'Доступ запрещен'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Некорректные данные',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $appointment = Appointment::where('id', $appointmentId)
            ->where('company_id', $company->id)
            ->firstOrFail();
            
        $oldDate = Carbon::parse($appointment->appointment_date)->format('d.m.Y');
        $oldTime = $appointment->getFormattedTimeAttribute();
        
        // Проверяем доступность нового времени
        $newDate = $request->appointment_date;
        $newTime = $request->appointment_time;
        
        $existingAppointment = Appointment::where('company_id', $company->id)
            ->where('appointment_date', $newDate)
            ->where('appointment_time', $newTime)
            ->where('id', '!=', $appointmentId)
            ->where('status', '!=', 'cancelled')
            ->first();
            
        if ($existingAppointment) {
            return response()->json([
                'success' => false,
                'error' => 'Это время уже занято'
            ], 409);
        }
        
        $appointment->appointment_date = $newDate;
        $appointment->appointment_time = $newTime;
        $appointment->save();
        
        // Отправляем уведомление о переносе
        try {
            $this->telegramService->sendRescheduledAppointmentNotification($appointment, $oldDate, $oldTime);
        } catch (\Exception $e) {
            Log::error('Ошибка отправки Telegram уведомления о переносе записи: ' . $e->getMessage());
        }
        
        // Очищаем кэш для старой и новой даты
        $this->clearAppointmentsCache($company->id, $appointment->appointment_date);
        $this->clearAppointmentsCache($company->id, $newDate);
        
        return response()->json([
            'success' => true,
            'message' => 'Запись успешно перенесена'
        ]);
    }
    
    /**
     * Универсально обновляет запись (дата/время, контакты, заметки)
     *
     * @param Request $request
     * @param string $slug
     * @param int $appointmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAppointment(Request $request, $slug, $appointmentId)
    {
        $company = $this->getActiveCompany($slug);
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (!auth()->check() || auth()->user()->id !== $company->user_id) {
            return response()->json([
                'success' => false,
                'error' => 'Доступ запрещен'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'appointment_date' => 'nullable|date|after_or_equal:today',
            'appointment_time' => 'nullable|date_format:H:i',
            'client_name' => 'nullable|string|max:255',
            'client_phone' => 'nullable|string|max:20',
            'owner_notes' => 'nullable|string|max:500'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Некорректные данные',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $appointment = Appointment::where('id', $appointmentId)
            ->where('company_id', $company->id)
            ->firstOrFail();
            
        $oldDate = $appointment->appointment_date;
        $needsReschedule = false;
        
        // Проверяем обновление даты/времени
        if ($request->has('appointment_date') && $request->has('appointment_time')) {
            $newDate = $request->appointment_date;
            $newTime = $request->appointment_time;
            
            // Проверяем доступность нового времени
            $existingAppointment = Appointment::where('company_id', $company->id)
                ->where('appointment_date', $newDate)
                ->where('appointment_time', $newTime)
                ->where('id', '!=', $appointmentId)
                ->where('status', '!=', 'cancelled')
                ->first();
                
            if ($existingAppointment) {
                return response()->json([
                    'success' => false,
                    'error' => 'Это время уже занято'
                ], 409);
            }
            
            $appointment->appointment_date = $newDate;
            $appointment->appointment_time = $newTime;
            $needsReschedule = true;
        }
        
        // Обновляем контактную информацию
        if ($request->has('client_name') && $request->client_name) {
            $appointment->client_name = $request->client_name;
        }
        
        if ($request->has('client_phone')) {
            $appointment->client_phone = $request->client_phone;
        }
        
        if ($request->has('owner_notes')) {
            $appointment->owner_notes = $request->owner_notes;
        }
        
        $appointment->save();
        
        // Очищаем кэш для старой и новой даты (если дата изменилась)
        if ($needsReschedule) {
            $this->clearAppointmentsCache($company->id, $oldDate);
            $this->clearAppointmentsCache($company->id, $appointment->appointment_date);
        } else {
            $this->clearAppointmentsCache($company->id, $appointment->appointment_date);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Запись успешно обновлена'
        ]);
    }
    
    /**
     * Обновляет контактную информацию записи
     *
     * @param Request $request
     * @param string $slug
     * @param int $appointmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAppointmentContact(Request $request, $slug, $appointmentId)
    {
        $company = $this->getActiveCompany($slug);
        
        // Проверяем, является ли текущий пользователь владельцем компании
        if (!auth()->check() || auth()->user()->id !== $company->user_id) {
            return response()->json([
                'success' => false,
                'error' => 'Доступ запрещен'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'client_name' => 'required|string|max:255',
            'client_phone' => 'nullable|string|max:20',
            'owner_notes' => 'nullable|string|max:500'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Некорректные данные',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $appointment = Appointment::where('id', $appointmentId)
            ->where('company_id', $company->id)
            ->firstOrFail();
            
        $appointment->client_name = $request->client_name;
        $appointment->client_phone = $request->client_phone;
        $appointment->owner_notes = $request->owner_notes;
        $appointment->save();
        
        // Очищаем кэш для данного дня
        $this->clearAppointmentsCache($company->id, $appointment->appointment_date);
        
        return response()->json([
            'success' => true,
            'message' => 'Контактная информация обновлена'
        ]);
    }
}
