<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\RuntimeException;
use L20n\Compiler\Expression;
use L20n\Compiler\Locals;

/**
 * Class Compiler\Expression\ConditionalExpression
 * @package L20n
 */
class ConditionalExpression implements ExpressionInterface
{
    /** @var ExpressionInterface */
    private $test;
    /** @var ExpressionInterface */
    private $consequent;
    /** @var ExpressionInterface */
    private $alternate;

    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null)
    {
        $this->test = Expression::factory($node['test'], $entry);
        $this->consequent = Expression::factory($node['consequent'], $entry);
        $this->alternate = Expression::factory($node['alternate'], $entry);
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
        /** @var bool $tested */
        $tested = Expression::_resolve($this->test, $locals, $ctxdata);
        if (!is_bool($tested)) {
            throw new RuntimeException('Conditional expressions must test a boolean');
        }
        if ($tested === true) {
            return $this->consequent->__invoke($locals, $ctxdata);
        }
        return $this->alternate->__invoke($locals, $ctxdata);
    }
}
