<?php

namespace L20n;

use L20n\Parser\Exception\ParserException;

/**
 * Class Parser
 * @package L20n
 */
class Parser
{
    const MAX_PLACEABLES = 100;

    /** @var string */
    private $_source = '';
    /** @var int */
    private $_index = 0;
    /** @var int */
    private $_length = 0;
    /** @var bool */
    private $throwOnErrors = false;

    /**
     * @param bool $throwOnErrors
     */
    public function __construct($throwOnErrors = false)
    {
        $this->throwOnErrors = $throwOnErrors;
    }

    /**
     * @param string $arg_string
     * @return array
     */
    public function parse($arg_string)
    {
        $this->_source = $arg_string;
        $this->_index = 0;
        $this->_length = strlen($arg_string);

        return $this->getL20n();
    }

    /**
     * @return array
     */
    private function getL20n()
    {
        if ($this->throwOnErrors) {
            return $this->getL20nPlain();
        }
        return $this->getL20nWithRecover();
    }

    /**
     * @return array
     * @throws ParserException
     */
    private function getComment()
    {
        $this->_index = $this->_index + 2;
        /** @var int $start */
        $start = $this->_index;
        /** @var int|false $end */
        $end = strpos($this->_source, '*/', $start);
        if ($end === false) {
            throw new ParserException('Comment without closing tag');
        }
        $this->_index = $end + 2;
        return [
            'type' => 'Comment',
            'content' => substr($this->_source, $start, $end - $start)
        ];
    }

    /**
     * @return array
     * @throws ParserException
     */
    private function getAttributes()
    {
        /** @var array $attrs */
        $attrs = [];

        while (true) {
            /** @var array $attr */
            $attr = $this->getKVPWithIndex('Attribute');
            $attr['local'] = substr($attr['key']['name'], 0, 1) === '_';
            $attrs[] = $attr;
            /** @var bool $ws1 */
            $ws1 = $this->getRequiredWS();
            /** @var string $ch */
            $ch = substr($this->_source, $this->_index, 1);
            if ($ch === '>') {
                break;
            } else {
                if ($ws1 === false) {
                    throw new ParserException('Expected ">"');
                }
            }
        }
        return $attrs;
    }

    /**
     * @param string $type
     * @return array
     * @throws ParserException
     */
    private function getKVP($type)
    {
        /** @var array $key */
        $key = $this->getIdentifier();
        $this->getWS();
        if (substr($this->_source, $this->_index, 1) !== ':') {
            throw new ParserException('Expected ":"');
        }
        $this->_index = $this->_index + 1;
        $this->getWS();
        return [
            'type' => $type,
            'key' => $key,
            'value' => $this->getValue()
        ];
    }

    /**
     * @param string|null $type
     * @return array
     * @throws ParserException
     */
    private function getKVPWithIndex($type = null)
    {
        /** @var array $key */
        $key = $this->getIdentifier();
        /** @var array $index */
        $index = [];

        if (substr($this->_source, $this->_index, 1) === '[') {
            $this->_index = $this->_index + 1;
            $this->getWS();
            $index = $this->getItemList('getExpression', ']');
        }
        $this->getWS();
        if (substr($this->_source, $this->_index, 1) !== ':') {
            throw new ParserException('Expected ":"');
        }
        $this->_index = $this->_index + 1;
        $this->getWS();
        return [
            'type' => $type,
            'key' => $key,
            'value' => $this->getValue(),
            'index' => $index
        ];
    }

    /**
     * @return array
     * @throws ParserException
     */
    private function getHash()
    {
        $this->_index = $this->_index + 1;
        $this->getWS();
        /** @var bool $hasDefItem */
        $hasDefItem = false;
        /** @var array $hash */
        $hash = [];
        while (true) {
            /** @var bool $defItem */
            $defItem = false;
            if (substr($this->_source, $this->_index, 1) === '*') {
                $this->_index = $this->_index + 1;
                if ($hasDefItem) {
                    throw new ParserException('Default item redefinition forbidden');
                }
                $defItem = true;
                $hasDefItem = true;
            }
            /** @var array $hi */
            $hi = $this->getKVP('HashItem');
            $hi['default'] = $defItem;
            $hash[] = $hi;
            $this->getWS();

            /** @var bool $comma */
            $comma = substr($this->_source, $this->_index, 1) === ',';
            if ($comma !== false) {
                $this->_index = $this->_index + 1;
                $this->getWS();
            }
            if (substr($this->_source, $this->_index, 1) === '}') {
                $this->_index = $this->_index + 1;
                break;
            }
            if ($comma === false) {
                throw new ParserException('Expected "}"');
            }
        }
        return [
            'type' => 'Hash',
            'content' => $hash
        ];
    }

    /**
     * @return string
     * @throws ParserException
     */
    private function _unescapeString()
    {
        $this->_index = $this->_index + 1;
        /** @var string $ch */
        $ch = substr($this->_source, $this->_index, 1);
        /** @var int $cc */
        $cc = 0;
        if ($ch === 'u') {
            /** @var string $ucode */
            $ucode = '';
            /** @var int $loop_count */
            $loop_count = 0;
            while ($loop_count < 4) {
                $this->_index = $this->_index + 1;
                $ch = substr($this->_source, $this->_index, 1);
                $cc = ord($ch);
                if (($cc > 96 && $cc < 103) ||
                    ($cc > 64 && $cc < 71) ||
                    ($cc > 47 && $cc < 58)
                ) {
                    $ucode = $ucode . $ch;
                } else {
                    throw new ParserException('Illegal unicode escape sequence');
                }
                $loop_count = $loop_count + 1;
            }
            return iconv('UCS-2', 'UTF-8//IGNORE', pack('H*', $ucode));
        }
        return $ch;
    }

    /**
     * @param string $opchar
     * @param int $opcharLen
     * @return array
     * @throws ParserException
     */
    private function getComplexString($opchar, $opcharLen)
    {
        /** @var array|null $body */
        $body = null;
        /** @var string $buf */
        $buf = '';
        /** @var int $placeables */
        $placeables = 0;

        $this->_index = $this->_index + $opcharLen - 1;

        /** @var int $start */
        $start = $this->_index + 1;

        while (true) {
            $this->_index = $this->_index + 1;
            /** @var string $ch */
            $ch = substr($this->_source, $this->_index, 1);
            if ($ch === '\\') {
                $buf = $buf . $this->_unescapeString();
            } else {
                if ($ch === '{' &&
                    substr($this->_source, $this->_index + 1, 1) === '{'
                ) {
                    if ($body === null) {
                        $body = [];
                    }
                    if ($placeables > self::MAX_PLACEABLES - 1) {
                        throw new ParserException(sprintf(
                            'Too many placeables, maximum allowed is %d', self::MAX_PLACEABLES
                        ));
                    }
                    if ($buf) {
                        $body[] = [
                            'type' => 'String',
                            'content' => $buf
                        ];
                    }
                    $this->_index = $this->_index + 2;
                    $this->getWS();
                    $body[] = $this->getExpression();
                    $this->getWS();
                    if (substr($this->_source, $this->_index, 1) !== '}' ||
                        substr($this->_source, $this->_index + 1, 1) !== '}'
                    ) {
                        throw new ParserException('Expected "}}"');
                    }
                    $this->_index = $this->_index + 1;
                    $placeables = $placeables + 1;

                    $buf = '';
                } else {
                    if ($opcharLen === 1) {
                        if ($ch === $opchar) {
                            $this->_index = $this->_index + 1;
                            break;
                        }
                    } else {
                        if ($ch === substr($opchar, 0, 1) &&
                            substr($this->_source, $this->_index + 1, 1) === $ch &&
                            substr($this->_source, $this->_index + 2, 1) === $ch
                        ) {
                            $this->_index = $this->_index + 3;
                            break;
                        }
                    }
                    $buf = $buf . $ch;
                    if ($this->_index + 1 >= $this->_length) {
                        throw new ParserException('Unclosed string literal');
                    }
                }
            }
        }
        if ($body === null) {
            return [
                'type' => 'String',
                'content' => $buf
            ];
        }
        if (strlen($buf)) {
            $body[] = [
                'type' => 'String',
                'content' => $buf
            ];
        }
        return [
            'type' => 'ComplexString',
            'content' => $body,
            'source' => substr($this->_source, $start, $this->_index - $opcharLen - $start)
        ];
    }

    /**
     * @param string $opchar
     * @param int $opcharLen
     * @return array
     * @throws ParserException
     */
    private function getString($opchar, $opcharLen)
    {
        /** @var int|false $opcharPos */
        $opcharPos = strpos($this->_source, $opchar, $this->_index + $opcharLen);

        if ($opcharPos === false) {
            throw new ParserException('Unclosed string literal');
        }
        /** @var string $buf */
        $buf = substr($this->_source, $this->_index + $opcharLen, $opcharPos - ($this->_index + $opcharLen));

        /** @var int|false $placeablePos */
        $placeablePos = strpos($buf, '{{');
        if ($placeablePos !== false) {
            return $this->getComplexString($opchar, $opcharLen);
        } else {
            /** @var int|false $escPos */
            $escPos = strpos($buf, '\\');
            if ($escPos !== false) {
                return $this->getComplexString($opchar, $opcharLen);
            }
        }

        $this->_index = $opcharPos + $opcharLen;

        return [
            'type' => 'String',
            'content' => $buf
        ];
    }

    /**
     * @param bool $optional
     * @param string|null $ch
     * @return array|null
     * @throws ParserException
     */
    private function getValue($optional = false, $ch = null)
    {
        if ($ch === null) {
            $ch = substr($this->_source, $this->_index, 1);
        }
        if ($ch === '\'' || $ch === '"') {
            if ($ch === substr($this->_source, $this->_index + 1, 1) &&
                $ch === substr($this->_source, $this->_index + 2, 1)
            ) {
                return $this->getString(str_repeat($ch, 3), 3);
            }
            return $this->getString($ch, 1);
        }
        if ($ch === '{') {
            return $this->getHash();
        }
        if ($optional === false) {
            throw new ParserException('Unknown value type');
        }
        return null;
    }

    /**
     * @return bool
     */
    private function getRequiredWS()
    {
        /** @var int $pos */
        $pos = $this->_index;
        /** @var int $cc */
        $cc = ord(substr($this->_source, $pos, 1));
        while ($cc === 32 || $cc === 10 || $cc === 9 || $cc === 13) {
            $this->_index = $this->_index + 1;
            $cc = ord(substr($this->_source, $this->_index, 1));
        }
        return $this->_index !== $pos;
    }

    /**
     *
     */
    private function getWS()
    {
        /** @var int $cc */
        $cc = ord(substr($this->_source, $this->_index, 1));
        while ($cc === 32 || $cc === 10 || $cc === 9 || $cc === 13) {
            $this->_index = $this->_index + 1;
            $cc = ord(substr($this->_source, $this->_index, 1));
        }
    }

    /**
     * @return array
     */
    private function getVariable()
    {
        $this->_index = $this->_index + 1;
        return [
            'type' => 'VariableExpression',
            'id' => $this->getIdentifier()
        ];
    }

    /**
     * @return array
     * @throws ParserException
     */
    private function getIdentifier()
    {
        /** @var int $index */
        $index = $this->_index;
        /** @var int $start */
        $start = $index;
        /** @var string $source */
        $source = $this->_source;
        /** @var int $cc */
        $cc = ord(substr($source, $start, 1));

        if (($cc < 97 || $cc > 122) && ($cc < 65 || $cc > 90) && $cc !== 95) {
            throw new ParserException('Identifier has to start with [a-zA-Z_]');
        }

        $index = $index + 1;
        $cc = ord(substr($source, $index, 1));
        while (($cc >= 97 && $cc <= 122) ||
            ($cc >= 65 && $cc <= 90) ||
            ($cc >= 48 && $cc <= 57) ||
            $cc === 95) {
            $index = $index + 1;
            $cc = ord(substr($source, $index, 1));
        }
        $this->_index = $index;
        return [
            'type' => 'Identifier',
            'name' => substr($source, $start, $index - $start)
        ];
    }

    /**
     * @return array
     * @throws ParserException
     */
    private function getImportStatement()
    {
        $this->_index = $this->_index + 6;
        if (substr($this->_source, $this->_index, 1) !== '(') {
            throw new ParserException('Expected "("');
        }
        $this->_index = $this->_index + 1;
        $this->getWS();
        /** @var string $uri */
        $uri = $this->getString(substr($this->_source, $this->_index, 1), 1);
        $this->getWS();
        if (substr($this->_source, $this->_index, 1) !== ')') {
            throw new ParserException('Expected ")"');
        }
        $this->_index = $this->_index + 1;
        return [
            'type' => 'ImportStatement',
            'uri' => $uri
        ];
    }

    /**
     * @param array $id
     * @return array
     * @throws ParserException
     */
    private function getMacro(array $id)
    {
        if (substr($id['name'], 0, 1) === '_') {
            throw new ParserException('Macro ID cannot start with "_"');
        }
        $this->_index = $this->_index + 1;
        /** @var array $idlist */
        $idlist = $this->getItemList('getVariable', ')');
        $this->getRequiredWS();

        if (substr($this->_source, $this->_index, 1) !== '{') {
            throw new ParserException('Expected "{"');
        }
        $this->_index = $this->_index + 1;
        $this->getWS();
        /** @var array $exp */
        $exp = $this->getExpression();
        $this->getWS();
        if (substr($this->_source, $this->_index, 1) !== '}') {
            throw new ParserException('Expected "}"');
        }
        $this->_index = $this->_index + 1;
        $this->getWS();
        if (ord(substr($this->_source, $this->_index, 1)) !== 62) {
            throw new ParserException('Expected ">"');
        }
        $this->_index = $this->_index + 1;
        return [
            'type' => 'Macro',
            'id' => $id,
            'args' => $idlist,
            'expression' => $exp
        ];
    }

    /**
     * @param array $id
     * @param array|null $index
     * @return array
     * @throws ParserException
     */
    private function getEntity(array $id, array $index = null)
    {
        if ($this->getRequiredWS() === false) {
            throw new ParserException('Expected white space');
        }

        /** @var string $ch */
        $ch = substr($this->_source, $this->_index, 1);
        /** @var array|null $value */
        $value = $this->getValue(true, $ch);
        /** @var array|null $attrs */
        $attrs = null;
        if ($value === null) {
            if ($ch === '>') {
                throw new ParserException('Expected ">"');
            }
            $attrs = $this->getAttributes();
        } else {
            /** @var bool $ws1 */
            $ws1 = $this->getRequiredWS();
            if (substr($this->_source, $this->_index, 1) !== '>') {
                if ($ws1 === false) {
                    throw new ParserException('Expected ">"');
                }
                $attrs = $this->getAttributes();
            }
        }

        $this->_index = $this->_index + 1;
        /** @var bool $is_local */
        $is_local = false;
        if (is_array($id['name'])) {
            if (ord($id['name'][0]) === 95) {
                $is_local = true;
            }
        }
        return [
            'type' => 'Entity',
            'id' => $id,
            'value' => $value,
            'index' => $index,
            'attrs' => $attrs,
            'local' => $is_local
        ];
    }

    /**
     * @return array
     * @throws ParserException
     */
    private function getEntry()
    {
        /** @var int $cc */
        $cc = ord(substr($this->_source, $this->_index, 1));

        if ($cc === 60) {
            $this->_index = $this->_index + 1;
            /** @var array $id */
            $id = $this->getIdentifier();
            /** @var int $cc */
            $cc = ord(substr($this->_source, $this->_index, 1));
            if ($cc === 40) {
                return $this->getMacro($id);
            }
            if ($cc === 91) {
                $this->_index = $this->_index + 1;
                return $this->getEntity($id, $this->getItemList('getExpression', ']'));
            }
            return $this->getEntity($id, null);
        }

        if (ord(substr($this->_source, $this->_index, 1)) === 47 &&
            ord(substr($this->_source, $this->_index + 1, 1)) === 42
        ) {
            return $this->getComment();
        }
        if (substr($this->_source, $this->_index, 6) === 'import') {
            return $this->getImportStatement();
        }
        throw new ParserException('Invalid entry');
    }

    /**
     * @return array
     */
    private function getL20nWithRecover()
    {
        /** @var array $entries */
        $entries = [];

        $this->getWS();
        while ($this->_index < $this->_length) {
            try {
                $entries[] = $this->getEntry();
            } catch (ParserException $e) {
                $entries[] = $this->recover();
            }
            if ($this->_index < $this->_length) {
                $this->getWS();
            }
        }

        return [
            'type' => 'L20n',
            'body' => $entries
        ];
    }

    /**
     * @return array
     */
    private function getL20nPlain()
    {
        /** @var array $entries */
        $entries = [];

        $this->getWS();
        while ($this->_index < $this->_length) {
            $entries[] = $this->getEntry();
            if ($this->_index < $this->_length) {
                $this->getWS();
            }
        }

        return [
            'type' => 'L20n',
            'body' => $entries
        ];
    }

    /**
     * @return array
     */
    private function getExpression()
    {
        return $this->getConditionalExpression();
    }

    /**
     * @param array $token
     * @param string $cl
     * @param string $op
     * @param string $nxt
     * @return array
     */
    private function getPrefixExpression(array $token, $cl, $op, $nxt)
    {
        /** @var array $exp */
        $exp = call_user_func([$this, $nxt]);
        while (true) {
            /** @var string $t */
            $t = '';
            $this->getWS();
            /** @var string $ch */
            $ch = substr($this->_source, $this->_index, 1);
            if (in_array($ch, $token[0]) === false) {
                break;
            }
            $t = $t . $ch;
            $this->_index = $this->_index + 1;
            if (count($token) > 1) {
                $ch = substr($this->_source, $this->_index, 1);
                if ($token[1] === $ch) {
                    $this->_index = $this->_index + 1;
                    $t = $t . $ch;
                } else {
                    if (isset($token[2]) && $token[2]) {
                        $this->_index = $this->_index - 1;
                        return $exp;
                    }
                }
            }
            $this->getWS();
            /** @var array $exp_tmp */
            $exp_tmp = [
                'type' => $cl,
                'operator' => [
                    'type' => $op,
                    'token' => $t
                ],
                'left' => $exp,
                'right' => call_user_func([$this, $nxt])
            ];
            $exp = $exp_tmp;
        }
        return $exp;
    }

    /**
     * @param array $token
     * @param string $cl
     * @param string $op
     * @param string $nxt
     * @return array
     */
    private function getPostfixExpression(array $token, $cl, $op, $nxt)
    {
        /** @var int $cc */
        $cc = ord(substr($this->_source, $this->_index, 1));
        if (in_array($cc, $token) === false) {
            return call_user_func([$this, $nxt]);
        }
        $this->_index = $this->_index + 1;
        $this->getWS();
        return [
            'type' => $cl,
            'operator' => [
                'type' => $op,
                'token' => chr($cc)
            ],
            'argument' => $this->getPostfixExpression($token, $cl, $op, $nxt)
        ];
    }

    /**
     * @return array
     * @throws ParserException
     */
    private function getConditionalExpression()
    {
        /** @var array $exp */
        $exp = $this->getOrExpression();
        $this->getWS();
        if (ord(substr($this->_source, $this->_index, 1)) !== 63) {
            return $exp;
        }
        $this->_index = $this->_index + 1;
        $this->getWS();
        /** @var array $consequent */
        $consequent = $this->getExpression();
        $this->getWS();
        if (ord(substr($this->_source, $this->_index, 1)) !== 58) {
            throw new ParserException('Expected ":"');
        }
        $this->_index = $this->_index + 1;
        $this->getWS();
        return [
            'type' => 'ConditionalExpression',
            'test' => $exp,
            'consequent' => $consequent,
            'alternate' => $this->getExpression()
        ];
    }

    /**
     * @return array
     */
    private function getOrExpression()
    {
        return $this->getPrefixExpression(
            [['|'], '|', true],
            'LogicalExpression',
            'LogicalOperator',
            'getAddExpression'
        );
    }

    /**
     * @return array
     */
    private function getAddExpression()
    {
        return $this->getPrefixExpression(
            [['&'], '&', true],
            'LogicalExpression',
            'LogicalOperator',
            'getEqualityExpression'
        );
    }

    /**
     * @return array
     */
    private function getEqualityExpression()
    {
        return $this->getPrefixExpression(
            [['=', '!'], '=', true],
            'BinaryExpression',
            'BinaryOperator',
            'getRelationalExpression'
        );
    }

    /**
     * @return array
     */
    private function getRelationalExpression()
    {
        return $this->getPrefixExpression(
            [['<', '>'], '=', false],
            'BinaryExpression',
            'BinaryOperator',
            'getAdditiveExpression'
        );
    }

    /**
     * @return array
     */
    private function getAdditiveExpression()
    {
        return $this->getPrefixExpression(
            [['+', '-']],
            'BinaryExpression',
            'BinaryOperator',
            'getModuloExpression'
        );
    }

    /**
     * @return array
     */
    private function getModuloExpression()
    {
        return $this->getPrefixExpression(
            [['%']],
            'BinaryExpression',
            'BinaryOperator',
            'getMultiplicativeExpression'
        );
    }

    /**
     * @return array
     */
    private function getMultiplicativeExpression()
    {
        return $this->getPrefixExpression(
            [['*']],
            'BinaryExpression',
            'BinaryOperator',
            'getDividiveExpression'
        );
    }

    /**
     * @return array
     */
    private function getDividiveExpression()
    {
        return $this->getPrefixExpression(
            [['/']],
            'BinaryExpression',
            'BinaryOperator',
            'getUnaryExpression'
        );
    }

    /**
     * @return array
     */
    private function getUnaryExpression()
    {
        return $this->getPostfixExpression(
            [43, 45, 33], // + - !
            'UnaryExpression',
            'UnaryOperator',
            'getMemberExpression'
        );
    }

    /**
     * @param array $callee
     * @return array
     */
    private function getCallExpression(array $callee)
    {
        $this->getWS();
        return [
            'type' => 'CallExpression',
            'callee' => $callee,
            'arguments' => $this->getItemList('getExpression', ')')
        ];
    }

    /**
     * @param array $idref
     * @param bool $computed
     * @return array
     * @throws ParserException
     */
    private function getAttributeExpression(array $idref, $computed)
    {
        if ($idref['type'] !== 'ParenthesisExpression' &&
            $idref['type'] !== 'Identifier' &&
            $idref['type'] !== 'ThisExpression'
        ) {
            throw new ParserException('AttributeExpression must have Identifier, This or Parenthesis as left node');
        }
        if ($computed) {
            $this->getWS();
            /** @var array $exp */
            $exp = $this->getExpression();
            $this->getWS();
            if (substr($this->_source, $this->_index, 1) !== ']') {
                throw new ParserException('Expected "]"');
            }
            $this->_index = $this->_index + 1;
            return [
                'type' => 'AttributeExpression',
                'expression' => $idref,
                'attribute' => $exp,
                'computed' => true
            ];
        }
        /** @var array $exp */
        $exp = $this->getIdentifier();
        return [
            'type' => 'AttributeExpression',
            'expression' => $idref,
            'attribute' => $exp,
            'computed' => false
        ];
    }

    /**
     * @param array $idref
     * @param bool $computed
     * @return array
     * @throws ParserException
     */
    private function getPropertyExpression(array $idref, $computed)
    {
        if ($computed) {
            $this->getWS();
            /** @var array $exp */
            $exp = $this->getExpression();
            $this->getWS();
            if (substr($this->_source, $this->_index, 1) !== ']') {
                throw new ParserException('Expected "]"');
            }
            $this->_index = $this->_index + 1;
            return [
                'type' => 'PropertyExpression',
                'expression' => $idref,
                'property' => $exp,
                'computed' => true
            ];
        }
        /** @var array $exp */
        $exp = $this->getIdentifier();
        return [
            'type' => 'PropertyExpression',
            'expression' => $idref,
            'property' => $exp,
            'computed' => false
        ];
    }

    /**
     * @return array|null
     */
    private function getMemberExpression()
    {
        /** @var array|null $exp */
        $exp = $this->getParenthesisExpression();

        while (true) {
            /** @var int $cc */
            $cc = ord(substr($this->_source, $this->_index, 1));
            if ($cc === 46 || $cc === 91) {
                $this->_index = $this->_index + 1;
                /** @var bool $computed */
                if ($cc === 91) {
                    $computed = true;
                } else {
                    $computed = false;
                }
                /** @var array $exp_tmp */
                $exp_tmp = $this->getPropertyExpression($exp, $computed);
                /** @var array $exp */
                $exp = $exp_tmp;
            } else {
                if ($cc === 58 &&
                    ord(substr($this->_source, $this->_index + 1, 1)) === 58
                ) {
                    $this->_index = $this->_index + 2;
                    if (ord(substr($this->_source, $this->_index, 1)) === 91) {
                        $this->_index = $this->_index + 1;
                        /** @var array $exp_tmp */
                        $exp_tmp = $this->getAttributeExpression($exp, true);
                        /** @var array $exp */
                        $exp = $exp_tmp;
                    } else {
                        /** @var array $exp_tmp */
                        $exp_tmp = $this->getAttributeExpression($exp, false);
                        /** @var array $exp */
                        $exp = $exp_tmp;
                    }
                } else {
                    if ($cc === 40) {
                        $this->_index = $this->_index + 1;
                        /** @var array $exp_tmp */
                        $exp_tmp = $this->getCallExpression($exp);
                        /** @var array $exp */
                        $exp = $exp_tmp;
                    } else {
                        break;
                    }
                }
            }
        }
        return $exp;
    }

    /**
     * @return array|null
     * @throws ParserException
     */
    private function getParenthesisExpression()
    {
        if (ord(substr($this->_source, $this->_index, 1)) === 40) {
            $this->_index = $this->_index + 1;
            $this->getWS();
            /** @var array $pexp */
            $pexp = [
                'type' => 'ParenthesisExpression',
                'expression' => $this->getExpression()
            ];
            $this->getWS();
            if (ord(substr($this->_source, $this->_index, 1)) !== 41) {
                throw new ParserException('Expected ")"');
            }
            $this->_index = $this->_index + 1;
            return $pexp;
        }
        return $this->getPrimaryExpression();
    }

    /**
     * @return array|null
     */
    private function getPrimaryExpression()
    {
        /** @var int $pos */
        $pos = $this->_index;
        /** @var int $cc */
        $cc = ord(substr($this->_source, $pos, 1));
        while ($cc > 47 && $cc < 58) {
            $pos = $pos + 1;
            $cc = ord(substr($this->_source, $pos, 1));
        }
        if ($pos > $this->_index) {
            /** @var int $start */
            $start = $this->_index;
            $this->_index = $pos;
            return [
                'type' => 'Number',
                'value' => (int)substr($this->_source, $start, $pos - $start)
            ];
        }

        switch ($cc) {
            case 39:
            case 34:
            case 123:
            case 91:
                return $this->getValue();

            case 36:
                return $this->getVariable();

            case 64:
                $this->_index = $this->_index + 1;
                return [
                    'type' => 'GlobalsExpression',
                    'id' => $this->getIdentifier()
                ];

            case 126:
                $this->_index = $this->_index + 1;
                return [
                    'type' => 'ThisExpression'
                ];

            default:
                return $this->getIdentifier();
        }
    }

    /**
     * @param string $callback
     * @param string $closeChar
     * @return array
     * @throws ParserException
     */
    private function getItemList($callback, $closeChar)
    {
        $this->getWS();
        if (substr($this->_source, $this->_index, 1) === $closeChar) {
            $this->_index = $this->_index + 1;
            return [];
        }

        /** @var array $items */
        $items = [];

        while (true) {
            $items[] = call_user_func([$this, $callback]);
            $this->getWS();
            /** @var string $ch */
            $ch = substr($this->_source, $this->_index, 1);
            if ($ch === ',') {
                $this->_index = $this->_index + 1;
                $this->getWS();
            } else {
                if ($ch === $closeChar) {
                    $this->_index = $this->_index + 1;
                    break;
                } else {
                    throw new ParserException(sprintf('Expected "," or "%s"', $closeChar));
                }
            }
        }
        return $items;
    }

    /**
     * @return array
     */
    private function recover()
    {
        /** @var int|false $opening */
        $opening = strpos($this->_source, '<', $this->_index);
        /** @var array|null $junk */
        $junk = null;
        if ($opening === false) {
            $junk = [
                'type' => 'JunkEntry',
                'content' => substr($this->_source, $this->_index, $this->_length - $this->_index)
            ];
            $this->_index = $this->_length;
            return $junk;
        }
        $junk = [
            'type' => 'JunkEntry',
            'content' => substr($this->_source, $this->_index, $opening - $this->_index)
        ];
        $this->_index = $opening;
        return $junk;
    }
}
