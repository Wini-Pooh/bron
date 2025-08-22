function createTimeSlot(slot) {
    const slotElement = document.createElement('div');
    slotElement.className = 'time-slot';
    
    // Добавляем класс для прошедших слотов
    if (slot.isPast) {
        slotElement.classList.add('past');
    }
    
    const timeLabel = document.createElement('div');
    timeLabel.className = 'time-label';
    timeLabel.textContent = slot.time;
    
    const timeContent = document.createElement('div');
    timeContent.className = 'time-content';
    
    // Добавляем класс для прошедших слотов
    if (slot.isPast) {
        timeContent.classList.add('past');
    }
    
    // Логика отображения для владельца и клиентов
    // Приоритет 1: Если это владелец и есть записи - показываем карточки записей с возможностью редактирования
    if (isOwner && slot.appointments && slot.appointments.length > 0) {
        // Показываем карточки записей для владельца
        slot.appointments.forEach((appointment, index) => {
            const appointmentCard = document.createElement('div');
            appointmentCard.className = `appointment-card owner-view ${appointment.type || ''}`;
            
            // Добавляем класс для прошедших записей
            if (slot.isPast) {
                appointmentCard.classList.add('past');
            }
            
            // Добавляем стиль для множественных записей
            if (slot.appointments.length > 1) {
                appointmentCard.classList.add('multiple-booking');
                appointmentCard.style.marginBottom = '5px';
            }
            
            // Добавляем курсор pointer для показа возможности клика
            appointmentCard.style.cursor = 'pointer';
            appointmentCard.title = 'Нажмите для редактирования записи';
            
            appointmentCard.innerHTML = `
                ${slot.appointments.length > 1 ? `<div class="booking-number">Запись ${index + 1}/${slot.appointments.length}</div>` : ''}
                <div class="appointment-title">${appointment.title || 'Запись'}</div>
                <div class="appointment-client">
                    <i class="fas fa-user"></i> ${appointment.client_name}
                </div>
                ${appointment.client_phone ? `
                    <div class="appointment-phone">
                        <i class="fas fa-phone"></i> ${appointment.client_phone}
                    </div>
                ` : ''}
                ${appointment.client_email ? `
                    <div class="appointment-email">
                        <i class="fas fa-envelope"></i> ${appointment.client_email}
                    </div>
                ` : ''}
                <div class="appointment-details">
                    <span class="appointment-duration">
                        <i class="fas fa-clock"></i> ${appointment.duration || '60 мин'}
                    </span>
                    <span class="appointment-price">
                        <i class="fas fa-ruble-sign"></i> ${appointment.price || 'Бесплатно'}
                    </span>
                </div>
                ${appointment.notes ? `
                    <div class="appointment-notes">
                        <i class="fas fa-comment"></i> ${appointment.notes}
                    </div>
                ` : ''}
                <div class="appointment-status status-${appointment.status}">
                    ${getStatusText(appointment.status)}
                </div>
                <div class="edit-hint">
                    <i class="fas fa-edit"></i> Нажмите для редактирования
                </div>
            `;
            
            // Добавляем обработчик клика для редактирования записи
            appointmentCard.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Подготавливаем данные записи для передачи в модальное окно
                const appointmentData = {
                    id: appointment.id,
                    client_name: appointment.client_name,
                    client_phone: appointment.client_phone,
                    client_email: appointment.client_email,
                    appointment_date: appointment.appointment_date,
                    appointment_time: appointment.appointment_time,
                    notes: appointment.notes,
                    status: appointment.status,
                    created_at: appointment.created_at,
                    owner_notes: appointment.owner_notes || ''
                };
                
                // Открываем модальное окно редактирования
                if (typeof openEditAppointmentModal === 'function') {
                    openEditAppointmentModal(appointmentData);
                } else {
                    console.error('Функция openEditAppointmentModal не найдена');
                }
            });
            
            timeContent.appendChild(appointmentCard);
        });
        
        // Если слот еще доступен для новых записей, добавляем кнопку "Добавить еще"
        if (slot.available) {
            const addMoreButton = document.createElement('div');
            addMoreButton.className = 'add-more-slot';
            addMoreButton.innerHTML = `
                <div class="available-slot">
                    <i class="fas fa-plus-circle"></i> Добавить ещё (${slot.appointment_count || 0}/${slot.max_appointments || 1})
                </div>
            `;
            
            // Добавляем обработчик для кнопки "Добавить еще"
            addMoreButton.addEventListener('click', function() {
                // Сначала обновим расписание, чтобы убедиться, что время все еще доступно
                const targetDate = selectedDate || currentViewDate;
                
                // Убедимся, что у нас есть правильная дата
                if (!targetDate) {
                    console.error('Не выбрана дата!');
                    return;
                }
                
                // Проверяем доступность слота в реальном времени
                const dateString = calendarUtils.formatDateForServer(targetDate);
                const timeValue = slot.time;
                
                fetch(`{{ route('company.appointments', $company->slug) }}?date=${dateString}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    // Ищем слот с выбранным временем
                    const currentSlot = data.timeSlots.find(s => s.time === timeValue);
                    
                    if (!currentSlot || !currentSlot.available) {
                        // Слот уже занят
                        const message = currentSlot && currentSlot.multiple_bookings_enabled ? 
                            'Извините, все места на это время заняты. Обновляем расписание...' :
                            'Извините, это время уже занято. Обновляем расписание...';
                        alert(message);
                        renderDaySchedule(data.timeSlots);
                        return;
                    }
                    
                    // Время все еще доступно, продолжаем с формой записи
                    const formattedDate = calendarUtils.formatDateForServer(targetDate);
                    
                    console.log('Установка даты в модальное окно:', formattedDate);
                    console.log('Исходная выбранная дата:', targetDate.toString());
                    
                    // Устанавливаем дату в скрытое поле
                    document.getElementById('modal_appointment_date').value = formattedDate;
                    document.getElementById('modal_appointment_time').value = timeValue;
                    
                    // Показываем дате пользователю в читаемом формате
                    const formattedDisplayDate = formatDateForDisplay(targetDate);
                    document.getElementById('selected_date_display').textContent = formattedDisplayDate;
                    
                    appointmentModal.show();
                })
                .catch(error => {
                    console.error('Ошибка при проверке доступности слота:', error);
                    alert('Произошла ошибка при проверке доступности времени. Пожалуйста, попробуйте снова.');
                });
            });
            
            timeContent.appendChild(addMoreButton);
        }
    }
    // Приоритет 2: Если это владелец и слот доступен для записи
    else if (isOwner && slot.available) {
        const addButton = document.createElement('div');
        addButton.className = 'add-more-slot';
        addButton.innerHTML = `
            <div class="available-slot">
                <i class="fas fa-plus-circle"></i> Добавить (${slot.appointment_count || 0}/${slot.max_appointments || 1})
            </div>
        `;
        
        // Добавляем обработчик для кнопки "Добавить"
        addButton.addEventListener('click', function() {
            // Код для добавления новой записи (аналогично существующему)
            const targetDate = selectedDate || currentViewDate;
            
            if (!targetDate) {
                console.error('Не выбрана дата!');
                return;
            }
            
            const dateString = calendarUtils.formatDateForServer(targetDate);
            const timeValue = slot.time;
            
            fetch(`{{ route('company.appointments', $company->slug) }}?date=${dateString}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                const currentSlot = data.timeSlots.find(s => s.time === timeValue);
                
                if (!currentSlot || !currentSlot.available) {
                    const message = currentSlot && currentSlot.multiple_bookings_enabled ? 
                        'Извините, все места на это время заняты. Обновляем расписание...' :
                        'Извините, это время уже занято. Обновляем расписание...';
                    alert(message);
                    renderDaySchedule(data.timeSlots);
                    return;
                }
                
                const formattedDate = calendarUtils.formatDateForServer(targetDate);
                
                document.getElementById('modal_appointment_date').value = formattedDate;
                document.getElementById('modal_appointment_time').value = timeValue;
                
                const formattedDisplayDate = formatDateForDisplay(targetDate);
                document.getElementById('selected_date_display').textContent = formattedDisplayDate;
                
                appointmentModal.show();
            })
            .catch(error => {
                console.error('Ошибка при проверке доступности слота:', error);
                alert('Произошла ошибка при проверке доступности времени. Пожалуйста, попробуйте снова.');
            });
        });
        
        timeContent.appendChild(addButton);
    }
    // Приоритет 3: Если слот доступен для клиентов
    else if (slot.available) {
        // Показываем свободный слот для клиентов
        timeContent.classList.add('available');
        
        let availabilityText;
        if (slot.multiple_bookings_enabled) {
            availabilityText = `Свободно (${slot.appointment_count || 0}/${slot.max_appointments})`;
        } else {
            availabilityText = 'Свободно';
        }
        
        timeContent.innerHTML = `
            <div class="available-slot">
                <i class="fas fa-plus-circle"></i> ${availabilityText}
            </div>
        `;
        
        // Добавляем обработчик для доступных слотов
        timeContent.addEventListener('click', function() {
            // Сначала обновим расписание, чтобы убедиться, что время все еще доступно
            const targetDate = selectedDate || currentViewDate;
            
            // Убедимся, что у нас есть правильная дата
            if (!targetDate) {
                console.error('Не выбрана дата!');
                return;
            }
            
            // Код для клиентской записи (аналогично существующему)
            const dateString = calendarUtils.formatDateForServer(targetDate);
            const timeValue = slot.time;
            
            fetch(`{{ route('company.appointments', $company->slug) }}?date=${dateString}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                const currentSlot = data.timeSlots.find(s => s.time === timeValue);
                
                if (!currentSlot || !currentSlot.available) {
                    const message = currentSlot && currentSlot.multiple_bookings_enabled ? 
                        'Извините, все места на это время заняты. Обновляем расписание...' :
                        'Извините, это время уже занято. Обновляем расписание...';
                    alert(message);
                    renderDaySchedule(data.timeSlots);
                    return;
                }
                
                const formattedDate = calendarUtils.formatDateForServer(targetDate);
                
                document.getElementById('modal_appointment_date').value = formattedDate;
                document.getElementById('modal_appointment_time').value = timeValue;
                
                const formattedDisplayDate = formatDateForDisplay(targetDate);
                document.getElementById('selected_date_display').textContent = formattedDisplayDate;
                
                appointmentModal.show();
            })
            .catch(error => {
                console.error('Ошибка при проверке доступности слота:', error);
                alert('Произошла ошибка при проверке доступности времени. Пожалуйста, попробуйте снова.');
            });
        });
    }
    // Приоритет 4: Для клиентов, если слот занят
    else if (slot.appointments && slot.appointments.length > 0 && !isOwner) {
        // Для клиентов показываем информацию о занятости (полностью занято)
        timeContent.classList.add('occupied');
        const appointment = slot.appointments[0]; // Берем первую запись для отображения
        
        let occupiedText;
        if (slot.multiple_bookings_enabled) {
            occupiedText = `Занято (${slot.appointment_count || 0}/${slot.max_appointments || 1})`;
        } else {
            occupiedText = 'Занято';
        }
        
        timeContent.innerHTML = `
            <div class="occupied-slot">
                <i class="fas fa-ban"></i> ${occupiedText}
            </div>
        `;
    }
    // Приоритет 5: Недоступные слоты
    else {
        // Слот недоступен
        if (slot.isPast) {
            timeContent.classList.add('unavailable', 'past');
            timeContent.innerHTML = `
                <div class="unavailable-slot">
                    <i class="fas fa-clock"></i> Прошло
                </div>
            `;
        } else {
            // Проверяем, является ли это временем перерыва
            const isBreakTime = calendarUtils.isBreakTime(slot.time);
            
            if (isBreakTime) {
                timeContent.classList.add('break');
                timeContent.innerHTML = `
                    <div class="break-slot">
                        <i class="fas fa-coffee"></i> Перерыв
                    </div>
                `;
            } else {
                timeContent.classList.add('occupied');
                timeContent.innerHTML = `
                    <div class="occupied-slot">
                        <i class="fas fa-ban"></i> Занято
                    </div>
                `;
            }
        }
    }
    
    slotElement.appendChild(timeLabel);
    slotElement.appendChild(timeContent);
    
    return slotElement;
}
