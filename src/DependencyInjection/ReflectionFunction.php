<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\DependencyInjection;

class ReflectionFunction extends \ReflectionFunction
{
    private $function;

    public function __construct($function)
    {
        parent::__construct($function);
        $this->function = $function;
    }

    public function getParameters()
    {
        $params = parent::getParameters();
        $parameters = [];

        foreach ($params as $param) {
            $data['className']  = $param->getClass() !== null ? $param->getClass()->getName() : null;  //|| (isset($annotations['DI']['Inject']) && isset($annotations['DI']['Inject']->value->{$param->getName()})) ? isset($annotations['DI']['Inject']) && isset($annotations['DI']['Inject']->value->{$param->getName()}) ? $annotations['DI']['Inject']->value->{$param->getName()} : $param->getClass()->getName() : null;
            $data['isObject']   = $data['className'] !== null;
            $data['paramName']  = $param->getName();
            $data['hasDefaultValue'] = $param->isDefaultValueAvailable();
            $data['defaultValue']   = $data['hasDefaultValue'] ? $param->getDefaultValue() : null;
            $parameters[] = $data;
        }

        return $parameters;
    }
}
