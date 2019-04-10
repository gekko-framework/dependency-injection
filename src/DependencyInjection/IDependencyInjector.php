<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\DependencyInjection;

interface IDependencyInjector
{
    public function make($needle);
    public function getContainer();
}
