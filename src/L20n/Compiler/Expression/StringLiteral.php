<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\RuntimeException;
use L20n\Compiler\Locals;

/**
 * Class Compiler\Expression\StringLiteral
 * @package L20n
 */
class StringLiteral implements ExpressionInterface
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
     * @throws RuntimeException
     */
    public function __invoke(Locals $locals, \stdClass $ctxdata = null, $prop = null)
    {
        if ($prop !== null) {
            throw new RuntimeException(sprintf('Cannot get property of a string: %s', $prop));
        }
        return [$locals, $this->node['content']];
    }
}
