<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Locals;

/**
 * Class Compiler\Expression\NumberLiteral
 * @package L20n
 */
class NumberLiteral implements ExpressionInterface
{
    /** @var array */
    private $node = [];

    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null)
    {
        $this->node = $node;
    }

    /**
     * @param Locals $locals
     * @param \stdClass|null $ctxdata
     * @param string|null $prop
     * @return array
     */
    public function __invoke(Locals $locals, \stdClass $ctxdata = null, $prop = null)
    {
        return [$locals, $this->node['value']];
    }
}
