<?php declare(strict_types=1);

use Lkrms\Sync\Event\SyncStoreLoadedEvent;
use Lkrms\Time\Sync\SyncClassResolver;
use Salient\Core\Facade\Event;

Event::listen(
    fn(SyncStoreLoadedEvent $event) =>
        $event->store()->namespace(
            'tt',
            'https://sync.linacreative.com/time-tracking',
            'Lkrms\Time\Sync',
            SyncClassResolver::class,
        ),
);
