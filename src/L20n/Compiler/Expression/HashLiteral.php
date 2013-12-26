<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\IndexException;
use L20n\Compiler\Expression;
use L20n\Compiler\Locals;

/**
 * Class Compiler\Expression\HashLiteral
 * @package L20n
 */
class HashLiteral implements ExpressionInterface
{
    /** @var ExpressionInterface[] */
    private $content = [];
    /** @var string|null */
    private $defaultIndex = null;
    /** @var string|null */
    private $defaultKey = null;

    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null)
    {
        $this->content = [];
        $this->defaultKey = null;
        $this->defaultIndex = $index ? array_shift($index) : null;
        foreach ($node['content'] as $elem) {
            $this->content[$elem['key']['name']] = Expression::factory($elem['value'], $entry, $index);
            if ($elem['default']) {
                $this->defaultKey = $elem['key']['name'];
            }
        }
    }

    /**
     * @param Locals $locals
     * @param \stdClass|null $ctxdata
     * @param string|null $prop
     * @return array
     * @throws IndexException
     */
    public function __invoke(Locals $locals, \stdClass $ctxdata = null, $prop = null)
    {
        /** @var array $keysToTry */
        $keysToTry = [$prop, $this->defaultIndex, $this->defaultKey];
        /** @var array $keysTried */
        $keysTried = [];
        foreach ($keysToTry as $keyToTry) {
            /** @var string|null $key */
            $key = Expression::_resolve($keyToTry, $locals, $ctxdata);
            if ($key === null) {
                continue;
            }
            if (!is_string($key)) {
                throw new IndexException('Index must be a string');
            }
            $keysTried[] = $key;
            if (isset($this->content[$key])) {
                return [$locals, $this->content[$key]];
            }
        }
        if (count($keysTried)) {
            /** @var string $message */
            $message = sprintf('Hash key lookup failed (tried "%s").', implode('", "', $keysTried));
        } else {
            $message = 'Hash key lookup failed.';
        }
        throw new IndexException($message);
    }
}
