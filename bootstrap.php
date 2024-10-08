<?php declare(strict_types=1);

use Lkrms\Time\Sync\SyncNamespaceHelper;
use Salient\Core\Facade\Event;
use Salient\Sync\Event\SyncStoreLoadedEvent;

Event::getInstance()->listen(
    fn(SyncStoreLoadedEvent $event) =>
        $event->getStore()->registerNamespace(
            'tt',
            'https://sync.linacreative.com/time-tracking',
            'Lkrms\Time\Sync',
            new SyncNamespaceHelper(),
        ),
);
