<?php declare(strict_types=1);

namespace Lkrms\Time\Entity\Provider;

use DateTimeInterface;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Time\Entity\Client;
use Lkrms\Time\Entity\Project;
use Lkrms\Time\Entity\TimeEntry;
use Lkrms\Time\Entity\User;

/**
 * Syncs billable TimeEntry objects with a backend
 *
 * @method iterable<TimeEntry> getTimeEntries(SyncContext $ctx, User|int|string|null $user = null, Client|int|string|null $client = null, Project|int|string|null $project = null, DateTimeInterface|null $from = null, DateTimeInterface|null $to = null, bool|null $billable = null, bool|null $billed)
 * @method void markTimeEntriesInvoiced(iterable<TimeEntry> $timeEntries, bool $unmark = false)
 */
interface BillableTimeEntryProvider extends TimeEntryProvider
{
}
