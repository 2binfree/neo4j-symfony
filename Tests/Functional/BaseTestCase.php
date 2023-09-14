<?php

namespace Neo4j\Neo4jBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class BaseTestCase extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        require_once __DIR__.'/app/AppKernel.php';

        return 'Neo4j\Neo4jBundle\Tests\Functional\app\AppKernel';
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        $class = self::getKernelClass();

        return new $class(
            $options['config'] ?? 'default.yml'
        );
    }
}
