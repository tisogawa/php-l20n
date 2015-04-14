<?php

namespace L20n\Compiler\Expression;

use L20n\Compiler\EntryInterface;
use L20n\Compiler\Exception\CompilerException;
use L20n\Compiler\Exception\RuntimeException;
use L20n\Compiler\Exception\ValueException;
use L20n\Compiler\Expression;
use L20n\Compiler\Locals;

/**
 * Class Compiler\Expression\ComplexString
 * @package L20n
 */
class ComplexString implements ExpressionInterface
{
    const MAX_PLACEABLE_LENGTH = 2500;

    /** @var bool */
    private $dirty = false;
    /** @var ExpressionInterface[] */
    private $content = [];

    /**
     * @param array $node
     * @param EntryInterface|null $entry
     * @param array|null $index
     */
    public function __construct(array $node, EntryInterface $entry = null, array $index = null)
    {
        $this->dirty = false;
        /** @var int $limit */
        $limit = count($node['content']);
        for ($i = 0; $i < $limit; $i++) {
            $this->content[] = Expression::factory($node['content'][$i], $entry);
        }
    }

    /**
     * @param Locals $locals
     * @param \stdClass|null $ctxdata
     * @param string|null $prop
     * @return array
     * @throws ValueException
     * @throws RuntimeException
     * @throws \Exception
     */
    public function __invoke(Locals $locals, \stdClass $ctxdata = null, $prop = null)
    {
        if ($prop !== null) {
            throw new RuntimeException(sprintf('Cannot get property of a string: %s', $prop));
        }
        if ($this->dirty) {
            throw new RuntimeException('Cyclic reference detected');
        }
        $this->dirty = true;
        /** @var array $parts */
        $parts = [];
        try {
            /** @var int $limit */
            $limit = count($this->content);
            for ($i = 0; $i < $limit; $i++) {
                /** @var string|int|float $part */
                $part = Expression::_resolve($this->content[$i], $locals, $ctxdata);
                /** @var string $type_of_part */
                $type_of_part = gettype($part);
                if ($type_of_part !== 'string' && $type_of_part !== 'integer' && $type_of_part !== 'double') {
                    throw new RuntimeException('Placeables must be strings or numbers');
                }
                if (iconv_strlen($part, 'UTF-8') > static::MAX_PLACEABLE_LENGTH) {
                    throw new RuntimeException(sprintf(
                        'Placeable has too many characters, maximum allowed is %d', static::MAX_PLACEABLE_LENGTH
                    ));
                }
                $parts[] = $part;
            }
            $this->dirty = false;
        } catch (CompilerException $e) {
            $this->dirty = false;
            throw new ValueException($e->getMessage());
        } catch (\Exception $e) {
            $this->dirty = false;
            throw $e;
        }
        return [$locals, implode('', $parts)];
    }
}
