<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

use Lkrms\Sync\Support\SyncContext;

/**
 * Syncs TimeEntry objects with a backend
 *
 * @method TimeEntry createTimeEntry(SyncContext $ctx, TimeEntry $timeEntry)
 * @method TimeEntry getTimeEntry(SyncContext $ctx, int|string|null $id)
 * @method TimeEntry updateTimeEntry(SyncContext $ctx, TimeEntry $timeEntry)
 * @method TimeEntry deleteTimeEntry(SyncContext $ctx, TimeEntry $timeEntry)
 * @method iterable<TimeEntry> getTimeEntries(SyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --class='Lkrms\Time\Entity\TimeEntry' --extend='Lkrms\Time\Entity\ClientProvider,Lkrms\Time\Entity\ProjectProvider,Lkrms\Time\Entity\TaskProvider,Lkrms\Time\Entity\UserProvider' --magic --op='create,get,update,delete,get-list'
 */
interface TimeEntryProvider extends ClientProvider, ProjectProvider, TaskProvider, UserProvider
{
}
