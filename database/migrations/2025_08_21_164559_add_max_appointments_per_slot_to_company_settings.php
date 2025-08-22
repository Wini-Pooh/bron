<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Company;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Обновляем настройки существующих компаний, добавляя max_appointments_per_slot
        Company::chunk(100, function ($companies) {
            foreach ($companies as $company) {
                $settings = $company->settings ?? [];
                
                // Добавляем новую настройку, если её еще нет
                if (!isset($settings['max_appointments_per_slot'])) {
                    $settings['max_appointments_per_slot'] = 1; // По умолчанию 1 запись на слот
                    $company->settings = $settings;
                    $company->save();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем настройку max_appointments_per_slot из существующих компаний
        Company::chunk(100, function ($companies) {
            foreach ($companies as $company) {
                $settings = $company->settings ?? [];
                
                // Удаляем настройку
                if (isset($settings['max_appointments_per_slot'])) {
                    unset($settings['max_appointments_per_slot']);
                    $company->settings = $settings;
                    $company->save();
                }
            }
        });
    }
};
