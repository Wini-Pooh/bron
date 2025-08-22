<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TelegramWebhookController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('/home');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Публичные маршруты для компаний
Route::get('/company/{slug}', [CompanyController::class, 'show'])->name('company.show');
Route::get('/company/{slug}/appointments', [CompanyController::class, 'getAppointments'])->name('company.appointments');
Route::get('/company/{slug}/monthly-stats', [CompanyController::class, 'getMonthlyStats'])->name('company.monthly-stats');
Route::post('/company/{slug}/appointments', [CompanyController::class, 'createAppointment'])->name('company.appointments.create');

// Маршруты для управления записями (требуют аутентификации)
Route::middleware(['auth'])->group(function () {
    // Управление записями
    Route::put('/company/{slug}/appointments/{appointmentId}/update', [CompanyController::class, 'updateAppointment'])->name('company.appointments.update');
    Route::put('/company/{slug}/appointments/{appointmentId}/cancel', [CompanyController::class, 'cancelAppointment'])->name('company.appointments.cancel');
    Route::put('/company/{slug}/appointments/{appointmentId}/complete', [CompanyController::class, 'completeAppointment'])->name('company.appointments.complete');
    Route::put('/company/{slug}/appointments/{appointmentId}/reschedule', [CompanyController::class, 'rescheduleAppointment'])->name('company.appointments.reschedule');
    Route::put('/company/{slug}/appointments/{appointmentId}/update-contact', [CompanyController::class, 'updateAppointmentContact'])->name('company.appointments.update-contact');
    
    // Управление компанией
    Route::get('/company/create', [App\Http\Controllers\CompanyManagementController::class, 'create'])->name('company.create');
    Route::post('/company', [App\Http\Controllers\CompanyManagementController::class, 'store'])->name('company.store');
    Route::get('/company/{slug}/edit', [App\Http\Controllers\CompanyManagementController::class, 'edit'])->name('company.edit');
    Route::put('/company/{slug}', [App\Http\Controllers\CompanyManagementController::class, 'update'])->name('company.update');
    Route::get('/company/{slug}/settings', [App\Http\Controllers\CompanyManagementController::class, 'settings'])->name('company.settings');
    Route::put('/company/{slug}/settings', [App\Http\Controllers\CompanyManagementController::class, 'updateSettings'])->name('company.settings.update');
    Route::get('/company/{slug}/all-appointments', [App\Http\Controllers\CompanyManagementController::class, 'appointments'])->name('company.all-appointments');
    
    // Временный роут для отладки настроек
    Route::get('/company/{slug}/debug-settings', [App\Http\Controllers\CompanyManagementController::class, 'debugSettings'])->name('company.debug-settings');
    
    // Управление услугами
    Route::get('/company/{slug}/services', [App\Http\Controllers\ServiceController::class, 'index'])->name('company.services.index');
    Route::get('/company/{slug}/services/create', [App\Http\Controllers\ServiceController::class, 'create'])->name('company.services.create');
    Route::post('/company/{slug}/services', [App\Http\Controllers\ServiceController::class, 'store'])->name('company.services.store');
    Route::get('/company/{slug}/services/{serviceId}/edit', [App\Http\Controllers\ServiceController::class, 'edit'])->name('company.services.edit');
    Route::put('/company/{slug}/services/{serviceId}', [App\Http\Controllers\ServiceController::class, 'update'])->name('company.services.update');
    Route::delete('/company/{slug}/services/{serviceId}', [App\Http\Controllers\ServiceController::class, 'destroy'])->name('company.services.destroy');
    
    // Telegram настройки
    Route::put('/company/{slug}/telegram/settings', [TelegramController::class, 'updateSettings'])->name('company.telegram.settings');
    Route::post('/company/{slug}/telegram/test', [TelegramController::class, 'testConnection'])->name('company.telegram.test');
    Route::get('/company/{slug}/telegram/bot-info', [TelegramController::class, 'getBotInfo'])->name('company.telegram.bot-info');
    Route::post('/company/{slug}/telegram/webhook', [TelegramController::class, 'setWebhook'])->name('company.telegram.webhook');
    Route::get('/company/{slug}/telegram/webhook-info', [TelegramController::class, 'getWebhookInfo'])->name('company.telegram.webhook-info');
});

// Публичный маршрут для Telegram webhook (без middleware auth)
Route::post('/telegram/webhook/{botToken}', [TelegramWebhookController::class, 'handle'])->name('telegram.webhook');
