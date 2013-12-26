<?php

namespace L20n\Compiler\Operator;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\CompilationException;
use L20n\Compiler\Exception\RuntimeException;

/**
 * Class Compiler\Operator\BinaryOperator
 * @package L20n
 */
class BinaryOperator
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
        if ($token !== '==' && $token !== '!=' &&
            $token !== '<' && $token !== '<=' && $token !== '>' && $token !== '>=' &&
            $token !== '+' && $token !== '-' && $token !== '*' && $token !== '/' && $token !== '%'
        ) {
            throw new CompilationException(sprintf('Unknown token: %s', $token));
        }
        $this->token = $token;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return bool|float|int|string
     * @throws RuntimeException
     */
    public function __invoke($left, $right)
    {
        if ($this->token === '==') {
            return $this->equalOperator($left, $right);
        }
        if ($this->token === '!=') {
            return $this->notEqualOperator($left, $right);
        }
        if ($this->token === '<') {
            return $this->lessThanOperator($left, $right);
        }
        if ($this->token === '<=') {
            return $this->lessThanOrEqualToOperator($left, $right);
        }
        if ($this->token === '>') {
            return $this->greaterThanOperator($left, $right);
        }
        if ($this->token === '>=') {
            return $this->greaterThanOrEqualToOperator($left, $right);
        }
        if ($this->token === '+') {
            return $this->additionOperator($left, $right);
        }
        if ($this->token === '-') {
            return $this->subtractionOperator($left, $right);
        }
        if ($this->token === '*') {
            return $this->multiplicationOperator($left, $right);
        }
        if ($this->token === '/') {
            return $this->divisionOperator($left, $right);
        }
        if ($this->token === '%') {
            return $this->modulusOperator($left, $right);
        }
        throw new RuntimeException(sprintf('Unknown token: %s', $this->token));
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return bool
     * @throws RuntimeException
     */
    public function equalOperator($left, $right)
    {
        /** @var string $type_of_left */
        $type_of_left = gettype($left);
        /** @var string $type_of_right */
        $type_of_right = gettype($right);
        if ((($type_of_left !== 'integer' && $type_of_left !== 'double') ||
                ($type_of_right !== 'integer' && $type_of_right !== 'double')) &&
            ($type_of_left !== 'string' || $type_of_right !== 'string')
        ) {
            throw new RuntimeException('The == operator takes two numbers or two strings');
        }
        return $left == $right;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return bool
     * @throws RuntimeException
     */
    private function notEqualOperator($left, $right)
    {
        /** @var string $type_of_left */
        $type_of_left = gettype($left);
        /** @var string $type_of_right */
        $type_of_right = gettype($right);
        if ((($type_of_left !== 'integer' && $type_of_left !== 'double') ||
                ($type_of_right !== 'integer' && $type_of_right !== 'double')) &&
            ($type_of_left !== 'string' || $type_of_right !== 'string')
        ) {
            throw new RuntimeException('The != operator takes two numbers or two strings');
        }
        return $left !== $right;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return bool
     * @throws RuntimeException
     */
    private function lessThanOperator($left, $right)
    {
        /** @var string $type_of_left */
        $type_of_left = gettype($left);
        /** @var string $type_of_right */
        $type_of_right = gettype($right);
        if (($type_of_left !== 'integer' && $type_of_left !== 'double') ||
            ($type_of_right !== 'integer' && $type_of_right !== 'double')
        ) {
            throw new RuntimeException('The < operator takes two numbers');
        }
        return $left < $right;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return bool
     * @throws RuntimeException
     */
    private function lessThanOrEqualToOperator($left, $right)
    {
        /** @var string $type_of_left */
        $type_of_left = gettype($left);
        /** @var string $type_of_right */
        $type_of_right = gettype($right);
        if (($type_of_left !== 'integer' && $type_of_left !== 'double') ||
            ($type_of_right !== 'integer' && $type_of_right !== 'double')
        ) {
            throw new RuntimeException('The <= operator takes two numbers');
        }
        return $left <= $right;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return bool
     * @throws RuntimeException
     */
    private function greaterThanOperator($left, $right)
    {
        /** @var string $type_of_left */
        $type_of_left = gettype($left);
        /** @var string $type_of_right */
        $type_of_right = gettype($right);
        if (($type_of_left !== 'integer' && $type_of_left !== 'double') ||
            ($type_of_right !== 'integer' && $type_of_right !== 'double')
        ) {
            throw new RuntimeException('The > operator takes two numbers');
        }
        return $left > $right;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return bool
     * @throws RuntimeException
     */
    private function greaterThanOrEqualToOperator($left, $right)
    {
        /** @var string $type_of_left */
        $type_of_left = gettype($left);
        /** @var string $type_of_right */
        $type_of_right = gettype($right);
        if (($type_of_left !== 'integer' && $type_of_left !== 'double') ||
            ($type_of_right !== 'integer' && $type_of_right !== 'double')
        ) {
            throw new RuntimeException('The >= operator takes two numbers');
        }
        return $left >= $right;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return int|float|string
     * @throws RuntimeException
     */
    private function additionOperator($left, $right)
    {
        /** @var string $type_of_left */
        $type_of_left = gettype($left);
        /** @var string $type_of_right */
        $type_of_right = gettype($right);
        if ((($type_of_left !== 'integer' && $type_of_left !== 'double') ||
                ($type_of_right !== 'integer' && $type_of_right !== 'double')) &&
            ($type_of_left !== 'string' || $type_of_right !== 'string')
        ) {
            throw new RuntimeException('The + operator takes two numbers or two strings');
        }
        if ($type_of_left === 'string') {
            return "$left$right";
        } else {
            return $left + $right;
        }
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return int|float
     * @throws RuntimeException
     */
    private function subtractionOperator($left, $right)
    {
        /** @var string $type_of_left */
        $type_of_left = gettype($left);
        /** @var string $type_of_right */
        $type_of_right = gettype($right);
        if (($type_of_left !== 'integer' && $type_of_left !== 'double') ||
            ($type_of_right !== 'integer' && $type_of_right !== 'double')
        ) {
            throw new RuntimeException('The - operator takes two numbers');
        }
        return $left - $right;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return int|float
     * @throws RuntimeException
     */
    private function multiplicationOperator($left, $right)
    {
        /** @var string $type_of_left */
        $type_of_left = gettype($left);
        /** @var string $type_of_right */
        $type_of_right = gettype($right);
        if (($type_of_left !== 'integer' && $type_of_left !== 'double') ||
            ($type_of_right !== 'integer' && $type_of_right !== 'double')
        ) {
            throw new RuntimeException('The * operator takes two numbers');
        }
        return $left * $right;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return int|float
     * @throws RuntimeException
     */
    private function divisionOperator($left, $right)
    {
        /** @var string $type_of_left */
        $type_of_left = gettype($left);
        /** @var string $type_of_right */
        $type_of_right = gettype($right);
        if (($type_of_left !== 'integer' && $type_of_left !== 'double') ||
            ($type_of_right !== 'integer' && $type_of_right !== 'double')
        ) {
            throw new RuntimeException('The / operator takes two numbers');
        }
        if ($right === 0) {
            throw new RuntimeException('Division by zero not allowed.');
        }
        return $left / $right;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return int
     * @throws RuntimeException
     */
    private function modulusOperator($left, $right)
    {
        /** @var string $type_of_left */
        $type_of_left = gettype($left);
        /** @var string $type_of_right */
        $type_of_right = gettype($right);
        if (($type_of_left !== 'integer' && $type_of_left !== 'double') ||
            ($type_of_right !== 'integer' && $type_of_right !== 'double')
        ) {
            throw new RuntimeException('The % operator takes two numbers');
        }
        if ($right === 0) {
            throw new RuntimeException('Modulo zero not allowed.');
        }
        return $left % $right;
    }
}
