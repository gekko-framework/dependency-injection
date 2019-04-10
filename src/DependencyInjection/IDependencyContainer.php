<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\DependencyInjection;

interface IDependencyContainer
{
    public function add(string $class, $definition) : void;
    public function get(string $class);
    public function has(string $class) : bool;
}
