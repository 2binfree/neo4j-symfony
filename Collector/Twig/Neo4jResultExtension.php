<?php

declare(strict_types=1);

namespace Neo4j\Neo4jBundle\Collector\Twig;

use GraphAware\Neo4j\Client\Formatter\Type\Node;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Neo4jResultExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('neo4jResult', [$this, 'getType']),
        ];
    }

    /**
     * @param mixed $object
     *
     * @return string
     */
    public function getType(mixed $object): string
    {
        return $this->doGetType($object, true);
    }

    public function getName(): string
    {
        return 'neo4j.result';
    }

    /**
     * @param mixed $object
     * @param bool  $recursive
     *
     * @return string
     */
    private function doGetType(mixed $object, bool $recursive): string
    {
        if ($object instanceof Node) {
            return sprintf('%s: %s', $object->identity(), implode(', ', $object->labels()));
        } elseif (is_array($object) && $recursive) {
            if (empty($object)) {
                return 'Empty array';
            }
            $ret = [];
            foreach ($object as $o) {
                $ret[] = $this->doGetType($o, false);
            }

            return sprintf('[%s]', implode(', ', $ret));
        }

        return is_object($object) ? get_class($object) : gettype($object);
    }
}
