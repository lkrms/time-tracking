<?php declare(strict_types=1);

namespace Lkrms\Time\Entity\Provider;

use Lkrms\Time\Entity\TimeEntry;

/**
 * Syncs billable TimeEntry objects with a backend
 */
interface BillableTimeProvider extends TimeEntryProvider
{
    /**
     * @param iterable<TimeEntry> $timeEntries
     * @param bool $unmark
     */
    public function markTimeEntriesInvoiced(iterable $timeEntries, bool $unmark = false): void;
}
