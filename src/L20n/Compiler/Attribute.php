<?php

namespace L20n\Compiler;

use L20n\Compiler\Exception\CompilerException;
use L20n\Compiler\Expression\IndexExpression;
use L20n\Compiler\Expression\LazyExpression;

/**
 * Class Compiler\Attribute
 * @package L20n
 */
class Attribute implements EntryInterface
{
    /** @var string */
    public $key = '';
    /** @var bool */
    public $local = false;
    /** @var IndexExpression[] */
    public $index = [];

    /** @var LazyExpression|string */
    public $value;
    /** @var Entity */
    public $entity;

    /**
     * @param array $node
     * @param Entity $entity
     */
    public function __construct(array $node, Entity $entity)
    {
        $this->key = $node['key']['name'];
        $this->local = isset($node['local']) ? $node['local'] : false;
        if (isset($node['index'])) {
            /** @var int $limit */
            $limit = count($node['index']);
            for ($i = 0; $i < $limit; $i++) {
                $this->index[] = new IndexExpression($node['index'][$i], $this);
            }
        }
        if (isset($node['value']['type']) && $node['value']['type'] === 'String') {
            $this->value = $node['value']['content'];
        } else {
            $this->value = LazyExpression::factory($node['value'], $entity, $this->index);
        }
        $this->entity = $entity;
    }

    /**
     * @param \stdClass $ctxdata
     * @return string
     * @throws CompilerException
     * @throws \Exception
     */
    public function getString(\stdClass $ctxdata = null)
    {
        try {
            /** @var Locals $locals */
            $locals = new Locals();
            $locals->__this__ = $this->entity;
            $locals->__env__ = $this->entity->env;
            return Expression::_resolve($this->value, $locals, $ctxdata);
        } catch (CompilerException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
