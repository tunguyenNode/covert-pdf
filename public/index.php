<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Views\PhpRenderer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use mikehaertl\wkhtmlto\Pdf;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Khởi tạo container
$container = new Container();
AppFactory::setContainer($container);

// Khởi tạo ứng dụng Slim
$app = AppFactory::create();
//
//// Cấu hình Eloquent ORM
//$capsule = new Capsule;
//$capsule->addConnection([
//    'driver' => $_ENV['DB_CONNECTION'],
//    'host' => $_ENV['DB_HOST'],
//    'port' => $_ENV['DB_PORT'],
//    'database' => $_ENV['DB_DATABASE'],
//    'username' => $_ENV['DB_USERNAME'],
//    'password' => $_ENV['DB_PASSWORD'],
//    'charset' => 'utf8mb4',
//    'collation' => 'utf8mb4_unicode_ci',
//    'prefix' => '',
//]);
//$capsule->setAsGlobal();
//$capsule->bootEloquent();

$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . '/../templates');
});

// Middleware xử lý lỗi
//$app->addErrorMiddleware(true, true, true);
// Load routes
require __DIR__ . '/../src/routes.php';


/**
 * GET /{file} - Load a static asset.
 *
 * THIS MUST BE PLACED AT THE VERY BOTTOM OF YOUR SLIM APPLICATION, JUST BEFORE
 * $app->run()!!!
 */
$app->get('/{file}', function (Request $request, Response $response, $args) {
    $filePath = __DIR__ . '/assets/css/' . $args['file'];

    if (!file_exists($filePath)) {
        return $response->withStatus(404, 'File Not Found');
    }

    $mimeType = match (pathinfo($filePath, PATHINFO_EXTENSION)) {
        'css' => 'text/css',
        'js' => 'application/javascript',
        default => 'text/html',
    };

    $newResponse = $response->withHeader('Content-Type', $mimeType . '; charset=UTF-8');

    $newResponse->getBody()->write(file_get_contents($filePath));

    return $newResponse;
});

$app->get('/view/pt_resume', function (Request $request, Response $response, array $args) use ($container) {
    $renderer = $this->get('renderer');
    $uri = $request->getUri();
    $baseUrl = $uri->getScheme() . '://' . $uri->getHost() . ($uri->getPort() ? ':' . $uri->getPort() : '') . $request->getAttribute('basePath', '');
    return $renderer->render($response, 'pt_resume.php', [
        'base_url' => $baseUrl
    ]);
});

$app->run();
