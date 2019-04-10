<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\DependencyInjection;

use \Closure;

class DependencyInjector implements IDependencyInjector
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var array
     */
    private static $singletons;

    /**
     * @var array
     */
    private static $reflections;

    public function __construct()
    {
        $this->container = new Container();
        $this->container->add(\Gekko\DependencyInjection\IDependencyInjector::class, ['reference' => $this]);

        if (empty(self::$singletons)) {
            self::$singletons = [];
        }
        if (empty(self::$reflections)) {
            self::$reflections = [];
        }
    }

    public function has(string $needle) : bool
    {
        return $this->container->has($needle);
    }

    /**
     * Creates a new instance of an object using the definition registered in the
     * container for dependency $needle
     * @param $needle class or name (named dependency)
     * @param $bindDependencies dependencies to be injected to override the ones registerd in the
     * definition
     */
    public function make($needle, array $bindDependencies = [])
    {
        if ($this->isClosure($needle)) {
            return $this->makeClosure($needle, $bindDependencies);
        } elseif (is_array($needle) && count($needle) == 2 && class_exists($needle[0]) && method_exists($needle[0], $needle[1])) {
            return $this->makeMethod($needle[0], $needle[1], $bindDependencies);
        }

        $data = null;
        if ($this->container->has($needle)) {
            // Try to get the dependency definition for $needle from the container
            $data = $this->container->get($needle);

            // If $bindDependencies has values, override the definition's dependencies with the new ones
            // (just for this request)
            if (!empty($bindDependencies)) {
                foreach ($bindDependencies as $key => $value) {
                    $data['dependencies'][$key] = $value;
                }
            }
        } else {
            // If the dependency is not registerd, try to resolve it "by hand", registering it in the
            // container.
            $data = [
                'class'         => $needle,
                'dependencies'  => $bindDependencies,
                'singleton'     => false,
                'reference'     => null,
                'factory'       => null
            ];
            $definition = $data;
            // Do not register the dependencies, because they could be particular for the current request
            unset($definition['dependencies']);
            $this->container->add($needle, $definition);
        }

        // Use makeClosure to handle factories
        if ($data['factory'] != null) {
            return $this->makeClosure($data['factory']);
        }

        // Get the class name
        $class          = $data['class'];
        // Get the dependencies to resolve/bind
        $dependencies   = $data['dependencies'];
        // Get the reference (if registered)
        $reference      = $data['reference'];

        // If the dependency is registered as a singleton, and it is already instatiated, return the instance
        if ($data['singleton'] && isset(self::$singletons[$class])) {
            return self::$singletons[$class];
        }
        
        // Object to instantiate
        $object = null;

        if (!empty($reference)) {
            // If the dependency is a 'reference', return the registered reference.
            if (is_object($reference) && !$this->isClosure($reference) && ($reference instanceof $class)) {
                $object = $reference;
            } elseif (is_string($reference) && $this->container->has($reference)) {
                $object = $this->make($reference);
            } elseif ($this->isClosure($reference)) {
                $object = $this->makeClosure($reference, $bindDependencies);
            }
        } else {
            // Instantiate the new object
            $object = $this->makeClass($class, $dependencies);
        }


        // Check if it is a singleton. In that case, save the object in the $singletons map
        if ($data['singleton']) {
            self::$singletons[$class] = $object;
        }

        return $object;
    }

    protected function makeClass($class, $dependencies)
    {
        if (isset(self::$reflections[$class])) {
            $reference = self::$reflections[$class];
        } else {
            $reference = new ReflectionClass($class);
        }

        // If it is an interface return null
        // TODO: Throw an error here maybe?
        if ($reference->isInterface()) {
            return null;
        }

        $constructor = null;
        $invokableMethod = null;
        if ($reference->hasMethod('__construct')) {
            $constructor = $reference->getConstructor();
            $invokableMethod = $constructor;
        }
        
        $object = null;
        if (empty($constructor) && !$reference->hasMethod("instance")) {
            $object = $reference->newInstance();
        } elseif ($reference->hasMethod("instance")) {
            $method = $reference->getMethod("instance");
            if ($method->isStatic() && ($constructor->isProtected() || $constructor->isPrivate())) {
                $invokableMethod = $method;
            }
        }

        if (empty($object)) {
            $parameters = $invokableMethod->getParameters();
            $args = $this->mapParameters($parameters, $dependencies, $class);

            if ($reference->isInstantiable()) {
                $object = $reference->newInstanceArgs($args);
            } elseif ($reference->hasMethod("instance")) {
                if ($method->isStatic() && ($constructor->isProtected() || $constructor->isPrivate())) {
                    $object = $reference->getMethod("instance")->invokeArgs(null, $args);
                }
            }
        }
        
        self::$reflections[$class] = $reference;

        return $object;
    }

    public function makeClosure($needle, $dependencies = [])
    {
        if (!$this->isClosure($needle)) {
            return null;
        }
        $closure = new ReflectionFunction($needle);
        $parameters = $closure->getParameters();
        $args = $this->mapParameters($parameters, $dependencies, $needle);
        return function () use ($closure, $args) {
            return call_user_func_array($closure->getClosure(), $args);
        };
    }

    public function makeMethod($class, $needle, $dependencies = [])
    {
        $closure = new ReflectionMethod($class, $needle);
        $parameters = $closure->getParameters();
        $args = $this->mapParameters($parameters, $dependencies, $needle);
        if ($closure->isStatic()) {
            return $closure->invokeArgs(null, $args);
        }
        $classObj = $this->make($class);
        return function () use ($closure, $classObj, $args) {
            return $closure->invokeArgs($classObj, $args);
        };
    }

    public function resolveMethodDependencies($classRef, $method, $dependencies = [])
    {
        $method = new ReflectionMethod($classRef, $method);
        $parameters = $method->getParameters();
        $args = $this->mapParameters($parameters, $dependencies, $method->getDeclaringClass()->getName());
        return $args;
    }

    public function resolveFunctionDependencies($function, $dependencies = [])
    {
        $function = new ReflectionFunction($function);
        $parameters = $function->getParameters();
        $args = $this->mapParameters($parameters, $dependencies, null);
        return $args;
    }

    protected function mapParameters($targetParams, $dependencies, $class)
    {
        $qParams = count($targetParams);
        $args = [];

        $emptyDeps = false;
        if (!is_array($dependencies)) {
            $emptyDeps = true;
            $dependencies = [];
        }

        for ($i=0, $b = 0; $i < $qParams; $i++) {
            $param = $targetParams[$i];
            $paramIsObject  = $param['isObject'];
            $paramClass     = $param['className'];
            $paramName      = $param['paramName'];
            $hasParamDef    = $param['hasDefaultValue'];
            $paramDef       = $param['defaultValue'];

            $bindVal  = !$emptyDeps && array_key_exists($paramName, $dependencies) ? $dependencies[$paramName] : null;
            $bindName = !$emptyDeps && array_key_exists($paramName, $dependencies) ? $paramName : null;
            
            if ((empty($bindName) && empty($bindVal)) && $b < count($dependencies) && isset($dependencies[$i])) {
                $bindVal = $dependencies[$i];
            }

            
            /* La dependencia especifica concretamente el nombre del parametro */
            if ($paramName === $bindName) {
                /* La dependencia es un String que representa, u otra dependencia registrada en el
                    container, o es el nombre de una clase 
                */
                if ($paramIsObject && is_string($bindVal)) {
                    if ($this->container->has($bindVal)) {
                        $args[$paramName] = $this->make($bindVal);
                    } elseif (class_exists($bindVal)) {
                        $args[$paramName] = new $bindVal;
                    }
                } else {
                    $args[$paramName] = $bindVal;
                }
                $b++;
            } else {
                $hasBindVal = isset($bindVal);
                /* La dependencia no tiene nombre especifico, hay que determinar si "encaja" con el parametro */
                if ($paramIsObject && is_object($bindVal) && $bindVal instanceof $paramClass) {
                    /* La dependencia es un objeto concreto */
                    $args[$paramName] = $bindVal;
                    $b++;
                } elseif ($paramIsObject && is_string($bindVal)) {
                    /* La dependencia es un string que puede ser otra dep. o una clase */
                    if ($this->container->has($bindVal)) {
                        $args[$paramName] = $this->make($bindVal);
                    } elseif (class_exists($bindVal)) {
                        $args[$paramName] = new $bindVal;
                    }
                    $b++;
                } elseif ($paramIsObject && (($hasBindVal && !($bindVal instanceof $paramClass)) || !$hasBindVal) && !$hasParamDef) {
                    $args[$paramName] = $this->make($paramClass);
                } elseif ($paramIsObject && !$hasBindVal && $this->container->has($paramClass)) {
                    $args[$paramName] = $this->make($paramClass);
                } elseif (!$paramIsObject && $hasBindVal) {
                    $args[$paramName] = $bindVal;
                } elseif ($hasParamDef) {
                    $args[$paramName] = $paramDef;
                } else {
                    if (is_string($class)) {
                        throw new MissingArgumentException("Parameter '{$paramName}' is required in class {$class}.");
                    } elseif ($this->isClosure($class)) {
                        throw new MissingArgumentException("Parameter '{$paramName}' is required in closure.");
                    } else {
                        throw new MissingArgumentException("Parameter '{$paramName}' is required.");
                    }
                }
            }
        }
        return $args;
    }

    protected function isClosure($needle)
    {
        return (is_string($needle) && function_exists($needle)) || ($needle instanceof Closure);
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function dump()
    {
        $this->container->dump();
    }
}
