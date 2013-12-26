<?php

namespace L20n\Compiler;

use L20n\Compiler\Exception\CompilationException;
use L20n\Compiler\Exception\RuntimeException;
use L20n\Compiler\Expression\ExpressionInterface;

/**
 * Class Compiler\Expression
 * @package L20n
 */
class Expression
{
    /** @var array */
    private static $EXPRESSION_TYPES = [
        // Primary expressions.
        'Identifier' => 'Identifier',
        'ThisExpression' => 'ThisExpression',
        'VariableExpression' => 'VariableExpression',
        'GlobalsExpression' => 'GlobalsExpression',

        // Value expressions.
        'Number' => 'NumberLiteral',
        'String' => 'StringLiteral',
        'Hash' => 'HashLiteral',
        // 'HashItem' => 'Expression',
        'ComplexString' => 'ComplexString',

        // Logical expressions.
        'UnaryExpression' => 'UnaryExpression',
        'BinaryExpression' => 'BinaryExpression',
        'LogicalExpression' => 'LogicalExpression',
        'ConditionalExpression' => 'ConditionalExpression',

        // Member expressions.
        'CallExpression' => 'CallExpression',
        'PropertyExpression' => 'PropertyExpression',
        'AttributeExpression' => 'AttributeExpression',
        'ParenthesisExpression' => 'ParenthesisExpression'
    ];

    /**
     * @param array|null $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     * @return ExpressionInterface
     * @throws CompilationException
     */
    public static function factory(array $node = null, EntryInterface $entry = null, $index = null)
    {
        if (!$node) {
            return null;
        }
        if (!isset(static::$EXPRESSION_TYPES[$node['type']])) {
            throw new CompilationException(sprintf('Unknown expression type: %s', $node['type']));
        }
        if ($node['type'] === 'ParenthesisExpression') {
            return static::factory($node['expression'], $entry);
        }
        /** @var string $class */
        $class = sprintf('%s\\Expression\\%s', __NAMESPACE__, static::$EXPRESSION_TYPES[$node['type']]);
        return new $class($node, $entry, $index);
    }

    /**
     * @param mixed $expr
     * @param Locals $locals
     * @param \stdClass $ctxdata
     * @return mixed
     * @throws RuntimeException
     */
    public static function _resolve($expr, Locals $locals = null, \stdClass $ctxdata = null)
    {
        /** @var string $type_of_expression */
        $type_of_expression = gettype($expr);
        if ($type_of_expression === 'string' ||
            $type_of_expression === 'boolean' ||
            $type_of_expression === 'integer' ||
            $type_of_expression === 'double' ||
            $type_of_expression === 'NULL'
        ) {
            return $expr;
        }

        if ($expr instanceof Entity || $expr instanceof Attribute) {
            return static::_resolve($expr->value, $locals, $ctxdata);
        }

        if ($expr instanceof ExpressionInterface) {
            /** @var array $current */
            $current = $expr($locals, $ctxdata);
            /** @var Locals $locals */
            $locals = $current[0];
            /** @var mixed $current */
            $current = $current[1];
            return static::_resolve($current, $locals, $ctxdata);
        }

        if ($expr instanceof Macro) {
            throw new RuntimeException(sprintf('Uncalled macro: %s', $expr->id));
        }

        throw new RuntimeException(sprintf('Cannot resolve ctxdata or global of type %s', $type_of_expression));
    }
}
