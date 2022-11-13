<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity\Provider;

use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Time\Entity\Task;

/**
 * Syncs Task objects with a backend
 *
 * @method Task getTask(SyncContext $ctx, int|string|null $id)
 * @method iterable<Task> getTasks(SyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --class='Lkrms\Time\Entity\Task' --magic --op='get,get-list'
 */
interface TaskProvider extends ISyncProvider
{
}
