<?php

declare(strict_types=1);

namespace Neo4j\Neo4jBundle\Collector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * @author Xavier Coureau <xavier@pandawan-technology.com>
 */
final class Neo4jDataCollector extends DataCollector
{
    /**
     * @var QueryLogger
     */
    private QueryLogger $queryLogger;

    public function __construct(QueryLogger $logger)
    {
        $this->queryLogger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception|\Throwable $exception = null): void
    {
        $this->data['time'] = $this->queryLogger->getElapsedTime();
        $this->data['nb_queries'] = count($this->queryLogger);
        $this->data['statements'] = $this->queryLogger->getStatements();
        $this->data['failed_statements'] = array_filter($this->queryLogger->getStatements(), function ($statement) {
            return !isset($statement['success']) || !$statement['success'];
        });
    }

    public function reset(): void
    {
        $this->data = [];
        $this->queryLogger->reset();
    }

    /**
     * @return int
     */
    public function getQueryCount(): int
    {
        return $this->data['nb_queries'];
    }

    /**
     * Return all statements, successful and not successful.
     *
     * @return array
     */
    public function getStatements(): array
    {
        return $this->data['statements'];
    }

    /**
     * Return not successful statements.
     *
     * @return array
     */
    public function getFailedStatements(): array
    {
        return $this->data['failed_statements'];
    }

    /**
     * @return float
     */
    public function getTime(): float
    {
        return $this->data['time'];
    }

    /**
     * @return float
     */
    public function getTimeForQuery(): float
    {
        return $this->data['time'];
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'neo4j';
    }
}
