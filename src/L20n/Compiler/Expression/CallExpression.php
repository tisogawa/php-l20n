<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\RuntimeException;
use L20n\Compiler\Expression;
use L20n\Compiler\Locals;
use L20n\Compiler\Macro;

/**
 * Class Compiler\Expression\CallExpression
 * @package L20n
 */
class CallExpression implements ExpressionInterface
{
    /** @var ExpressionInterface */
    private $callee;
    /** @var array */
    private $args = [];

    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null)
    {
        $this->callee = Expression::factory($node['callee'], $entry);
        $this->args = [];
        /** @var int $limit */
        $limit = count($node['arguments']);
        for ($i = 0; $i < $limit; $i++) {
            $this->args[] = Expression::factory($node['arguments'][$i], $entry);
        }
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
        /** @var array $evaluated_args */
        $evaluated_args = [];
        /** @var int $limit */
        $limit = count($this->args);
        for ($i = 0; $i < $limit; $i++) {
            $evaluated_args[] = $this->args[$i]($locals, $ctxdata);
        }
        /** @var array $macro */
        $macro = $this->callee->__invoke($locals, $ctxdata);
        /** @var Locals $locals */
        $locals = $macro[0];
        /** @var Macro $macro */
        $macro = $macro[1];
        if (!$macro instanceof Macro) {
            throw new RuntimeException('Expected a macro, got a non-callable.');
        }
        return $macro->_call($evaluated_args, $ctxdata);
    }
}
