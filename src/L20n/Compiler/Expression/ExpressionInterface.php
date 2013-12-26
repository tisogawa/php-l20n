<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Locals;

/**
 * Interface Compiler\Expression\ExpressionInterface
 * @package L20n
 */
interface ExpressionInterface
{
    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null);

    /**
     * @param Locals $locals
     * @param \stdClass|null $ctxdata
     * @param string|null $prop
     * @return array
     */
    public function __invoke(Locals $locals, \stdClass $ctxdata = null, $prop = null);
}
