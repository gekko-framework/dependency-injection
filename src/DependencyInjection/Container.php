<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\DependencyInjection;

class Container implements IDependencyContainer
{
    /**
     * @var array
     */
    private static $map;

    /**
     * @var array
     */
    private static $synonyms;

    public function __construct()
    {
        if (empty(self::$map)) {
            self::$map = [];
        }
        if (empty(self::$synonyms)) {
            self::$synonyms = [];
        }
    }

    public function add(string $class, $definition) : void
    {
        if (empty($class)) {
            throw new \Exception("Invalid definition for class '{$class}'.");
        }

        if (isset(self::$map[$class])) {
            if (!isset($definition['override'])) {
                trigger_error("If you want to replace or modify dependencies of class '{$class}', first remove it using " . __CLASS__ . "::remove('{$class}') or specify in definition 'override' => true.");
            } elseif (!$definition['override']) {
                return;
            }
        }

        // Class name
        if (!isset($definition['class'])) {
            $definition['class'] = $class;
        }
        
        // Dependencies of Class to be built
        if (!isset($definition['dependencies'])) {
            $definition['dependencies'] = [];
        }

        // If dependency has singleton lifestyle
        if (!isset($definition['singleton'])) {
            $definition['singleton'] = false;
        }
        
        // Class can be instatiated through its class name or using a synonym (a named dependency)
        if (!isset($definition['synonym'])) {
            $definition['synonym'] = $class;
        } else {
            self::$synonyms[$definition['synonym']] = $class;
        }

        // We can use a reference to an object, the container will return the reference every time Class is requested
        if (!isset($definition['reference'])) {
            $definition['reference'] = null;
        }

        // A factory will be used to construct the Class instance
        if (!isset($definition['factory'])) {
            $definition['factory'] = null;
        }

        self::$map[$class] = $definition;
    }

    public function addList($deps) : bool
    {
        foreach ($deps as $class => $definition) {
            if (empty($class)) {
                continue;
            }
            $this->add($class, $definition);
        }
        return true;
    }

    public function get(string $needle)
    {
        if (isset(self::$map[$needle])) {
            return self::$map[$needle];
        }
        if (isset(self::$synonyms[$needle])) {
            return self::$map[self::$synonyms[$needle]];
        }
        return null;
    }

    public function getClass(string $needle)
    {
        if (isset(self::$map[$needle])) {
            return self::$map[$needle]['class'];
        }
        if (isset(self::$synonyms[$needle])) {
            return self::$map[self::$synonyms[$needle]]['class'];
        }
        return null;
    }

    public function has(string $needle) : bool
    {
        return isset(self::$map[$needle]) || (isset(self::$synonyms[$needle]) && isset(self::$map[self::$synonyms[$needle]]));
    }

    public function remove(string $class) : void
    {
        if (isset(self::$map[$class])) {
            unset(self::$map[$class]);
        }
        if (isset(self::$synonyms[$class])) {
            unset(self::$map[self::$synonyms[$needle]]);
        }
    }
}
