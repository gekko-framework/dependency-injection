<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\DependencyInjection;

class ReflectionClass extends \ReflectionClass
{
    public function __construct($class)
    {
        parent::__construct($class);
        $this->class = $class;
    }

    public function getConstructor() : ReflectionMethod
    {
        return new ReflectionMethod($this->class, "__construct");
    }

    public function getMethod($name) : ReflectionMethod
    {
        return new ReflectionMethod($this->class, $name);
    }
}
