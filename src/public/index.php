<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Action\ContactAction;
use App\Action\ErrorAction;
use App\Config;
use App\Logger\LoggerFactory;
use App\Service\Mailer\Mailer;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

// Config
$config = Config::fromEnv();
$container->set(Config::class, fn () => $config);

// Logger
$logger = LoggerFactory::create($config);
$container->set(LoggerInterface::class, fn () => $logger);

$logger->debug('config', [(array) $config]);

// Twig
$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$container->set(Twig::class, fn () => $twig);
$app->add(TwigMiddleware::createFromContainer($app, Twig::class));

// Mailer
$container->set(Mailer::class, fn () => new Mailer($config, $logger, $twig));

// CSRF
$app->add(function (Request $request, $handler): Response {
    $csrf = $_COOKIE['csrf_token'] ?? bin2hex(random_bytes(32));

    $response = $handler->handle(
        $request->withAttribute('csrf_token', $csrf)
    );

    return $response->withHeader(
        'Set-Cookie',
        'csrf_token=' . $csrf . '; Path=/; HttpOnly; SameSite=Lax'
    );
});

// Error Handling
$errorMiddleware = $app->addErrorMiddleware(
    $config->isDebug,
    true,
    true
);
$errorMiddleware->setDefaultErrorHandler(
    $container->get(ErrorAction::class)
);

$app->get('/contact[/]', ContactAction::class . ':input');
$app->post('/contact[/]', ContactAction::class . ':input');
$app->get('/contact/confirm[/]', ContactAction::class . ':redirectInput');
$app->post('/contact/confirm[/]', ContactAction::class . ':confirm');
$app->post('/contact/complete[/]', ContactAction::class . ':execute');
$app->get('/contact/complete[/]', ContactAction::class . ':complete');

$app->run();
