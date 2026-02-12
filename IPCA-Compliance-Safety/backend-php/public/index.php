<?php
declare(strict_types=1);

// -------------------------------------------------------
//  Simple CORS headers for local development
//  Allows requests from React dev server (http://localhost:5173)
// -------------------------------------------------------
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");

// Handle preflight OPTIONS requests quickly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Load environment variables
 */
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

/**
 * Dependency Injection Container
 */
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/config.php');
$container = $containerBuilder->build();

/**
 * Create Slim App
 */
AppFactory::setContainer($container);
$app = AppFactory::create();

// IMPORTANT: since backend is mounted under /api
$app->setBasePath('/api');

/**
 * Middleware
 */
$app->addBodyParsingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(
    displayErrorDetails: true,   // set false in production
    logErrors: false,
    logErrorDetails: false
);

/**
 * Routes
 */
(require __DIR__ . '/../config/routes.php')($app);

/**
 * Run application
 */
$app->run();
