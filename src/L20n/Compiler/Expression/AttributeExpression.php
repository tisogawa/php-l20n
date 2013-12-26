<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\Entity;
use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\RuntimeException;
use L20n\Compiler\Expression;
use L20n\Compiler\Locals;

/**
 * Class Compiler\Expression\AttributeExpression
 * @package L20n
 */
class AttributeExpression implements ExpressionInterface
{
    /** @var ExpressionInterface */
    private $expression;
    /** @var ExpressionInterface|string */
    private $attribute;

    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null)
    {
        $this->expression = Expression::factory($node['expression'], $entry);
        $this->attribute = $node['computed'] ?
            Expression::factory($node['attribute'], $entry) :
            $node['attribute']['name'];
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
        /** @var string $attr */
        $attr = Expression::_resolve($this->attribute, $locals, $ctxdata);
        /** @var array $entity */
        $entity = $this->expression->__invoke($locals, $ctxdata);
        /** @var Locals $locals */
        $locals = $entity[0];
        /** @var Entity $entity */
        $entity = $entity[1];
        if (!$entity instanceof Entity) {
            throw new RuntimeException(sprintf('Cannot get attribute of a non-entity: %s', $attr));
        }
        if (!property_exists($entity->attributes, $attr)) {
            throw new RuntimeException(sprintf('%s has no attribute %s', $entity->id, $attr));
        }
        return [$locals, $entity->attributes->$attr];
    }
}
