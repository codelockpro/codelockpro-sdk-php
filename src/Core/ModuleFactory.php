<?php

declare(strict_types=1);

namespace CodeLockPro\Core;

/**
 * Module factory contract.
 *
 * A factory is any ``callable(ModuleContext): object`` — typically a
 * static method like ``KnowledgeBase::create(...)`` or an invokable
 * class. The SDK core invokes it once at registration time and stores
 * the returned object in the registry. The factory shape is identical
 * for every module, so the registration surface is module-agnostic.
 *
 * This class exists purely as documentation; PHP cannot express the
 * ``callable(ModuleContext): object`` shape natively. ``ModuleRegistry``
 * accepts any callable.
 */
final class ModuleFactory
{
    private function __construct()
    {
    }
}
