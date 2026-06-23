<?php
declare(strict_types=1);

/**
 * Front controller for the Weighted Random API.
 *
 * Works both behind a real web server (php-fpm + nginx/apache) and with PHP's
 * built-in server used as a router script:
 *
 *     php -S 0.0.0.0:8080 -t public public/index.php
 */

use mschandr\WeightedRandom\Api\Application;

require __DIR__ . '/../vendor/autoload.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$body = [];
if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid JSON: ' . $e->getMessage()], JSON_PRETTY_PRINT);
            return;
        }
        if (!is_array($decoded)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Request body must be a JSON object.'], JSON_PRETTY_PRINT);
            return;
        }
        $body = $decoded;
    }
}

[$status, $payload] = (new Application())->handle($method, $path, $body);

http_response_code($status);
header('Content-Type: application/json');
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

