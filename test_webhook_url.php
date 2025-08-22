<?php
/**
 * Тестирование доступности webhook URL
 */

$webhookUrl = 'https://tg.sticap.ru/telegram/webhook/8251179594:AAGvNDON5sPI4pSXp8IXg2o02EuX1Uii1Rc';

echo "=== Тестирование доступности webhook URL ===\n\n";

echo "Проверяем URL: $webhookUrl\n\n";

// Проверяем доступность URL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'update_id' => 12345,
    'message' => [
        'message_id' => 1,
        'from' => [
            'id' => 123456789,
            'first_name' => 'Test',
            'username' => 'testuser'
        ],
        'chat' => [
            'id' => 123456789,
            'type' => 'private'
        ],
        'date' => time(),
        'text' => '/start'
    ]
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$startTime = microtime(true);
$response = curl_exec($ch);
$endTime = microtime(true);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$responseTime = round(($endTime - $startTime) * 1000, 2);

curl_close($ch);

echo "Результат:\n";
echo "HTTP код: $httpCode\n";
echo "Время ответа: {$responseTime}ms\n";

if ($error) {
    echo "❌ Ошибка cURL: $error\n";
} else {
    echo "✅ Запрос выполнен успешно\n";
}

echo "Ответ сервера:\n";
echo "================\n";
echo $response . "\n";
echo "================\n";

// Анализ результатов
if ($httpCode == 200) {
    echo "\n✅ Webhook URL доступен и отвечает корректно!\n";
} elseif ($httpCode == 0 && $error) {
    echo "\n❌ Сервер недоступен или есть проблемы с сетью\n";
    echo "Возможные причины:\n";
    echo "- Сервер выключен\n";
    echo "- Проблемы с DNS\n";
    echo "- Файрвол блокирует соединения\n";
    echo "- SSL сертификат неправильный\n";
} elseif ($httpCode >= 400) {
    echo "\n⚠️ Webhook URL доступен, но возвращает ошибку HTTP $httpCode\n";
    echo "Возможные причины:\n";
    echo "- Маршрут не найден (404)\n";
    echo "- Ошибка в коде обработчика\n";
    echo "- Проблемы с аутентификацией\n";
} else {
    echo "\n⚠️ Неожиданный HTTP код: $httpCode\n";
}

echo "\n=== Тестирование завершено ===\n";
