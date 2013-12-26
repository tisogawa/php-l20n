<?php

namespace L20n\Compiler;

use L20n\Compiler\Exception\RuntimeException;
use L20n\Compiler\Expression\LazyExpression;

/**
 * Class Compiler\Macro
 * @package L20n
 */
class Macro implements EntryInterface
{
    /** @var string */
    public $id = '';
    /** @var \stdClass */
    public $env;
    /** @var bool */
    public $local = false;
    /** @var LazyExpression */
    public $expression;
    /** @var array */
    public $args = [];

    /**
     * @param array $node
     * @param \stdClass $env
     */
    public function __construct(array $node, \stdClass $env)
    {
        $this->id = $node['id']['name'];
        $this->env = $env;
        $this->local = isset($node['local']) ? $node['local'] : false;
        $this->expression = LazyExpression::factory($node['expression'], $this);
        $this->args = $node['args'];
    }


    /**
     * @param array $args
     * @param \stdClass $ctxdata
     * @return array
     * @throws RuntimeException
     */
    public function _call(array $args, \stdClass $ctxdata = null)
    {
        /** @var Locals $locals */
        $locals = new Locals();
        $locals->__this__ = $this;
        $locals->__env__ = $this->env;
        if (count($this->args) !== count($args)) {
            throw new RuntimeException(sprintf(
                '%s() takes exactly %d argument(s) (%d given)', $this->id, count($this->args), count($args)
            ));
        }

        /** @var int $limit */
        $limit = count($this->args);
        for ($i = 0; $i < $limit; $i++) {
            $locals->{$this->args[$i]['id']['name']} = $args[$i];
        }
        /** @var array $final */
        $final = $this->expression->__invoke($locals, $ctxdata);
        /** @var Locals $locals */
        $locals = $final[0];
        /** @var mixed $final */
        $final = $final[1];
        return [$locals, Expression::_resolve($final, $locals, $ctxdata)];
    }
}
