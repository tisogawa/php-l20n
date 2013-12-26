<?php

namespace L20n\Compiler\Operator;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\CompilationException;
use L20n\Compiler\Exception\RuntimeException;

/**
 * Class Compiler\Operator\LogicalOperator
 * @package L20n
 */
class LogicalOperator
{
    /** @var string */
    private $token = '';

    /**
     * @param $token
     * @param EntryInterface $entry
     * @throws CompilationException
     */
    public function __construct($token, EntryInterface $entry)
    {
        if ($token !== '&&' && $token !== '||') {
            throw new CompilationException(sprintf('Unknown token: %s', $token));
        }
        $this->token = $token;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return bool
     * @throws RuntimeException
     */
    public function __invoke($left, $right)
    {
        if ($this->token === '&&') {
            return $this->andOperator($left, $right);
        }
        if ($this->token === '||') {
            return $this->orOperator($left, $right);
        }
        throw new RuntimeException(sprintf('Unknown token: %s', $this->token));
    }

    /**
     * @param $left
     * @param $right
     * @return bool
     * @throws RuntimeException
     */
    private function andOperator($left, $right)
    {
        if (gettype($left) !== 'boolean' || gettype($right) !== 'boolean') {
            throw new RuntimeException('The && operator takes two booleans');
        }
        return $left && $right;
    }

    /**
     * @param $left
     * @param $right
     * @return bool
     * @throws RuntimeException
     */
    private function orOperator($left, $right)
    {
        if (gettype($left) !== 'boolean' || gettype($right) !== 'boolean') {
            throw new RuntimeException('The || operator takes two booleans');
        }
        return $left || $right;
    }
}
