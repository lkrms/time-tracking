<?php declare(strict_types=1);

namespace Lkrms\Time\Entity\Provider;

use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Time\Entity\Client;

/**
 * Syncs Client objects with a backend
 *
 * @method Client getClient(ISyncContext $ctx, int|string|null $id)
 * @method iterable<Client> getClients(ISyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --magic --op='get,get-list' 'Lkrms\Time\Entity\Client'
 */
interface ClientProvider extends ISyncProvider
{
}
