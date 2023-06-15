<?php declare(strict_types=1);

namespace Lkrms\Time\Entity\Provider;

use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Time\Entity\User;

/**
 * Syncs User objects with a backend
 *
 * @method User getUser(ISyncContext $ctx, int|string|null $id)
 * @method iterable<User> getUsers(ISyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --magic --op='get,get-list' 'Lkrms\Time\Entity\User'
 */
interface UserProvider extends ISyncProvider
{
}
