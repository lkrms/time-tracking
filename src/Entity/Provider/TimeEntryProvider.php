<?php declare(strict_types=1);

namespace Lkrms\Time\Entity\Provider;

use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Time\Entity\TimeEntry;

/**
 * Syncs TimeEntry objects with a backend
 *
 * @method TimeEntry createTimeEntry(ISyncContext $ctx, TimeEntry $timeEntry)
 * @method TimeEntry getTimeEntry(ISyncContext $ctx, int|string|null $id)
 * @method TimeEntry updateTimeEntry(ISyncContext $ctx, TimeEntry $timeEntry)
 * @method TimeEntry deleteTimeEntry(ISyncContext $ctx, TimeEntry $timeEntry)
 * @method iterable<TimeEntry> getTimeEntries(ISyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --extend='Lkrms\Time\Entity\Provider\ClientProvider,Lkrms\Time\Entity\Provider\ProjectProvider,Lkrms\Time\Entity\Provider\TaskProvider,Lkrms\Time\Entity\Provider\UserProvider' --magic --op='create,get,update,delete,get-list' 'Lkrms\Time\Entity\TimeEntry'
 */
interface TimeEntryProvider extends ClientProvider, ProjectProvider, TaskProvider, UserProvider
{
}
