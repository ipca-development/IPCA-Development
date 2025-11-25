<?php
declare(strict_types=1);

use IPCA\SafetyCompliance\Application\Compliance\CreateAuditHandler;
use IPCA\SafetyCompliance\Application\Compliance\AddFindingHandler;
use IPCA\SafetyCompliance\Application\Compliance\AddActionHandler;
use IPCA\SafetyCompliance\Application\Compliance\RecordRcaHandler;
use IPCA\SafetyCompliance\Application\Compliance\UpdateFindingHandler;
use IPCA\SafetyCompliance\Application\Compliance\UpdateActionHandler;

use IPCA\SafetyCompliance\Domain\Compliance\AuditRepositoryInterface;
use IPCA\SafetyCompliance\Domain\Compliance\FindingRepositoryInterface;
use IPCA\SafetyCompliance\Domain\Compliance\FindingActionRepositoryInterface;

use IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance\PdoAuditRepository;
use IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance\PdoFindingRepository;
use IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance\PdoFindingActionRepository;
use IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance\PdoRcaRepository;

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
        $port = $_ENV['DB_PORT'] ?? '8889';   // MAMP default MySQL port
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
     *  REPOSITORIES (Domain → Infrastructure)
     * -------------------------------------------------------
     */
    AuditRepositoryInterface::class          => DI\autowire(PdoAuditRepository::class),
    FindingRepositoryInterface::class        => DI\autowire(PdoFindingRepository::class),
    FindingActionRepositoryInterface::class  => DI\autowire(PdoFindingActionRepository::class),

    // PdoRcaRepository is used directly (not via interface yet)
    PdoRcaRepository::class => DI\autowire(PdoRcaRepository::class),

    /**
     * -------------------------------------------------------
     *  APPLICATION LAYER (Use Cases)
     * -------------------------------------------------------
     */
    CreateAuditHandler::class   => DI\autowire(CreateAuditHandler::class),
    AddFindingHandler::class    => DI\autowire(AddFindingHandler::class),
    AddActionHandler::class     => DI\autowire(AddActionHandler::class),
    RecordRcaHandler::class     => DI\autowire(RecordRcaHandler::class),
    UpdateFindingHandler::class => DI\autowire(UpdateFindingHandler::class),
    UpdateActionHandler::class  => DI\autowire(UpdateActionHandler::class),
];