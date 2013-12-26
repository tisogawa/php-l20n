<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Expression;
use L20n\Compiler\Locals;
use L20n\Compiler\Operator\UnaryOperator;

/**
 * Class Compiler\Expression\UnaryExpression
 * @package L20n
 */
class UnaryExpression implements ExpressionInterface
{
    /** @var UnaryOperator */
    private $operator;
    /** @var ExpressionInterface */
    private $argument;

    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null)
    {
        $this->operator = new UnaryOperator($node['operator']['token'], $entry);
        $this->argument = Expression::factory($node['argument'], $entry);
    }

    /**
     * @param Locals $locals
     * @param \stdClass|null $ctxdata
     * @param string|null $prop
     * @return array
     */
    public function __invoke(Locals $locals, \stdClass $ctxdata = null, $prop = null)
    {
        return [
            $locals,
            $this->operator->__invoke(
                Expression::_resolve($this->argument, $locals, $ctxdata)
            )
        ];
    }
}
