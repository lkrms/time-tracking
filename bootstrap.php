<?php declare(strict_types=1);

use Lkrms\Facade\Event;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Time\Sync\SyncClassResolver;

Event::listen(
    'sync.store.load',
    fn(SyncStore $store) =>
        $store->namespace(
            'tt',
            'https://sync.linacreative.com/time-tracking',
            'Lkrms\Time\Sync',
            SyncClassResolver::class,
        ),
);
