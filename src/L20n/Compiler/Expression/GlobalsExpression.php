<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\RuntimeException;
use L20n\Compiler;
use L20n\Compiler\Locals;

/**
 * Class Compiler\Expression\GlobalsExpression
 * @package L20n
 */
class GlobalsExpression implements ExpressionInterface
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
        $this->name = $node['id']['name'];
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

        if (!Compiler::$_globals) {
            throw new RuntimeException(sprintf('No globals set (tried @%s)', $this->name));
        }
        if (!isset(Compiler::$_globals[$this->name])) {
            throw new RuntimeException(sprintf('Reference to an unknown global: %s', $this->name));
        }
        /** @var mixed $value */
        $value = null;
        try {
            $value = Compiler::$_globals[$this->name]->get();
        } catch (\Exception $e) {
            throw new RuntimeException(sprintf('Cannot evaluate global %s', $this->name));
        }
        Compiler::$_references['globals'][$this->name] = true;
        return [$locals, $value];
    }
}
