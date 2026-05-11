<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/CodeLockProApiException.php';
require_once __DIR__ . '/../src/Core/EventBus.php';
require_once __DIR__ . '/../src/Core/ModuleFactory.php';
require_once __DIR__ . '/../src/Core/ModuleRegistry.php';
require_once __DIR__ . '/../src/Core/ModuleContext.php';
require_once __DIR__ . '/../src/Modules/KnowledgeBase.php';
require_once __DIR__ . '/../src/Modules/Community.php';
require_once __DIR__ . '/../src/Modules/Portal.php';
require_once __DIR__ . '/../src/CodeLockPro.php';

use CodeLockPro\CodeLockPro;
use CodeLockPro\Modules\Community;
use CodeLockPro\Modules\Portal;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$client = new CodeLockPro(
    baseUrl: 'https://api.example.test',
    applicationId: 'app_123',
    modules: false,
);

$client->register('portal', [Portal::class, 'create']);
$client->register('community', [Community::class, 'create']);

$portal = $client->portal();
$community = $client->community();

assert_true($portal instanceof Portal, 'portal() should return Portal');
assert_true($community instanceof Community, 'community() should return Community');

$portalReflection = new ReflectionClass(Portal::class);
$communityReflection = new ReflectionClass(Community::class);

$portalMethods = [];
foreach ($portalReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
    if ($method->isStatic() || $method->isConstructor() || str_starts_with($method->getName(), '__')) {
        continue;
    }
    $portalMethods[$method->getName()] = $method;
}

$communityMethods = [];
foreach ($communityReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
    if ($method->isStatic() || $method->isConstructor() || str_starts_with($method->getName(), '__')) {
        continue;
    }
    $communityMethods[$method->getName()] = $method;
}

$portalMethodNames = array_keys($portalMethods);
$communityMethodNames = array_keys($communityMethods);
sort($portalMethodNames);
sort($communityMethodNames);

assert_true(
    $portalMethodNames === $communityMethodNames,
    'Portal and Community instance method names should match for BC'
);

foreach ($portalMethodNames as $name) {
    $portalMethod = $portalMethods[$name];
    $communityMethod = $communityMethods[$name];

    assert_true(
        $portalMethod->getNumberOfRequiredParameters() === $communityMethod->getNumberOfRequiredParameters(),
        "Method $name should keep required parameter count for BC"
    );
    assert_true(
        $portalMethod->getNumberOfParameters() === $communityMethod->getNumberOfParameters(),
        "Method $name should keep total parameter count for BC"
    );

    $portalParameters = $portalMethod->getParameters();
    $communityParameters = $communityMethod->getParameters();
    foreach ($portalParameters as $index => $portalParameter) {
        $communityParameter = $communityParameters[$index];

        assert_true(
            $portalParameter->isOptional() === $communityParameter->isOptional(),
            "Method $name parameter #$index optionality should match"
        );
        assert_true(
            $portalParameter->isVariadic() === $communityParameter->isVariadic(),
            "Method $name parameter #$index variadic flag should match"
        );
        assert_true(
            $portalParameter->isPassedByReference() === $communityParameter->isPassedByReference(),
            "Method $name parameter #$index reference flag should match"
        );

        $portalParameterType = $portalParameter->getType();
        $communityParameterType = $communityParameter->getType();
        assert_true(
            ($portalParameterType?->__toString() ?? null) === ($communityParameterType?->__toString() ?? null),
            "Method $name parameter #$index type should match"
        );
    }

    $portalReturnType = $portalMethod->getReturnType();
    $communityReturnType = $communityMethod->getReturnType();
    assert_true(
        ($portalReturnType?->__toString() ?? null) === ($communityReturnType?->__toString() ?? null),
        "Method $name return type should match"
    );
}

$seen = [];
$portalHandler = $portal->on('thread.created', function (array $payload) use (&$seen): void {
    $seen['portal'] = $payload['id'] ?? null;
});
$communityHandler = $community->on('thread.created', function (array $payload) use (&$seen): void {
    $seen['community'] = $payload['id'] ?? null;
});

$client->emit('portal.thread.created', ['id' => 'pt_1']);
$client->emit('community.thread.created', ['id' => 'ct_1']);

assert_true(($seen['portal'] ?? null) === 'pt_1', 'portal namespace event should be routed');
assert_true(($seen['community'] ?? null) === 'ct_1', 'community namespace event should be routed');

$portal->off('thread.created', $portalHandler);
$community->off('thread.created', $communityHandler);

echo "OK\n";
