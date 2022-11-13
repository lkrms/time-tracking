<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

use DateTime;
use Lkrms\Sync\Support\SyncContext;

/**
 * Syncs billable TimeEntry objects with a backend
 *
 * @method iterable<TimeEntry> getTimeEntries(SyncContext $ctx, User|int|string|null $user = null, Client|int|string|null $client = null, Project|int|string|null $project = null, DateTime|null $from = null, DateTime|null $to = null, bool|null $billable = null, bool|null $billed)
 * @method void markTimeEntriesInvoiced(iterable<TimeEntry> $timeEntries, bool $unmark = false)
 */
interface BillableTimeEntryProvider extends TimeEntryProvider
{
}
