<?php

namespace L20n\Compiler;

use L20n\Compiler\Exception\CompilerException;
use L20n\Compiler\Expression\IndexExpression;
use L20n\Compiler;
use L20n\Compiler\Expression\LazyExpression;

/**
 * Class Compiler\Entity
 * @package L20n
 */
class Entity implements EntryInterface
{
    /** @var string */
    public $id = '';
    /** @var \stdClass */
    public $env;
    /** @var bool */
    public $local = false;
    /** @var IndexExpression[] */
    public $index = [];
    /** @var \stdClass|null */
    public $attributes = null;
    /** @var string[] */
    public $publicAttributes = [];

    /** @var LazyExpression|string */
    public $value;

    /**
     * @param array $node
     * @param \stdClass $env
     */
    public function __construct(array $node, \stdClass $env)
    {
        $this->id = $node['id']['name'];
        $this->env = $env;
        $this->local = isset($node['local']) ? $node['local'] : false;
        if (isset($node['index'])) {
            /** @var int $limit */
            $limit = count($node['index']);
            for ($i = 0; $i < $limit; $i++) {
                $this->index[] = new IndexExpression($node['index'][$i], $this);
            }
        }
        if (isset($node['attrs'])) {
            $this->attributes = new \stdClass();
            foreach ($node['attrs'] as $attr) {
                $this->attributes->{$attr['key']['name']} = new Attribute($attr, $this);
                if (!$attr['local']) {
                    $this->publicAttributes[] = $attr['key']['name'];
                }
            }
        }
        if (isset($node['value']['type']) && $node['value']['type'] === 'String') {
            $this->value = $node['value']['content'];
        } else {
            $this->value = LazyExpression::factory($node['value'], $this, $this->index);
        }
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
            $locals->__this__ = $this;
            $locals->__env__ = $this->env;
            return Expression::_resolve($this->value, $locals, $ctxdata);
        } catch (CompilerException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param \stdClass $ctxdata
     * @return \stdClass
     */
    public function get(\stdClass $ctxdata = null)
    {
        Compiler::$_references['globals'] = [];
        /** @var \stdClass $entity */
        $entity = new \stdClass();
        $entity->value = $this->getString($ctxdata);
        $entity->attributes = new \stdClass();
        if ($this->publicAttributes) {
            $entity->attributes = new \stdClass();
            foreach ($this->publicAttributes as $attr) {
                $entity->attributes->$attr = $this->attributes->$attr->getString($ctxdata);
            }
        }
        $entity->globals = Compiler::$_references['globals'];
        return $entity;
    }
}
