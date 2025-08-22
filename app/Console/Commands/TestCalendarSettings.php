<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use Carbon\Carbon;

class TestCalendarSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calendar:test-settings {company_slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Тестирует настройки календаря для указанной компании';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $slug = $this->argument('company_slug');
        $company = Company::where('slug', $slug)->first();
        
        if (!$company) {
            $this->error("Компания с slug '{$slug}' не найдена");
            return 1;
        }
        
        $this->info("Тестирование настроек календаря для компании: {$company->name}");
        $this->newLine();
        
        $settings = $company->settings ?? [];
        
        // Отображаем текущие настройки
        $this->info("Текущие настройки:");
        $this->table(['Параметр', 'Значение'], [
            ['Рабочее время', ($settings['work_start_time'] ?? '09:00') . ' - ' . ($settings['work_end_time'] ?? '18:00')],
            ['Интервал записи', ($settings['appointment_interval'] ?? 30) . ' минут'],
            ['Дни для записи вперед', ($settings['appointment_days_ahead'] ?? 14) . ' дней'],
            ['Рабочие дни', implode(', ', $settings['work_days'] ?? [])],
            ['Email уведомления', ($settings['email_notifications'] ?? true) ? 'Включены' : 'Отключены'],
            ['Требует подтверждения', ($settings['require_confirmation'] ?? false) ? 'Да' : 'Нет'],
            ['Праздники', implode(', ', $settings['holidays'] ?? [])],
            ['Перерывы', $this->formatBreakTimes($settings['break_times'] ?? [])],
        ]);
        
        $this->newLine();
        
        // Тестируем ближайшие 7 дней
        $this->info("Тестирование доступности ближайших 7 дней:");
        $this->newLine();
        
        $now = Carbon::now();
        $headers = ['Дата', 'День недели', 'Рабочий день', 'Праздник', 'Доступен', 'Временные слоты'];
        $rows = [];
        
        for ($i = 0; $i < 7; $i++) {
            $testDate = $now->copy()->addDays($i);
            
            $dayOfWeek = strtolower($testDate->format('l'));
            $isWorkDay = in_array($dayOfWeek, $settings['work_days'] ?? []);
            $isHoliday = $this->isHoliday($testDate, $settings['holidays'] ?? []);
            $maxDate = $now->copy()->addDays($settings['appointment_days_ahead'] ?? 14);
            $isAvailable = $isWorkDay && !$isHoliday && $testDate->lessThanOrEqualTo($maxDate);
            
            $timeSlots = 0;
            if ($isAvailable) {
                $timeSlots = $this->calculateTimeSlots($settings);
            }
            
            $rows[] = [
                $testDate->format('d.m.Y'),
                $this->translateDayName($dayOfWeek),
                $isWorkDay ? 'Да' : 'Нет',
                $isHoliday ? 'Да' : 'Нет',
                $isAvailable ? 'Да' : 'Нет',
                $timeSlots > 0 ? $timeSlots : '-'
            ];
        }
        
        $this->table($headers, $rows);
        
        $this->newLine();
        $this->info("Тестирование завершено!");
        
        return 0;
    }
    
    private function isHoliday($date, $holidays)
    {
        $dateString = $date->format('Y-m-d');
        $monthDay = $date->format('m-d');
        
        return in_array($dateString, $holidays) || in_array($monthDay, $holidays);
    }
    
    private function calculateTimeSlots($settings)
    {
        $startTime = Carbon::parse($settings['work_start_time'] ?? '09:00');
        $endTime = Carbon::parse($settings['work_end_time'] ?? '18:00');
        $interval = $settings['appointment_interval'] ?? 30;
        $breakTimes = $settings['break_times'] ?? [];
        
        $slots = 0;
        $currentTime = $startTime->copy();
        
        while ($currentTime->lessThan($endTime)) {
            $timeString = $currentTime->format('H:i');
            
            // Проверяем, не время ли перерыва
            $isBreakTime = false;
            foreach ($breakTimes as $breakTime) {
                if (isset($breakTime['start']) && isset($breakTime['end'])) {
                    $breakStart = Carbon::parse($breakTime['start']);
                    $breakEnd = Carbon::parse($breakTime['end']);
                    
                    if ($currentTime->between($breakStart, $breakEnd)) {
                        $isBreakTime = true;
                        break;
                    }
                }
            }
            
            if (!$isBreakTime) {
                $slots++;
            }
            
            $currentTime->addMinutes($interval);
        }
        
        return $slots;
    }
    
    private function formatBreakTimes($breakTimes)
    {
        if (empty($breakTimes)) {
            return 'Нет';
        }
        
        $formatted = [];
        foreach ($breakTimes as $breakTime) {
            if (isset($breakTime['start']) && isset($breakTime['end'])) {
                $formatted[] = $breakTime['start'] . '-' . $breakTime['end'];
            }
        }
        
        return implode(', ', $formatted) ?: 'Нет';
    }
    
    private function translateDayName($dayName)
    {
        $translation = [
            'monday' => 'Понедельник',
            'tuesday' => 'Вторник',
            'wednesday' => 'Среда',
            'thursday' => 'Четверг',
            'friday' => 'Пятница',
            'saturday' => 'Суббота',
            'sunday' => 'Воскресенье'
        ];
        
        return $translation[$dayName] ?? $dayName;
    }
}
