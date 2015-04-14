<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\CompilerException;
use L20n\Compiler\Exception\IndexException;
use L20n\Compiler\Exception\RuntimeException;
use L20n\Compiler\Expression;
use L20n\Compiler\Locals;

/**
 * Class Compiler\Expression\IndexExpression
 * @package L20n
 */
class IndexExpression implements ExpressionInterface
{
    /** @var bool */
    private $dirty = false;
    /** @var ExpressionInterface */
    private $expression;

    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null)
    {
        $this->dirty = false;
        $this->expression = Expression::factory($node, $entry);
    }

    /**
     * @param Locals $locals
     * @param \stdClass|null $ctxdata
     * @param string|null $prop
     * @return array
     * @throws IndexException
     * @throws RuntimeException
     * @throws \Exception
     */
    public function __invoke(Locals $locals, \stdClass $ctxdata = null, $prop = null)
    {
        if ($this->dirty) {
            throw new RuntimeException('Cyclic reference detected');
        }
        $this->dirty = true;
        /** @var mixed $retval */
        $retval = null;
        try {
            $retval = Expression::_resolve($this->expression, $locals, $ctxdata);
            $this->dirty = false;
        } catch (IndexException $e) {
            $this->dirty = false;
            throw $e;
        } catch (CompilerException $e) {
            $this->dirty = false;
            throw new IndexException($e->getMessage());
        } catch (\Exception $e) {
            $this->dirty = false;
            throw $e;
        }
        return [$locals, $retval];
    }
}
