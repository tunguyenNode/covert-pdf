<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;
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

// Cấu hình Eloquent ORM
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => $_ENV['DB_CONNECTION'],
    'host' => $_ENV['DB_HOST'],
    'port' => $_ENV['DB_PORT'],
    'database' => $_ENV['DB_DATABASE'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Cấu hình Twig
$container->set('twig', function () {
    $loader = new FilesystemLoader(__DIR__ . '/templates');
    return new Environment($loader);
});

// Middleware xử lý lỗi
$app->addErrorMiddleware(true, true, true);

// Load routes
require __DIR__ . '/../src/routes.php';

$app->run();
