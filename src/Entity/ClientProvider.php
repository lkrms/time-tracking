<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;

/**
 * Syncs Client objects with a backend
 *
 * @method Client getClient(SyncContext $ctx, int|string|null $id)
 * @method iterable<Client> getClients(SyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --class='Lkrms\Time\Entity\Client' --magic --op='get,get-list'
 */
interface ClientProvider extends ISyncProvider
{
}
