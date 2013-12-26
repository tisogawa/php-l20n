<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\RuntimeException;
use L20n\Compiler\Locals;

/**
 * Class Compiler\Expression\VariableExpression
 * @package L20n
 */
class VariableExpression implements ExpressionInterface
{
    /** @var string */
    private $name = '';

    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null)
    {
        $this->name = $node['id']['name'];
    }

    /**
     * @param Locals $locals
     * @param \stdClass|null $ctxdata
     * @param string|null $prop
     * @return array
     * @throws RuntimeException
     */
    public function __invoke(Locals $locals, \stdClass $ctxdata = null, $prop = null)
    {
        if (property_exists($locals, $this->name)) {
            return $locals->{$this->name};
        }
        if (!$ctxdata || !property_exists($ctxdata, $this->name)) {
            throw new RuntimeException(sprintf('Reference to an unknown variable: %s', $this->name));
        }
        return [$locals, $ctxdata->{$this->name}];
    }
}
