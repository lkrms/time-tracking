<?php declare(strict_types=1);

namespace Lkrms\Time\Entity\Provider;

use Lkrms\Sync\Support\SyncContext;
use Lkrms\Time\Entity\TimeEntry;

/**
 * Syncs TimeEntry objects with a backend
 *
 * @method TimeEntry createTimeEntry(SyncContext $ctx, TimeEntry $timeEntry)
 * @method TimeEntry getTimeEntry(SyncContext $ctx, int|string|null $id)
 * @method TimeEntry updateTimeEntry(SyncContext $ctx, TimeEntry $timeEntry)
 * @method TimeEntry deleteTimeEntry(SyncContext $ctx, TimeEntry $timeEntry)
 * @method iterable<TimeEntry> getTimeEntries(SyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --class='Lkrms\Time\Entity\TimeEntry' --extend='Lkrms\Time\Entity\Provider\ClientProvider,Lkrms\Time\Entity\Provider\ProjectProvider,Lkrms\Time\Entity\Provider\TaskProvider,Lkrms\Time\Entity\Provider\UserProvider' --magic --op='create,get,update,delete,get-list'
 */
interface TimeEntryProvider extends ClientProvider, ProjectProvider, TaskProvider, UserProvider
{
}
