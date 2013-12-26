<?php

use L20n\Parser;
use L20n\Parser\Exception\ParserException;

/**
 * Class ParserTest
 */
class ParserTest extends PHPUnit_Framework_TestCase
{
    /** @var Parser */
    private $parser;
    /** @var Parser */
    private $parserToThrowOnErrors;
    /** @var int */
    private $max_nesting_level_to_set = 200;

    /**
     *
     */
    public function setUp()
    {
        // To avoid the fatal error of nested function caused by xdebug.
        $current_max_nesting_level = ini_get('xdebug.max_nesting_level');
        ini_set('xdebug.max_nesting_level', $this->max_nesting_level_to_set);
        $this->max_nesting_level_to_set = $current_max_nesting_level;

        $this->parser = new Parser();
        $this->parserToThrowOnErrors = new Parser(true);
    }

    /**
     *
     */
    public function tearDown()
    {
        ini_set('xdebug.max_nesting_level', $this->max_nesting_level_to_set);
    }

    /**
     *
     */
    public function test_attributes()
    {
        // basic attributes
        $ast = $this->parser->parse('<id attr1: "foo">');
        $this->assertEquals(1, count($ast['body'][0]['attrs']));
        $this->assertEquals('attr1', $ast['body'][0]['attrs'][0]['key']['name']);
        $this->assertEquals('foo', $ast['body'][0]['attrs'][0]['value']['content']);

        $ast = $this->parser->parse('<id attr1: "foo" attr2: "foo2"    >');
        $this->assertEquals(2, count($ast['body'][0]['attrs']));
        $this->assertEquals('attr1', $ast['body'][0]['attrs'][0]['key']['name']);
        $this->assertEquals('foo', $ast['body'][0]['attrs'][0]['value']['content']);

        $ast = $this->parser->parse('<id attr1: "foo" attr2: "foo2" attr3: "foo3" >');
        $this->assertEquals(3, count($ast['body'][0]['attrs']));
        $this->assertEquals('attr1', $ast['body'][0]['attrs'][0]['key']['name']);
        $this->assertEquals('foo', $ast['body'][0]['attrs'][0]['value']['content']);
        $this->assertEquals('attr2', $ast['body'][0]['attrs'][1]['key']['name']);
        $this->assertEquals('foo2', $ast['body'][0]['attrs'][1]['value']['content']);
        $this->assertEquals('attr3', $ast['body'][0]['attrs'][2]['key']['name']);
        $this->assertEquals('foo3', $ast['body'][0]['attrs'][2]['value']['content']);

        $ast = $this->parser->parse('<id "value" attr1: "foo">');
        $this->assertEquals('value', $ast['body'][0]['value']['content']);
        $this->assertEquals('attr1', $ast['body'][0]['attrs'][0]['key']['name']);
        $this->assertEquals('foo', $ast['body'][0]['attrs'][0]['value']['content']);

        // camelCase attributes
        $ast = $this->parser->parse('<id "value" atTr1: "foo">');
        $this->assertEquals('value', $ast['body'][0]['value']['content']);
        $this->assertEquals('atTr1', $ast['body'][0]['attrs'][0]['key']['name']);
        $this->assertEquals('foo', $ast['body'][0]['attrs'][0]['value']['content']);

        $ast = $this->parser->parse('<id atTr1: "foo">');
        $this->assertEquals('atTr1', $ast['body'][0]['attrs'][0]['key']['name']);
        $this->assertEquals('foo', $ast['body'][0]['attrs'][0]['value']['content']);
        // attributes with indexes
        $ast = $this->parser->parse('<id attr[2]: "foo">');
        $this->assertEquals(2, $ast['body'][0]['attrs'][0]['index'][0]['value']);

        $ast = $this->parser->parse('<id attr[2+3?"foo":"foo2"]: "foo">');
        $this->assertEquals(2, $ast['body'][0]['attrs'][0]['index'][0]['test']['left']['value']);
        $this->assertEquals(3, $ast['body'][0]['attrs'][0]['index'][0]['test']['right']['value']);

        $ast = $this->parser->parse('<id attr[2, 3]: "foo">');
        $this->assertEquals(2, $ast['body'][0]['attrs'][0]['index'][0]['value']);
        $this->assertEquals(3, $ast['body'][0]['attrs'][0]['index'][1]['value']);

        // missing attribute id error
        try {
            $this->parserToThrowOnErrors->parse('<id : "foo">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Identifier has to start with [a-zA-Z_]', $e->getMessage());
        }
        // attribute id starting with an integer
        try {
            $this->parserToThrowOnErrors->parse('<id 2: >');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Identifier has to start with [a-zA-Z_]', $e->getMessage());
        }
        // attribute with no value
        try {
            $this->parserToThrowOnErrors->parse('<id a: >');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Unknown value type', $e->getMessage());
        }
        // mistaken entity id for attribute id
        try {
            $this->parserToThrowOnErrors->parse('<id: "">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Expected white space', $e->getMessage());
        }
        // attribute with no value
        try {
            $this->parserToThrowOnErrors->parse('<id a: b:>');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Unknown value type', $e->getMessage());
        }
        // follow up attribute with no id
        try {
            $this->parserToThrowOnErrors->parse('<id a: "foo" "heh">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Identifier has to start with [a-zA-Z_]', $e->getMessage());
        }
        // integer value
        try {
            $this->parserToThrowOnErrors->parse('<id a: 2>');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Unknown value type', $e->getMessage());
        }
        // string as id
        try {
            $this->parserToThrowOnErrors->parse('<id "a": "a">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Expected ">"', $e->getMessage());
        }
        // string as id
        try {
            $this->parserToThrowOnErrors->parse('<id "a": \'a\'>');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Expected ">"', $e->getMessage());
        }
        // integer id
        try {
            $this->parserToThrowOnErrors->parse('<id 2: "a">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Identifier has to start with [a-zA-Z_]', $e->getMessage());
        }
        // no white space between attributes
        try {
            $this->parserToThrowOnErrors->parse('<id a2:"a"a3:"v">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Expected ">"', $e->getMessage());
        }
    }

    /**
     *
     */
    public function test_escapes()
    {
        // string value quotes
        $ast = $this->parser->parse('<id "\\"">');
        $this->assertEquals('"', $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse("<id '\\''>");
        $this->assertEquals("'", $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse('<id """\\"\\"\\"""">');
        $this->assertEquals('"""', $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse('<id "str\\"ing">');
        $this->assertEquals('str"ing', $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse("<id 'str\\'ing'>");
        $this->assertEquals("str'ing", $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse('<id """str"ing""">');
        $this->assertEquals('str"ing', $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse('<id """str\'ing""">');
        $this->assertEquals('str\'ing', $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse("<id '''str\"ing'''>");
        $this->assertEquals("str\"ing", $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse('<id """"string\\"""">');
        $this->assertEquals('"string"', $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse("<id ''''string\\''''>");
        $this->assertEquals("'string'", $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse("<id 'test \\{{ more'>");
        $this->assertEquals("test {{ more", $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse("<id \"test \\{{ \\\"more\\\" }}\">");
        $this->assertEquals("test {{ \"more\" }}", $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse("<id \"test \\\\{{ \"more\" }}\">");
        $this->assertEquals("more", $ast['body'][0]['value']['content'][1]['content']);

        $ast = $this->parser->parse('<id "test \\\\\\{{ \\"more\\" }}">');
        $this->assertEquals("test \\{{ \"more\" }}", $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse('<id "test \\\\\\{{ \\"more\\" }}\\\\">');
        $this->assertEquals("test \\{{ \"more\" }}\\", $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse("<id 'test \\\\ more'>");
        $this->assertEquals("test \\ more", $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse("<id 'test \\a more'>");
        $this->assertEquals("test a more", $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse("<id 'test more\\\\'>");
        $this->assertEquals("test more\\", $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse("<id 'test more\\\\\\''>");
        $this->assertEquals("test more\\'", $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse("<id '\\'test more'>");
        $this->assertEquals("'test more", $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse('<id " \\\\">');
        $this->assertEquals(' \\', $ast['body'][0]['value']['content']);
        // unescape unicode
        /* We want to use double quotes in those tests for readability */
        /* jshint -W109 */
        $ast = $this->parser->parse("<id 'string \\ua0a0 foo'>");
        $this->assertEquals('string ꂠ foo', $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse("<id 'string \\ua0a0 {{ foo }} foo \\ua0a0'>");
        $this->assertEquals('string ꂠ ', $ast['body'][0]['value']['content'][0]['content']);
        $this->assertEquals(' foo ꂠ', $ast['body'][0]['value']['content'][2]['content']);

        // nested strings
        $ast = $this->parser->parse('<id "{{ "Foo" }}">');
        $this->assertEquals('String', $ast['body'][0]['value']['content'][0]['type']);

        $ast = $this->parser->parse('<id "{{ "{{ bar }}" }}">');
        $this->assertEquals('ComplexString', $ast['body'][0]['value']['content'][0]['type']);
        $this->assertEquals('Identifier', $ast['body'][0]['value']['content'][0]['content'][0]['type']);

        $ast = $this->parser->parse('<id "{{ "{{ \'Foo\' }}" }}">');
        $this->assertEquals('ComplexString', $ast['body'][0]['value']['content'][0]['type']);
        $this->assertEquals('String', $ast['body'][0]['value']['content'][0]['content'][0]['type']);

        $ast = $this->parser->parse('<id "{{ "{{ "Foo" }}" }}">');
        $this->assertEquals('ComplexString', $ast['body'][0]['value']['content'][0]['type']);
        $this->assertEquals('String', $ast['body'][0]['value']['content'][0]['content'][0]['type']);

        $ast = $this->parser->parse('<id "{{ "{{ "{{ bar }}" }}" }}">');
        $this->assertEquals('ComplexString', $ast['body'][0]['value']['content'][0]['type']);
        $this->assertEquals('ComplexString', $ast['body'][0]['value']['content'][0]['content'][0]['type']);
        $this->assertEquals('Identifier', $ast['body'][0]['value']['content'][0]['content'][0]['content'][0]['type']);

        $ast = $this->parserToThrowOnErrors->parse('<id """
          {{ "{{ \'Foo\' }}" }}
        """>');
        $this->assertEquals('ComplexString', $ast['body'][0]['value']['content'][1]['type']);
        $this->assertEquals('String', $ast['body'][0]['value']['content'][1]['content'][0]['type']);
        try {
            $this->parserToThrowOnErrors->parse('<id "{{ \\"Foo\\" }}">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Identifier has to start with [a-zA-Z_]', $e->getMessage());
        }
        // unescaped identifier in placeable, nested
        try {
            $this->parserToThrowOnErrors->parse('<id "{{ \\"{{ bar }}\\" }}">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Identifier has to start with [a-zA-Z_]', $e->getMessage());
        }
        // unescaped identifier in placeable, nested x2
        try {
            $this->parserToThrowOnErrors->parse('<id "{{ \\"{{ \'Foo\' }}\\" }}">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Identifier has to start with [a-zA-Z_]', $e->getMessage());
        }
        // unescaped identifier in placeable, nested x2
        try {
            $this->parserToThrowOnErrors->parse('<id "{{ \\"{{ \\"Foo\\" }}\\" }}">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Identifier has to start with [a-zA-Z_]', $e->getMessage());
        }
        // unescaped identifier in placeable, nested x3
        try {
            $this->parserToThrowOnErrors->parse('<id "{{ \\"{{ \'{{ bar }}\' }}\\" }}">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Identifier has to start with [a-zA-Z_]', $e->getMessage());
        }
        // unescaped string in escaped placeable
        try {
            $this->parserToThrowOnErrors->parse('<id " da \\{{ "foo" }}">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Expected ">"', $e->getMessage());
        }
        // double escaped placeable
        try {
            $this->parserToThrowOnErrors->parse('<id "\\\\{{ \\"foo\\" }}">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Identifier has to start with [a-zA-Z_]', $e->getMessage());
        }
        // triple escaped placeable
        try {
            $this->parserToThrowOnErrors->parse('<id "\\\\\\{{ "foo" }}">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Expected ">"', $e->getMessage());
        }
        // triple escaped placeable with escaped backslash at the end
        try {
            $this->parserToThrowOnErrors->parse('<id "\\\\\\{{ "foo" }}\\">');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Expected ">"', $e->getMessage());
        }

    }

    /**
     *
     */
    public function test_expressions()
    {
        // expression
        $ast = $this->parser->parse('<id[0 == 1 || 1] "foo">');
        $this->assertEquals('||', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('==', $ast['body'][0]['index'][0]['left']['operator']['token']);

        $ast = $this->parser->parse('<id[a == b == c] "foo">');
        $this->assertEquals('==', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('==', $ast['body'][0]['index'][0]['left']['operator']['token']);

        $ast = $this->parser->parse('<id[ a == b || c == d || e == f ] "foo"  >');
        $this->assertEquals('||', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('||', $ast['body'][0]['index'][0]['left']['operator']['token']);
        $this->assertEquals('==', $ast['body'][0]['index'][0]['right']['operator']['token']);

        $ast = $this->parser->parse('<id[0 && 1 || 1] "foo">');
        $this->assertEquals('||', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('&&', $ast['body'][0]['index'][0]['left']['operator']['token']);

        $ast = $this->parser->parse('<id[0 && (1 || 1)] "foo">');
        $this->assertEquals('&&', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('||', $ast['body'][0]['index'][0]['right']['expression']['operator']['token']);

        $ast = $this->parser->parse('<id[1 || 1 && 0] "foo">');
        $this->assertEquals('||', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('&&', $ast['body'][0]['index'][0]['right']['operator']['token']);

        $ast = $this->parser->parse('<id[1 + 2] "foo">');
        $this->assertEquals('+', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals(1, $ast['body'][0]['index'][0]['left']['value']);
        $this->assertEquals(2, $ast['body'][0]['index'][0]['right']['value']);

        $ast = $this->parser->parse('<id[1 + 2 - 3 > 4 < 5 <= a >= "d" * 3 / q % 10] "foo">');
        $this->assertEquals('>=', $ast['body'][0]['index'][0]['operator']['token']);

        $ast = $this->parser->parse('<id[! +1] "foo">');
        $this->assertEquals('!', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('+', $ast['body'][0]['index'][0]['argument']['operator']['token']);
        $this->assertEquals(1, $ast['body'][0]['index'][0]['argument']['argument']['value']);

        $ast = $this->parser->parse('<id[1+2] "foo">');
        $this->assertEquals('+', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals(1, $ast['body'][0]['index'][0]['left']['value']);
        $this->assertEquals(2, $ast['body'][0]['index'][0]['right']['value']);

        $ast = $this->parser->parse('<id[(1+2)] "foo">');
        $this->assertEquals('+', $ast['body'][0]['index'][0]['expression']['operator']['token']);
        $this->assertEquals(1, $ast['body'][0]['index'][0]['expression']['left']['value']);
        $this->assertEquals(2, $ast['body'][0]['index'][0]['expression']['right']['value']);

        $ast = $this->parser->parse('<id[id2["foo"]] "foo2">');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals('foo2', $ast['body'][0]['value']['content']);
        $this->assertEquals('id2', $ast['body'][0]['index'][0]['expression']['name']);
        $this->assertEquals('foo', $ast['body'][0]['index'][0]['property']['content']);

        $ast = $this->parser->parse('<id[id["foo"]] "foo">');
        //$ast = $this->parser->parse('<id[id["foo"]]>');
        $this->assertEquals(1, count($ast['body']));
        //$this->assertEquals(null, $ast['body'][0]['value']);
        $this->assertEquals('id', $ast['body'][0]['index'][0]['expression']['name']);
        $this->assertEquals('foo', $ast['body'][0]['index'][0]['property']['content']);
        // expression errors
        $strings = [
            '<id[1+()] "foo">',
            '<id[1<>2] "foo">',
            '<id[1+=2] "foo">',
            '<id[>2] "foo">',
            '<id[1==] "foo">',
            '<id[1+ "foo">',
            '<id[2==1+] "foo">',
            '<id[2==3+4 "fpp">',
            '<id[2==3+ "foo">',
            '<id[2>>2] "foo">',
            '<id[1 ? 2 3] "foo">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
        // logical expression
        $ast = $this->parser->parse('<id[0 || 1] "foo">');
        $this->assertEquals('||', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals(0, $ast['body'][0]['index'][0]['left']['value']);
        $this->assertEquals(1, $ast['body'][0]['index'][0]['right']['value']);

        $ast = $this->parser->parse('<id[0 || 1 && 2 || 3] "foo">');
        $this->assertEquals('||', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('||', $ast['body'][0]['index'][0]['left']['operator']['token']);
        $this->assertEquals(3, $ast['body'][0]['index'][0]['right']['value']);
        $this->assertEquals(0, $ast['body'][0]['index'][0]['left']['left']['value']);
        $this->assertEquals(1, $ast['body'][0]['index'][0]['left']['right']['left']['value']);
        $this->assertEquals(2, $ast['body'][0]['index'][0]['left']['right']['right']['value']);
        $this->assertEquals('&&', $ast['body'][0]['index'][0]['left']['right']['operator']['token']);
        // logical expression errors
        $strings = [
            '<id[0 || && 1] "foo">',
            '<id[0 | 1] "foo">',
            '<id[0 & 1] "foo">',
            '<id[|| 1] "foo">',
            '<id[0 ||] "foo">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
        // binary expression
        $ast = $this->parser->parse('<id[a / b * c] "foo">');
        $this->assertEquals('*', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('/', $ast['body'][0]['index'][0]['left']['operator']['token']);

        $ast = $this->parser->parse('<id[8 * 9 % 11] "foo">');
        $this->assertEquals('%', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('*', $ast['body'][0]['index'][0]['left']['operator']['token']);

        $ast = $this->parser->parse('<id[6 + 7 - 8 * 9 / 10 % 11] "foo">');
        $this->assertEquals('-', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('+', $ast['body'][0]['index'][0]['left']['operator']['token']);
        $this->assertEquals('%', $ast['body'][0]['index'][0]['right']['operator']['token']);

        $ast = $this->parser->parse('<id[0 == 1 != 2 > 3 < 4 >= 5 <= 6 + 7 - 8 * 9 / 10 % 11] "foo">');
        $this->assertEquals('!=', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('==', $ast['body'][0]['index'][0]['left']['operator']['token']);
        $this->assertEquals('<=', $ast['body'][0]['index'][0]['right']['operator']['token']);

        // binary expression errors
        $strings = [
            '<id[1 \\ 2] "foo">',
            '<id[1 ** 2] "foo">',
            '<id[1 * / 2] "foo">',
            '<id[1 !> 2] "foo">',
            '<id[1 <* 2] "foo">',
            '<id[1 += 2] "foo">',
            '<id[1 %= 2] "foo">',
            '<id[1 ^ 2] "foo">',
            '<id 2 < 3 "foo">',
            '<id 2 > 3 "foo">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
        // unary expression
        $ast = $this->parser->parse('<id[! + - 1] "foo">');
        $this->assertEquals('!', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('+', $ast['body'][0]['index'][0]['argument']['operator']['token']);
        $this->assertEquals('-', $ast['body'][0]['index'][0]['argument']['argument']['operator']['token']);
        // unary expression errors
        $strings = [
            '<id[a ! v] "foo">',
            '<id[!] "foo">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
        // call expression
        $ast = $this->parser->parse('<id[foo()] "foo">');
        $this->assertEquals('foo', $ast['body'][0]['index'][0]['callee']['name']);
        $this->assertEquals(0, count($ast['body'][0]['index'][0]['arguments']));

        $ast = $this->parser->parse('<id[foo(d, e, f, g)] "foo">');
        $this->assertEquals('foo', $ast['body'][0]['index'][0]['callee']['name']);
        $this->assertEquals(4, count($ast['body'][0]['index'][0]['arguments']));
        $this->assertEquals('d', $ast['body'][0]['index'][0]['arguments'][0]['name']);
        $this->assertEquals('e', $ast['body'][0]['index'][0]['arguments'][1]['name']);
        $this->assertEquals('f', $ast['body'][0]['index'][0]['arguments'][2]['name']);
        $this->assertEquals('g', $ast['body'][0]['index'][0]['arguments'][3]['name']);
        // call expression errors
        $strings = [
            '<id[1+()] "foo">',
            '<id[foo(fo fo)] "foo">',
            '<id[foo(()] "foo">',
            '<id[foo(())] "foo">',
            '<id[foo())] "foo">',
            '<id[foo("ff)] "foo">',
            '<id[foo(ff")] "foo">',
            '<id[foo(a, b, )] "foo">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
        // member expression
        $ast = $this->parser->parse('<id[x["d"]] "foo">');
        $this->assertEquals('x', $ast['body'][0]['index'][0]['expression']['name']);
        $this->assertEquals('d', $ast['body'][0]['index'][0]['property']['content']);

        $ast = $this->parser->parse('<id[x.d] "foo">');
        $this->assertEquals('x', $ast['body'][0]['index'][0]['expression']['name']);
        $this->assertEquals('d', $ast['body'][0]['index'][0]['property']['name']);

        $ast = $this->parser->parse('<id[a||b.c] "foo">');
        $this->assertEquals('||', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('b', $ast['body'][0]['index'][0]['right']['expression']['name']);

        $this->parser->parse('<id[ x.d ] "foo" >');
        $this->parser->parse('<id[ x[ "d" ] ] "foo" >');
        $this->parser->parse('<id[ x["d"] ] "foo" >');
        $this->parser->parse('<id[x["d"]["e"]] "foo" >');
        $this->parser->parse('<id[! (a?b:c)["d"]["e"]] "foo" >');
        // member expression errors
        $strings = [
            '<id[x[[]] "foo">',
            '<id[x[] "foo">',
            '<id[x[1 "foo">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
        // attribute expression
        $ast = $this->parser->parse('<id[x::["d"]] "foo">');
        $this->assertEquals('x', $ast['body'][0]['index'][0]['expression']['name']);
        $this->assertEquals('d', $ast['body'][0]['index'][0]['attribute']['content']);

        $ast = $this->parser->parse('<id[x::d] "foo">');
        $this->assertEquals('x', $ast['body'][0]['index'][0]['expression']['name']);
        $this->assertEquals('d', $ast['body'][0]['index'][0]['attribute']['name']);
        // attribute expression errors
        $strings = [
            '<id[x:::d] "foo">',
            '<id[x[::"d"]] "foo">',
            '<id[x[::::d]] "foo">',
            '<id[x:::[d]] "foo">',
            '<id[x.y::z] "foo">',
            '<id[x::y::z] "foo">',
            '<id[x.y::["z"]] "foo">',
            '<id[x::y::["z"]] "foo">',
            '<id[x::[1 "foo">',
            '<id[x()::attr1] "foo">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
        // parenthesis expression
        $ast = $this->parser->parse('<id[(1 + 2) * 3] "foo">');
        $this->assertEquals('*', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('+', $ast['body'][0]['index'][0]['left']['expression']['operator']['token']);

        $ast = $this->parser->parse('<id[(1) + ((2))] "foo">');
        $this->assertEquals('+', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals(2, $ast['body'][0]['index'][0]['right']['expression']['expression']['value']);

        $ast = $this->parser->parse('<id[(a||b).c] "foo">');
        $this->assertEquals('||', $ast['body'][0]['index'][0]['expression']['expression']['operator']['token']);
        $this->assertEquals('c', $ast['body'][0]['index'][0]['property']['name']);

        $ast = $this->parser->parse('<id[!(a||b).c] "foo">');
        $this->assertEquals('!', $ast['body'][0]['index'][0]['operator']['token']);
        $this->assertEquals('||', $ast['body'][0]['index'][0]['argument']['expression']['expression']['operator']['token']);
        $this->assertEquals('c', $ast['body'][0]['index'][0]['argument']['property']['name']);

        $ast = $this->parser->parse('<id[a().c] "foo">');
        $this->assertEquals('a', $ast['body'][0]['index'][0]['expression']['callee']['name']);
        $this->assertEquals('c', $ast['body'][0]['index'][0]['property']['name']);
        // parenthesis expression errors
        $strings = [
            '<id[1+()] "foo">',
            '<id[(+)*(-)] "foo">',
            '<id[(!)] "foo">',
            '<id[(())] "foo">',
            '<id[(] "foo">',
            '<id[)] "foo">',
            '<id[1+(2] "foo">',
            '<id[a().c.[d]()] "foo">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
        // primary expression
        $ast = $this->parser->parse('<id[$foo] "foo">');
        $this->assertEquals('foo', $ast['body'][0]['index'][0]['id']['name']);

        $ast = $this->parser->parse('<id[@foo] "foo">');
        $this->assertEquals('foo', $ast['body'][0]['index'][0]['id']['name']);

        $ast = $this->parser->parse('<id[~] "foo">');
        $this->assertEquals('ThisExpression', $ast['body'][0]['index'][0]['type']);
        // literal expression
        $ast = $this->parser->parse('<id[012] "foo">');
        $this->assertEquals(12, $ast['body'][0]['index'][0]['value']);
        // literal expression errors
        $strings = [
            '<id[012x1] "foo">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
        // value expression
        $ast = $this->parser->parse('<id["foo"] "foo">');
        $this->assertEquals('foo', $ast['body'][0]['index'][0]['content']);

        $ast = $this->parser->parse('<id[{a: "foo", b: "foo2"}] "foo">');
        $this->assertEquals('foo', $ast['body'][0]['index'][0]['content'][0]['value']['content']);
        $this->assertEquals('foo2', $ast['body'][0]['index'][0]['content'][1]['value']['content']);
        // value expression errors
        $strings = [
            '<id[[0, 1]] "foo">',
            '<id["foo] "foo">',
            '<id[foo"] "foo">',
            '<id[["foo]] "foo">',
            '<id[{"a": "foo"}] "foo">',
            '<id[{a: 0}] "foo">',
            '<id[{a: "foo"] "foo">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
    }

    /**
     *
     */
    public function test_hash()
    {
        // hash value
        $ast = $this->parser->parse('<id {a: "b", a2: "c", d: "d" }>');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals(3, count($ast['body'][0]['value']['content']));
        $this->assertEquals('b', $ast['body'][0]['value']['content'][0]['value']['content']);

        $ast = $this->parser->parse('<id {a: "2", b: "3"} >');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals(2, count($ast['body'][0]['value']['content']));
        $this->assertEquals('2', $ast['body'][0]['value']['content'][0]['value']['content']);
        $this->assertEquals('3', $ast['body'][0]['value']['content'][1]['value']['content']);
        // hash value with trailing comma
        $ast = $this->parser->parse('<id {a: "2", b: "3", } >');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals(2, count($ast['body'][0]['value']['content']));
        $this->assertEquals('2', $ast['body'][0]['value']['content'][0]['value']['content']);
        $this->assertEquals('3', $ast['body'][0]['value']['content'][1]['value']['content']);
        // nested hash value
        $ast = $this->parser->parse('<id {a: "foo", b: {a2: "p"}}>');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals(2, count($ast['body'][0]['value']['content']));
        $this->assertEquals('foo', $ast['body'][0]['value']['content'][0]['value']['content']);
        $this->assertEquals('a2', $ast['body'][0]['value']['content'][1]['value']['content'][0]['key']['name']);
        $this->assertEquals('p', $ast['body'][0]['value']['content'][1]['value']['content'][0]['value']['content']);
        // hash with default
        $ast = $this->parser->parse('<id {a: "v", *b: "c"}>');
        $this->assertEquals(true, $ast['body'][0]['value']['content'][1]['default']);
        // hash errors
        $strings = [
            '<id {}>',
            '<id {a: 2}>',
            '<id {a: "d">',
            '<id a: "d"}>',
            '<id {{a: "d"}>',
            '<id {a: "d"}}>',
            '<id {a:} "d"}>',
            '<id {2}>',
            '<id {"a": "foo"}>',
            '<id {"a": \'foo\'}>',
            '<id {2: "foo"}>',
            '<id {a:"foo"b:"foo"}>',
            '<id {a }>',
            '<id {a: 2, b , c: 3 } >',
            '<id {*a: "v", *b: "c"}>',
            '<id {}>',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
    }

    /**
     *
     */
    public function test_parser()
    {
        // empty entity
        $ast = $this->parser->parse('<id>');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        // empty entity with white space
        $ast = $this->parser->parse('<id >');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        // basic errors
        $strings = [
            '< "str\\"ing">',
            '<>',
            '<id',
            '<id ',
            'id>',
            '<id "value>',
            '<id value">',
            '<id \'value>',
            '<id value\'',
            '<id\'value\'>',
            '<id"value">',
            '<id """value"""">',
            '< id "value">',
            '<()>',
            '<+s>',
            '<id-id2>',
            '<-id>',
            '<id 2>',
            '<"id">',
            '<\'id\'>',
            '<2>',
            '<09>',
        ];

        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
        // index
        //$ast = $this->parser->parse("<id[]>");
        //$this->assertEquals(1, count($ast['body']));
        //$this->assertEquals(0, count($ast['body'][0]['index']));
        //$ast = $this->parser->parse("<id[ ] >");
        $ast = $this->parser->parse('<id["foo"] "foo2">');
        $this->assertEquals('foo', $ast['body'][0]['index'][0]['content']);
        $this->assertEquals('foo2', $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse('<id[2] "foo2">');
        $this->assertEquals(2, $ast['body'][0]['index'][0]['value']);
        $this->assertEquals('foo2', $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse('<id[2, "foo", 3] "foo2">');
        $this->assertEquals(2, $ast['body'][0]['index'][0]['value']);
        $this->assertEquals('foo', $ast['body'][0]['index'][1]['content']);
        $this->assertEquals(3, $ast['body'][0]['index'][2]['value']);
        $this->assertEquals('foo2', $ast['body'][0]['value']['content']);
        // index errors
        $strings = [
            '<id[ "foo">',
            '<id] "foo">',
            '<id[ \'] "foo">',
            '<id{ ] "foo">',
            '<id[ } "foo">',
            '<id[" ] "["a"]>',
            '<id[a]["a"]>',
            '<id["foo""foo"] "fo">',
            '<id[a, b, ] "foo">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
        // macro
        $ast = $this->parser->parse('<id($n) {2}>');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals(1, count($ast['body'][0]['args']));
        $this->assertEquals(2, $ast['body'][0]['expression']['value']);

        $ast = $this->parser->parse('<id( $n, $m, $a ) {2}  >');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals(3, count($ast['body'][0]['args']));
        $this->assertEquals(2, $ast['body'][0]['expression']['value']);
        // macro errors
        $strings = [
            '<id (n) {2}>',
            '<id ($n) {2}>',
            '<(n) {2}>',
            '<id(() {2}>',
            '<id()) {2}>',
            '<id[) {2}>',
            '<id(] {2}>',
            '<id(-) {2}>',
            '<id(2+2) {2}>',
            '<id("a") {2}>',
            '<id(\'a\') {2}>',
            '<id(2) {2}>',
            '<_id($n) {2}>',
            '<id($n) 2}>',
            '<id($n',
            '<id($n ',
            '<id($n)',
            '<id($n) ',
            '<id($n) {',
            '<id($n) { ',
            '<id($n) {2',
            '<id($n) {2}',
            '<id(nm nm) {2}>',
            '<id($n) {}>',
            '<id($n, $m ,) {2}>',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
        // comment
        $ast = $this->parser->parse('/* test */');
        $this->assertEquals(' test ', $ast['body'][0]['content']);
        // comment errors
        $strings = [
            '/* foo ',
            'foo */',
            '<id /* test */ "foo">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
        // identifier
        /*$ast = $this->parser->parse('<id>');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals('id', $ast['body'][0]['id']['name']);

        $ast = $this->parser->parse('<ID>');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals('ID', $ast['body'][0]['id']['name']);*/
        // identifier errors
        $strings = [
            '<i`d "foo">',
            '<0d "foo">',
            '<09 "foo">',
            '<i!d "foo">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }

        try {
            $ast = $this->parserToThrowOnErrors->parse('<id<');
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Expected white space', $e->getMessage());
        }

        $ast = $this->parserToThrowOnErrors->parse('<id "value"> <id2 "value2">');
        $this->assertEquals('value2', $ast['body'][1]['value']['content']);

        // import
        $ast = $this->parser->parse('import("./foo.l20n")');
        $this->assertEquals('ImportStatement', $ast['body'][0]['type']);
        $this->assertEquals('./foo.l20n', $ast['body'][0]['uri']['content']);
        // import errors
        $strings = [
            '@import("foo.l20n")',
            'import)(',
            'import(()',
            'import("foo.l20n"]',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }

    }

    /**
     *
     */
    public function test_string()
    {
        // string value
        $ast = $this->parser->parse('<id "">');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals('Entity', $ast['body'][0]['type']);
        $this->assertEquals('String', $ast['body'][0]['value']['type']);
        $this->assertEquals('', $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse('<id """""">');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals('Entity', $ast['body'][0]['type']);
        $this->assertEquals('String', $ast['body'][0]['value']['type']);
        $this->assertEquals('', $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse('<id \'string\'>');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals('Entity', $ast['body'][0]['type']);
        $this->assertEquals('id', $ast['body'][0]['id']['name']);
        $this->assertEquals('string', $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse('<id \'\'\'string\'\'\'>');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals('Entity', $ast['body'][0]['type']);
        $this->assertEquals('id', $ast['body'][0]['id']['name']);
        $this->assertEquals('string', $ast['body'][0]['value']['content']);

        $ast = $this->parser->parse('<id """string""">');
        $this->assertEquals(1, count($ast['body']));
        $this->assertEquals('Entity', $ast['body'][0]['type']);
        $this->assertEquals('id', $ast['body'][0]['id']['name']);
        $this->assertEquals('string', $ast['body'][0]['value']['content']);

        // complex string
        $ast = $this->parser->parse('<id "test {{ var }} test2">');
        $this->assertEquals('test ', $ast['body'][0]['value']['content'][0]['content']);
        $this->assertEquals('var', $ast['body'][0]['value']['content'][1]['name']);
        $this->assertEquals(' test2', $ast['body'][0]['value']['content'][2]['content']);

        $ast = $this->parser->parse("<id \"test \\\" {{ var }} test2\">");
        $this->assertEquals('test " ', $ast['body'][0]['value']['content'][0]['content']);
        $this->assertEquals('var', $ast['body'][0]['value']['content'][1]['name']);
        $this->assertEquals(' test2', $ast['body'][0]['value']['content'][2]['content']);

        $ast = $this->parser->parse("<id 'test \\{{ var }} test2'>");
        $this->assertEquals('test {{ var }} test2', $ast['body'][0]['value']['content']);
        // complex string errors
        $strings = [
            '<id "test {{ var ">',
            '<id "test {{ var \\}} ">',
        ];
        foreach ($strings as $string) {
            $ast = $this->parser->parse($string);
            $this->assertEquals('JunkEntry', $ast['body'][0]['type']);
        }
    }

    /**
     *
     */
    public function test_insecure_dos()
    {
        // Quadratic Blowup
        $source = '
/*
 * Project Gutenberg\'s Alice\'s Adventures in Wonderland,
 * by Lewis Carroll
 *
 * This eBook is for the use of anyone anywhere at no cost and with
 * almost no restrictions whatsoever.  You may copy it, give it away
 * or re-use it under the terms of the Project Gutenberg License
 * included with this eBook or online at www.gutenberg.org
 */

<alice """

  CHAPTER I. Down the Rabbit-Hole

  Alice was beginning to get very tired of sitting by her sister on
  the bank, and of having nothing to do: once or twice she had peeped
  into the book her sister was reading, but it had no pictures or
  conversations in it, \'and what is the use of a book,\' thought
  Alice \'without pictures or conversation?\'

  So she was considering in her own mind (as well as she could, for
  the hot day made her feel very sleepy and stupid), whether the
  pleasure of making a daisy-chain would be worth the trouble of
  getting up and picking the daisies, when suddenly a White Rabbit
  with pink eyes ran close by her.

  There was nothing so VERY remarkable in that; nor did Alice think
  it so VERY much out of the way to hear the Rabbit say to itself,
  \'Oh dear!  Oh dear! I shall be late!\' (when she thought it over
  afterwards, it occurred to her that she ought to have wondered at
  this, but at the time it all seemed quite natural); but when the
  Rabbit actually TOOK A WATCH OUT OF ITS WAISTCOAT-POCKET, and
  looked at it, and then hurried on, Alice started to her feet, for
  it flashed across her mind that she had never before seen a rabbit
  with either a waistcoat-pocket, or a watch to take out of it, and
  burning with curiosity, she ran across the field after it, and
  fortunately was just in time to see it pop down a large rabbit-hole
  under the hedge.

  In another moment down went Alice after it, never once considering
  how in the world she was to get out again.

  The rabbit-hole went straight on like a tunnel for some way, and
  then dipped suddenly down, so suddenly that Alice had not a moment
  to think about stopping herself before she found herself falling
  down a very deep well.

""">

<malice """
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
  {{alice}} {{alice}} {{alice}} {{alice}} {{alice}} {{alice}}
""">
';
        try {
            $this->parserToThrowOnErrors->parse($source);
            $this->assertTrue(false);
        } catch (ParserException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'Too many placeables') !== false);
        }
    }
}
