<?php
declare(strict_types=1);

use IPCA\SafetyCompliance\Application\Compliance\CreateAuditHandler;
use IPCA\SafetyCompliance\Application\Compliance\AddFindingHandler;
use IPCA\SafetyCompliance\Application\Compliance\AddActionHandler;
use IPCA\SafetyCompliance\Application\Compliance\RecordRcaHandler;

use IPCA\SafetyCompliance\Domain\Compliance\AuditRepositoryInterface;
use IPCA\SafetyCompliance\Domain\Compliance\FindingRepositoryInterface;
use IPCA\SafetyCompliance\Domain\Compliance\FindingActionRepositoryInterface;

use IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance\PdoAuditRepository;
use IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance\PdoFindingRepository;
use IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance\PdoFindingActionRepository;
use IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance\PdoRcaRepository;
use IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance\PdoFindingRcaRepository;

use IPCA\SafetyCompliance\Infrastructure\Ai\OpenAiClient;
use IPCA\SafetyCompliance\Infrastructure\Ai\RcaAiService;

use Psr\Container\ContainerInterface;
use PDO;

return [

    /**
     * -------------------------------------------------------
     *  PDO MySQL Connection
     * -------------------------------------------------------
     */
    PDO::class => function (ContainerInterface $c): PDO {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $db   = $_ENV['DB_NAME'] ?? 'IPCA';
        $port = $_ENV['DB_PORT'] ?? '8889';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? 'root';

        $dsn = "mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;
    },

    /**
     * -------------------------------------------------------
     *  REPOSITORIES
     * -------------------------------------------------------
     */
    AuditRepositoryInterface::class         => DI\autowire(PdoAuditRepository::class),
    FindingRepositoryInterface::class       => DI\autowire(PdoFindingRepository::class),
    FindingActionRepositoryInterface::class => DI\autowire(PdoFindingActionRepository::class),

    // Existing RCA repository (if you still use it)
    PdoRcaRepository::class => DI\autowire(PdoRcaRepository::class),

    // New: Finding RCA storage repository (stores steps_json per finding)
    PdoFindingRcaRepository::class => DI\autowire(PdoFindingRcaRepository::class),

    /**
     * -------------------------------------------------------
     *  AI SERVICES
     * -------------------------------------------------------
     */
    OpenAiClient::class => function () {
        $key = $_ENV['OPENAI_API_KEY'] ?? '';
        if ($key === '') {
            throw new RuntimeException('OPENAI_API_KEY missing in backend-php/.env');
        }
        $model = $_ENV['OPENAI_MODEL'] ?? 'gpt-5';
        return new OpenAiClient($key, $model);
    },

    RcaAiService::class => DI\autowire(RcaAiService::class),

    /**
     * -------------------------------------------------------
     *  APPLICATION LAYER (Use Cases)
     * -------------------------------------------------------
     */
    CreateAuditHandler::class => DI\autowire(CreateAuditHandler::class),
    AddFindingHandler::class  => DI\autowire(AddFindingHandler::class),
    AddActionHandler::class   => DI\autowire(AddActionHandler::class),
    RecordRcaHandler::class   => DI\autowire(RecordRcaHandler::class),
];