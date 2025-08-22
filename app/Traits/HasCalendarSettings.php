<?php

namespace App\Traits;

use Carbon\Carbon;

trait HasCalendarSettings
{
    /**
     * Получает настройки календаря с дефолтными значениями
     *
     * @return array
     */
    public function getCalendarSettings(): array
    {
        $settings = $this->settings ?? [];
        
        // Базовые настройки календаря с дефолтными значениями
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
            'max_appointments_per_slot' => (int)($settings['max_appointments_per_slot'] ?? 1),
        ];
        
        // Валидация настроек
        if (!is_array($calendarSettings['work_days']) || empty($calendarSettings['work_days'])) {
            $calendarSettings['work_days'] = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        }
        
        if (!is_array($calendarSettings['holidays'])) {
            $calendarSettings['holidays'] = [];
        }
        
        if (!is_array($calendarSettings['break_times'])) {
            $calendarSettings['break_times'] = [];
        }
        
        return $calendarSettings;
    }
    
    /**
     * Проверяет, является ли дата праздником
     *
     * @param Carbon $date
     * @return bool
     */
    public function isHoliday(Carbon $date): bool
    {
        $settings = $this->getCalendarSettings();
        $formattedDate = $date->format('Y-m-d');
        
        return in_array($formattedDate, $settings['holidays']);
    }
    
    /**
     * Проверяет, является ли дата рабочим днем
     *
     * @param Carbon $date
     * @return bool
     */
    public function isWorkDay(Carbon $date): bool
    {
        $settings = $this->getCalendarSettings();
        $dayName = strtolower($date->englishDayOfWeek);
        
        return in_array($dayName, $settings['work_days']) && !$this->isHoliday($date);
    }
    
    /**
     * Проверяет, попадает ли время в перерыв
     *
     * @param string $time Формат 'HH:MM'
     * @return bool
     */
    public function isBreakTime(string $time): bool
    {
        $settings = $this->getCalendarSettings();
        $timeCarbon = Carbon::createFromFormat('H:i', $time);
        
        foreach ($settings['break_times'] as $breakTime) {
            $breakStart = Carbon::createFromFormat('H:i', $breakTime['start']);
            $breakEnd = Carbon::createFromFormat('H:i', $breakTime['end']);
            
            if ($timeCarbon->between($breakStart, $breakEnd, true)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Генерирует временные слоты для указанной даты
     *
     * @param Carbon $date
     * @return array
     */
    public function generateTimeSlots(Carbon $date): array
    {
        $settings = $this->getCalendarSettings();
        $slots = [];
        
        // Если не рабочий день, возвращаем пустой массив
        if (!$this->isWorkDay($date)) {
            return $slots;
        }
        
        // Получаем время начала и окончания работы
        $startTime = Carbon::createFromFormat('H:i', $settings['work_start_time']);
        $endTime = Carbon::createFromFormat('H:i', $settings['work_end_time']);
        $interval = $settings['appointment_interval'];
        
        // Создаем временный объект для итерации
        $currentTime = $startTime->copy();
        
        // Получаем текущее время для проверки прошедших слотов
        $now = Carbon::now();
        $isToday = $date->isToday();
        
        // Генерируем слоты
        while ($currentTime < $endTime) {
            $timeString = $currentTime->format('H:i');
            
            // Проверяем, не попадает ли в перерыв
            if (!$this->isBreakTime($timeString)) {
                $isPast = $isToday && $currentTime < $now;
                
                $slots[] = [
                    'time' => $timeString,
                    'isPast' => $isPast,
                    'available' => !$isPast,
                ];
            }
            
            // Увеличиваем время на интервал
            $currentTime->addMinutes($interval);
        }
        
        return $slots;
    }
}
