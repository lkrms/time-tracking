<?php declare(strict_types=1);

namespace Lkrms\Time\Entity\Provider;

use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Time\Entity\Task;

/**
 * Syncs Task objects with a backend
 *
 * @method Task getTask(ISyncContext $ctx, int|string|null $id)
 * @method iterable<Task> getTasks(ISyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --magic --op='get,get-list' 'Lkrms\Time\Entity\Task'
 */
interface TaskProvider extends ISyncProvider
{
}
