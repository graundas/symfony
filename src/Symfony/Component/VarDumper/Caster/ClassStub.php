<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use Symfony\Component\DependencyInjection\LazyProxy\InheritanceProxyInterface;

/**
 * Represents a PHP class identifier.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ClassStub extends ConstStub
{
    /**
     * Constructor.
     *
     * @param string   A PHP identifier, e.g. a class, method, interface, etc. name
     * @param callable The callable targeted by the identifier when it is ambiguous or not a real PHP identifier
     */
    public function __construct($identifier, $callable = null)
    {
        $this->value = $identifier;

        if (0 < $i = strrpos($identifier, '\\')) {
            $this->attr['ellipsis'] = strlen($identifier) - $i;
        }

        try {
            if (null !== $callable) {
                if ($callable instanceof \Closure) {
                    $r = new \ReflectionFunction($callable);

                    if (preg_match('#^/\*\* @closure-proxy ([^: ]++)::([^: ]++) \*/$#', $r->getDocComment(), $m)) {
                        $r = array($m[1], $m[2]);
                    }
                } elseif (is_object($callable)) {
                    $r = array($callable, '__invoke');
                } elseif (is_array($callable)) {
                    $r = $callable;
                } elseif (false !== $i = strpos($callable, '::')) {
                    $r = array(substr($callable, 0, $i), substr($callable, 2 + $i));
                } else {
                    $r = new \ReflectionFunction($callable);
                }
            } elseif (false !== $i = strpos($identifier, '::')) {
                $r = array(substr($identifier, 0, $i), substr($identifier, 2 + $i));
            } else {
                $r = new \ReflectionClass($identifier);
            }

            if (is_array($r)) {
                try {
                    $r = new \ReflectionMethod($r[0], $r[1]);
                } catch (\ReflectionException $e) {
                    $r = new \ReflectionClass($r[0]);
                }
            }
        } catch (\ReflectionException $e) {
            return;
        }

        if (interface_exists(InheritanceProxyInterface::class, false)) {
            $c = $r instanceof \ReflectionMethod ? $r->getDeclaringClass() : $r;
            if ($c instanceof \ReflectionClass && $c->implementsInterface(InheritanceProxyInterface::class)) {
                $p = $c->getParentClass();
                $this->value = $identifier = str_replace($c->name, $p->name.'@proxy', $identifier);
                if (0 < $i = strrpos($identifier, '\\')) {
                    $this->attr['ellipsis'] = strlen($identifier) - $i;
                }
                $r = $r instanceof \ReflectionMethod ? $p->getMethod($r->name) : $p;
            }
        }

        if ($f = $r->getFileName()) {
            $this->attr['file'] = $f;
            $this->attr['line'] = $r->getStartLine();
        }
    }

    public static function wrapCallable($callable)
    {
        if (is_object($callable) || !is_callable($callable)) {
            return $callable;
        }

        if (!is_array($callable)) {
            $callable = new static($callable);
        } elseif (is_string($callable[0])) {
            $callable[0] = new static($callable[0]);
        } else {
            $callable[1] = new static($callable[1], $callable);
        }

        return $callable;
    }
}
