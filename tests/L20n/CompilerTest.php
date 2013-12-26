<?php

use L20n\Parser;
use L20n\Compiler;
use L20n\Compiler\Exception\CompilerException;
use L20n\Compiler\Exception\IndexException;
use L20n\Compiler\Exception\ValueException;

/**
 * Class CompilerTest
 */
class CompilerTest extends PHPUnit_Framework_TestCase
{
    /** @var Parser */
    private $parser;
    /** @var Compiler */
    private $compiler;
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
        $this->compiler = new Compiler();
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
        // missing or invalid syntax
        $source = '
<foo "Foo"
 attr: "An attribute"
>
<getAttr "{{ foo::missing }}">
<getPropOfAttr "{{ foo::attr.missing }}">
<getPropOfMissing "{{ foo::missing.another }}">
<getAttrOfParen "{{ (foo::attr)::invalid }}">
<getAttrOfMissing "{{ (foo::missing)::invalid }}">
';
        $ast = $this->parser->parse($source);
        /** @var \stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->getAttr->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'has no attribute missing') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->getPropOfAttr->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cannot get property of a string: missing', $e->getMessage());
            $this->assertTrue(true);
        }
        try {
            $env->getPropOfMissing->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'has no attribute') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->getAttrOfParen->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cannot get attribute of a non-entity: invalid', $e->getMessage());
            $this->assertTrue(true);
        }
        try {
            $env->getAttrOfMissing->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'has no attribute missing') !== false);
            $this->assertTrue(true);
        }

        // with string values
        $source = '
<foo "Foo"
 attr: "An attribute"
 attrComplex: "An attribute of {{ foo }}"
>
<getFoo "{{ foo::attr }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $entity = $env->foo->get();
        $this->assertEquals('An attribute', $entity->attributes->attr);
        $entity = $env->foo->get();
        $this->assertEquals('An attribute of Foo', $entity->attributes->attrComplex);
        $this->assertEquals('string', gettype($env->foo->attributes->attr->value));
        $this->assertTrue(is_callable($env->foo->attributes->attrComplex->value));
        $this->assertEquals('An attribute', $env->getFoo->getString());

        // with hash values (no defval, no index)
        $source = '
<brandName "Firefox"
  title: {
    win: "Firefox for Windows",
    linux: "Firefox for Linux"
  }
>
<about "About {{ brandName::title }}">
<aboutWin "About {{ brandName::title.win }}">
<aboutMac "About {{ brandName::title.mac }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->about->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'Hash key lookup failed') !== false);
            $this->assertTrue(true);
        }
        $this->assertEquals('About Firefox for Windows', $env->aboutWin->getString());
        try {
            $env->aboutMac->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'Hash key lookup failed') !== false);
            $this->assertTrue(true);
        }

        // with hash values (defval, no index)
        $source = '
<brandName "Firefox"
  title: {
   *win: "Firefox for Windows",
    linux: "Firefox for Linux"
  }
>
<about "About {{ brandName::title }}">
<aboutMac "About {{ brandName::title.mac }}">
<aboutLinux "About {{ brandName::title.linux }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('About Firefox for Windows', $env->about->getString());
        $this->assertEquals('About Firefox for Linux', $env->aboutLinux->getString());
        $this->assertEquals('About Firefox for Windows', $env->aboutMac->getString());

        // with hash values (no defval, index)
        $source = '
<brandName "Firefox"
  title["win"]: {
    win: "Firefox for Windows",
    linux: "Firefox for Linux"
  }
>
<about "About {{ brandName::title }}">
<aboutMac "About {{ brandName::title.mac }}">
<aboutLinux "About {{ brandName::title.linux }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('About Firefox for Windows', $env->about->getString());
        $this->assertEquals('About Firefox for Linux', $env->aboutLinux->getString());
        $this->assertEquals('About Firefox for Windows', $env->aboutMac->getString());

        // with hash values (defval, index)
        $source = '
<brandName "Firefox"
  title["win"]: {
    win: "Firefox for Windows",
   *linux: "Firefox for Linux"
  }
>
<about "About {{ brandName::title }}">
<aboutMac "About {{ brandName::title.mac }}">
<aboutLinux "About {{ brandName::title.linux }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('About Firefox for Windows', $env->about->getString());
        $this->assertEquals('About Firefox for Linux', $env->aboutLinux->getString());
        $this->assertEquals('About Firefox for Windows', $env->aboutMac->getString());

        // with hash values (no defval, missing key in index)
        $source = '
<brandName "Firefox"
  title["mac"]: {
    win: "Firefox for Windows",
    linux: "Firefox for Linux"
  }
>
<about "About {{ brandName::title }}">
<aboutMac "About {{ brandName::title.mac }}">
<aboutLinux "About {{ brandName::title.linux }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->about->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'Hash key lookup failed') !== false);
            $this->assertTrue(true);
        }
        $this->assertEquals('About Firefox for Linux', $env->aboutLinux->getString());
        try {
            $env->aboutMac->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'Hash key lookup failed') !== false);
            $this->assertTrue(true);
        }

        // with hash values (no defval, extra key in index)
        $source = '
<brandName "Firefox"
  title["win", "metro"]: {
    win: "Firefox for Windows",
    linux: "Firefox for Linux"
  }
>
<about "About {{ brandName::title }}">
<aboutMac "About {{ brandName::title.mac }}">
<aboutLinux "About {{ brandName::title.linux }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('About Firefox for Windows', $env->about->getString());
        $this->assertEquals('About Firefox for Linux', $env->aboutLinux->getString());
        $this->assertEquals('About Firefox for Windows', $env->aboutMac->getString());

        // with nested hash values (defval, no index)
        $source = '
<brandName "Firefox"
  title: {
   *win: {
      metro: "Firefox for Windows 8",
     *other: "Firefox for Windows"
    },
    linux: "Firefox for Linux"
  }
>
<about "About {{ brandName::title }}">
<aboutWin "About {{ brandName::title.win }}">
<aboutMetro "About {{ brandName::title.win.metro }}">
<aboutMac "About {{ brandName::title.mac }}">
<aboutLinux "About {{ brandName::title.linux }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('About Firefox for Windows', $env->about->getString());
        $this->assertEquals('About Firefox for Windows', $env->aboutWin->getString());
        $this->assertEquals('About Firefox for Linux', $env->aboutLinux->getString());
        $this->assertEquals('About Firefox for Windows 8', $env->aboutMetro->getString());
        $this->assertEquals('About Firefox for Windows', $env->aboutMac->getString());

        // with nested hash values (no defval, double index)
        $source = '
<brandName "Firefox"
  title["win", "other"]: {
    win: {
      metro: "Firefox for Windows 8",
      other: "Firefox for Windows"
    },
    linux: "Firefox for Linux",
    mobile: {
       android: "Firefox for Android",
       fxos: "Firefox for Firefox OS",
       other: "Firefox for Mobile"
    }
  }
>
<about "About {{ brandName::title }}">
<aboutWin "About {{ brandName::title.win }}">
<aboutMetro "About {{ brandName::title.win.metro }}">
<aboutMac "About {{ brandName::title.mac }}">
<aboutLinux "About {{ brandName::title.linux }}">
<aboutMobile "About {{ brandName::title.mobile }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('About Firefox for Windows', $env->about->getString());
        $this->assertEquals('About Firefox for Windows', $env->aboutWin->getString());
        $this->assertEquals('About Firefox for Linux', $env->aboutLinux->getString());
        $this->assertEquals('About Firefox for Windows 8', $env->aboutMetro->getString());
        $this->assertEquals('About Firefox for Windows', $env->aboutMac->getString());
        $this->assertEquals('About Firefox for Mobile', $env->aboutMobile->getString());

        // with nested hash values (no index on attr, index on the entity)
        $source = '
<brandName["beta"] {
  release: "Firefox",
  beta: "Firefox Beta",
  testing: "Aurora"
}
 accesskey: {
   release: "F",
   beta: "B",
   testing: "A"
 }
>
<about "About {{ brandName }}">
<press "Press {{ brandName::accesskey }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('About Firefox Beta', $env->about->getString());
        try {
            $env->press->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'Hash key lookup failed') !== false);
            $this->assertTrue(true);
        }

        // with nested hash values (index different than the entitiy\'s)
        $source = '
<brandName["beta"] {
  release: "Firefox",
  beta: "Firefox Beta",
  testing: "Aurora"
}
 accesskey["testing"]: {
   release: "F",
   beta: "B",
   testing: "A"
 }
>
<about "About {{ brandName }}">
<press "Press {{ brandName::accesskey }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('About Firefox Beta', $env->about->getString());
        $this->assertEquals('Press A', $env->press->getString());

        // with relative this-references
        $source = '
<brandName "Firefox"
  title: "Mozilla {{ ~ }}"
>
<about "About {{ brandName::title }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $entity = $env->brandName->get();
        $this->assertEquals('Mozilla Firefox', $entity->attributes->title);
        $this->assertEquals('About Mozilla Firefox', $env->about->getString());

        // with relative this-references and a property expression
        $source = '
<brandName {
 *subjective: "Firefox",
  possessive: "Firefox\'s"
}
 license: "{{ ~.possessive }} license"
>
<about "About {{ brandName::license }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $entity = $env->brandName->get();
        $this->assertEquals('Firefox\'s license', $entity->attributes->license);
        $this->assertEquals('About Firefox\'s license', $env->about->getString());

        // referenced by a this-reference
        $source = '
<brandName "{{ ~::title }}"
  title: "Mozilla Firefox"
>
<about "About {{ brandName }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Mozilla Firefox', $env->brandName->getString());
        $this->assertEquals('About Mozilla Firefox', $env->about->getString());

        // cyclic this-reference
        $source = '
<brandName "Firefox"
  title: "{{ ~::title }}"
>
<about "About {{ brandName::title }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->brandName->get();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
            $this->assertTrue(true);
        }
        try {
            $env->about->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
            $this->assertTrue(true);
        }

        // complex but non-cyclic this-reference
        $source = '
<foo "Foo"
  attr: {
    bar: "{{ ~::attr.self }} Bar",
   *baz: "{{ ~::attr.bar }} Baz",
    self: "{{ ~ }}"
  }
>
<quux "{{ foo::attr }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Foo Bar Baz', $env->quux->getString());
    }

    /**
     *
     */
    public function test_env()
    {
        // works
        $source = '
<foo "Foo">
<getFoo "{{ foo }}">
<getBar "{{ bar }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Foo', $env->foo->getString());
        $this->assertEquals('Foo', $env->getFoo->getString());
        try {
            $env->getBar->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'unknown entry: bar') !== false);
            $this->assertTrue(true);
        }

        // cannot be modified by another compilation
        $source2 = '
<foo "Baz">
<bar "Bar">
';
        $ast2 = $this->parser->parse($source2);
        /** @var stdClass $env */
        $this->compiler->compile($ast2);
        $this->assertEquals('Foo', $env->foo->getString());
        $this->assertEquals('Foo', $env->getFoo->getString());
        try {
            $env->getBar->getString();
            $this->assertFalse(true);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'unknown entry: bar') !== false);
            $this->assertTrue(true);
        }
    }

    /**
     *
     */
    public function test_erros()
    {
        // A complex string referencing an existing entity
        $source = '
<_file "file">
<prompt["remove"] {
  remove: "Remove {{ _file }}?",
  keep: "Keep {{ _file }}?"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Remove file?', $env->prompt->getString());
        // A complex string referencing a missing entity
        $source = '
<prompt["remove"] {
  remove: "Remove {{ _file }}?",
  keep: "Keep {{ _file }}?"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->prompt->getString();
            $this->assertTrue(false);
        } catch (ValueException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->prompt->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'unknown entry') !== false);
            $this->assertTrue(strpos($e->getMessage(), 'unknown entry') !== false);
        }
        // An existing entity in the index
        $source = '
<_keep "keep">
<prompt[_keep] {
  remove: "Remove file?",
  keep: "Keep file?"
}>
<bypass "{{ prompt.remove }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Keep file?', $env->prompt->getString());
        $this->assertEquals('Remove file?', $env->bypass->getString());
        // A missing entity in the index
        $source = '
<prompt[_keep] {
  remove: "Remove file?",
  keep: "Keep file?"
}>
<bypass "{{prompt.remove}}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->prompt->getString();
            $this->assertTrue(false);
        } catch (IndexException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->prompt->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'unknown entry') !== false);
        }
        $this->assertEquals('Remove file?', $env->bypass->getString());
        // A complex string referencing an existing entity in the index
        $source = '
<_keep "keep">
<prompt["{{ _keep }}"] {
  remove: "Remove file?",
  keep: "Keep file?"
}>
<bypass "{{ prompt.remove }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Keep file?', $env->prompt->getString());
        $this->assertEquals('Remove file?', $env->bypass->getString());
        // A complex string referencing a missing entity in the index
        $source = '
<prompt["{{ _keep }}"] {
  remove: "Remove file?",
  keep: "Keep file?"
}>
<bypass "{{ prompt.remove }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->prompt->getString();
            $this->assertTrue(false);
        } catch (IndexException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->prompt->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'unknown entry') !== false);
        }
        $this->assertEquals('Remove file?', $env->bypass->getString());


        // Member look-up order: property, default value

        // No index, with a default value set
        $source = '
<settings {
  win: "Options",
 *other: "Preferences"
}>
<bypass "{{ settings.win }}">
<bypassNoKey "{{ settings.lin }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Preferences', $env->settings->getString());
        $this->assertEquals('Options', $env->bypass->getString());
        $this->assertEquals('Preferences', $env->bypassNoKey->getString());
        // No index, without a default value set
        $source = '
<settings {
  win: "Options",
  other: "Preferences"
}>
<bypass "{{ settings.win }}">
<bypassNoKey "{{ settings.lin }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->settings->getString();
            $this->assertTrue(false);
        } catch (IndexException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->settings->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Hash key lookup failed.', $e->getMessage());
        }
        $this->assertEquals('Options', $env->bypass->getString());
        try {
            // This will actually throw a ValueError, not IndexError, because we're
            // asking for `bypassNoKey`, not `settings` directly.  There is no API
            // to directly request a member of a hash value of an entity.  The way
            // we know this test works is by checking the message of the error.
            $env->bypassNoKey->getString();
            $this->assertTrue(false);
        } catch (ValueException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->bypassNoKey->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Hash key lookup failed (tried "lin").', $e->getMessage());
        }


        // Member look-up order: property, index, default value

        // A valid reference in the index, with a default value set
        $source = '
/* this would normally be a global, @os, not a variable */
<settings[$os] {
  win: "Options",
 *other: "Preferences"
}>
<bypass "{{ settings.win }}">
<bypassNoKey "{{ settings.lin }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Options', $env->settings->getString(json_decode('{"os": "win"}')));
        $this->assertEquals('Preferences', $env->settings->getString(json_decode('{"os": "mac"}')));
        $this->assertEquals('Options', $env->bypass->getString(json_decode('{"os": "mac"}')));
        $this->assertEquals('Options', $env->bypassNoKey->getString(json_decode('{"os": "win"}')));
        $this->assertEquals('Preferences', $env->bypassNoKey->getString(json_decode('{"os": "mac"}')));
        // An invalid reference in the index, with a default value set
        $source = '
        /* this would normally be a global, @os, not a variable */
<settings[$os] {
  win: "Options",
 *other: "Preferences"
}>
<bypass "{{ settings.win }}">
<bypassNoKey "{{ settings.lin }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->settings->getString();
            $this->assertTrue(false);
        } catch (IndexException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->settings->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'unknown variable') !== false);
        }
        $this->assertEquals('Options', $env->bypass->getString());
        try {
            // This will actually throw a ValueError, not IndexError.  See above.
            $env->bypassNoKey->getString();
            $this->assertTrue(false);
        } catch (ValueException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->bypassNoKey->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'unknown variable') !== false);
        }
        // A valid reference in the index, without a default value
        $source = '
/* this would normally be a global, @os, not a variable */
<settings[$os] {
  win: "Options",
  other: "Preferences"
}>
<bypass "{{ settings.win }}">
<bypassNoKey "{{ settings.lin }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Options', $env->settings->getString(json_decode('{"os": "win"}')));
        try {
            $env->settings->getString(json_decode('{"os": "mac"}'));
            $this->assertTrue(false);
        } catch (IndexException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->settings->getString(json_decode('{"os": "mac"}'));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'Hash key lookup failed') !== false);
        }
        $this->assertEquals('Options', $env->bypass->getString(json_decode('{"os": "mac"}')));
        $this->assertEquals('Options', $env->bypassNoKey->getString(json_decode('{"os": "win"}')));
        try {
            // This will actually throw a ValueError, not IndexError.  See above.
            $env->bypassNoKey->getString(json_decode('{"os": "mac"}'));
            $this->assertTrue(false);
        } catch (ValueException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->bypassNoKey->getString(json_decode('{"os": "mac"}'));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Hash key lookup failed (tried "lin", "mac").', $e->getMessage());
        }
        // An invalid reference in the index, without a default value
        $source = '
/* this would normally be a global, @os, not a variable */
<settings[$os] {
  win: "Options",
  other: "Preferences"
}>
<bypass "{{ settings.win }}">
<bypassNoKey "{{ settings.lin }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->settings->getString();
            $this->assertTrue(false);
        } catch (IndexException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->settings->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'unknown variable') !== false);
        }
        $this->assertEquals('Options', $env->bypass->getString());
        try {
            // This will actually throw a ValueError, not IndexError.  See above.
            $env->bypassNoKey->getString();
            $this->assertTrue(false);
        } catch (ValueException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->bypassNoKey->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'unknown variable') !== false);
        }

    }

    /**
     *
     */
    public function test_expression()
    {
        // maths
        $source = '
<double($n) { $n + $n }>
<quadruple($n) { double(double($n)) }>
<fib($n) { $n == 0 ?
             0 :
             $n == 1 ?
               1 :
               fib($n - 1) + fib($n - 2) }>
<fac($n) { $n == 0 ?
             1 :
             $n * fac($n - 1) }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        // args are passed as [locals, value] tuple
        $value = $env->double->_call([[null, 3]]);
        $this->assertEquals(6, $value[1]);
        $value = $env->quadruple->_call([[null, 3]]);
        $this->assertEquals(12, $value[1]);
        $value = $env->fib->_call([[null, 12]]);
        $this->assertEquals(144, $value[1]);
        $value = $env->fac->_call([[null, 5]]);
        $this->assertEquals(120, $value[1]);

        // plural
        $source = '
<plural($n) {
  $n == 0 ? "zero" :
    $n == 1 ? "one" :
      $n % 10 >= 2 &&
      $n % 10 <= 4 &&
      ($n % 100 < 10 || $n % 100 >= 20) ? "few" :
        "many" }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        // args are passed as [locals, value] tuple
        $value = $env->plural->_call([[null, 0]]);
        $this->assertEquals('zero', $value[1]);
        $value = $env->plural->_call([[null, 1]]);
        $this->assertEquals('one', $value[1]);
        $value = $env->plural->_call([[null, 2]]);
        $this->assertEquals('few', $value[1]);
        $value = $env->plural->_call([[null, 5]]);
        $this->assertEquals('many', $value[1]);
        $value = $env->plural->_call([[null, 11]]);
        $this->assertEquals('many', $value[1]);
        $value = $env->plural->_call([[null, 22]]);
        $this->assertEquals('few', $value[1]);
        $value = $env->plural->_call([[null, 101]]);
        $this->assertEquals('many', $value[1]);
        $value = $env->plural->_call([[null, 102]]);
        $this->assertEquals('few', $value[1]);
        $value = $env->plural->_call([[null, 111]]);
        $this->assertEquals('many', $value[1]);
        $value = $env->plural->_call([[null, 121]]);
        $this->assertEquals('many', $value[1]);
        $value = $env->plural->_call([[null, 122]]);
        $this->assertEquals('few', $value[1]);

        //shape guards
        // unary -', function() {
        $source = '
<expr($n) { -$n }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, 1]]);
        $this->assertEquals(-1, $value[1]);
        try {
            $env->expr->_call([[null, "foo"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes a number') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes a number') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes a number') !== false);
            $this->assertTrue(true);
        }


        // unary +', function() {
        $source = '
<expr($n) { +$n }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, 1]]);
        $this->assertEquals(1, $value[1]);
        try {
            $env->expr->_call([[null, "foo"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes a number') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes a number') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes a number') !== false);
            $this->assertTrue(true);
        }


        // unary !', function() {
        $source = '
<expr($n) { !$n }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, true]]);
        $this->assertEquals(false, $value[1]);
        try {
            $env->expr->_call([[null, "foo"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes a boolean') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes a boolean') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes a boolean') !== false);
            $this->assertTrue(true);
        }


        // binary ==', function() {
        $source = '
<expr($n, $k) { $n == $k }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, 1], [null, 1]]);
        $this->assertEquals(true, $value[1]);
        $value = $env->expr->_call([[null, 1], [null, 2]]);
        $this->assertEquals(false, $value[1]);
        $value = $env->expr->_call([[null, "foo"], [null, "foo"]]);
        $this->assertEquals(true, $value[1]);
        $value = $env->expr->_call([[null, "foo"], [null, "bar"]]);
        $this->assertEquals(false, $value[1]);
        try {
            $env->expr->_call([[null, "foo"], [null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, true], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }


        // binary !=', function() {
        $source = '
<expr($n, $k) { $n != $k }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, 1], [null, 1]]);
        $this->assertEquals(false, $value[1]);
        $value = $env->expr->_call([[null, 1], [null, 2]]);
        $this->assertEquals(true, $value[1]);
        $value = $env->expr->_call([[null, "foo"], [null, "foo"]]);
        $this->assertEquals(false, $value[1]);
        $value = $env->expr->_call([[null, "foo"], [null, "bar"]]);
        $this->assertEquals(true, $value[1]);
        try {
            $env->expr->_call([[null, "foo"], [null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, true], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }


        // binary <', function() {
        $source = '
<expr($n, $k) { $n < $k }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, 1], [null, 2]]);
        $this->assertEquals(true, $value[1]);
        $value = $env->expr->_call([[null, 1], [null, 1]]);
        $this->assertEquals(false, $value[1]);
        $value = $env->expr->_call([[null, 2], [null, 1]]);
        $this->assertEquals(false, $value[1]);
        try {
            $env->expr->_call([[null, "foo"], [null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, "bar"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, true], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }


        // binary <=', function() {
        $source = '
<expr($n, $k) { $n <= $k }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, 1], [null, 2]]);
        $this->assertEquals(true, $value[1]);
        $value = $env->expr->_call([[null, 1], [null, 1]]);
        $this->assertEquals(true, $value[1]);
        $value = $env->expr->_call([[null, 2], [null, 1]]);
        $this->assertEquals(false, $value[1]);
        try {
            $env->expr->_call([[null, "foo"], [null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, "bar"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, true], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }


        // binary >', function() {
        $source = '
<expr($n, $k) { $n > $k }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, 1], [null, 2]]);
        $this->assertEquals(false, $value[1]);
        $value = $env->expr->_call([[null, 1], [null, 1]]);
        $this->assertEquals(false, $value[1]);
        $value = $env->expr->_call([[null, 2], [null, 1]]);
        $this->assertEquals(true, $value[1]);
        try {
            $env->expr->_call([[null, "foo"], [null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, "bar"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, true], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }


        // binary >=', function() {
        $source = '
<expr($n, $k) { $n >= $k }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, 1], [null, 2]]);
        $this->assertEquals(false, $value[1]);
        $value = $env->expr->_call([[null, 1], [null, 1]]);
        $this->assertEquals(true, $value[1]);
        $value = $env->expr->_call([[null, 2], [null, 1]]);
        $this->assertEquals(true, $value[1]);
        try {
            $env->expr->_call([[null, "foo"], [null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, "bar"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, true], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }


        // binary +', function() {
        $source = '
<expr($n, $k) { $n + $k }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, 1], [null, 1]]);
        $this->assertEquals(2, $value[1]);
        $value = $env->expr->_call([[null, "foo"], [null, "bar"]]);
        $this->assertEquals('foobar', $value[1]);
        try {
            $env->expr->_call([[null, "foo"], [null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, true], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers or two strings') !== false);
            $this->assertTrue(true);
        }


        // binary -', function() {
        $source = '
<expr($n, $k) { $n - $k }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, 1], [null, 2]]);
        $this->assertEquals(-1, $value[1]);
        try {
            $env->expr->_call([[null, "foo"], [null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, "bar"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, true], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }


        // binary *', function() {
        $source = '
<expr($n, $k) { $n * $k }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, 3], [null, 2]]);
        $this->assertEquals(6, $value[1]);
        try {
            $env->expr->_call([[null, "foo"], [null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, "bar"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, true], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }


        // binary /', function() {
        $source = '
<expr($n, $k) { $n / $k }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, 6], [null, 2]]);
        $this->assertEquals(3, $value[1]);
        // throws if the second argument is 0
        try {
            $env->expr->_call([[null, 1], [null, 0]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'Division by zero') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, "bar"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, true], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }


        // binary %', function() {
        $source = '
<expr($n, $k) { $n % $k }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, 11], [null, 10]]);
        $this->assertEquals(1, $value[1]);
        // throws if the second argument is 0
        try {
            $env->expr->_call([[null, 1], [null, 0]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'Modulo zero') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, "bar"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, true], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }


        // logical ||', function() {
        $source = '
<expr($n, $k) { $n || $k }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, true], [null, true]]);
        $this->assertEquals(true, $value[1]);
        $value = $env->expr->_call([[null, true], [null, false]]);
        $this->assertEquals(true, $value[1]);
        $value = $env->expr->_call([[null, false], [null, false]]);
        $this->assertEquals(false, $value[1]);

        try {
            $env->expr->_call([[null, "foo"], [null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, "bar"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, 0]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }


        // logical &&', function() {
        $source = '
<expr($n, $k) { $n && $k }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, true], [null, true]]);
        $this->assertEquals(true, $value[1]);
        $value = $env->expr->_call([[null, true], [null, false]]);
        $this->assertEquals(false, $value[1]);
        $value = $env->expr->_call([[null, false], [null, false]]);
        $this->assertEquals(false, $value[1]);

        try {
            $env->expr->_call([[null, "foo"], [null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, true]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, "foo"], [null, "bar"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1], [null, 0]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null], [null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two booleans') !== false);
            $this->assertTrue(true);
        }


        // conditional', function() {
        $source = '
<expr($n) { $n ? 1 : 0 }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->expr->_call([[null, true]]);
        $this->assertEquals(1, $value[1]);
        $value = $env->expr->_call([[null, false]]);
        $this->assertEquals(0, $value[1]);
        try {
            $env->expr->_call([[null, "foo"]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'test a boolean') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, 1]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'test a boolean') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->expr->_call([[null, null]]);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'test a boolean') !== false);
            $this->assertTrue(true);
        }
    }

    /**
     *
     */
    public function test_hashes()
    {
        //without index nor default value
        $source = '
<brandName {
  masculine: "Firefox",
  feminine: "Aurora"
}>
<plain "{{ brandName }}">
<property "{{ brandName.masculine }}">
<computed "{{ brandName[\'masculine\'] }}">
<missing "{{ brandName.missing }}">
<missingTwice "{{ brandName.missing.another }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->brandName->getString();
            $this->assertTrue(false);
        } catch (IndexException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->plain->getString();
            $this->assertTrue(false);
        } catch (ValueException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->plain->getString();
        } catch (CompilerException $e) {
            $this->assertTrue(true);
        }
        $this->assertEquals('Firefox', $env->property->getString());
        $this->assertEquals('Firefox', $env->computed->getString());
        try {
            $env->missing->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'Hash key lookup failed') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->missingTwice->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'Hash key lookup failed') !== false);
            $this->assertTrue(true);
        }

        //with index but no default value
        $source = '
<brandName["feminine"] {
  masculine: "Firefox",
  feminine: "Aurora"
}>
<plain "{{ brandName }}">
<property "{{ brandName.masculine }}">
<computed "{{ brandName[\'masculine\'] }}">
<missing "{{ brandName.missing }}">
<missingTwice "{{ brandName.missing.another }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Aurora', $env->brandName->getString());
        $this->assertEquals('Aurora', $env->plain->getString());
        $this->assertEquals('Firefox', $env->property->getString());
        $this->assertEquals('Firefox', $env->computed->getString());
        $this->assertEquals('Aurora', $env->missing->getString());
        try {
            $env->missingTwice->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: another', $e->getMessage());
        }

        //without index but with a default value
        $source = '
<brandName {
  masculine: "Firefox",
 *feminine: "Aurora"
}>
<plain "{{ brandName }}">
<property "{{ brandName.masculine }}">
<computed "{{ brandName[\'masculine\'] }}">
<missing "{{ brandName.missing }}">
<missingTwice "{{ brandName.missing.another }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Aurora', $env->brandName->getString());
        $this->assertEquals('Aurora', $env->plain->getString());
        $this->assertEquals('Firefox', $env->property->getString());
        $this->assertEquals('Firefox', $env->computed->getString());
        $this->assertEquals('Aurora', $env->missing->getString());
        try {
            $env->missingTwice->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: another', $e->getMessage());
        }

        //with index and with a default value
        $source = '
<brandName["feminine"] {
 *masculine: "Firefox",
  feminine: "Aurora"
}>
<plain "{{ brandName }}">
<property "{{ brandName.masculine }}">
<computed "{{ brandName[\'masculine\'] }}">
<missing "{{ brandName.missing }}">
<missingTwice "{{ brandName.missing.another }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Aurora', $env->brandName->getString());
        $this->assertEquals('Aurora', $env->plain->getString());
        $this->assertEquals('Firefox', $env->property->getString());
        $this->assertEquals('Firefox', $env->computed->getString());
        $this->assertEquals('Aurora', $env->missing->getString());
        try {
            $env->missingTwice->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: another', $e->getMessage());
        }

        //with an extra index and without default value
        $source = '
<brandName["feminine", "foo"] {
  masculine: "Firefox",
  feminine: "Aurora"
}>
<plain "{{ brandName }}">
<property "{{ brandName.masculine }}">
<computed "{{ brandName[\'masculine\'] }}">
<missing "{{ brandName.missing }}">
<missingTwice "{{ brandName.missing.another }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Aurora', $env->brandName->getString());
        $this->assertEquals('Aurora', $env->plain->getString());
        $this->assertEquals('Firefox', $env->property->getString());
        $this->assertEquals('Firefox', $env->computed->getString());
        $this->assertEquals('Aurora', $env->missing->getString());
        try {
            $env->missingTwice->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: another', $e->getMessage());
        }

        //with a valid but non-matching index and without default value
        $source = '
<brandName["foo"] {
  masculine: "Firefox",
  feminine: "Aurora"
}>
<plain "{{ brandName }}">
<property "{{ brandName.masculine }}">
<computed "{{ brandName[\'masculine\'] }}">
<missing "{{ brandName.missing }}">
<missingTwice "{{ brandName.missing.another }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->brandName->getString();
        } catch (IndexException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->plain->getString();
        } catch (ValueException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->plain->getString();
        } catch (CompilerException $e) {
            $this->assertTrue(true);
        }
        $this->assertEquals('Firefox', $env->property->getString());
        $this->assertEquals('Firefox', $env->computed->getString());
        try {
            $env->missing->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Hash key lookup failed (tried "missing", "foo").', $e->getMessage());
        }
        try {
            $env->missingTwice->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Hash key lookup failed (tried "missing", "foo").', $e->getMessage());
        }

        //with a valid but non-matching index and with default value
        $source = '
<brandName["foo"] {
  masculine: "Firefox",
 *feminine: "Aurora"
}>
<plain "{{ brandName }}">
<property "{{ brandName.masculine }}">
<computed "{{ brandName[\'masculine\'] }}">
<missing "{{ brandName.missing }}">
<missingTwice "{{ brandName.missing.another }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Aurora', $env->brandName->getString());
        $this->assertEquals('Aurora', $env->plain->getString());
        $this->assertEquals('Firefox', $env->property->getString());
        $this->assertEquals('Firefox', $env->computed->getString());
        $this->assertEquals('Aurora', $env->missing->getString());
        try {
            $env->missingTwice->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: another', $e->getMessage());
        }

        //with an invalid index and without default value
        $source = '
<brandName[foo] {
  masculine: "Firefox",
  feminine: "Aurora"
}>
<plain "{{ brandName }}">
<property "{{ brandName.masculine }}">
<computed "{{ brandName[\'masculine\'] }}">
<missing "{{ brandName.missing }}">
<missingTwice "{{ brandName.missing.another }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->brandName->getString();
        } catch (IndexException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->brandName->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'Reference to an unknown entry') !== false);
        }
        try {
            $env->plain->getString();
        } catch (ValueException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->plain->getString();
        } catch (CompilerException $e) {
            $this->assertTrue(true);
        }
        $this->assertEquals('Firefox', $env->property->getString());
        $this->assertEquals('Firefox', $env->computed->getString());
        try {
            $env->missing->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'Reference to an unknown entry') !== false);
        }
        try {
            $env->missingTwice->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'Reference to an unknown entry') !== false);
        }

        //with an invalid index and with default value
        $source = '
<brandName[foo] {
  masculine: "Firefox",
 *feminine: "Aurora"
}>
<plain "{{ brandName }}">
<property "{{ brandName.masculine }}">
<computed "{{ brandName[\'masculine\'] }}">
<missing "{{ brandName.missing }}">
<missingTwice "{{ brandName.missing.another }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->brandName->getString();
        } catch (IndexException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->brandName->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'Reference to an unknown entry') !== false);
        }
        try {
            $env->plain->getString();
        } catch (ValueException $e) {
            $this->assertTrue(true);
        }
        try {
            $env->plain->getString();
        } catch (CompilerException $e) {
            $this->assertTrue(true);
        }
        $this->assertEquals('Firefox', $env->property->getString());
        $this->assertEquals('Firefox', $env->computed->getString());
        try {
            $env->missing->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'Reference to an unknown entry') !== false);
        }
        try {
            $env->missingTwice->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'Reference to an unknown entry') !== false);
        }

        //and built-in properties
        $source = '
<bar {
 *key: "Bar"
}>
<foo "{{ bar.length }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Bar', $env->foo->getString());
    }

    /**
     *
     */
    public function test_indexes()
    {
        // IndexError in an entity in the index
        $source = '
<bar[1 - "a"] {
 *key: "Bar"
}>
<foo[bar] {
 *key: "Foo"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes two numbers') !== false);
            $this->assertTrue(true);
        }

        // Cyclic reference to named entity
        $source = '
<foo[foo] {
 *key: "Foo"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
            $this->assertTrue(true);
        }

        // Reference to an existing member of named entity
        $source = '
<foo[foo.bar] {
 *key: "Foo",
  bar: "key"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Foo', $env->foo->getString());

        // Cyclic reference to a missing member of named entity
        $source = '
<foo[foo.xxx] {
 *key: "Foo",
  bar: "key"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
            $this->assertTrue(true);
        }

        // Reference to an existing attribute of named entity
        $source = '
<foo[foo::attr] {
 *key: "Foo",
  bar: "key"
}
 attr: "key"
>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Foo', $env->foo->getString());

        // Reference to a missing attribute of named entity
        $source = '
<foo[foo::missing] {
 *key: "Foo",
  bar: "key"
}
 attr: "key"
>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'has no attribute') !== false);
            $this->assertTrue(true);
        }

        // Cyclic reference to this entity
        $source = '
<foo[~] {
 *key: "Foo"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
            $this->assertTrue(true);
        }

        // Reference to an existing member of this entity
        $source = '
<foo[~.bar]   {
 *key: "Foo",
  bar: "key"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Foo', $env->foo->getString());

        // Cyclic reference to a missing member of this entity
        $source = '
<foo[~.xxx] {
 *key: "Foo",
  bar: "key"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
            $this->assertTrue(true);
        }

        // Reference to an existing attribute of this entity
        $source = '
<foo[~::attr] {
 *key: "Foo",
  bar: "key"
}
 attr: "key"
>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Foo', $env->foo->getString());

        // Reference to a missing attribute of this entity
        $source = '
<foo[~::missing] {
 *key: "Foo",
  bar: "key"
}
 attr: "key"
>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'has no attribute') !== false);
            $this->assertTrue(true);
        }
    }

    /**
     *
     */
    public function test_macros()
    {
        // calling
        $source = '
<foo "Foo">
<bar {
  baz: "Baz"
}
 attr: "Attr"
>
<identity($n) { $n }>
<callFoo "{{ foo() }}">
<callBar "{{ bar() }}">
<callBaz "{{ bar.baz() }}">
<callAttr "{{ bar::attr() }}">
<callMissingAttr "{{ bar::missing() }}">
<returnMacro() { identity }>
<returnMacroProp() { identity.property }>
<returnMacroAttr() { identity::attribute }>
<placeMacro "{{ identity }}">
<placeMacroProp "{{ identity.property }}">
<placeMacroAttr "{{ identity::attribute }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->callFoo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'non-callable') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->callBar->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'non-callable') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->callBaz->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'non-callable') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->callAttr->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'non-callable') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->callMissingAttr->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'has no attribute') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->returnMacro->_call(json_decode('[]'));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Uncalled macro: identity', $e->getMessage());
            $this->assertTrue(true);
        }
        try {
            $env->returnMacroProp->_call(json_decode('[]'));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cannot get property of a macro: property', $e->getMessage());
            $this->assertTrue(true);
        }
        try {
            $env->returnMacroAttr->_call(json_decode('[]'));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'non-entity') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->placeMacro->getString();
        } catch (CompilerException $e) {
            $this->assertTrue(strpos(strtolower($e->getMessage()), 'uncalled') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->placeMacroProp->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cannot get property of a macro: property', $e->getMessage());
            $this->assertTrue(true);
        }
        try {
            $env->placeMacroAttr->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'non-entity') !== false);
            $this->assertTrue(true);
        }

        // arguments
        $source = '
<foo "Foo">
<bar {
  baz: "Baz"
}>
<identity($n) { $n }>
<sum($n, $k) { $n + $k }>
<getBaz($n) { $n.baz }>
<say() { "Hello" }>
<call($n) { $n() }>
<callWithArg($n, $arg) { $n($arg) }>
<noArg "{{ identity() }}">
<tooFewArgs "{{ sum(2) }}">
<tooManyArgs "{{ sum(2, 3, 4) }}">
<stringArg "{{ identity(\'string\') }}">
<numberArg "{{ identity(1) }}">
<entityArg "{{ identity(foo) }}">
<entityReferenceArg "{{ getBaz(bar) }}">
<macroArg "{{ identity(say) }}">
<callMacro "{{ call(say) }}">
<callMacroWithArg "{{ callWithArg(identity, 2) }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->noArg->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes exactly') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->tooFewArgs->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes exactly') !== false);
            $this->assertTrue(true);
        }
        try {
            $env->tooManyArgs->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes exactly') !== false);
            $this->assertTrue(true);
        }
        $this->assertEquals('string', $env->stringArg->getString());
        $this->assertEquals('1', $env->numberArg->getString());
        $this->assertEquals('Foo', $env->entityArg->getString());
        $this->assertEquals('Baz', $env->entityReferenceArg->getString());
        $this->assertEquals('Hello', $env->callMacro->getString());
        $this->assertEquals('2', $env->callMacroWithArg->getString());

        // return values
        $source = '
<foo "Foo">
<bar {
 *bar: "Bar",
  baz: "Baz"
}>
<string() { "foo" }>
<number() { 1 }>
<stringEntity() { foo }>
<hashEntity() { bar }>
<stringMissingProp "{{ stringEntity().missing }}">
<stringMissingAttr "{{ (stringEntity())::missing }}">
<hashBazProp "{{ hashEntity().baz }}">
<hashMissingProp "{{ hashEntity().missing }}">
<hashMissingAttr "{{ (stringEntity())::missing }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $value = $env->string->_call(json_decode('[]'));
        $this->assertEquals('foo', $value[1]);
        $value = $env->number->_call(json_decode('[]'));
        $this->assertEquals(1, $value[1]);
        $value = $env->stringEntity->_call(json_decode('[]'));
        $this->assertEquals('Foo', $value[1]);
        $value = $env->hashEntity->_call(json_decode('[]'));
        $this->assertEquals('Bar', $value[1]);
        try {
            $env->stringMissingProp->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cannot get property of a string: missing', $e->getMessage());
            $this->assertTrue(true);
        }
        try {
            $env->stringMissingAttr->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'non-entity') !== false);
            $this->assertTrue(true);
        }

        try {
            $env->hashBazProp->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cannot get property of a string: baz', $e->getMessage());
            $this->assertTrue(true);
        }
        try {
            $env->hashMissingProp->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cannot get property of a string: missing', $e->getMessage());
            $this->assertTrue(true);
        }
        try {
            $env->hashMissingAttr->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'non-entity') !== false);
            $this->assertTrue(true);
        }

        // and ctxdata:
        $ctxdata = '
{
  "n": 3
}
';
        $source = '
<identity($n) { $n }>
<getFromContext() { $n }>
<foo "{{ $n }}">
<bar {
 *key: "{{ $n }}",
}>
<getFoo($n) { foo }>
<getBar($n) { bar }>
<getBarKey($n) { bar.key }>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $ctxdata = json_decode($ctxdata);
        $value = $env->identity->_call(json_decode('[[null, "foo"]]'), $ctxdata);
        $this->assertEquals('foo', $value[1]);
        try {
            $env->identity->_call(json_decode('[]'), $ctxdata);
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'takes exactly') !== false);
            $this->assertTrue(true);
        }
        $value = $env->getFromContext->_call(json_decode('[]'), $ctxdata);
        $this->assertEquals(3, $value[1]);
        $value = $env->getFoo->_call(json_decode('[[null, "foo"]]'), $ctxdata);
        $this->assertEquals(3, $value[1]);
        $value = $env->getBar->_call(json_decode('[[null, "foo"]]'), $ctxdata);
        $this->assertEquals(3, $value[1]);
        $value = $env->getBarKey->_call(json_decode('[[null, "foo"]]'), $ctxdata);
        $this->assertEquals(3, $value[1]);
    }

    /**
     *
     */
    public function test_primitives()
    {
        // Numbers
        $source = '
<one "{{ 1 }}">
<missing "{{ 1.missing }} ">
<builtin "{{ 1.valueOf }} ">
<index[1] {
  key: "value"
}>
<indexMissing[1.missing] {
  key: "value"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('1', $env->one->getString());
        try {
            $env->missing->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cannot get property of a integer: missing', $e->getMessage());
            $this->assertTrue(true);
        }
        try {
            $env->builtin->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cannot get property of a integer: valueOf', $e->getMessage());
            $this->assertTrue(true);
        }
        try {
            $env->index->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Index must be a string', $e->getMessage());
            $this->assertTrue(true);
        }
        try {
            $env->indexMissing->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertEquals('Cannot get property of a integer: missing', $e->getMessage());
            $this->assertTrue(true);
        }

        // Booleans
        $source = '
<true "{{ 1 == 1 }} ">
<missing "{{ (1 == 1).missing }} ">
<builtin "{{ (1 == 1).valueOf }} ">
<index[(1 == 1)] {
  key: "value"
}>
<indexMissing[(1 == 1).missing] {
  key: "value"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->true->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Placeables must be strings or numbers', $e->getMessage());
        }
        try {
            $env->missing->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a boolean: missing', $e->getMessage());
        }
        try {
            $env->builtin->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a boolean: valueOf', $e->getMessage());
        }
        try {
            $env->index->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Index must be a string', $e->getMessage());
        }
        try {
            $env->indexMissing->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a boolean: missing', $e->getMessage());
        }

        // Simple string value
        $source = '
<foo "Foo">
<fooMissing "{{ foo.missing }} ">
<fooLength "{{ foo.length }} ">
<literalMissing "{{ \'string\'.missing }} ">
<literalLength "{{ \'string\'.length }} ">
<literalIndex["string".missing] {
  key: "value"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Foo', $env->foo->getString());
        try {
            $env->fooMissing->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: missing', $e->getMessage());
        }
        try {
            $env->fooLength->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: length', $e->getMessage());
        }
        try {
            $env->literalMissing->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: missing', $e->getMessage());
        }
        try {
            $env->literalLength->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: length', $e->getMessage());
        }
        try {
            $env->literalIndex->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: missing', $e->getMessage());
        }

        // Complex string value
        $source = '
<foo "Foo">
<bar "{{ foo }} Bar">
<barMissing "{{ bar.missing }} ">
<barLength "{{ bar.length }} ">
<baz "{{ missing }}">
<quux "{{ foo.missing }} ">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Foo Bar', $env->bar->getString());
        $this->assertTrue(is_callable($env->bar->value));
        try {
            $env->baz->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'unknown entry') !== false);
        }
        try {
            $env->quux->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: missing', $e->getMessage());
        }

        // String value in a hash
        $source = '
<foo {
  key: "Foo"
}>
<bar "{{ foo.key }}">
<missing "{{ foo.key.missing }}">
<undef "{{ foo.key[$undef] }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Foo', $env->bar->getString());
        $this->assertTrue(is_callable($env->bar->value));
        try {
            $env->missing->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: missing', $e->getMessage());
        }
        try {
            $env->undef->getString(json_decode('{ "undef": null }'));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'Property name must evaluate to a string') !== false);
        }

        // Complex string referencing an entity with null value
        $source = '
<foo
  attr: "Foo"
>
<bar "{{ foo }} Bar">
<baz "{{ foo::attr }} Bar">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $entity = $env->foo->get();
        $this->assertTrue(array_key_exists('value', $entity));
        try {
            $env->bar->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Placeables must be strings or numbers', $e->getMessage());
        }
        $this->assertEquals('Foo Bar', $env->baz->getString());

        // This-reference
        $source = '
<foo {
 *foo: "{{ ~.bar }}",
  bar: "Bar"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Bar', $env->foo->getString());
        $this->assertTrue(is_callable($env->foo->value));

        // Cyclic reference
        $source = '
<foo "{{ bar }}">
<bar "{{ foo }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
        }

        // Cyclic self-reference
        $source = '
<foo "{{ foo }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
        }

        // Cyclic self-reference in a hash
        $source = '
<foo {
 *foo: "{{ foo }}",
  bar: "Bar"
}>
<bar "{{ foo.bar }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
        }
        $this->assertEquals('Bar', $env->bar->getString());

        // Non-cyclic self-reference to a property of a hash
        $source = '
<foo {
 *foo: "{{ foo.bar }}",
  bar: "Bar"
}>
<bar "{{ foo.bar }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Bar', $env->foo->getString());
        $this->assertEquals('Bar', $env->bar->getString());

        // Cyclic self-reference to a property of a hash which references self
        $source = '
<foo {
 *foo: "{{ foo.bar }}",
  bar: "{{ foo }}"
}>
<bar "{{ foo.bar }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
        }
        try {
            $env->bar->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
        }

        // Cyclic this-reference
        $source = '
<foo "{{ ~ }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
        }

        // Cyclic this-reference in a hash
        $source = '
<foo {
 *foo: "{{ ~ }}",
  bar: "Bar"
}>
<bar "{{ foo.bar }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
        }
        $this->assertEquals('Bar', $env->bar->getString());

        // Cyclic this-reference to a property of a hash
        $source = '
<foo {
 *foo: "{{ ~.foo }}",
  bar: "Bar"
}>
<bar "{{ foo.bar }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
        }
        $this->assertEquals('Bar', $env->bar->getString());

        // Non-cyclic this-reference to a property of a hash
        $source = '
<foo {
 *foo: "{{ ~.bar }}",
  bar: "Bar"
}>
<bar "{{ foo.bar }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Bar', $env->foo->getString());
        $this->assertEquals('Bar', $env->bar->getString());

        // Cyclic this-reference to a property of a hash which references this
        $source = '
<foo {
 *foo: "{{ ~.bar }}",
  bar: "{{ ~ }}"
}>
<bar "{{ foo.bar }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->foo->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
        }
        try {
            $env->bar->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cyclic reference detected', $e->getMessage());
        }
    }

    /**
     *
     */
    public function test_ctxdata()
    {
        // in entities
        $ctxdata = '
{
    "unreadNotifications": 3
}
';
        $this->assertTrue((json_decode($ctxdata) instanceof stdClass));
        $source = '
<plural($n) { $n == 1 ? "one" : "many" }>
<unread "Unread notifications: {{ $unreadNotifications }}">
<unreadPlural[plural($unreadNotifications)] {
  one: "One unread notification",
  many: "{{ $unreadNotifications }} unread notifications"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('Unread notifications: 3', $env->unread->getString(json_decode($ctxdata)));
        $this->assertEquals('3 unread notifications', $env->unreadPlural->getString(json_decode($ctxdata)));

        // in macros
        $ctxdata = '
{
    "n": 3
}
';
        $this->assertTrue((json_decode($ctxdata) instanceof stdClass));
        $source = '
<macro($n) { $n == 1 ? "one" : "many" }>
<macroNoArg() { $n == 1 ? "one" : "many" }>
<one "{{ macro(1) }}">
<passAsArg "{{ macro($n) }}">
<noArgs "{{ macroNoArg() }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('one', $env->one->getString(json_decode($ctxdata)));
        $this->assertEquals('many', $env->passAsArg->getString(json_decode($ctxdata)));
        $this->assertEquals('many', $env->noArgs->getString(json_decode($ctxdata)));

        // and simple errors
        $ctxdata = '
{
    "nested": {
    }
}
';
        $this->assertTrue((json_decode($ctxdata) instanceof stdClass));
        $source = '
<missing "{{ $missing }}">
<missingTwice "{{ $missing.another }}">
<nested "{{ $nested }}">
<nestedMissing "{{ $nested.missing }}">
<nestedMissingTwice "{{ $nested.missing.another }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->missing->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'unknown variable') !== false);
        }
        try {
            $env->missingTwice->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'unknown variable') !== false);
        }
        try {
            $env->nested->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot resolve ctxdata or global of type object', $e->getMessage());
        }
        try {
            $env->nestedMissing->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'not defined') !== false);
        }
        try {
            $env->nestedMissingTwice->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'not defined') !== false);
        }

        // and strings
        $ctxdata = '
{
    "property": "property",
    "nested": {
        "property": "property"
    }
}
';
        $this->assertTrue(json_decode($ctxdata) instanceof stdClass);
        $source = '
<property "{{ $property }}">
<propertyMissing "{{ $property.missing }}">
<nestedProperty "{{ $nested.property }}">
<nestedPropertyMissing "{{ $nested.property.missing }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('property', $env->property->getString(json_decode($ctxdata)));
        try {
            $env->propertyMissing->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: missing', $e->getMessage());
        }
        $this->assertEquals('property', $env->nestedProperty->getString(json_decode($ctxdata)));
        try {
            $env->nestedPropertyMissing->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a string: missing', $e->getMessage());
        }

        // $nested (a dict-like ctxdata) and numbers
        $ctxdata = '
{
    "num": 1,
    "nested": {
        "number": 1
    }
}
';
        $this->assertTrue(json_decode($ctxdata) instanceof stdClass);
        $source = '
<num "{{ $num }}">
<number "{{ $nested.number }}">
<numberMissing "{{ $nested.number.missing }}">
<numberValueOf "{{ $nested.number.valueOf }}">
<numberIndex[$nested.number] {
  key: "value"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('1', $env->num->getString(json_decode($ctxdata)));
        $this->assertEquals('1', $env->number->getString(json_decode($ctxdata)));
        try {
            $env->numberMissing->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a integer: missing', $e->getMessage());
        }
        try {
            $env->numberMissing->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a integer: missing', $e->getMessage());
        }
        try {
            $env->numberIndex->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Index must be a string', $e->getMessage());
        }

        // $nested (a dict-like ctxdata) and bools
        $ctxdata = '
{
    "bool": true,
    "nested": {
        "bool": true
    }
}
';
        $this->assertTrue(json_decode($ctxdata) instanceof stdClass);
        $source = '
<just "{{ $bool ? 1 : 0 }}">
<bool "{{ $nested.bool ? 1 : 0 }}">
<boolMissing "{{ $nested.bool.missing }}">
<boolIndex[$nested.bool] {
  key: "value"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        $this->assertEquals('1', $env->just->getString(json_decode($ctxdata)));
        $this->assertEquals('1', $env->bool->getString(json_decode($ctxdata)));
        try {
            $env->boolMissing->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a boolean: missing', $e->getMessage());
        }
        try {
            $env->boolIndex->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Index must be a string', $e->getMessage());
        }

        // $nested (a dict-like ctxdata) and undefined
        $ctxdata = '
{
    "undef": null,
    "nested": {
        "undef": null
    }
}
';
        $this->assertTrue(json_decode($ctxdata) instanceof stdClass);
        $source = '
<just "{{ $undef }}">
<undef "{{ $nested.undef }}">
<undefMissing "{{ $nested.undef.missing }}">
<undefIndex[$nested.undef] {
  key: "value",
  undefined: "undef"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->just->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Placeables must be strings or numbers', $e->getMessage());
        }
        try {
            $env->undef->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Placeables must be strings or numbers', $e->getMessage());
        }
        try {
            $env->undefMissing->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a null: missing', $e->getMessage());
        }
        try {
            $env->undefIndex->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'Hash key lookup failed') !== false);
        }

        // $nested (a dict-like ctxdata) and null
        $ctxdata = '
{
    "nullable": null,
    "nested": {
        "nullable": null
    }
}
';
        $this->assertTrue(json_decode($ctxdata) instanceof stdClass);
        $source = '
<just "{{ $nullable }}">
<nullable "{{ $nested.nullable }}">
<nullableMissing "{{ $nested.nullable.missing }}">
<nullableIndex[$nested.nullable] {
  key: "value"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->just->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Placeables must be strings or numbers', $e->getMessage());
        }
        try {
            $env->nullable->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Placeables must be strings or numbers', $e->getMessage());
        }
        try {
            $env->nullableMissing->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of a null: missing', $e->getMessage());
        }
// TODO this test cannot be ported as is to PHP.
//        try {
//            $env->nullableIndex->getString(json_decode($ctxdata));
//            $this->assertTrue(false);
//        } catch (CompilerException $e) {
//            $this->assertTrue(true);
//            $this->assertEquals('Index must be a string', $e->getMessage());
//        }

        // $nested (a dict-like ctxdata) and arrays
        $ctxdata = '
{
    "arr": [3, 4],
    "nested": {
        "arr": [3, 4]
    }
}
';
        $this->assertTrue(json_decode($ctxdata) instanceof stdClass);
        $source = '
<just "{{ $arr }}">
<arr "{{ $nested.arr }}">
<arrMissing "{{ $nested.arr.missing }}">
<arrLength "{{ $nested.arr.length }}">
<arrIndex[$nested.arr] {
  key: "value"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->just->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot resolve ctxdata or global of type array', $e->getMessage());
        }
        try {
            $env->arr->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot resolve ctxdata or global of type array', $e->getMessage());
        }
        try {
            $env->arrMissing->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of an array: missing', $e->getMessage());
        }
        try {
            $env->arrLength->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot get property of an array: length', $e->getMessage());
        }
        try {
            $env->arrIndex->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot resolve ctxdata or global of type array', $e->getMessage());
        }

        // $nested (a dict-like ctxdata) and objects
        $ctxdata = '
{
    "nested": {
        "obj": { "key": "value" }
    }
}
';
        $this->assertTrue(json_decode($ctxdata) instanceof stdClass);
        $source = '
<just "{{ $nested }}">
<obj "{{ $nested.obj }}">
<objKey "{{ $nested.obj.key }}">
<objMissing "{{ $nested.obj.missing }}">
<objValueOf "{{ $nested.obj.valueOf }}">
<objIndex[$nested.obj] {
  key: "value"
}>
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->just->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot resolve ctxdata or global of type object', $e->getMessage());
        }
        try {
            $env->obj->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot resolve ctxdata or global of type object', $e->getMessage());
        }
        $this->assertEquals('value', $env->objKey->getString(json_decode($ctxdata)));
        try {
            $env->objMissing->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'missing is not defined on the object') !== false);
        }
        try {
            $env->objValueOf->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'valueOf is not defined on the object') !== false);
        }
        try {
            $env->objIndex->getString(json_decode($ctxdata));
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertEquals('Cannot resolve ctxdata or global of type object', $e->getMessage());
        }

// TODO the following tests are marked to skip in the original test in ctxdata.js
//        // $nested (a dict-like ctxdata) and functions
//        $ctxdata = '
//{
//    "fn": "function fn() {}",
//    "nested": {
//        "fn": "function fn() {}"
//    }
//}
//';
//        $this->assertTrue(json_decode($ctxdata) instanceof stdClass);
//        $source = '
//<just "{{ $fn }}">
//<fn "{{ $nested.fn }}">
//<fnKey "{{ $nested.fn.key }}">
//<fnMissing "{{ $nested.fn.missing }}">
//<fnValueOf "{{ $nested.fn.valueOf }}">
//<fnIndex[$nested.fn] {
//  key: "value"
//}>
//';
//        $ast = $this->parser->parse($source);
//        /** @var stdClass $env */
//        $env = $this->compiler->compile($ast);
//        try {
//            $env->just->getString(json_decode($ctxdata));
//            $this->assertTrue(false);
//        } catch (CompilerException $e) {
//            $this->assertTrue(true);
//            $this->assertEquals('Cannot resolve ctxdata or global of type object', $e->getMessage());
//        }
//        try {
//            $env->fn->getString(json_decode($ctxdata));
//            $this->assertTrue(false);
//        } catch (CompilerException $e) {
//            $this->assertTrue(true);
//            $this->assertEquals('Cannot resolve ctxdata or global of type object', $e->getMessage());
//        }
//        $this->assertEquals('value', $env->objKey->getString(json_decode($ctxdata)));
//        try {
//            $env->fnMissing->getString(json_decode($ctxdata));
//            $this->assertTrue(false);
//        } catch (CompilerException $e) {
//            $this->assertTrue(true);
//            $this->assertEquals('missing is not defined on the object', $e->getMessage());
//        }
//        try {
//            $env->fnValueOf->getString(json_decode($ctxdata));
//            $this->assertTrue(false);
//        } catch (CompilerException $e) {
//            $this->assertTrue(true);
//            $this->assertEquals('valueOf is not defined on the object', $e->getMessage());
//        }
//        try {
//            $env->fnIndex->getString(json_decode($ctxdata));
//            $this->assertTrue(false);
//        } catch (CompilerException $e) {
//            $this->assertTrue(true);
//            $this->assertEquals('Cannot resolve ctxdata or global of type object', $e->getMessage());
//        }
    }

    /**
     *
     */
    public function test_insecure_dos()
    {
        // Billion Laughs
        $source = '
<lol0 "LOL">
<lol1 "{{lol0}} {{lol0}} {{lol0}} {{lol0}} {{lol0}} {{lol0}} {{lol0}} {{lol0}} {{lol0}} {{lol0}}">
<lol2 "{{lol1}} {{lol1}} {{lol1}} {{lol1}} {{lol1}} {{lol1}} {{lol1}} {{lol1}} {{lol1}} {{lol1}}">
<lol3 "{{lol2}} {{lol2}} {{lol2}} {{lol2}} {{lol2}} {{lol2}} {{lol2}} {{lol2}} {{lol2}} {{lol2}}">
<lol4 "{{lol3}} {{lol3}} {{lol3}} {{lol3}} {{lol3}} {{lol3}} {{lol3}} {{lol3}} {{lol3}} {{lol3}}">
<lol5 "{{lol4}} {{lol4}} {{lol4}} {{lol4}} {{lol4}} {{lol4}} {{lol4}} {{lol4}} {{lol4}} {{lol4}}">
<lol6 "{{lol5}} {{lol5}} {{lol5}} {{lol5}} {{lol5}} {{lol5}} {{lol5}} {{lol5}} {{lol5}} {{lol5}}">
<lol7 "{{lol6}} {{lol6}} {{lol6}} {{lol6}} {{lol6}} {{lol6}} {{lol6}} {{lol6}} {{lol6}} {{lol6}}">
<lol8 "{{lol7}} {{lol7}} {{lol7}} {{lol7}} {{lol7}} {{lol7}} {{lol7}} {{lol7}} {{lol7}} {{lol7}}">
<lol9 "{{lol8}} {{lol8}} {{lol8}} {{lol8}} {{lol8}} {{lol8}} {{lol8}} {{lol8}} {{lol8}} {{lol8}}">
<lolz "{{ lol9 }}">
';
        $ast = $this->parser->parse($source);
        /** @var stdClass $env */
        $env = $this->compiler->compile($ast);
        try {
            $env->lolz->getString();
            $this->assertTrue(false);
        } catch (CompilerException $e) {
            $this->assertTrue(true);
            $this->assertTrue(strpos($e->getMessage(), 'too many characters') !== false);
        }
    }
}
