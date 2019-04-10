<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\DependencyInjection;

class ReflectionMethod extends \ReflectionMethod
{
    private static $parameters;
    private $ref;
    private $className;

    public function __construct($ref, $methodName)
    {
        parent::__construct($ref, $methodName);
        $this->ref = $ref;
        $this->className = $this->getDeclaringClass()->name;
    }

    public function getParameters()
    {
        if (isset(self::$parameters[$this->className]) && isset(self::$parameters[$this->className][$this->name])) {
            return self::$parameters[$this->className][$this->name];
        }

        $params = parent::getParameters();
        $parameters = [];

        foreach ($params as $param) {
            $data['className']  = $param->getClass() !== null ? $param->getClass()->getName() : null; //|| (isset($annotations['DI']['Inject']) && isset($annotations['DI']['Inject']->value->{$param->getName()})) ? isset($annotations['DI']['Inject']) && isset($annotations['DI']['Inject']->value->{$param->getName()}) ? $annotations['DI']['Inject']->value->{$param->getName()} : $param->getClass()->getName() : null;
            $data['isObject']   = $data['className'] !== null;
            $data['paramName']  = $param->getName();
            $data['hasDefaultValue'] = $param->isDefaultValueAvailable();
            $data['defaultValue']   = $data['hasDefaultValue'] ? $param->getDefaultValue() : null;
            $parameters[] = $data;
        }
        
        self::$parameters[$this->className][$this->name] = $parameters;

        return $parameters;
    }
}
