<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Expression;
use L20n\Compiler\Locals;

/**
 * Class Compiler\Expression\LazyExpression
 * @package L20n
 */
class LazyExpression implements ExpressionInterface
{
    /** @var array */
    private $node;
    /** @var EntryInterface|null */
    private $entry;
    /** @var array|null */
    private $index;
    /** @var ExpressionInterface */
    private $expr = null;

    /**
     * @param array|null $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     * @return LazyExpression|null
     */
    public static function factory(array $node = null, EntryInterface $entry = null, $index = null)
    {
        if (!$node) {
            return null;
        }
        return new LazyExpression($node, $entry, $index);
    }

    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null)
    {
        $this->node = $node;
        $this->entry = $entry;
        $this->index = $index;
    }

    /**
     * @param Locals $locals
     * @param \stdClass|null $ctxdata
     * @param string|null $prop
     * @return array
     */
    public function __invoke(Locals $locals, \stdClass $ctxdata = null, $prop = null)
    {
        if (!$this->expr) {
            $this->expr = Expression::factory($this->node, $this->entry, $this->index);
        }
        return $this->expr->__invoke($locals, $ctxdata, $prop);
    }
}
