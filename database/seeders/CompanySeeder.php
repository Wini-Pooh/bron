<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Service;
use App\Models\Appointment;
use Carbon\Carbon;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем тестовую компанию
        $company = Company::create([
            'name' => 'МедиЦентр "Здоровье+"',
            'slug' => 'medcentr-zdorove',
            'description' => 'Многопрофильная медицинская клиника с современным оборудованием и высококвалифицированными специалистами.',
            'specialty' => 'Многопрофильная медицинская клиника',
            'phone' => '+7 (495) 123-45-67',
            'email' => 'info@zdorovye-plus.ru',
            'address' => 'г. Москва, ул. Примерная, д. 123',
            'website' => 'https://zdorovye-plus.ru',
            'total_clients' => 1250,
            'total_specialists' => 15,
            'years_experience' => 5,
            'satisfaction_rate' => 98.5,
            'is_active' => true,
        ]);

        // Создаем услуги для компании
        $services = [
            [
                'name' => 'Терапевтическая консультация',
                'description' => 'Первичный осмотр и консультация врача-терапевта',
                'price' => 2500.00,
                'duration_minutes' => 30,
                'type' => 'consultation'
            ],
            [
                'name' => 'Кардиологическое обследование',
                'description' => 'Полное обследование сердечно-сосудистой системы',
                'price' => 4500.00,
                'duration_minutes' => 45,
                'type' => 'treatment'
            ],
            [
                'name' => 'УЗИ диагностика',
                'description' => 'Ультразвуковое исследование органов',
                'price' => 3200.00,
                'duration_minutes' => 30,
                'type' => 'procedure'
            ],
            [
                'name' => 'Неврологическая консультация',
                'description' => 'Консультация врача-невролога',
                'price' => 3000.00,
                'duration_minutes' => 40,
                'type' => 'consultation'
            ],
            [
                'name' => 'Срочная консультация',
                'description' => 'Экстренная медицинская помощь',
                'price' => 5000.00,
                'duration_minutes' => 20,
                'type' => 'urgent'
            ]
        ];

        foreach ($services as $serviceData) {
            $serviceData['company_id'] = $company->id;
            Service::create($serviceData);
        }

        // Создаем тестовые записи на сегодня и завтра
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        
        $appointments = [
            // Записи на сегодня
            [
                'company_id' => $company->id,
                'service_id' => 1, // Терапевтическая консультация
                'client_name' => 'Иван Петров',
                'client_phone' => '+7 (901) 234-56-78',
                'client_email' => 'ivan.petrov@example.com',
                'appointment_date' => $today,
                'appointment_time' => '09:00:00',
                'duration_minutes' => 30,
                'status' => 'confirmed'
            ],
            [
                'company_id' => $company->id,
                'service_id' => 2, // Кардиологическое обследование
                'client_name' => 'Мария Сидорова',
                'client_phone' => '+7 (902) 345-67-89',
                'appointment_date' => $today,
                'appointment_time' => '10:30:00',
                'duration_minutes' => 45,
                'status' => 'confirmed'
            ],
            [
                'company_id' => $company->id,
                'service_id' => 3, // УЗИ диагностика
                'client_name' => 'Алексей Козлов',
                'client_phone' => '+7 (903) 456-78-90',
                'appointment_date' => $today,
                'appointment_time' => '13:00:00',
                'duration_minutes' => 30,
                'status' => 'confirmed'
            ],
            [
                'company_id' => $company->id,
                'service_id' => 5, // Срочная консультация
                'client_name' => 'Елена Волкова',
                'client_phone' => '+7 (904) 567-89-01',
                'appointment_date' => $today,
                'appointment_time' => '15:30:00',
                'duration_minutes' => 20,
                'status' => 'confirmed'
            ],
            // Записи на завтра
            [
                'company_id' => $company->id,
                'service_id' => 1, // Терапевтическая консультация
                'client_name' => 'Дмитрий Новиков',
                'client_phone' => '+7 (905) 678-90-12',
                'appointment_date' => $tomorrow,
                'appointment_time' => '11:00:00',
                'duration_minutes' => 30,
                'status' => 'confirmed'
            ],
            [
                'company_id' => $company->id,
                'service_id' => 2, // Кардиологическое обследование
                'client_name' => 'Анна Морозова',
                'client_phone' => '+7 (906) 789-01-23',
                'appointment_date' => $tomorrow,
                'appointment_time' => '14:00:00',
                'duration_minutes' => 45,
                'status' => 'confirmed'
            ]
        ];

        foreach ($appointments as $appointmentData) {
            Appointment::create($appointmentData);
        }

        // Создаем еще одну компанию для примера
        $company2 = Company::create([
            'name' => 'Стоматология "Белоснежка"',
            'slug' => 'stomatologiya-belosnezhka',
            'description' => 'Современная стоматологическая клиника с полным спектром услуг.',
            'specialty' => 'Стоматологические услуги',
            'phone' => '+7 (495) 987-65-43',
            'email' => 'info@belosnezhka.ru',
            'address' => 'г. Москва, ул. Зубная, д. 45',
            'total_clients' => 850,
            'total_specialists' => 8,
            'years_experience' => 3,
            'satisfaction_rate' => 96.8,
            'is_active' => true,
        ]);

        // Услуги стоматологии
        $dentalServices = [
            [
                'company_id' => $company2->id,
                'name' => 'Консультация стоматолога',
                'description' => 'Первичный осмотр и составление плана лечения',
                'price' => 1500.00,
                'duration_minutes' => 30,
                'type' => 'consultation'
            ],
            [
                'company_id' => $company2->id,
                'name' => 'Лечение кариеса',
                'description' => 'Лечение кариеса с установкой пломбы',
                'price' => 4000.00,
                'duration_minutes' => 60,
                'type' => 'treatment'
            ],
            [
                'company_id' => $company2->id,
                'name' => 'Профессиональная чистка зубов',
                'description' => 'Удаление зубного камня и налета',
                'price' => 3500.00,
                'duration_minutes' => 45,
                'type' => 'procedure'
            ],
            [
                'company_id' => $company2->id,
                'name' => 'Экстренная помощь',
                'description' => 'Экстренное лечение острой зубной боли',
                'price' => 2500.00,
                'duration_minutes' => 30,
                'type' => 'urgent'
            ]
        ];

        foreach ($dentalServices as $serviceData) {
            Service::create($serviceData);
        }
    }
}
