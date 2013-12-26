<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Expression;
use L20n\Compiler\Locals;
use L20n\Compiler\Operator\BinaryOperator;

/**
 * Class Compiler\Expression\BinaryExpression
 * @package L20n
 */
class BinaryExpression implements ExpressionInterface
{
    /** @var BinaryOperator */
    private $operator;
    /** @var ExpressionInterface */
    private $left;
    /** @var ExpressionInterface */
    private $right;

    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null)
    {
        $this->operator = new BinaryOperator($node['operator']['token'], $entry);
        $this->left = Expression::factory($node['left'], $entry);
        $this->right = Expression::factory($node['right'], $entry);
    }

    /**
     * @param Locals $locals
     * @param \stdClass|null $ctxdata
     * @param string|null $prop
     * @return array
     */
    public function __invoke(Locals $locals, \stdClass $ctxdata = null, $prop = null)
    {
        return [$locals, $this->operator->__invoke(
            Expression::_resolve($this->left, $locals, $ctxdata),
            Expression::_resolve($this->right, $locals, $ctxdata)
        )];
    }
}
