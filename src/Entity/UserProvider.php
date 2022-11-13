<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;

/**
 * Syncs User objects with a backend
 *
 * @method User getUser(SyncContext $ctx, int|string|null $id)
 * @method iterable<User> getUsers(SyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --class='Lkrms\Time\Entity\User' --extend='' --magic --op='get,get-list'
 */
interface UserProvider extends ISyncProvider
{
}
