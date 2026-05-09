<?php

declare(strict_types=1);

namespace CodeLockPro\Core;

/**
 * Internal module registry. Exposed via :meth:`CodeLockPro::register`
 * and :meth:`CodeLockPro::module`.
 */
final class ModuleRegistry
{
    /** @var array<string, object> */
    private array $modules = [];

    public function register(string $name, object $instance): object
    {
        if ($name === '') {
            throw new \InvalidArgumentException('register: module name is required');
        }
        if (isset($this->modules[$name])) {
            throw new \LogicException("register: module \"$name\" is already registered");
        }
        $this->modules[$name] = $instance;
        return $instance;
    }

    public function get(string $name): object
    {
        if (!isset($this->modules[$name])) {
            throw new \LogicException("module: no module registered as \"$name\"");
        }
        return $this->modules[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->modules);
    }
}
