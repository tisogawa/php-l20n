<?php

namespace L20n\Compiler\Operator;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\CompilationException;
use L20n\Compiler\Exception\RuntimeException;

/**
 * Class Compiler\Operator\UnaryOperator
 * @package L20n
 */
class UnaryOperator
{
    /** @var string */
    private $token = '';

    /**
     * @param string $token
     * @param EntryInterface $entry
     * @throws CompilationException
     */
    public function __construct($token, EntryInterface $entry)
    {
        if ($token !== '-' && $token !== '+' && $token !== '!') {
            throw new CompilationException(sprintf('Unknown token: %s', $token));
        }
        $this->token = $token;
    }

    /**
     * @param $argument
     * @return bool|float|int
     * @throws RuntimeException
     */
    public function __invoke($argument)
    {
        if ($this->token === '-') {
            return $this->negationOperator($argument);
        }
        if ($this->token === '+') {
            return $this->additionOperator($argument);
        }
        if ($this->token === '!') {
            return $this->notOperator($argument);
        }
        throw new RuntimeException(sprintf('Unknown token: %s', $this->token));
    }

    /**
     * @param $argument
     * @return int|float
     * @throws RuntimeException
     */
    private function negationOperator($argument)
    {
        /** @var string $type_of_argument */
        $type_of_argument = gettype($argument);
        if ($type_of_argument !== 'integer' && $type_of_argument !== 'double') {
            throw new RuntimeException('The unary - operator takes a number');
        }
        return -$argument;
    }

    /**
     * @param $argument
     * @return int|float
     * @throws RuntimeException
     */
    private function additionOperator($argument)
    {
        /** @var string $type_of_argument */
        $type_of_argument = gettype($argument);
        if ($type_of_argument !== 'integer' && $type_of_argument !== 'double') {
            throw new RuntimeException('The unary + operator takes a number');
        }
        return +$argument;
    }

    /**
     * @param $argument
     * @return bool
     * @throws RuntimeException
     */
    private function notOperator($argument)
    {
        if (gettype($argument) !== 'boolean') {
            throw new RuntimeException('The ! operator takes a boolean');
        }
        return !$argument;
    }
}
