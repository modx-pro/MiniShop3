<?php

if (empty($_REQUEST['action']) && empty($_REQUEST['ms3_action'])) {
    http_response_code(403);
    echo "Ошибка: отсутствуют необходимые параметры.";
} else {
    // Сообщение об успешной отправке
    echo "Данные отправлены!";
}

if (!empty($_REQUEST['action'])) {
    $_REQUEST['ms3_action'] = $_REQUEST['action'];
}

/** @noinspection PhpIncludeInspection */
require dirname(__FILE__, 4) . '/index.php';
