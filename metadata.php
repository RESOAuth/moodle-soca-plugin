<?php
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/bootstrap.php');
try {
    $client = (new \auth_soca\local\flow())->client();
    header('Content-Type: application/json');
    header('Cache-Control: public, max-age=300');
    echo json_encode($client->metadata(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(503);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo '{"error":"SOCA configuration unavailable"}';
}
