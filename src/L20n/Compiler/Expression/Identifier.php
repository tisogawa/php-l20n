<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\RuntimeException;
use L20n\Compiler\Locals;

/**
 * Class Compiler\Expression\Identifier
 * @package L20n
 */
class Identifier implements ExpressionInterface
{
    /** @var string */
    private $name = '';

    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null)
    {
        $this->name = $node['name'];
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
        if (!property_exists($locals->__env__, $this->name)) {
            throw new RuntimeException(sprintf('Reference to an unknown entry: %s', $this->name));
        }
        /** @var Locals $locals2 */
        $locals2 = new Locals();
        $locals2->__this__ = $locals->__env__->{$this->name};
        $locals2->__env__ = $locals->__env__;
        $locals = $locals2;
        return [$locals, $locals->__this__];
    }
}
