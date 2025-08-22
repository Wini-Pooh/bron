@extends('layouts.app')

@section('styles')
<link href="{{ asset('css/company-settings.css') }}" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
@endsection

@section('content')
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Заголовок страницы -->
            <div class="settings-card fade-in-up">
                <div class="settings-card-header">
                    <i class="fas fa-cogs"></i>
                    Настройки компании "{{ $company->name }}"
                    <div class="ms-auto">
                        <a href="{{ route('company.show', $company->slug) }}" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left"></i> Назад к профилю
                        </a>
                    </div>
                </div>
                <div class="settings-card-body">
                    <!-- Уведомления -->
                    @if (session('success'))
                        <div class="alert alert-success-enhanced" role="alert">
                            <i class="fas fa-check-circle"></i>
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger-enhanced" role="alert">
                            <i class="fas fa-exclamation-circle"></i>
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger-enhanced">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Исправьте следующие ошибки:</strong>
                            <ul class="mb-0 mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('company.settings.update', $company->slug) }}" id="settingsForm">
                @csrf
                @method('PUT')

                <!-- Рабочее время -->
                <div class="settings-card fade-in-up">
                    <div class="settings-card-header">
                        <i class="fas fa-clock"></i>
                        Рабочее время и интервалы
                    </div>
                    <div class="settings-card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label for="work_start_time" class="form-label-enhanced">
                                        Время начала рабочего дня
                                        <span class="field-tooltip" data-tooltip="Время, с которого начинается прием клиентов">
                                        </span>
                                    </label>
                                    <div class="time-input-wrapper">
                                        <input type="time" 
                                               class="form-control-enhanced time-input @error('work_start_time') is-invalid @enderror" 
                                               id="work_start_time" 
                                               name="work_start_time" 
                                               value="{{ old('work_start_time', ($company->settings['work_start_time'] ?? '09:00')) }}"
                                               step="900">
                                        <button type="button" class="clear-time" onclick="clearTimeField('work_start_time')" title="Очистить поле">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    @error('work_start_time')
                                        <div class="invalid-feedback-enhanced">
                                            <i class="fas fa-exclamation-circle"></i>
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label for="work_end_time" class="form-label-enhanced">
                                        Время окончания рабочего дня
                                        <span class="field-tooltip" data-tooltip="Время, до которого осуществляется прием клиентов">
                                        </span>
                                    </label>
                                    <div class="time-input-wrapper">
                                        <input type="time" 
                                               class="form-control-enhanced time-input @error('work_end_time') is-invalid @enderror" 
                                               id="work_end_time" 
                                               name="work_end_time" 
                                               value="{{ old('work_end_time', ($company->settings['work_end_time'] ?? '18:00')) }}"
                                               step="900">
                                        <button type="button" class="clear-time" onclick="clearTimeField('work_end_time')" title="Очистить поле">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    @error('work_end_time')
                                        <div class="invalid-feedback-enhanced">
                                            <i class="fas fa-exclamation-circle"></i>
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label for="appointment_interval" class="form-label-enhanced">
                                        Интервал записи
                                        <span class="field-tooltip" data-tooltip="Минимальное время между записями в минутах">
                                        </span>
                                    </label>
                                    <div class="number-input-wrapper" data-suffix="мин">
                                        <input type="number" 
                                               class="form-control-enhanced @error('appointment_interval') is-invalid @enderror" 
                                               id="appointment_interval" 
                                               name="appointment_interval" 
                                               min="10" max="120" step="5"
                                               value="{{ old('appointment_interval', ($company->settings['appointment_interval'] ?? 30)) }}">
                                    </div>
                                    @error('appointment_interval')
                                        <div class="invalid-feedback-enhanced">
                                            <i class="fas fa-exclamation-circle"></i>
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label for="appointment_days_ahead" class="form-label-enhanced">
                                        Дней для предварительной записи
                                        <span class="field-tooltip" data-tooltip="На сколько дней вперед можно записаться">
                                        </span>
                                    </label>
                                    <div class="number-input-wrapper" data-suffix="дн">
                                        <input type="number" 
                                               class="form-control-enhanced @error('appointment_days_ahead') is-invalid @enderror" 
                                               id="appointment_days_ahead" 
                                               name="appointment_days_ahead" 
                                               min="1" max="90"
                                               value="{{ old('appointment_days_ahead', ($company->settings['appointment_days_ahead'] ?? 14)) }}">
                                    </div>
                                    @error('appointment_days_ahead')
                                        <div class="invalid-feedback-enhanced">
                                            <i class="fas fa-exclamation-circle"></i>
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label for="max_appointments_per_slot" class="form-label-enhanced">
                                        Максимум записей на одно время
                                        <span class="field-tooltip" data-tooltip="Количество клиентов на один временной слот">
                                        </span>
                                    </label>
                                    <div class="number-input-wrapper" data-suffix="чел">
                                        <input type="number" 
                                               class="form-control-enhanced @error('max_appointments_per_slot') is-invalid @enderror" 
                                               id="max_appointments_per_slot" 
                                               name="max_appointments_per_slot" 
                                               min="1" max="10"
                                               value="{{ old('max_appointments_per_slot', ($company->settings['max_appointments_per_slot'] ?? 1)) }}">
                                    </div>
                                    @error('max_appointments_per_slot')
                                        <div class="invalid-feedback-enhanced">
                                            <i class="fas fa-exclamation-circle"></i>
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Рабочие дни -->
                <div class="settings-card fade-in-up">
                    <div class="settings-card-header">
                        <i class="fas fa-calendar-week"></i>
                        Рабочие дни
                    </div>
                    <div class="settings-card-body">
                        <div class="form-group-enhanced">
                            @php
                                $days = [
                                    'monday' => 'Понедельник',
                                    'tuesday' => 'Вторник', 
                                    'wednesday' => 'Среда', 
                                    'thursday' => 'Четверг', 
                                    'friday' => 'Пятница', 
                                    'saturday' => 'Суббота', 
                                    'sunday' => 'Воскресенье'
                                ];
                                $workDays = ($company->settings['work_days'] ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
                            @endphp
                            
                            <div class="checkbox-group">
                                @foreach($days as $key => $day)
                                    <div class="checkbox-item {{ in_array($key, old('work_days', $workDays)) ? 'active' : '' }}" 
                                         data-day="{{ $key }}">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="work_days[]" 
                                               value="{{ $key }}" 
                                               id="day_{{ $key }}" 
                                               {{ in_array($key, old('work_days', $workDays)) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="day_{{ $key }}">
                                            {{ $day }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Перерывы -->
                <div class="settings-card fade-in-up">
                    <div class="settings-card-header">
                        <i class="fas fa-coffee"></i>
                        Перерывы в рабочем дне
                    </div>
                    <div class="settings-card-body">
                        <div class="time-input-group">
                            <div class="form-group-enhanced">
                                <label for="break_start" class="form-label-enhanced">
                                    Начало перерыва
                                    <span class="field-tooltip" data-tooltip="Время начала обеденного перерыва">
                                    </span>
                                </label>
                                <div class="time-input-wrapper">
                                    <input type="time" 
                                           class="form-control-enhanced time-input @error('break_start') is-invalid @enderror" 
                                           id="break_start" 
                                           name="break_start" 
                                           value="{{ old('break_start', ($company->settings['break_times'][0]['start'] ?? '')) }}"
                                           step="900">
                                    <button type="button" class="clear-time" onclick="clearTimeField('break_start')" title="Очистить поле">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                @error('break_start')
                                    <div class="invalid-feedback-enhanced">
                                        <i class="fas fa-exclamation-circle"></i>
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group-enhanced">
                                <label for="break_end" class="form-label-enhanced">
                                    Конец перерыва
                                    <span class="field-tooltip" data-tooltip="Время окончания обеденного перерыва">
                                    </span>
                                </label>
                                <div class="time-input-wrapper">
                                    <input type="time" 
                                           class="form-control-enhanced time-input @error('break_end') is-invalid @enderror" 
                                           id="break_end" 
                                           name="break_end" 
                                           value="{{ old('break_end', ($company->settings['break_times'][0]['end'] ?? '')) }}"
                                           step="900">
                                    <button type="button" class="clear-time" onclick="clearTimeField('break_end')" title="Очистить поле">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                @error('break_end')
                                    <div class="invalid-feedback-enhanced">
                                        <i class="fas fa-exclamation-circle"></i>
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Праздники -->
                <div class="settings-card fade-in-up">
                    <div class="settings-card-header">
                        <i class="fas fa-calendar-times"></i>
                        Праздники и выходные дни
                    </div>
                    <div class="settings-card-body">
                        <div class="form-group-enhanced">
                            <label for="holidays" class="form-label-enhanced">
                                Праздничные дни
                                <span class="field-tooltip" data-tooltip="Даты, когда компания не работает">
                                </span>
                            </label>
                            <input type="text" 
                                   class="form-control-enhanced @error('holidays') is-invalid @enderror" 
                                   id="holidays" 
                                   name="holidays" 
                                   data-input
                                   value="{{ old('holidays', implode(', ', ($company->settings['holidays'] ?? []))) }}"
                                   placeholder="Выберите даты праздников">
                            @error('holidays')
                                <div class="invalid-feedback-enhanced">
                                    <i class="fas fa-exclamation-circle"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-info-circle"></i>
                                Кликните в поле для выбора дат с помощью календаря
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Настройки уведомлений -->
                <div class="settings-card fade-in-up">
                    <div class="settings-card-header">
                        <i class="fas fa-bell"></i>
                        Настройки системы
                    </div>
                    <div class="settings-card-body">
                        <div class="switch-container">
                            <div>
                                <strong>Email уведомления</strong>
                                <p class="text-muted mb-0">Получать уведомления о новых записях на почту</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" 
                                       name="email_notifications" 
                                       id="email_notifications" 
                                       {{ old('email_notifications', ($company->settings['email_notifications'] ?? true)) ? 'checked' : '' }}>
                                <span class="switch-slider"></span>
                            </label>
                        </div>

                        <div class="switch-container">
                            <div>
                                <strong>Подтверждение записи</strong>
                                <p class="text-muted mb-0">Требовать подтверждение записи от администратора</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" 
                                       name="require_confirmation" 
                                       id="require_confirmation" 
                                       {{ old('require_confirmation', ($company->settings['require_confirmation'] ?? false)) ? 'checked' : '' }}>
                                <span class="switch-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Предпросмотр настроек -->
                <div class="settings-card fade-in-up">
                    <div class="settings-card-header">
                        <i class="fas fa-eye"></i>
                        Предпросмотр настроек
                    </div>
                    <div class="settings-card-body">
                        <div class="settings-preview">
                            <div class="preview-item">
                                <span class="preview-label">
                                    <i class="fas fa-calendar-week"></i>
                                    Рабочие дни:
                                </span>
                                <span class="preview-value" id="workDaysPreview">Загрузка...</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">
                                    <i class="fas fa-clock"></i>
                                    Рабочее время:
                                </span>
                                <span class="preview-value" id="workTimePreview">Загрузка...</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">
                                    <i class="fas fa-stopwatch"></i>
                                    Временных слотов в день:
                                </span>
                                <span class="preview-value" id="slotsCountPreview">Загрузка...</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">
                                    <i class="fas fa-users"></i>
                                    Максимум записей на слот:
                                </span>
                                <span class="preview-value" id="maxAppointmentsPreview">Загрузка...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Telegram уведомления -->
                <div class="settings-card fade-in-up">
                    <div class="settings-card-header">
                        <i class="fab fa-telegram-plane"></i>
                        Telegram уведомления
                    </div>
                    <div class="settings-card-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Как настроить Telegram-бота:</strong>
                                    <ol class="mt-2 mb-0">
                                        <li>Создайте бота через <a href="https://t.me/BotFather" target="_blank">@BotFather</a></li>
                                        <li>Получите токен бота (начинается с цифр и содержит двоеточие)</li>
                                        <li>Добавьте бота в группу или найдите ID своего чата</li>
                                        <li>Введите данные ниже и протестируйте подключение</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label for="telegram_bot_token" class="form-label-enhanced">
                                        Токен Telegram-бота
                                        <span class="field-tooltip" data-tooltip="Токен, полученный от @BotFather">
                                        </span>
                                    </label>
                                    <input type="password" 
                                           class="form-control-enhanced" 
                                           id="telegram_bot_token" 
                                           name="telegram_bot_token" 
                                           value="{{ old('telegram_bot_token', $company->telegram_bot_token) }}"
                                           placeholder="1234567890:ABCdefGHIjklMNOpqrsTUVwxyz">
                                    @if($company->telegram_bot_token)
                                        <small class="form-text text-muted">
                                            Текущий токен: {{ $company->getMaskedBotToken() }}
                                        </small>
                                    @endif
                                    <div class="invalid-feedback" id="telegram_bot_token_error"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label for="telegram_chat_id" class="form-label-enhanced">
                                        ID чата для уведомлений
                                        <span class="field-tooltip" data-tooltip="ID группы или личного чата для получения уведомлений">
                                        </span>
                                    </label>
                                    <input type="text" 
                                           class="form-control-enhanced" 
                                           id="telegram_chat_id" 
                                           name="telegram_chat_id" 
                                           value="{{ old('telegram_chat_id', $company->telegram_chat_id) }}"
                                           placeholder="-1001234567890 или 123456789">
                                    <small class="form-text text-muted">
                                        Начинается с "-" для групп или просто цифры для личных чатов
                                    </small>
                                    <div class="invalid-feedback" id="telegram_chat_id_error"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <div class="switch-container">
                                        <label class="form-label-enhanced">
                                            Включить Telegram уведомления
                                        </label>
                                        <label class="switch">
                                            <input type="checkbox" 
                                                   name="telegram_notifications_enabled" 
                                                   id="telegram_notifications_enabled"
                                                   value="1" 
                                                   {{ old('telegram_notifications_enabled', $company->telegram_notifications_enabled) ? 'checked' : '' }}>
                                            <span class="switch-slider"></span>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">
                                        Получать уведомления о новых записях, отменах и переносах
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label class="form-label-enhanced">Управление ботом</label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" 
                                                class="btn btn-info btn-sm" 
                                                id="testTelegramBtn"
                                                {{ !$company->telegram_bot_token ? 'disabled' : '' }}>
                                            <i class="fas fa-paper-plane"></i>
                                            Тест
                                        </button>
                                        <button type="button" 
                                                class="btn btn-secondary btn-sm" 
                                                id="getBotInfoBtn"
                                                {{ !$company->telegram_bot_token ? 'disabled' : '' }}>
                                            <i class="fas fa-info"></i>
                                            Инфо
                                        </button>
                                        <button type="button" 
                                                class="btn btn-success btn-sm" 
                                                id="setWebhookBtn"
                                                {{ !$company->telegram_bot_token ? 'disabled' : '' }}>
                                            <i class="fas fa-link"></i>
                                            Активировать бот
                                        </button>
                                    </div>
                                    <div id="telegram_status" class="mt-2"></div>
                                    <small class="form-text text-muted">
                                        После настройки токена нажмите "Активировать бот" для включения интерактивного режима
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Кнопки действий -->
                <div class="settings-card fade-in-up">
                    <div class="settings-card-body">
                        <div class="button-group">
                            <button type="submit" class="btn-enhanced btn-primary-enhanced" id="submitBtn">
                                <i class="fas fa-save"></i>
                                Сохранить настройки
                            </button>
                            <a href="{{ route('company.show', $company->slug) }}" class="btn-enhanced btn-secondary-enhanced">
                                <i class="fas fa-times"></i>
                                Отмена
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Инициализация основных элементов
    const form = document.getElementById('settingsForm');
    const submitBtn = document.getElementById('submitBtn');
    
    // Элементы для предпросмотра
    const workDaysPreview = document.getElementById('workDaysPreview');
    const workTimePreview = document.getElementById('workTimePreview');
    const slotsCountPreview = document.getElementById('slotsCountPreview');
    const maxAppointmentsPreview = document.getElementById('maxAppointmentsPreview');

    // Получаем все поля времени и добавляем обработчики
    const timeInputs = document.querySelectorAll('.time-input');
    timeInputs.forEach(input => {
        input.addEventListener('change', updatePreview);
        input.addEventListener('input', updatePreview);
        
        // Добавляем визуальную обратную связь при фокусе
        input.addEventListener('focus', function() {
            this.closest('.time-input-wrapper').classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.closest('.time-input-wrapper').classList.remove('focused');
        });
    });

    // Обработка кликов по дням недели
    document.querySelectorAll('.checkbox-item').forEach(item => {
        item.addEventListener('click', function() {
            const checkbox = this.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            this.classList.toggle('active', checkbox.checked);
            updatePreview();
        });
    });

    // Функция для обновления предпросмотра
    function updatePreview() {
        const formData = new FormData(form);
        
        // Обновляем рабочие дни
        const workDays = formData.getAll('work_days[]');
        const dayNames = {
            'monday': 'Пн',
            'tuesday': 'Вт',
            'wednesday': 'Ср',
            'thursday': 'Чт',
            'friday': 'Пт',
            'saturday': 'Сб',
            'sunday': 'Вс'
        };
        
        if (workDaysPreview) {
            const workDaysText = workDays.length > 0 ? 
                workDays.map(day => dayNames[day] || day).join(', ') : 
                'Не выбрано';
            workDaysPreview.textContent = workDaysText;
        }
        
        // Обновляем рабочее время
        const startTime = formData.get('work_start_time') || '09:00';
        const endTime = formData.get('work_end_time') || '18:00';
        if (workTimePreview) {
            workTimePreview.textContent = `${startTime} - ${endTime}`;
        }
        
        // Подсчитываем количество слотов
        const interval = parseInt(formData.get('appointment_interval')) || 30;
        const breakStart = formData.get('break_start');
        const breakEnd = formData.get('break_end');
        
        if (slotsCountPreview) {
            try {
                const start = new Date(`1970-01-01T${startTime}:00`);
                const end = new Date(`1970-01-01T${endTime}:00`);
                
                let workMinutes = (end - start) / (1000 * 60);
                
                // Вычитаем время перерыва
                if (breakStart && breakEnd) {
                    const bStart = new Date(`1970-01-01T${breakStart}:00`);
                    const bEnd = new Date(`1970-01-01T${breakEnd}:00`);
                    const breakMinutes = (bEnd - bStart) / (1000 * 60);
                    workMinutes -= breakMinutes;
                }
                
                const slots = workMinutes > 0 ? Math.floor(workMinutes / interval) : 0;
                slotsCountPreview.textContent = `${slots} слотов по ${interval} мин`;
            } catch (e) {
                slotsCountPreview.textContent = 'Ошибка расчета';
            }
        }
        
        // Обновляем информацию о максимальных записях
        const maxAppointments = parseInt(formData.get('max_appointments_per_slot')) || 1;
        if (maxAppointmentsPreview) {
            maxAppointmentsPreview.textContent = `${maxAppointments} ${maxAppointments === 1 ? 'запись' : 'записей'}`;
        }
    }

    // Добавляем обработчики изменений для всех полей
    form.addEventListener('change', updatePreview);
    form.addEventListener('input', updatePreview);
    
    // Изначальное обновление предпросмотра
    updatePreview();

    // Обработчик отправки формы
    form.addEventListener('submit', function(e) {
        // Показываем индикатор загрузки
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Сохранение...';
        
        // Валидация
        const formData = new FormData(form);
        
        // Проверяем время работы
        const startTime = formData.get('work_start_time');
        const endTime = formData.get('work_end_time');
        
        if (startTime && endTime && startTime >= endTime) {
            e.preventDefault();
            showAlert('Время начала рабочего дня должно быть меньше времени окончания', 'error');
            resetSubmitButton();
            return false;
        }
        
        // Проверяем время перерыва
        const breakStart = formData.get('break_start');
        const breakEnd = formData.get('break_end');
        
        if (breakStart && breakEnd && breakStart >= breakEnd) {
            e.preventDefault();
            showAlert('Время начала перерыва должно быть меньше времени окончания перерыва', 'error');
            resetSubmitButton();
            return false;
        }
        
        // Проверяем рабочие дни
        const workDays = formData.getAll('work_days[]');
        if (workDays.length === 0) {
            e.preventDefault();
            showAlert('Необходимо выбрать хотя бы один рабочий день', 'error');
            resetSubmitButton();
            return false;
        }
    });

    // ===== ОБРАБОТЧИКИ TELEGRAM КНОПОК =====
    
    // Объявляем переменные в глобальной области видимости
    window.testTelegramBtn = document.getElementById('testTelegramBtn');
    window.getBotInfoBtn = document.getElementById('getBotInfoBtn');
    window.setWebhookBtn = document.getElementById('setWebhookBtn');
    window.telegramBotTokenField = document.getElementById('telegram_bot_token');
    window.telegramChatIdField = document.getElementById('telegram_chat_id');
    window.telegramEnabledField = document.getElementById('telegram_notifications_enabled');
    window.telegramStatus = document.getElementById('telegram_status');
    
    if (window.testTelegramBtn) {
        window.testTelegramBtn.addEventListener('click', testTelegramConnection);
    }
    
    if (window.getBotInfoBtn) {
        window.getBotInfoBtn.addEventListener('click', getBotInfo);
    }
    
    if (window.setWebhookBtn) {
        window.setWebhookBtn.addEventListener('click', setWebhook);
    }
    
    // Отслеживание изменений токена для активации/деактивации кнопок
    if (window.telegramBotTokenField) {
        window.telegramBotTokenField.addEventListener('input', function() {
            const hasToken = this.value.length > 0;
            if (window.testTelegramBtn) window.testTelegramBtn.disabled = !hasToken;
            if (window.getBotInfoBtn) window.getBotInfoBtn.disabled = !hasToken;
            if (window.setWebhookBtn) window.setWebhookBtn.disabled = !hasToken;
        });
    }

    // Функция для сброса кнопки отправки
    function resetSubmitButton() {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Сохранить настройки';
    }

    // Функция для очистки полей времени
    function clearTimeField(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = '';
            field.focus();
            updatePreview();
        }
    }

    // Анимация появления карточек
    const cards = document.querySelectorAll('.settings-card');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        }, index * 100);
    });
    
    // ===== TELEGRAM ФУНКЦИОНАЛЬНОСТЬ =====
    
// Функция тестирования подключения Telegram
async function testTelegramConnection() {
    const chatId = window.telegramChatIdField.value.trim();
    
    if (!chatId) {
        showTelegramStatus('Укажите ID чата для отправки тестового сообщения', 'warning');
        return;
    }
    
    window.testTelegramBtn.disabled = true;
    window.testTelegramBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Тест...';
    
    try {
        // Сначала сохраняем настройки
        await saveTelegramSettings();
        
        // Затем тестируем
        const response = await fetch(`{{ route('company.telegram.test', $company->slug) }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                chat_id: chatId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showTelegramStatus('Тестовое сообщение отправлено успешно!', 'success');
        } else {
            showTelegramStatus(data.message || 'Ошибка отправки тестового сообщения', 'error');
        }
    } catch (error) {
        showTelegramStatus('Произошла ошибка при тестировании', 'error');
        console.error('Ошибка тестирования Telegram:', error);
    } finally {
        window.testTelegramBtn.disabled = false;
        window.testTelegramBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Тест';
    }
}
    
    // Функция получения информации о боте
    async function getBotInfo() {
        getBotInfoBtn.disabled = true;
        getBotInfoBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Загрузка...';
        
        try {
            // Сначала сохраняем настройки
            await saveTelegramSettings();
            
            const response = await fetch(`{{ route('company.telegram.bot-info', $company->slug) }}`);
            const data = await response.json();
            
            if (data.success) {
                const botInfo = data.data;
                showTelegramStatus(`Бот: @${botInfo.username} (${botInfo.first_name})`, 'success');
            } else {
                showTelegramStatus(data.message || 'Не удалось получить информацию о боте', 'error');
            }
        } catch (error) {
            showTelegramStatus('Произошла ошибка при получении информации о боте', 'error');
            console.error('Ошибка получения информации о боте:', error);
        } finally {
            getBotInfoBtn.disabled = false;
            getBotInfoBtn.innerHTML = '<i class="fas fa-info"></i> Инфо';
        }
    }
    
// Функция сохранения Telegram настроек
async function saveTelegramSettings() {
    const response = await fetch(`{{ route('company.telegram.settings', $company->slug) }}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            telegram_bot_token: window.telegramBotTokenField.value.trim(),
            telegram_chat_id: window.telegramChatIdField.value.trim(),
            telegram_notifications_enabled: window.telegramEnabledField.checked
        })
    });
    
    if (!response.ok) {
        throw new Error('Ошибка сохранения настроек');
    }
    
    return response.json();
}
});

// ===== ГЛОБАЛЬНЫЕ ФУНКЦИИ =====

// Глобальная функция для показа уведомлений
function showAlert(message, type = 'info') {
    const form = document.getElementById('settingsForm');
    if (!form) return;
    
    const alertClass = type === 'error' || type === 'danger' ? 'alert-danger-enhanced' : 
                      type === 'warning' ? 'alert-warning-enhanced' : 
                      type === 'success' ? 'alert-success-enhanced' : 'alert-info-enhanced';
    const icon = type === 'error' || type === 'danger' ? 'fas fa-exclamation-circle' : 
                type === 'warning' ? 'fas fa-exclamation-triangle' :
                type === 'success' ? 'fas fa-check-circle' : 'fas fa-info-circle';
    
    const alertHTML = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="${icon}"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // Добавляем уведомление в начало формы
    form.insertAdjacentHTML('afterbegin', alertHTML);
    
    // Автоматически убираем через 5 секунд
    setTimeout(() => {
        const alert = form.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}
function clearTimeField(fieldId) {
    const field = document.getElementById(fieldId);
    if (field) {
        field.value = '';
        field.focus();
        
        // Триггерим событие change для обновления предпросмотра
        const event = new Event('change', { bubbles: true });
        field.dispatchEvent(event);
    }
}

// Глобальная функция для показа статуса Telegram
function showTelegramStatus(message, type = 'info') {
    const telegramStatus = document.getElementById('telegram_status');
    if (!telegramStatus) return;
    
    const iconMap = {
        'success': 'fas fa-check-circle text-success',
        'error': 'fas fa-exclamation-circle text-danger',
        'warning': 'fas fa-exclamation-triangle text-warning',
        'info': 'fas fa-info-circle text-info'
    };
    
    const icon = iconMap[type] || iconMap['info'];
    
    telegramStatus.innerHTML = `
        <div class="alert alert-${type === 'error' ? 'danger' : type === 'warning' ? 'warning' : type === 'success' ? 'success' : 'info'} alert-sm">
            <i class="${icon}"></i> ${message}
        </div>
    `;
    
    // Убираем сообщение через 5 секунд
    setTimeout(() => {
        telegramStatus.innerHTML = '';
    }, 5000);
}

// ===== TELEGRAM ФУНКЦИИ =====

// Установка webhook для бота
async function setWebhook() {
    const token = document.getElementById('telegram_bot_token').value.trim();
    
    if (!token) {
        showAlert('Пожалуйста, введите токен бота', 'warning');
        return;
    }
    
    const webhookBtn = document.getElementById('setWebhookBtn');
    const originalText = webhookBtn.innerHTML;
    
    try {
        webhookBtn.disabled = true;
        webhookBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Активация...';
        
        console.log('Отправляем запрос на:', `/company/{{ $company->slug }}/telegram/webhook`);
        console.log('CSRF Token:', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        
        const response = await fetch(`/company/{{ $company->slug }}/telegram/webhook`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        const result = await response.json();
        console.log('Response data:', result);
        
        if (result.success) {
            let message = '✅ Бот успешно активирован!\n\n';
            message += `🔗 Webhook установлен\n`;
            if (result.bot_username) {
                message += `🤖 Имя бота: ${result.bot_username}\n`;
            }
            message += '\n📱 Теперь клиенты могут записываться через Telegram-бот!\n';
            message += '💡 Отправьте команду /start боту для проверки.';
            
            showAlert(message, 'success');
            
            // Обновляем текст кнопки
            webhookBtn.innerHTML = '<i class="fas fa-check"></i> Активирован';
            webhookBtn.classList.remove('btn-success');
            webhookBtn.classList.add('btn-outline-success');
            
        } else {
            showAlert(`❌ Ошибка активации бота: ${result.message || result.error}`, 'danger');
            webhookBtn.disabled = false;
            webhookBtn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Ошибка установки webhook:', error);
        showAlert('Произошла ошибка при активации бота', 'danger');
        webhookBtn.disabled = false;
        webhookBtn.innerHTML = originalText;
    }
}

// Очистка ошибок валидации при изменении полей Telegram
document.addEventListener('DOMContentLoaded', function() {
    const telegramFields = ['telegram_bot_token', 'telegram_chat_id'];
    
    telegramFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        const errorDiv = document.getElementById(`${fieldId}_error`);
        
        if (field && errorDiv) {
            field.addEventListener('input', function() {
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
            });
        }
    });
});
</script>
@endsection
