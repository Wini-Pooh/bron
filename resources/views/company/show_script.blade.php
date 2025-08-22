<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== УТИЛИТЫ КАЛЕНДАРЯ (встроено вместо js/calendar-utils.js) =====
    class CalendarUtils {
        constructor(settings = {}) {
            this.settings = Object.assign({
                work_start_time: '09:00',
                work_end_time: '18:00',
                appointment_interval: 30,
                appointment_days_ahead: 14,
                work_days: ['monday','tuesday','wednesday','thursday','friday'],
                holidays: [], // 'YYYY-MM-DD' или 'MM-DD'
                break_times: [] // [{start:'12:00', end:'13:00'}]
            }, settings || {});
        }

        formatDateForServer(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        isWorkDay(date) {
            const weekday = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'][date.getDay()];
            return Array.isArray(this.settings.work_days) && this.settings.work_days.includes(weekday);
        }

        isHoliday(date) {
            const ymd = this.formatDateForServer(date);
            const md = ymd.slice(5);
            const list = this.settings.holidays || [];
            return list.some(h => (String(h).length === 10 ? h === ymd : h === md));
        }

        isDateAvailable(date) {
            const today = new Date();
            today.setHours(0,0,0,0);
            const target = new Date(date);
            target.setHours(0,0,0,0);
            const diffDays = Math.floor((target - today) / (24*60*60*1000));
            if (diffDays < 0) return false;
            if (this.settings.appointment_days_ahead > 0 && diffDays > this.settings.appointment_days_ahead) return false;
            if (!this.isWorkDay(target)) return false;
            if (this.isHoliday(target)) return false;
            return true;
        }

        isBreakTime(timeStr) {
            if (!timeStr) return false;
            const toMinutes = t => {
                const [hh, mm] = String(t).split(':');
                return parseInt(hh,10) * 60 + parseInt(mm||'0',10);
            };
            const t = toMinutes(timeStr);
            const breaks = this.settings.break_times || [];
            return breaks.some(b => toMinutes(b.start) <= t && t < toMinutes(b.end));
        }
    }
    // ===== КОНФИГУРАЦИЯ =====
    const config = {
        isOwner: @json($isOwner ?? false),
        calendarSettings: @json($calendarSettings ?? []),
        companySlug: '{{ $company->slug }}',
        csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        months: [
            'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
            'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
        ]
    };

    // ===== СОСТОЯНИЕ ПРИЛОЖЕНИЯ =====
    const state = {
        currentDate: new Date(),
        selectedDate: null,
        currentViewDate: null,
        dayViewVisible: false,
        monthlyStats: {},
        calendarUtils: null
    };

    // ===== DOM ЭЛЕМЕНТЫ =====
    const elements = {
        calendarTitle: document.getElementById('calendarTitle'),
        calendarDays: document.getElementById('calendarDays'),
        prevMonth: document.getElementById('prevMonth'),
        nextMonth: document.getElementById('nextMonth'),
        dayViewContainer: document.getElementById('dayViewContainer'),
        dayViewTitle: document.getElementById('dayViewTitle'),
        closeDayView: document.getElementById('closeDayView'),
        timeSlots: document.getElementById('timeSlots'),
        appointmentForm: document.getElementById('appointmentForm')
    };

    // ===== МОДАЛЬНЫЕ ОКНА =====
    const modals = {
        appointment: new bootstrap.Modal(document.getElementById('appointmentModal')),
        edit: config.isOwner ? new bootstrap.Modal(document.getElementById('editAppointmentModal')) : null
    };

    // ===== ИНИЦИАЛИЗАЦИЯ =====
    function init() {
        // Создаем утилиты календаря
        state.calendarUtils = new CalendarUtils(config.calendarSettings);
        
        // Настраиваем AJAX
        setupAjax();
        
        // Инициализируем обработчики
        initEventHandlers();
        
        // Инициализируем маски ввода
        initInputMasks();
        
        // Загружаем начальные данные
        loadMonthlyStats();
    }

    // ===== НАСТРОЙКА AJAX =====
    function setupAjax() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': config.csrfToken
            }
        });
    }

    // ===== ОБРАБОТЧИКИ СОБЫТИЙ =====
    function initEventHandlers() {
        // Навигация по месяцам
        elements.prevMonth?.addEventListener('click', () => {
            state.currentDate.setMonth(state.currentDate.getMonth() - 1);
            loadMonthlyStats();
        });

        elements.nextMonth?.addEventListener('click', () => {
            state.currentDate.setMonth(state.currentDate.getMonth() + 1);
            loadMonthlyStats();
        });

        // Закрытие детального вида
        elements.closeDayView?.addEventListener('click', hideDayView);

        // Форма записи
        elements.appointmentForm?.addEventListener('submit', handleAppointmentSubmit);

        // Счетчики символов
        initCharacterCounters();

        // Обработчики для владельца
        if (config.isOwner) {
            initOwnerHandlers();
        }
    }

    // ===== КАЛЕНДАРЬ =====
    function loadMonthlyStats() {
        const year = state.currentDate.getFullYear();
        const month = String(state.currentDate.getMonth() + 1).padStart(2, '0');
        
        fetch(`{{ route('company.monthly-stats', $company->slug) }}?month=${year}-${month}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            state.monthlyStats = data.stats || {};
            renderCalendar();
        })
        .catch(error => {
            console.error('Ошибка загрузки статистики:', error);
            renderCalendar();
        });
    }

    function renderCalendar() {
        const year = state.currentDate.getFullYear();
        const month = state.currentDate.getMonth();
        
        // Обновляем заголовок
        if (elements.calendarTitle) {
            elements.calendarTitle.textContent = `${config.months[month]} ${year}`;
        }
        if (elements.calendarDays) {
            elements.calendarDays.innerHTML = '';
        }
        
        // Расчет дней
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        
        let startingDayOfWeek = firstDay.getDay();
        startingDayOfWeek = startingDayOfWeek === 0 ? 6 : startingDayOfWeek - 1;
        
        // Дни предыдущего месяца
        const prevMonth = new Date(year, month, 0);
        const daysInPrevMonth = prevMonth.getDate();
        
        for (let i = startingDayOfWeek - 1; i >= 0; i--) {
            elements.calendarDays?.appendChild(
                createDayElement(daysInPrevMonth - i, true, month - 1, year)
            );
        }
        
        // Дни текущего месяца
        for (let day = 1; day <= daysInMonth; day++) {
            elements.calendarDays?.appendChild(
                createDayElement(day, false, month, year)
            );
        }
        
        // Дни следующего месяца
        const totalCells = elements.calendarDays.children.length;
        const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
        
        for (let day = 1; day <= remainingCells; day++) {
            elements.calendarDays?.appendChild(
                createDayElement(day, true, month + 1, year)
            );
        }
    }

    function createDayElement(day, isOtherMonth, month, year) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        
        if (isOtherMonth) {
            dayElement.classList.add('other-month');
        }
        
        // Корректируем месяц и год для других месяцев
        let actualMonth = month;
        let actualYear = year;
        
        if (actualMonth < 0) {
            actualMonth = 11;
            actualYear--;
        } else if (actualMonth > 11) {
            actualMonth = 0;
            actualYear++;
        }
        
        const date = new Date(actualYear, actualMonth, day, 12, 0, 0);
        const today = new Date();
        today.setHours(12, 0, 0, 0);
        
        // Добавляем классы
        if (!isOtherMonth) {
            if (!state.calendarUtils.isWorkDay(date)) {
                dayElement.classList.add('weekend');
            }
            
            if (state.calendarUtils.isHoliday(date)) {
                dayElement.classList.add('holiday');
            }
            
            if (!state.calendarUtils.isDateAvailable(date)) {
                dayElement.classList.add('disabled');
            }
            
            if (date.toDateString() === today.toDateString()) {
                dayElement.classList.add('today');
            }
        }
        
        // Контент дня
        const dayContent = document.createElement('div');
        dayContent.className = 'day-content';
        
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = day;
        dayContent.appendChild(dayNumber);
        
        // Счетчик записей
        if (!isOtherMonth) {
            const dateKey = state.calendarUtils.formatDateForServer(date);
            const count = state.monthlyStats[dateKey] || 0;
            
            if (count > 0) {
                const badge = document.createElement('div');
                badge.className = 'appointment-count';
                badge.textContent = count;
                dayContent.appendChild(badge);
            }
        }
        
        dayElement.appendChild(dayContent);
        
        // Обработчик клика
        if (!isOtherMonth && state.calendarUtils.isDateAvailable(date)) {
            dayElement.addEventListener('click', () => selectDate(date, dayElement));
        }
        
        return dayElement;
    }

    function selectDate(date, element) {
        // Убираем предыдущее выделение
        document.querySelectorAll('.calendar-day.selected').forEach(el => {
            el.classList.remove('selected');
        });
        
        // Выделяем новый день
        element.classList.add('selected');
        state.selectedDate = date;
        
        // Показываем детальный вид
        showDayView(date);
    }

    // ===== ДЕТАЛЬНЫЙ ВИД ДНЯ =====
    function showDayView(date) {
        state.dayViewVisible = true;
        state.currentViewDate = date;
        
        elements.dayViewContainer.style.display = 'block';
        elements.dayViewTitle.textContent = `Расписание на ${formatDateForDisplay(date)}`;
        
        loadDaySchedule(date);
        
        elements.dayViewContainer.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }

    function hideDayView() {
        state.dayViewVisible = false;
        state.currentViewDate = null;
        elements.dayViewContainer.style.display = 'none';
    }

    function loadDaySchedule(date) {
        const dateString = state.calendarUtils.formatDateForServer(date);
        
        fetch(`{{ route('company.appointments', $company->slug) }}?date=${dateString}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            renderTimeSlots(data.timeSlots || []);
        })
        .catch(error => {
            console.error('Ошибка загрузки расписания:', error);
            if (elements.timeSlots) {
                elements.timeSlots.innerHTML = '<div class="alert alert-danger">Ошибка загрузки расписания</div>';
            }
        });
    }

    function renderTimeSlots(slots) {
        elements.timeSlots.innerHTML = '';
        
        slots.forEach(slot => {
            const slotElement = createTimeSlot(slot);
            elements.timeSlots.appendChild(slotElement);
        });
    }

    function createTimeSlot(slot) {
        const slotDiv = document.createElement('div');
        slotDiv.className = 'time-slot';
        
        if (slot.isPast) {
            slotDiv.classList.add('past');
        }
        
        // Время
        const timeLabel = document.createElement('div');
        timeLabel.className = 'time-label';
        timeLabel.textContent = slot.time;
        
        // Контент
        const timeContent = document.createElement('div');
        timeContent.className = 'time-content';
        
        // Логика отображения
        if (config.isOwner && slot.appointments && slot.appointments.length > 0) {
            // Для владельца - показываем записи
            renderOwnerAppointments(timeContent, slot);
        } else if (slot.available) {
            // Доступный слот
            renderAvailableSlot(timeContent, slot);
        } else if (slot.isPast) {
            // Прошедшее время
            timeContent.classList.add('unavailable', 'past');
            timeContent.innerHTML = '<div class="unavailable-slot"><i class="fas fa-clock"></i> Прошло</div>';
        } else if (state.calendarUtils.isBreakTime(slot.time)) {
            // Перерыв
            timeContent.classList.add('break');
            timeContent.innerHTML = '<div class="break-slot"><i class="fas fa-coffee"></i> Перерыв</div>';
        } else {
            // Занято
            timeContent.classList.add('occupied');
            timeContent.innerHTML = '<div class="occupied-slot"><i class="fas fa-ban"></i> Занято</div>';
        }
        
        slotDiv.appendChild(timeLabel);
        slotDiv.appendChild(timeContent);
        
        return slotDiv;
    }

    function renderOwnerAppointments(container, slot) {
        const appointments = Array.isArray(slot.appointments) ? 
            slot.appointments : Object.values(slot.appointments);
        
        appointments.forEach((appointment, index) => {
            const card = createAppointmentCard(appointment, index, appointments.length);
            container.appendChild(card);
        });
        
        // Кнопка добавления если еще есть места
        if (slot.available) {
            const addButton = createAddButton(slot);
            container.appendChild(addButton);
        }
    }

    function createAppointmentCard(appointment, index, total) {
        const card = document.createElement('div');
        card.className = 'appointment-card owner-view';
        
        if (total > 1) {
            card.classList.add('multiple-booking');
        }
        
        card.innerHTML = `
            ${total > 1 ? `<div class="booking-number">Запись ${index + 1}/${total}</div>` : ''}
            <div class="appointment-client">
                <i class="fas fa-user"></i> ${appointment.client_name}
            </div>
            ${appointment.client_phone ? `
                <div class="appointment-phone">
                    <i class="fas fa-phone"></i> ${appointment.client_phone}
                </div>
            ` : ''}
            <div class="appointment-status status-${appointment.status || 'pending'}">
                ${getStatusText(appointment.status || 'pending')}
            </div>
            <div class="edit-hint">
                <i class="fas fa-edit"></i> Нажмите для редактирования
            </div>
        `;
        
        card.addEventListener('click', () => openEditModal(appointment));
        
        return card;
    }

    function renderAvailableSlot(container, slot) {
        container.classList.add('available');
        
        const text = slot.multiple_bookings_enabled ? 
            `Свободно (${slot.appointment_count || 0}/${slot.max_appointments})` : 
            'Свободно';
        
        container.innerHTML = `
            <div class="available-slot">
                <i class="fas fa-plus-circle"></i> ${text}
            </div>
        `;
        
        container.addEventListener('click', () => openAppointmentModal(slot));
    }

    function createAddButton(slot) {
        const button = document.createElement('div');
        button.className = 'add-more-slot';
        button.innerHTML = `
            <div class="available-slot">
                <i class="fas fa-plus-circle"></i> Добавить ещё (${slot.appointment_count || 0}/${slot.max_appointments || 1})
            </div>
        `;
        
        button.addEventListener('click', () => openAppointmentModal(slot));
        
        return button;
    }

    // ===== МОДАЛЬНЫЕ ОКНА =====
    function openAppointmentModal(slot) {
        const date = state.currentViewDate || state.selectedDate;
        
        if (!date) {
            showAlert('Не выбрана дата', 'danger');
            return;
        }
        
        // Проверяем доступность
        checkSlotAvailability(date, slot.time, () => {
            document.getElementById('modal_appointment_date').value = state.calendarUtils.formatDateForServer(date);
            document.getElementById('modal_appointment_time').value = slot.time;
            document.getElementById('selected_date_display').textContent = formatDateForDisplay(date);
            
            modals.appointment.show();
        });
    }

    function checkSlotAvailability(date, time, callback) {
        const dateString = state.calendarUtils.formatDateForServer(date);
        
        fetch(`{{ route('company.appointments', $company->slug) }}?date=${dateString}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            const slot = data.timeSlots.find(s => s.time === time);
            
            if (!slot || !slot.available) {
                showAlert('Это время уже занято', 'warning');
                if (state.dayViewVisible) {
                    renderTimeSlots(data.timeSlots);
                }
                return;
            }
            
            callback();
        })
        .catch(error => {
            console.error('Ошибка проверки доступности:', error);
            callback(); // Продолжаем в случае ошибки
        });
    }

    function handleAppointmentSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(elements.appointmentForm);
        
        // Валидация
        if (!validateAppointmentForm(formData)) {
            return;
        }
        
        // Отправка
        fetch(`{{ route('company.appointments.create', $company->slug) }}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': config.csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json().then(data => ({
            ok: response.ok,
            status: response.status,
            data
        })))
        .then(result => {
            if (result.ok) {
                showAlert('Запись успешно создана!', 'success');
                modals.appointment.hide();
                elements.appointmentForm.reset();
                
                if (state.dayViewVisible) {
                    loadDaySchedule(state.currentViewDate);
                }
                
                loadMonthlyStats();
            } else {
                if (result.status === 422 && result.data.errors) {
                    showValidationErrors(result.data.errors);
                } else {
                    showAlert(result.data.message || 'Ошибка при создании записи', 'danger');
                }
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showAlert('Произошла ошибка при создании записи', 'danger');
        });
    }

    function validateAppointmentForm(formData) {
        const date = formData.get('appointment_date');
        const time = formData.get('appointment_time');
        
        const appointmentDateTime = new Date(`${date}T${time}`);
        const now = new Date();
        
        if (appointmentDateTime < now) {
            showAlert('Нельзя записаться на прошедшее время', 'danger');
            return false;
        }
        
        return true;
    }

    // ===== ФУНКЦИИ ДЛЯ ВЛАДЕЛЬЦА =====
    function initOwnerHandlers() {
        if (!config.isOwner) return;
        
        // Обработчики для модального окна редактирования
        const editDateField = document.getElementById('edit_new_date');
        if (editDateField) {
            editDateField.addEventListener('change', handleEditDateChange);
        }
        
        const updateBtn = document.getElementById('updateBtn');
        if (updateBtn) {
            updateBtn.addEventListener('click', handleAppointmentUpdate);
        }
        
        const cancelBtn = document.getElementById('cancelAppointmentBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', handleAppointmentCancel);
        }
    }

    function openEditModal(appointment) {
        if (!config.isOwner || !modals.edit) return;
        
        // Заполняем данные
        fillEditModalData(appointment);
        
        // Загружаем доступные слоты
        loadEditModalTimeSlots(appointment.appointment_date, appointment.appointment_time);
        
        // Показываем модальное окно
        modals.edit.show();
    }

    function fillEditModalData(appointment) {
        document.getElementById('edit_appointment_id').value = appointment.id;
        document.getElementById('edit_client_name').textContent = appointment.client_name || '-';
        document.getElementById('edit_client_phone').textContent = appointment.client_phone || '-';
        document.getElementById('edit_appointment_date').textContent = appointment.appointment_date || '-';
        document.getElementById('edit_appointment_time').textContent = appointment.appointment_time || '-';
        document.getElementById('edit_created_at').textContent = appointment.created_at || '-';
        
        // Статус
        const statusEl = document.getElementById('edit_status');
        statusEl.textContent = getStatusText(appointment.status);
        statusEl.className = `badge bg-${getStatusColor(appointment.status)}`;
        
        // Комментарий клиента
        const notesSection = document.getElementById('client_notes_section');
        const notesEl = document.getElementById('edit_client_notes');
        
        if (appointment.notes) {
            notesEl.textContent = appointment.notes;
            notesSection.style.display = 'block';
        } else {
            notesSection.style.display = 'none';
        }
        
        // Поля редактирования
        document.getElementById('edit_client_name_field').value = appointment.client_name || '';
        document.getElementById('edit_client_phone_field').value = appointment.client_phone || '';
        document.getElementById('edit_owner_notes').value = appointment.owner_notes || '';
        
        // Дата для редактирования
        const dateForInput = convertDateToInputFormat(appointment.appointment_date);
        document.getElementById('edit_new_date').value = dateForInput;
        
        // Сохраняем данные для последующего использования
        document.getElementById('editAppointmentForm').dataset.appointment = JSON.stringify(appointment);
    }

    function loadEditModalTimeSlots(date, currentTime) {
        const serverDate = convertDateToServerFormat(date);
        const timeSelect = document.getElementById('edit_new_time');
        
        timeSelect.innerHTML = '<option value="">Загрузка...</option>';
        
        fetch(`{{ route('company.appointments', $company->slug) }}?date=${serverDate}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            timeSelect.innerHTML = '<option value="">Выберите время</option>';
            
            if (data.timeSlots) {
                data.timeSlots.forEach(slot => {
                    if (!slot.isPast) {
                        const option = document.createElement('option');
                        option.value = slot.time;
                        option.textContent = slot.time;
                        
                        if (slot.time === currentTime) {
                            option.selected = true;
                            option.textContent += ' (текущее)';
                        } else if (!slot.available) {
                            option.textContent += ' (занято)';
                        }
                        
                        timeSelect.appendChild(option);
                    }
                });
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки слотов:', error);
            timeSelect.innerHTML = '<option value="">Ошибка загрузки</option>';
        });
    }

    function handleEditDateChange(e) {
        const newDate = e.target.value;
        if (newDate) {
            loadEditModalTimeSlots(newDate, null);
        }
    }

    function handleAppointmentUpdate() {
        const form = document.getElementById('editAppointmentForm');
        const appointment = JSON.parse(form.dataset.appointment || '{}');
        
        const updateData = {};
        let hasChanges = false;
        
        // Проверяем изменения
        const newDate = document.getElementById('edit_new_date').value;
        const newTime = document.getElementById('edit_new_time').value;
        const newName = document.getElementById('edit_client_name_field').value.trim();
        const newPhone = document.getElementById('edit_client_phone_field').value;
        const ownerNotes = document.getElementById('edit_owner_notes').value;
        
        if (newDate && newTime) {
            const originalDate = convertDateToInputFormat(appointment.appointment_date);
            if (newDate !== originalDate || newTime !== appointment.appointment_time) {
                updateData.appointment_date = newDate;
                updateData.appointment_time = newTime;
                hasChanges = true;
            }
        }
        
        if (newName && newName !== appointment.client_name) {
            updateData.client_name = newName;
            hasChanges = true;
        }
        
        if (newPhone !== appointment.client_phone) {
            updateData.client_phone = newPhone;
            hasChanges = true;
        }
        
        if (ownerNotes !== (appointment.owner_notes || '')) {
            updateData.owner_notes = ownerNotes;
            hasChanges = true;
        }
        
        if (!hasChanges) {
            showAlert('Внесите изменения перед обновлением', 'warning');
            return;
        }
        
        // Отправка обновления
        updateAppointment(appointment.id, updateData);
    }

    function handleAppointmentCancel() {
        const form = document.getElementById('editAppointmentForm');
        const appointment = JSON.parse(form.dataset.appointment || '{}');
        
        if (!appointment.id) return;
        
        if (confirm('Отменить эту запись? Это действие нельзя отменить.')) {
            cancelAppointment(appointment.id);
        }
    }

    function updateAppointment(id, data) {
        fetch(`{{ url('/company/'.$company->slug) }}/appointments/${id}/update`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showAlert('Запись успешно обновлена!', 'success');
                modals.edit.hide();
                
                if (state.dayViewVisible) {
                    loadDaySchedule(state.currentViewDate);
                }
                
                loadMonthlyStats();
            } else {
                showAlert(result.error || 'Ошибка при обновлении записи', 'danger');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showAlert('Ошибка при обновлении записи', 'danger');
        });
    }

    function cancelAppointment(id) {
        fetch(`{{ url('/company/'.$company->slug) }}/appointments/${id}/cancel`, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': config.csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showAlert('Запись отменена!', 'success');
                modals.edit.hide();
                
                if (state.dayViewVisible) {
                    loadDaySchedule(state.currentViewDate);
                }
                
                loadMonthlyStats();
            } else {
                showAlert(result.error || 'Ошибка при отмене записи', 'danger');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showAlert('Ошибка при отмене записи', 'danger');
        });
    }

    // ===== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ =====
    function formatDateForDisplay(date) {
        const day = date.getDate().toString().padStart(2, '0');
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const year = date.getFullYear();
        return `${day}.${month}.${year}`;
    }

    function convertDateToInputFormat(dateStr) {
        if (!dateStr) return '';
        
        // Если формат dd.mm.yyyy
        if (dateStr.includes('.')) {
            const parts = dateStr.split('.');
            if (parts.length === 3) {
                return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
            }
        }
        
        return dateStr;
    }

    function convertDateToServerFormat(dateStr) {
        if (!dateStr) return '';
        
        // Если формат dd.mm.yyyy
        if (dateStr.includes('.')) {
            const parts = dateStr.split('.');
            if (parts.length === 3) {
                return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
            }
        }
        
        return dateStr;
    }

    function getStatusText(status) {
        const statusMap = {
            'pending': 'Ожидает',
            'confirmed': 'Подтверждена',
            'cancelled': 'Отменена',
            'completed': 'Выполнена'
        };
        return statusMap[status] || status;
    }

    function getStatusColor(status) {
        const colorMap = {
            'pending': 'warning',
            'confirmed': 'success',
            'cancelled': 'danger',
            'completed': 'info'
        };
        return colorMap[status] || 'secondary';
    }

    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        const container = document.querySelector('.container') || document.body;
        container.prepend(alertDiv);
        setTimeout(() => {
            alertDiv.classList.remove('show');
            alertDiv.remove();
        }, 5000);
    }

    // Маски ввода (телефон)
    function initInputMasks() {
        try {
            if (window.jQuery && typeof jQuery.fn.mask === 'function') {
                const $ = window.jQuery;
                $("#modal_client_phone, #edit_client_phone_field").mask('+0 (000) 000-00-00');
            }
        } catch (e) { /* ignore */ }
    }

    // Счетчики символов для текстовых полей
    function initCharacterCounters() {
        const notes = document.getElementById('modal_notes');
        const notesCounter = document.getElementById('notesCounter');
        if (notes && notesCounter) {
            const max = parseInt(notes.getAttribute('maxlength') || '500', 10);
            const update = () => notesCounter.textContent = String(Math.max(0, max - notes.value.length));
            notes.addEventListener('input', update);
            update();
        }

        const ownerNotes = document.getElementById('edit_owner_notes');
        const ownerCounter = document.getElementById('ownerNotesCounter');
        if (ownerNotes && ownerCounter) {
            const max2 = parseInt(ownerNotes.getAttribute('maxlength') || '500', 10);
            const update2 = () => ownerCounter.textContent = String(Math.max(0, max2 - ownerNotes.value.length));
            ownerNotes.addEventListener('input', update2);
            update2();
        }
    }

    // Показ валидационных ошибок бэкенда
    function showValidationErrors(errors) {
        try {
            const messages = [];
            Object.keys(errors || {}).forEach(key => {
                const arr = errors[key];
                if (Array.isArray(arr)) {
                    arr.forEach(msg => messages.push(String(msg)));
                } else if (arr) {
                    messages.push(String(arr));
                }
            });
            if (messages.length) {
                showAlert(messages.join('<br>'), 'danger');
            }
        } catch (e) {
            showAlert('Проверьте корректность введенных данных', 'danger');
        }
    }

    // Старт приложения
    init();
});
</script>