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

$portalMethods = array_values(array_diff(get_class_methods(Portal::class), ['__construct']));
$communityMethods = array_values(array_diff(get_class_methods(Community::class), ['__construct']));
sort($portalMethods);
sort($communityMethods);
assert_true(
    $portalMethods === $communityMethods,
    'Portal and Community public surfaces should match for BC'
);

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
