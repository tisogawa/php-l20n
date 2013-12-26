<?php

namespace L20n;

use L20n\Compiler\Exception\CompilerException;
use L20n\Platform\GlobalBase;

/**
 * Class Compiler
 * @package L20n
 */
class Compiler
{
    /** @var array */
    protected static $_entryTypes = [
        'Entity' => '\\L20n\\Compiler\\Entity',
        'Macro' => '\\L20n\\Compiler\\Macro'
    ];

    /** @var GlobalBase[] */
    public static $_globals;

    /** @var array */
    public static $_references = [
        'globals' => []
    ];

    /**
     * @param array $ast
     * @param \stdClass $env
     * @return \stdClass
     * @throws \Exception
     */
    public function compile(array $ast, \stdClass $env = null)
    {
        if ($env === null) {
            $env = new \stdClass();
        }
        foreach ($ast['body'] as $entry) {
            if (isset(static::$_entryTypes[$entry['type']])) {
                /** @var string $constructor */
                $constructor = static::$_entryTypes[$entry['type']];
                /** @var string $name */
                $name = $entry['id']['name'];
                try {
                    $env->$name = new $constructor($entry, $env);
                } catch (CompilerException $e) {
                } catch (\Exception $e) {
                    throw $e;
                }
            }
        }
        return $env;
    }

    /**
     * @param GlobalBase[] $globals
     * @return bool
     */
    public function setGlobals(array $globals)
    {
        static::$_globals = $globals;
        return true;
    }
}
