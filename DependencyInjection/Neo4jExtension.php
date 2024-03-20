<?php

declare(strict_types=1);

namespace Neo4j\Neo4jBundle\DependencyInjection;

use Exception;
use GraphAware\Bolt\Driver as BoltDriver;
use GraphAware\Neo4j\Client\ClientInterface;
use GraphAware\Neo4j\Client\Connection\Connection;
use GraphAware\Neo4j\Client\HttpDriver\Driver as HttpDriver;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Neo4jExtension extends Extension
{
    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $this->handleConnections($config, $container);
        $clientServiceIds = $this->handleClients($config, $container);

        // add aliases for the default services
        $container->setAlias('neo4j.connection', 'neo4j.connection.default');
        $container->setAlias(Connection::class, 'neo4j.connection.default');
        $container->setAlias('neo4j.client', 'neo4j.client.default');
        $container->setAlias(ClientInterface::class, 'neo4j.client.default');

        // Configure toolbar
        if ($this->isConfigEnabled($container, $config['profiling'])) {
            $loader->load('data-collector.xml');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration($container->getParameter('kernel.debug'));
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'neo4j';
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     *
     * @return array with service ids
     */
    private function handleClients(array &$config, ContainerBuilder $container): array
    {
        if (empty($config['clients'])) {
            // Add default entity manager if none set.
            $config['clients']['default'] = ['connections' => ['default']];
        }

        $serviceIds = [];
        foreach ($config['clients'] as $name => $data) {
            $connections = [];
            $serviceIds[$name] = $serviceId = sprintf('neo4j.client.%s', $name);
            foreach ($data['connections'] as $connectionName) {
                if (empty($config['connections'][$connectionName])) {
                    throw new InvalidConfigurationException(sprintf(
                        'Client "%s" is configured to use connection named "%s" but there is no such connection',
                        $name,
                        $connectionName
                    ));
                }
                $connections[] = $connectionName;
            }
            if (empty($connections)) {
                $connections[] = 'default';
            }

            $definition = class_exists(ChildDefinition::class)
                ? new ChildDefinition('neo4j.client.abstract')
                : new Definition('neo4j.client.abstract');

            $container
                ->setDefinition($serviceId, $definition)
                ->setArguments([$connections]);
        }

        return $serviceIds;
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     *
     * @return void with service ids
     */
    private function handleConnections(array &$config, ContainerBuilder $container): void
    {
        $serviceIds = [];
        $firstName = null;
        foreach ($config['connections'] as $name => $data) {
            if (null === $firstName || 'default' === $name) {
                $firstName = $name;
            }
            $def = new Definition(Connection::class);
            $def->addArgument($name);
            $def->addArgument($this->getUrl($data));
            $serviceIds[$name] = $serviceId = 'neo4j.connection.'.$name;
            $container->setDefinition($serviceId, $def);
        }

        // Make sure we got a 'default'
        if ('default' !== $firstName) {
            $config['connections']['default'] = $config['connections'][$firstName];
        }

        // Add connections to connection manager
        $connectionManager = $container->getDefinition('neo4j.connection_manager');
        foreach ($serviceIds as $name => $serviceId) {
            $connectionManager->addMethodCall('registerExistingConnection', [$name, new Reference($serviceId)]);
        }
        $connectionManager->addMethodCall('setMaster', [$firstName]);

    }

    /**
     * Get URL form config.
     *
     * @param array $config
     *
     * @return string
     */
    private function getUrl(array $config): string
    {
        if (null !== $config['dsn']) {
            return $config['dsn'];
        }

        return sprintf(
            '%s://%s:%s@%s:%d',
            $config['scheme'],
            $config['username'],
            $config['password'],
            $config['host'],
            $this->getPort($config)
        );
    }

    /**
     * Return the correct default port if not manually set.
     *
     * @param array $config
     *
     * @return int
     */
    private function getPort(array $config): int
    {
        if (isset($config['port'])) {
            return $config['port'];
        }

        return 'http' == $config['scheme'] ? HttpDriver::DEFAULT_HTTP_PORT : BoltDriver::DEFAULT_TCP_PORT;
    }
}
