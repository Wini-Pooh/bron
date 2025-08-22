@extends('layouts.app')
@section('styles')
@endsection
@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-8">
            
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ e(session('success')) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            
            <!-- Профиль компании -->
            <div class="company-profile">
                <div class="d-flex align-items-center">
                    @if($company->avatar)
                        <img src="{{ e($company->avatar) }}" 
                             alt="Аватар компании {{ e($company->name) }}" 
                             class="company-avatar me-3">
                    @else
                        <div class="company-avatar-placeholder me-3 d-flex align-items-center justify-content-center">
                            {{ strtoupper(substr(e($company->name), 0, 2)) }}
                        </div>
                    @endif
                    
                    <div class="company-info flex-grow-1">
                        <h2 class="company-name">{{ e($company->name) }}</h2>
                        
                        @if(Auth::check() && $company->user_id === Auth::id())
                        <div class="mt-2">
                            <a href="{{ route('company.edit', $company->slug) }}" class="btn btn-sm btn-outline-primary me-2">
                                <i class="fas fa-edit"></i> Редактировать
                            </a>
                            <a href="{{ route('company.settings', $company->slug) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-cog"></i> Настройки
                            </a>
                        </div>
                        @endif
                        
                    </div>
                </div>
                
                @if($company->description)
                <div class="company-description mt-3">
                    <p class="mb-0">{{ e($company->description) }}</p>
                </div>
                @endif
            </div>

            <!-- Календарь для записи -->
            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Выберите дату и время</h4>
                  
                </div>
                <div class="card-body">
                
                    
                    <!-- Календарь -->
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <button class="btn btn-outline-primary btn-sm" id="prevMonth">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <h5 class="calendar-title mb-0" id="calendarTitle"></h5>
                            <button class="btn btn-outline-primary btn-sm" id="nextMonth">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        
                        <div class="calendar-grid">
                            <div class="calendar-days-header">
                                <div class="calendar-day-name">Пн</div>
                                <div class="calendar-day-name">Вт</div>
                                <div class="calendar-day-name">Ср</div>
                                <div class="calendar-day-name">Чт</div>
                                <div class="calendar-day-name">Пт</div>
                                <div class="calendar-day-name weekend-header">Сб</div>
                                <div class="calendar-day-name weekend-header">Вс</div>
                            </div>
                            <div class="calendar-days" id="calendarDays">
                                <!-- Дни календаря будут генерироваться JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Детализированный вид дня -->
            <div class="card shadow mt-4" id="dayViewContainer" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <h5 class="mb-0" id="dayViewTitle">Расписание на день</h5>
                    <button class="btn btn-outline-secondary btn-sm" id="closeDayView">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="card-body p-3">
                    @if($isOwner)
                    <div class="day-settings-info mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> Рабочее время: {{ e($calendarSettings['work_start_time'] ?? '09:00') }} - {{ e($calendarSettings['work_end_time'] ?? '18:00') }}
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">
                                    @if(!empty($calendarSettings['break_times']))
                                        <i class="fas fa-coffee"></i> Перерыв: {{ e($calendarSettings['break_times'][0]['start'] ?? '') }} - {{ e($calendarSettings['break_times'][0]['end'] ?? '') }}
                                    @else
                                        <i class="fas fa-coffee"></i> Перерыв не установлен
                                    @endif
                                </small>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <div class="day-schedule-container">
                        <div class="time-slots" id="timeSlots">
                            <!-- Временные слоты будут генерироваться JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для записи -->
<div class="modal fade" id="appointmentModal" tabindex="-1" aria-labelledby="appointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentModalLabel">Запись на прием</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="appointmentForm" action="{{ route('company.appointments.create', $company->slug) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <!-- Скрытое поле для даты -->
                    <input type="hidden" id="modal_appointment_date" name="appointment_date">
                    
                    <!-- Показываем выбранную дату пользователю -->
                    <div class="mb-3">
                        <label class="form-label">Выбранная дата</label>
                        <div class="form-control-plaintext" id="selected_date_display">-</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_appointment_time" class="form-label">Время</label>
                        <input type="time" class="form-control" id="modal_appointment_time" name="appointment_time" required readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_client_name" class="form-label">Ваше имя</label>
                        <input type="text" class="form-control" id="modal_client_name" name="client_name" required maxlength="50">
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_client_phone" class="form-label">Телефон</label>
                        <input type="tel" class="form-control" id="modal_client_phone" name="client_phone" placeholder="+7 (999) 123-45-67" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_notes" class="form-label">Комментарий</label>
                        <textarea class="form-control" id="modal_notes" name="notes" rows="3" maxlength="500"></textarea>
                        <div class="form-text">Осталось символов: <span id="notesCounter">500</span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Записаться</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно для редактирования записи (только для владельца) -->
@if($isOwner)
<div class="modal fade" id="editAppointmentModal" tabindex="-1" aria-labelledby="editAppointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAppointmentModalLabel">Управление записью</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Информация о записи -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Информация о клиенте</h6>
                        <p class="mb-1"><strong>Имя:</strong> <span id="edit_client_name">-</span></p>
                        <p class="mb-1"><strong>Телефон:</strong> <span id="edit_client_phone">-</span></p>
                        <p class="mb-1"><strong>Статус:</strong> <span id="edit_status" class="badge">-</span></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Детали записи</h6>
                        <p class="mb-1"><strong>Дата:</strong> <span id="edit_appointment_date">-</span></p>
                        <p class="mb-1"><strong>Время:</strong> <span id="edit_appointment_time">-</span></p>
                        <p class="mb-1"><strong>Создана:</strong> <span id="edit_created_at">-</span></p>
                    </div>
                </div>

                <!-- Комментарий клиента -->
                <div class="mb-4" id="client_notes_section" style="display: none;">
                    <h6 class="text-muted mb-2">Комментарий клиента</h6>
                    <div class="alert alert-light" id="edit_client_notes">-</div>
                </div>

                <!-- Форма редактирования -->
                <form id="editAppointmentForm">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="edit_appointment_id" name="appointment_id">
                    
                    <!-- Изменение даты и времени -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Изменить дату и время</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_new_date" class="form-label">Новая дата</label>
                                    <input type="date" class="form-control" id="edit_new_date" name="new_date">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_new_time" class="form-label">Новое время</label>
                                    <select class="form-select" id="edit_new_time" name="new_time">
                                        <option value="">Выберите время</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Изменение контактных данных клиента -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Контактная информация клиента</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit_client_name_field" class="form-label">Имя клиента</label>
                                    <input type="text" class="form-control" id="edit_client_name_field" name="client_name" maxlength="50">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit_client_phone_field" class="form-label">Телефон клиента</label>
                                    <input type="tel" class="form-control" id="edit_client_phone_field" name="client_phone" placeholder="+7 (999) 123-45-67">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Добавление заметок владельца -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Заметки владельца</h6>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" id="edit_owner_notes" name="owner_notes" rows="3" placeholder="Добавить внутренние заметки..." maxlength="500"></textarea>
                            <div class="form-text">Осталось символов: <span id="ownerNotesCounter">500</span></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left"></i> Обратно
                </button>
                <button type="button" class="btn btn-success" id="updateBtn">
                    <i class="fas fa-save"></i> Обновить
                </button>
                <button type="button" class="btn btn-danger" id="cancelAppointmentBtn">
                    <i class="fas fa-times"></i> Отменить бронь
                </button>
            </div>
        </div>
    </div>
</div>
@endif
@include('company.show_script')
@endsection
