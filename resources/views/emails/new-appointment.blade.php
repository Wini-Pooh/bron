<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Новая запись</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #4a6fdc;
            color: #fff;
            padding: 10px 20px;
            border-radius: 5px 5px 0 0;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 0 20px 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #777;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Новая запись в {{ $appointment->company->name }}</h1>
        </div>
        <div class="content">
            <p>Здравствуйте!</p>
            <p>У вас появилась новая запись от клиента на {{ $appointment->formatted_date }} в {{ $appointment->formatted_time }}.</p>
            
            <table>
                <tr>
                    <th>Информация о записи</th>
                    <th></th>
                </tr>
                <tr>
                    <td>Клиент:</td>
                    <td><strong>{{ $appointment->client_name }}</strong></td>
                </tr>
                <tr>
                    <td>Телефон:</td>
                    <td>{{ $appointment->client_phone }}</td>
                </tr>
                @if($appointment->client_email)
                <tr>
                    <td>Email:</td>
                    <td>{{ $appointment->client_email }}</td>
                </tr>
                @endif
                <tr>
                    <td>Услуга:</td>
                    <td>{{ $appointment->service->name }}</td>
                </tr>
                <tr>
                    <td>Дата:</td>
                    <td>{{ $appointment->formatted_date }}</td>
                </tr>
                <tr>
                    <td>Время:</td>
                    <td>{{ $appointment->formatted_time }}</td>
                </tr>
                <tr>
                    <td>Длительность:</td>
                    <td>{{ $appointment->service->formatted_duration }}</td>
                </tr>
                @if($appointment->notes)
                <tr>
                    <td>Комментарий:</td>
                    <td>{{ $appointment->notes }}</td>
                </tr>
                @endif
            </table>
            
            <p>Войдите в свой личный кабинет, чтобы управлять записями и просматривать подробную информацию.</p>
            
            <p>С уважением,<br>
            Команда системы онлайн-записи</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Система онлайн-записи. Все права защищены.
        </div>
    </div>
</body>
</html>
