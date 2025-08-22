<?php
/**
 * Простой скрипт для тестирования Telegram Bot API
 * Запуск: php test_telegram_api.php
 */

// Настройки бота (замените на ваши данные)
$botToken = '8251179594:AAGvNDON5sPI4pSXp8IXg2o02EuX1Uii1Rc';
$webhookUrl = 'https://tg.sticap.ru/telegram/webhook/' . $botToken;

echo "=== Тестирование Telegram Bot API ===\n\n";

// 1. Получаем информацию о боте
echo "1. Получение информации о боте...\n";
$botInfoUrl = "https://api.telegram.org/bot{$botToken}/getMe";
$botInfo = file_get_contents($botInfoUrl);
$botData = json_decode($botInfo, true);

if ($botData && $botData['ok']) {
    echo "✅ Бот найден:\n";
    echo "   ID: " . $botData['result']['id'] . "\n";
    echo "   Username: @" . $botData['result']['username'] . "\n";
    echo "   Имя: " . $botData['result']['first_name'] . "\n\n";
} else {
    echo "❌ Ошибка получения информации о боте\n";
    echo "Ответ: " . $botInfo . "\n\n";
    exit(1);
}

// 2. Проверяем текущий webhook
echo "2. Проверка текущего webhook...\n";
$webhookInfoUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
$webhookInfo = file_get_contents($webhookInfoUrl);
$webhookData = json_decode($webhookInfo, true);

if ($webhookData && $webhookData['ok']) {
    $info = $webhookData['result'];
    if ($info['url']) {
        echo "✅ Webhook активен:\n";
        echo "   URL: " . $info['url'] . "\n";
        echo "   Ожидающих обновлений: " . ($info['pending_update_count'] ?? 0) . "\n";
        
        if (isset($info['last_error_date'])) {
            echo "❌ Последняя ошибка: " . date('Y-m-d H:i:s', $info['last_error_date']) . "\n";
            echo "   Сообщение: " . ($info['last_error_message'] ?? 'Неизвестная ошибка') . "\n";
        } else {
            echo "✅ Ошибок нет\n";
        }
    } else {
        echo "❌ Webhook не установлен\n";
    }
} else {
    echo "❌ Ошибка получения информации о webhook\n";
    echo "Ответ: " . $webhookInfo . "\n";
}

echo "\n";

// 3. Устанавливаем webhook
echo "3. Установка webhook...\n";
$setWebhookUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";
$postData = [
    'url' => $webhookUrl,
    'allowed_updates' => ['message', 'callback_query']
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query($postData)
    ]
]);

$setResult = file_get_contents($setWebhookUrl, false, $context);
$setData = json_decode($setResult, true);

if ($setData && $setData['ok']) {
    echo "✅ Webhook успешно установлен!\n";
    echo "   Описание: " . ($setData['description'] ?? 'Установлен') . "\n";
} else {
    echo "❌ Ошибка установки webhook\n";
    echo "Ответ: " . $setResult . "\n";
}

echo "\n";

// 4. Проверяем webhook еще раз после установки
echo "4. Финальная проверка webhook...\n";
$finalCheck = file_get_contents($webhookInfoUrl);
$finalData = json_decode($finalCheck, true);

if ($finalData && $finalData['ok']) {
    $info = $finalData['result'];
    if ($info['url']) {
        echo "✅ Webhook работает:\n";
        echo "   URL: " . $info['url'] . "\n";
        echo "   Ожидающих обновлений: " . ($info['pending_update_count'] ?? 0) . "\n";
        
        if (isset($info['last_error_date'])) {
            echo "⚠️  Есть ошибки: " . date('Y-m-d H:i:s', $info['last_error_date']) . "\n";
            echo "   Сообщение: " . ($info['last_error_message'] ?? 'Неизвестная ошибка') . "\n";
        } else {
            echo "✅ Ошибок нет\n";
        }
    } else {
        echo "❌ Webhook все еще не установлен\n";
    }
} else {
    echo "❌ Ошибка финальной проверки\n";
}

echo "\n=== Тестирование завершено ===\n";
echo "Теперь можете отправить /start боту @{$botData['result']['username']} для проверки\n";
