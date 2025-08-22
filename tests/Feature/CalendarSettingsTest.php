<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use Carbon\Carbon;

class CalendarSettingsTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $company;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id,
            'slug' => 'test-company',
            'settings' => [
                'work_start_time' => '09:00',
                'work_end_time' => '18:00',
                'appointment_interval' => 30,
                'appointment_days_ahead' => 14,
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'email_notifications' => true,
                'require_confirmation' => false,
                'holidays' => ['2025-01-01', '01-07'],
                'break_times' => [
                    ['start' => '13:00', 'end' => '14:00']
                ],
                'max_appointments_per_slot' => 3
            ]
        ]);
    }

    /** @test */
    public function компания_отображается_с_настройками_календаря()
    {
        $response = $this->get(route('company.show', $this->company->slug));

        $response->assertStatus(200)
            ->assertViewHas('calendarSettings')
            ->assertViewHas('company', $this->company);
    }

    /** @test */
    public function настройки_календаря_содержат_корректные_данные()
    {
        $response = $this->get(route('company.show', $this->company->slug));
        
        $calendarSettings = $response->viewData('calendarSettings');
        
        $this->assertEquals('09:00', $calendarSettings['work_start_time']);
        $this->assertEquals('18:00', $calendarSettings['work_end_time']);
        $this->assertEquals(30, $calendarSettings['appointment_interval']);
        $this->assertEquals(14, $calendarSettings['appointment_days_ahead']);
        $this->assertContains('monday', $calendarSettings['work_days']);
        $this->assertContains('2025-01-01', $calendarSettings['holidays']);
        $this->assertNotEmpty($calendarSettings['break_times']);
    }

    /** @test */
    public function авторизованный_владелец_может_обновить_настройки()
    {
        $this->actingAs($this->user);

        $response = $this->put(route('company.settings.update', $this->company->slug), [
            'work_start_time' => '08:00',
            'work_end_time' => '19:00',
            'appointment_interval' => 60,
            'appointment_days_ahead' => 30,
            'work_days' => ['monday', 'tuesday', 'wednesday'],
            'email_notifications' => true,
            'holidays' => '2025-12-31, 01-01',
            'break_start' => '12:00',
            'break_end' => '13:00'
        ]);

        $response->assertRedirect(route('company.settings', $this->company->slug))
            ->assertSessionHas('success');

        $this->company->refresh();
        $settings = $this->company->settings; // Это уже массив благодаря cast в модели
        
        $this->assertEquals('08:00', $settings['work_start_time']);
        $this->assertEquals('19:00', $settings['work_end_time']);
        $this->assertEquals(60, $settings['appointment_interval']);
        $this->assertEquals(30, $settings['appointment_days_ahead']);
        $this->assertContains('2025-12-31', $settings['holidays']);
        $this->assertContains('01-01', $settings['holidays']);
    }

    /** @test */
    public function временные_слоты_учитывают_рабочие_дни()
    {
        // Понедельник - рабочий день
        $monday = Carbon::parse('next monday')->format('Y-m-d');
        
        $response = $this->get(route('company.appointments', $this->company->slug) . '?date=' . $monday);
        
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertNotEmpty($data['timeSlots']);
        
        // Воскресенье - выходной день
        $sunday = Carbon::parse('next sunday')->format('Y-m-d');
        
        $response = $this->get(route('company.appointments', $this->company->slug) . '?date=' . $sunday);
        
        $data = $response->json();
        $this->assertEmpty($data['timeSlots']);
    }

    /** @test */
    public function временные_слоты_учитывают_праздники()
    {
        // Устанавливаем завтрашний день как праздник
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');
        $settings = $this->company->settings;
        $settings['holidays'] = [$tomorrow];
        
        $this->company->update(['settings' => $settings]);

        $response = $this->get(route('company.appointments', $this->company->slug) . '?date=' . $tomorrow);
        
        $data = $response->json();
        $this->assertEmpty($data['timeSlots']);
    }

    /** @test */
    public function нельзя_записаться_далеко_в_будущем()
    {
        // Дата больше чем appointment_days_ahead (14 дней)
        $farFuture = Carbon::now()->addDays(20)->format('Y-m-d');
        
        $response = $this->get(route('company.appointments', $this->company->slug) . '?date=' . $farFuture);
        
        $data = $response->json();
        $this->assertEmpty($data['timeSlots']);
    }

    /** @test */
    public function можно_создавать_несколько_записей_на_одно_время()
    {
        $this->actingAs($this->user);
        
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');
        $time = '10:00';
        
        // Создаем первую запись
        $response1 = $this->post(route('company.appointments.create', $this->company->slug), [
            'appointment_date' => $tomorrow,
            'appointment_time' => $time,
            'client_name' => 'Клиент 1',
            'client_phone' => '+7 (123) 456-78-90',
            'client_email' => 'client1@example.com'
        ]);
        
        $this->assertDatabaseHas('appointments', [
            'company_id' => $this->company->id,
            'appointment_date' => $tomorrow,
            'appointment_time' => $time,
            'client_name' => 'Клиент 1'
        ]);
        
        // Создаем вторую запись на то же время
        $response2 = $this->post(route('company.appointments.create', $this->company->slug), [
            'appointment_date' => $tomorrow,
            'appointment_time' => $time,
            'client_name' => 'Клиент 2',
            'client_phone' => '+7 (123) 456-78-91',
            'client_email' => 'client2@example.com'
        ]);
        
        $this->assertDatabaseHas('appointments', [
            'company_id' => $this->company->id,
            'appointment_date' => $tomorrow,
            'appointment_time' => $time,
            'client_name' => 'Клиент 2'
        ]);
        
        // Создаем третью запись на то же время
        $response3 = $this->post(route('company.appointments.create', $this->company->slug), [
            'appointment_date' => $tomorrow,
            'appointment_time' => $time,
            'client_name' => 'Клиент 3',
            'client_phone' => '+7 (123) 456-78-92',
            'client_email' => 'client3@example.com'
        ]);
        
        $this->assertDatabaseHas('appointments', [
            'company_id' => $this->company->id,
            'appointment_date' => $tomorrow,
            'appointment_time' => $time,
            'client_name' => 'Клиент 3'
        ]);
        
        // Проверяем, что 4-я запись будет отклонена
        $response4 = $this->post(route('company.appointments.create', $this->company->slug), [
            'appointment_date' => $tomorrow,
            'appointment_time' => $time,
            'client_name' => 'Клиент 4',
            'client_phone' => '+7 (123) 456-78-93',
            'client_email' => 'client4@example.com'
        ]);
        
        $this->assertDatabaseMissing('appointments', [
            'company_id' => $this->company->id,
            'appointment_date' => $tomorrow,
            'appointment_time' => $time,
            'client_name' => 'Клиент 4'
        ]);
    }
}
