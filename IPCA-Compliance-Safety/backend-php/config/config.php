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

return [

    /**
     * -------------------------------------------------------
     *  PDO MySQL Connection
     * -------------------------------------------------------
     */
    PDO::class => function (ContainerInterface $c): PDO {

        // Read App Platform environment variables first
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';

        $db = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE')
            ?: ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'IPCA');

        $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT')
            ?: '3306';

        $user = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME')
            ?: ($_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root');

        $pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD')
            ?: ($_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '');

        $dsn = "mysql:host={$host};dbname={$db};port={$port};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        // DigitalOcean Managed MySQL SSL handling
        $sslmode = strtolower($_ENV['DB_SSLMODE'] ?? getenv('DB_SSLMODE') ?: '');

        if ($sslmode === 'required') {
            // DO requires TLS but does not require client cert authentication
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        return new PDO($dsn, $user, $pass, $options);
    },

    /**
     * -------------------------------------------------------
     *  REPOSITORIES
     * -------------------------------------------------------
     */
    AuditRepositoryInterface::class         => DI\autowire(PdoAuditRepository::class),
    FindingRepositoryInterface::class       => DI\autowire(PdoFindingRepository::class),
    FindingActionRepositoryInterface::class => DI\autowire(PdoFindingActionRepository::class),

    PdoRcaRepository::class => DI\autowire(PdoRcaRepository::class),
    PdoFindingRcaRepository::class => DI\autowire(PdoFindingRcaRepository::class),

    /**
     * -------------------------------------------------------
     *  AI SERVICES
     * -------------------------------------------------------
     */
    OpenAiClient::class => function () {
        $key = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '';

        if ($key === '') {
            throw new RuntimeException('OPENAI_API_KEY missing in environment variables');
        }

        $model = $_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL') ?: 'gpt-5';

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
