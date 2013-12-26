<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\Attribute;
use L20n\Compiler\Entity;
use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\RuntimeException;
use L20n\Compiler\Expression;
use L20n\Compiler\Locals;
use L20n\Compiler\Macro;

/**
 * Class Compiler\Expression\PropertyExpression
 * @package L20n
 */
class PropertyExpression implements ExpressionInterface
{
    /** @var ExpressionInterface */
    private $expression;
    /** @var ExpressionInterface */
    private $property;

    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null)
    {
        $this->expression = Expression::factory($node['expression'], $entry);
        $this->property = $node['computed'] ? Expression::factory($node['property'], $entry) : $node['property']['name'];
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
        $prop = Expression::_resolve($this->property, $locals, $ctxdata);
        if (!is_string($prop)) {
            throw new RuntimeException(sprintf('Property name must evaluate to a string: %s', $prop));
        }

        /** @var array $parent */
        $parent = $this->expression->__invoke($locals, $ctxdata);
        /** @var Locals $locals */
        $locals = $parent[0];
        /** @var EntryInterface|ExpressionInterface|\stdClass|array|null $parent */
        $parent = $parent[1];
        if (($parent instanceof Entity || $parent instanceof Attribute) && $parent->value !== null) {
            if (!is_callable($parent->value)) {
                throw new RuntimeException(sprintf('Cannot get property of a %s: %s', gettype($parent->value), $prop));
            }
            return $parent->value->__invoke($locals, $ctxdata, $prop);
        }
        if ($parent instanceof ExpressionInterface) {
            return $parent($locals, $ctxdata, $prop);
        }
        if ($parent instanceof Macro) {
            throw new RuntimeException(sprintf('Cannot get property of a macro: %s', $prop));
        }
        if ($parent === null) {
            throw new RuntimeException(sprintf('Cannot get property of a null: %s', $prop));
        }
        if (is_array($parent)) {
            throw new RuntimeException(sprintf('Cannot get property of an array: %s', $prop));
        }
        if ($parent instanceof \stdClass) {
            if (!property_exists($parent, $prop)) {
                throw new RuntimeException(sprintf('%s is not defined on the object.', $prop));
            }
            return [$locals, $parent->$prop];
        }
        throw new RuntimeException(sprintf('Cannot get property of a %s: %s', gettype($parent), $prop));
    }
}
