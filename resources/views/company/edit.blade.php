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
                    <i class="fas fa-edit"></i>
                    Редактирование компании "{{ $company->name }}"
                    <div class="ms-auto">
                        <a href="{{ route('company.show', $company->slug) }}" class="btn btn-sm btn-light">
                            <i class="fas fa-arrow-left"></i> Назад к профилю
                        </a>
                    </div>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success-enhanced alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger-enhanced alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    Пожалуйста, исправьте следующие ошибки:
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <form method="POST" action="{{ route('company.update', $company->slug) }}" enctype="multipart/form-data" id="companyEditForm">
                @csrf
                @method('PUT')

                <!-- Основная информация -->
                <div class="settings-card fade-in-up">
                    <div class="settings-card-header">
                        <i class="fas fa-building"></i>
                        Основная информация
                    </div>
                    <div class="settings-card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label for="name" class="form-label-enhanced">
                                        <i class="fas fa-signature"></i>
                                        Название компании
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control-enhanced @error('name') is-invalid @enderror" 
                                           id="name" 
                                           name="name" 
                                           value="{{ old('name', $company->name) }}" 
                                           required
                                           maxlength="100"
                                           minlength="2"
                                           pattern="^[А-Яа-яA-Za-z0-9\s\-\.\"«»]+$"
                                           placeholder="Введите название компании"
                                           title="Название может содержать только буквы, цифры, пробелы, дефисы, точки и кавычки">
                                    @error('name')
                                        <div class="invalid-feedback-enhanced">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label for="specialty" class="form-label-enhanced">
                                        <i class="fas fa-star"></i>
                                        Специализация
                                    </label>
                                    <input type="text" 
                                           class="form-control-enhanced @error('specialty') is-invalid @enderror" 
                                           id="specialty" 
                                           name="specialty" 
                                           value="{{ old('specialty', $company->specialty) }}"
                                           maxlength="100"
                                           pattern="^[А-Яа-яA-Za-z0-9\s\-\,\.]+$"
                                           placeholder="Например: Салон красоты, Стоматология"
                                           title="Специализация может содержать только буквы, цифры, пробелы, дефисы, запятые и точки">
                                    @error('specialty')
                                        <div class="invalid-feedback-enhanced">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group-enhanced">
                            <label for="description" class="form-label-enhanced">
                                <i class="fas fa-align-left"></i>
                                Описание компании
                            </label>
                            <textarea class="form-control-enhanced @error('description') is-invalid @enderror" 
                                      id="description" 
                                      name="description" 
                                      rows="4"
                                      maxlength="500"
                                      placeholder="Опишите вашу компанию, услуги, преимущества...">{{ old('description', $company->description) }}</textarea>
                            <small class="form-text text-muted">
                                <span id="descriptionCounter">{{ strlen($company->description ?? '') }}</span>/500 символов
                            </small>
                            @error('description')
                                <div class="invalid-feedback-enhanced">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Контактная информация -->
                <div class="settings-card fade-in-up">
                    <div class="settings-card-header">
                        <i class="fas fa-address-book"></i>
                        Контактная информация
                    </div>
                    <div class="settings-card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label for="phone" class="form-label-enhanced">
                                        <i class="fas fa-phone"></i>
                                        Телефон
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="tel" 
                                           class="form-control-enhanced @error('phone') is-invalid @enderror" 
                                           id="phone" 
                                           name="phone" 
                                           value="{{ old('phone', $company->phone) }}" 
                                           required
                                           placeholder="+7 (___) ___-__-__"
                                           data-mask="+7 (000) 000-00-00">
                                    @error('phone')
                                        <div class="invalid-feedback-enhanced">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label for="email" class="form-label-enhanced">
                                        <i class="fas fa-envelope"></i>
                                        Email
                                    </label>
                                    <input type="email" 
                                           class="form-control-enhanced @error('email') is-invalid @enderror" 
                                           id="email" 
                                           name="email" 
                                           value="{{ old('email', $company->email) }}"
                                           maxlength="100"
                                           pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                                           placeholder="example@company.com"
                                           title="Введите корректный email адрес">
                                    @error('email')
                                        <div class="invalid-feedback-enhanced">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label for="address" class="form-label-enhanced">
                                        <i class="fas fa-map-marker-alt"></i>
                                        Адрес
                                    </label>
                                    <input type="text" 
                                           class="form-control-enhanced @error('address') is-invalid @enderror" 
                                           id="address" 
                                           name="address" 
                                           value="{{ old('address', $company->address) }}"
                                           maxlength="200"
                                           pattern="^[А-Яа-яA-Za-z0-9\s\-\.\,\/]+$"
                                           placeholder="г. Москва, ул. Примерная, д. 123"
                                           title="Адрес может содержать только буквы, цифры, пробелы, дефисы, точки, запятые и слеши">
                                    @error('address')
                                        <div class="invalid-feedback-enhanced">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-enhanced">
                                    <label for="website" class="form-label-enhanced">
                                        <i class="fas fa-globe"></i>
                                        Веб-сайт
                                    </label>
                                    <input type="url" 
                                           class="form-control-enhanced @error('website') is-invalid @enderror" 
                                           id="website" 
                                           name="website" 
                                           value="{{ old('website', $company->website) }}"
                                           maxlength="100"
                                           pattern="^https?:\/\/(www\.)?[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*\/?$"
                                           placeholder="https://example.com"
                                           title="Введите корректный URL сайта, начинающийся с http:// или https://">
                                    @error('website')
                                        <div class="invalid-feedback-enhanced">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Статистика и показатели -->
                <div class="settings-card fade-in-up">
                    <div class="settings-card-header">
                        <i class="fas fa-chart-line"></i>
                        Статистика и показатели
                    </div>
                    <div class="settings-card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group-enhanced">
                                    <label for="years_experience" class="form-label-enhanced">
                                        <i class="fas fa-calendar-alt"></i>
                                        Лет опыта
                                    </label>
                                    <input type="number" 
                                           class="form-control-enhanced @error('years_experience') is-invalid @enderror" 
                                           id="years_experience" 
                                           name="years_experience" 
                                           min="0" 
                                           max="100" 
                                           value="{{ old('years_experience', $company->years_experience) }}"
                                           placeholder="0">
                                    @error('years_experience')
                                        <div class="invalid-feedback-enhanced">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group-enhanced">
                                    <label for="total_clients" class="form-label-enhanced">
                                        <i class="fas fa-users"></i>
                                        Всего клиентов
                                    </label>
                                    <input type="number" 
                                           class="form-control-enhanced @error('total_clients') is-invalid @enderror" 
                                           id="total_clients" 
                                           name="total_clients" 
                                           min="0" 
                                           max="999999"
                                           value="{{ old('total_clients', $company->total_clients) }}"
                                           placeholder="0">
                                    @error('total_clients')
                                        <div class="invalid-feedback-enhanced">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group-enhanced">
                                    <label for="total_specialists" class="form-label-enhanced">
                                        <i class="fas fa-user-tie"></i>
                                        Всего специалистов
                                    </label>
                                    <input type="number" 
                                           class="form-control-enhanced @error('total_specialists') is-invalid @enderror" 
                                           id="total_specialists" 
                                           name="total_specialists" 
                                           min="0" 
                                           max="9999"
                                           value="{{ old('total_specialists', $company->total_specialists) }}"
                                           placeholder="0">
                                    @error('total_specialists')
                                        <div class="invalid-feedback-enhanced">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group-enhanced">
                                    <label for="satisfaction_rate" class="form-label-enhanced">
                                        <i class="fas fa-star"></i>
                                        Удовлетворенность (%)
                                    </label>
                                    <input type="number" 
                                           class="form-control-enhanced @error('satisfaction_rate') is-invalid @enderror" 
                                           id="satisfaction_rate" 
                                           name="satisfaction_rate" 
                                           min="0" 
                                           max="100" 
                                           step="0.1" 
                                           value="{{ old('satisfaction_rate', $company->satisfaction_rate) }}"
                                           placeholder="0.0">
                                    @error('satisfaction_rate')
                                        <div class="invalid-feedback-enhanced">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Настройки активности -->
                <div class="settings-card fade-in-up">
                    <div class="settings-card-header">
                        <i class="fas fa-toggle-on"></i>
                        Настройки активности
                    </div>
                    <div class="settings-card-body">
                        <div class="switch-container">
                            <div>
                                <strong><i class="fas fa-power-off"></i> Статус компании</strong>
                                <small class="text-muted d-block">Активная компания отображается в поиске и доступна для записи</small>
                            </div>
                            <label class="switch">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" 
                                       id="is_active" 
                                       name="is_active" 
                                       value="1"
                                       {{ old('is_active', $company->is_active) ? 'checked' : '' }}>
                                <span class="switch-slider"></span>
                            </label>
                        </div>
                        @error('is_active')
                            <div class="invalid-feedback-enhanced d-block">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>

                <!-- Кнопки управления -->
                <div class="settings-card fade-in-up">
                    <div class="settings-card-body">
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary-enhanced" id="submitBtn">
                                <i class="fas fa-save"></i>
                                Сохранить изменения
                            </button>
                            <a href="{{ route('company.show', $company->slug) }}" class="btn btn-secondary-enhanced">
                                <i class="fas fa-times"></i>
                                Отменить
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('companyEditForm');
    const submitBtn = document.getElementById('submitBtn');
    
    // Инициализация масок для полей ввода
    initInputMasks();
    
    // Счетчик символов для описания
    initCharacterCounter();
    
    // Валидация формы
    initFormValidation();
    
    // Анимация появления карточек
    initCardAnimations();
    
    // Функция инициализации масок
    function initInputMasks() {
        // Маска для телефона
        $('#phone').mask('+7 (000) 000-00-00', {
            placeholder: '+7 (___) ___-__-__',
            translation: {
                '0': {pattern: /[0-9]/}
            }
        });
        
        // Ограничения для текстовых полей
        const nameInput = document.getElementById('name');
        if (nameInput) {
            nameInput.addEventListener('input', function() {
                // Удаляем недопустимые символы
                this.value = this.value.replace(/[^А-Яа-яA-Za-z0-9\s\-\.\"«»]/g, '');
                // Ограничиваем последовательные пробелы
                this.value = this.value.replace(/\s{2,}/g, ' ');
                // Убираем пробелы в начале
                if (this.value.startsWith(' ')) {
                    this.value = this.value.trimStart();
                }
            });
        }
        
        const specialtyInput = document.getElementById('specialty');
        if (specialtyInput) {
            specialtyInput.addEventListener('input', function() {
                // Удаляем недопустимые символы
                this.value = this.value.replace(/[^А-Яа-яA-Za-z0-9\s\-\,\.]/g, '');
                // Ограничиваем последовательные пробелы
                this.value = this.value.replace(/\s{2,}/g, ' ');
                if (this.value.startsWith(' ')) {
                    this.value = this.value.trimStart();
                }
            });
        }
        
        const addressInput = document.getElementById('address');
        if (addressInput) {
            addressInput.addEventListener('input', function() {
                // Удаляем недопустимые символы
                this.value = this.value.replace(/[^А-Яа-яA-Za-z0-9\s\-\.\,\/]/g, '');
                // Ограничиваем последовательные пробелы
                this.value = this.value.replace(/\s{2,}/g, ' ');
                if (this.value.startsWith(' ')) {
                    this.value = this.value.trimStart();
                }
            });
        }
        
        // Ограничения для числовых полей
        const numberInputs = document.querySelectorAll('input[type="number"]');
        numberInputs.forEach(input => {
            input.addEventListener('input', function() {
                // Удаляем начальные нули (кроме единственного нуля)
                if (this.value.length > 1 && this.value.startsWith('0') && this.value !== '0' && !this.value.includes('.')) {
                    this.value = this.value.replace(/^0+/, '');
                }
                
                // Проверяем максимальные значения
                const max = parseFloat(this.max);
                const min = parseFloat(this.min);
                const value = parseFloat(this.value);
                
                if (!isNaN(max) && value > max) {
                    this.value = max;
                    showFieldWarning(this, `Максимальное значение: ${max}`);
                }
                
                if (!isNaN(min) && value < min) {
                    this.value = min;
                    showFieldWarning(this, `Минимальное значение: ${min}`);
                }
                
                // Специальная валидация для процентов
                if (this.id === 'satisfaction_rate') {
                    // Ограничиваем до 1 знака после запятой
                    if (this.value.includes('.')) {
                        const parts = this.value.split('.');
                        if (parts[1] && parts[1].length > 1) {
                            this.value = parts[0] + '.' + parts[1].substring(0, 1);
                        }
                    }
                }
            });
            
            // Дополнительная валидация при потере фокуса
            input.addEventListener('blur', function() {
                if (this.value === '') return;
                
                const value = parseFloat(this.value);
                const max = parseFloat(this.max);
                const min = parseFloat(this.min);
                
                if (isNaN(value)) {
                    this.value = this.min || 0;
                    showFieldWarning(this, 'Введите корректное число');
                } else if (!isNaN(max) && value > max) {
                    this.value = max;
                } else if (!isNaN(min) && value < min) {
                    this.value = min;
                }
            });
        });
        
        // Ограничение на длину полей
        const textInputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="url"], textarea');
        textInputs.forEach(input => {
            const maxLength = input.getAttribute('maxlength');
            if (maxLength) {
                input.addEventListener('input', function() {
                    if (this.value.length >= maxLength * 0.9) {
                        const remaining = maxLength - this.value.length;
                        if (remaining <= 0) {
                            showFieldWarning(this, `Достигнут максимум ${maxLength} символов`);
                        } else {
                            showFieldWarning(this, `Осталось ${remaining} символов`);
                        }
                    }
                });
            }
        });
        
        // Валидация URL
        const urlInput = document.getElementById('website');
        if (urlInput) {
            urlInput.addEventListener('input', function() {
                // Убираем пробелы
                this.value = this.value.trim();
                
                // Автоматически добавляем https:// если не указан протокол
                if (this.value && !this.value.match(/^https?:\/\//)) {
                    this.value = 'https://' + this.value.replace(/^\/+/, '');
                }
            });
            
            urlInput.addEventListener('blur', function() {
                if (this.value) {
                    // Проверяем корректность URL
                    try {
                        new URL(this.value);
                        // Дополнительная проверка на корректность домена
                        const domain = new URL(this.value).hostname;
                        if (!domain.includes('.') || domain.length < 3) {
                            showFieldError(this, 'Введите корректный URL сайта');
                        }
                    } catch {
                        showFieldError(this, 'Введите корректный URL сайта');
                    }
                }
            });
        }
        
        // Валидация email
        const emailInput = document.getElementById('email');
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                // Убираем пробелы и приводим к нижнему регистру
                this.value = this.value.trim().toLowerCase();
            });
            
            emailInput.addEventListener('blur', function() {
                if (this.value) {
                    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                    if (!emailRegex.test(this.value)) {
                        showFieldError(this, 'Введите корректный email адрес');
                    }
                }
            });
        }
    }
    
    // Функция счетчика символов
    function initCharacterCounter() {
        const descriptionField = document.getElementById('description');
        const counter = document.getElementById('descriptionCounter');
        
        if (descriptionField && counter) {
            descriptionField.addEventListener('input', function() {
                const currentLength = this.value.length;
                const maxLength = this.getAttribute('maxlength') || 500;
                
                counter.textContent = currentLength;
                
                // Изменяем цвет счетчика в зависимости от заполненности
                if (currentLength > maxLength * 0.9) {
                    counter.style.color = '#dc3545'; // Красный
                } else if (currentLength > maxLength * 0.7) {
                    counter.style.color = '#ffc107'; // Желтый
                } else {
                    counter.style.color = '#6c757d'; // Серый
                }
                
                // Предупреждение при приближении к лимиту
                if (currentLength >= maxLength * 0.9) {
                    const remaining = maxLength - currentLength;
                    if (remaining > 0) {
                        showFieldWarning(this, `Осталось ${remaining} символов`);
                    }
                }
            });
        }
    }
    
    // Функция валидации формы
    function initFormValidation() {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Показываем индикатор загрузки
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Сохранение...';
            
            // Очищаем предыдущие ошибки
            clearValidationErrors();
            
            let hasErrors = false;
            
            // Валидация обязательных полей
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    showFieldError(field, 'Это поле обязательно для заполнения');
                    hasErrors = true;
                }
            });
            
            // Валидация названия компании
            const nameField = document.getElementById('name');
            if (nameField && nameField.value) {
                const name = nameField.value.trim();
                if (name.length < 2) {
                    showFieldError(nameField, 'Название компании должно содержать минимум 2 символа');
                    hasErrors = true;
                } else if (name.length > 100) {
                    showFieldError(nameField, 'Название компании не должно превышать 100 символов');
                    hasErrors = true;
                } else if (!/^[А-Яа-яA-Za-z0-9\s\-\.\"«»]+$/.test(name)) {
                    showFieldError(nameField, 'Название может содержать только буквы, цифры, пробелы, дефисы, точки и кавычки');
                    hasErrors = true;
                }
            }
            
            // Валидация специализации
            const specialtyField = document.getElementById('specialty');
            if (specialtyField && specialtyField.value) {
                const specialty = specialtyField.value.trim();
                if (specialty.length > 100) {
                    showFieldError(specialtyField, 'Специализация не должна превышать 100 символов');
                    hasErrors = true;
                } else if (!/^[А-Яа-яA-Za-z0-9\s\-\,\.]+$/.test(specialty)) {
                    showFieldError(specialtyField, 'Специализация может содержать только буквы, цифры, пробелы, дефисы, запятые и точки');
                    hasErrors = true;
                }
            }
            
            // Валидация описания
            const descriptionField = document.getElementById('description');
            if (descriptionField && descriptionField.value) {
                const description = descriptionField.value.trim();
                if (description.length > 500) {
                    showFieldError(descriptionField, 'Описание не должно превышать 500 символов');
                    hasErrors = true;
                }
            }
            
            // Валидация телефона
            const phoneField = document.getElementById('phone');
            if (phoneField && phoneField.value) {
                const cleanPhone = phoneField.value.replace(/\D/g, '');
                if (cleanPhone.length !== 11) {
                    showFieldError(phoneField, 'Введите корректный номер телефона в формате +7 (XXX) XXX-XX-XX');
                    hasErrors = true;
                } else if (!cleanPhone.startsWith('7')) {
                    showFieldError(phoneField, 'Номер телефона должен начинаться с +7');
                    hasErrors = true;
                }
            }
            
            // Валидация email
            const emailField = document.getElementById('email');
            if (emailField && emailField.value) {
                const email = emailField.value.trim();
                const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                if (!emailRegex.test(email)) {
                    showFieldError(emailField, 'Введите корректный email адрес');
                    hasErrors = true;
                } else if (email.length > 100) {
                    showFieldError(emailField, 'Email не должен превышать 100 символов');
                    hasErrors = true;
                }
            }
            
            // Валидация адреса
            const addressField = document.getElementById('address');
            if (addressField && addressField.value) {
                const address = addressField.value.trim();
                if (address.length > 200) {
                    showFieldError(addressField, 'Адрес не должен превышать 200 символов');
                    hasErrors = true;
                } else if (!/^[А-Яа-яA-Za-z0-9\s\-\.\,\/]+$/.test(address)) {
                    showFieldError(addressField, 'Адрес может содержать только буквы, цифры, пробелы, дефисы, точки, запятые и слеши');
                    hasErrors = true;
                }
            }
            
            // Валидация URL
            const websiteField = document.getElementById('website');
            if (websiteField && websiteField.value) {
                const website = websiteField.value.trim();
                try {
                    const url = new URL(website);
                    if (!['http:', 'https:'].includes(url.protocol)) {
                        showFieldError(websiteField, 'URL должен начинаться с http:// или https://');
                        hasErrors = true;
                    } else if (website.length > 100) {
                        showFieldError(websiteField, 'URL не должен превышать 100 символов');
                        hasErrors = true;
                    }
                } catch {
                    showFieldError(websiteField, 'Введите корректный URL сайта');
                    hasErrors = true;
                }
            }
            
            // Валидация числовых полей
            const numberFields = ['years_experience', 'total_clients', 'total_specialists', 'satisfaction_rate'];
            numberFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field && field.value) {
                    const value = parseFloat(field.value);
                    const min = parseFloat(field.min);
                    const max = parseFloat(field.max);
                    
                    if (isNaN(value)) {
                        showFieldError(field, 'Введите корректное число');
                        hasErrors = true;
                    } else if (!isNaN(min) && value < min) {
                        showFieldError(field, `Минимальное значение: ${min}`);
                        hasErrors = true;
                    } else if (!isNaN(max) && value > max) {
                        showFieldError(field, `Максимальное значение: ${max}`);
                        hasErrors = true;
                    }
                    
                    // Специальная валидация для процентов
                    if (fieldId === 'satisfaction_rate' && value % 0.1 !== 0) {
                        showFieldError(field, 'Введите значение с точностью до 0.1%');
                        hasErrors = true;
                    }
                }
            });
            
            if (hasErrors) {
                resetSubmitButton();
                showAlert('Пожалуйста, исправьте ошибки в форме', 'error');
                
                // Прокручиваем к первой ошибке
                const firstError = form.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
                return;
            }
            
            // Если все валидации прошли успешно, отправляем форму
            this.submit();
        });
    }
    
    // Функция анимации карточек
    function initCardAnimations() {
        const cards = document.querySelectorAll('.settings-card');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }
    
    // Вспомогательные функции
    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        
        // Удаляем предыдущие сообщения об ошибках
        const existingError = field.parentNode.querySelector('.invalid-feedback-enhanced');
        if (existingError) {
            existingError.textContent = message;
        } else {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback-enhanced';
            errorDiv.textContent = message;
            field.parentNode.appendChild(errorDiv);
        }
    }
    
    function showFieldWarning(field, message) {
        // Временно показываем предупреждение
        const warning = document.createElement('div');
        warning.className = 'alert alert-warning-enhanced mt-1';
        warning.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
        
        field.parentNode.appendChild(warning);
        
        setTimeout(() => {
            if (warning.parentNode) {
                warning.parentNode.removeChild(warning);
            }
        }, 3000);
    }
    
    function clearValidationErrors() {
        const invalidFields = form.querySelectorAll('.is-invalid');
        invalidFields.forEach(field => {
            field.classList.remove('is-invalid');
        });
        
        const errorMessages = form.querySelectorAll('.invalid-feedback-enhanced:not([data-original])');
        errorMessages.forEach(error => {
            if (error.parentNode) {
                error.parentNode.removeChild(error);
            }
        });
    }
    
    function resetSubmitButton() {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Сохранить изменения';
    }
    
    function showAlert(message, type = 'info') {
        const alertClass = type === 'error' ? 'alert-danger-enhanced' : 'alert-success-enhanced';
        const icon = type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
        
        const alertHTML = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="${icon}"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        form.insertAdjacentHTML('afterbegin', alertHTML);
        
        // Прокручиваем к уведомлению
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Автоматически убираем через 5 секунд
        setTimeout(() => {
            const alert = form.querySelector('.alert');
            if (alert) {
                alert.remove();
            }
        }, 5000);
    }
});
</script>
@endsection
