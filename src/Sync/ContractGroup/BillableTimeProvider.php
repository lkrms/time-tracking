<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\ContractGroup;

use Lkrms\Time\Sync\Contract\ProvidesClient;
use Lkrms\Time\Sync\Contract\ProvidesProject;
use Lkrms\Time\Sync\Contract\ProvidesTask;
use Lkrms\Time\Sync\Contract\ProvidesTimeEntry;
use Lkrms\Time\Sync\Contract\ProvidesUser;
use Lkrms\Time\Sync\Entity\TimeEntry;

/**
 * Syncs billable time entries and related entities with a backend
 */
interface BillableTimeProvider extends
    ProvidesTimeEntry,
    ProvidesClient,
    ProvidesProject,
    ProvidesTask,
    ProvidesUser
{
    /**
     * Mark or unmark time entries as invoiced
     *
     * @param iterable<TimeEntry> $timeEntries
     */
    public function markTimeEntriesInvoiced(iterable $timeEntries, bool $unmark = false): void;
}
