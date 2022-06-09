<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

use DateTime;

/**
 * Synchronises TimeEntry objects with a backend
 *
 */
interface TimeEntryProvider extends ClientProvider, ProjectProvider, TaskProvider, UserProvider
{
    /**
     * @param TimeEntry $timeEntry
     * @return TimeEntry
     */
    public function createTimeEntry(TimeEntry $timeEntry): TimeEntry;

    /**
     * @param int|string $id
     * @return TimeEntry
     */
    public function getTimeEntry($id): TimeEntry;

    /**
     * @param TimeEntry $timeEntry
     * @return TimeEntry
     */
    public function updateTimeEntry(TimeEntry $timeEntry): TimeEntry;

    /**
     * @param TimeEntry $timeEntry
     * @return null|TimeEntry
     */
    public function deleteTimeEntry(TimeEntry $timeEntry): ?TimeEntry;

    /**
     * @param User|int|string|null $user
     * @param Client|int|string|null $client
     * @param Project|int|string|null $project
     * @param DateTime|null $from
     * @param DateTime|null $to
     * @param bool|null $billable
     * @param bool|null $billed
     * @return iterable<TimeEntry>
     */
    public function getTimeEntries(
        $user          = null,
        $client        = null,
        $project       = null,
        DateTime $from = null,
        DateTime $to   = null,
        bool $billable = null,
        bool $billed   = null
    ): iterable;

    /**
     * @param iterable<TimeEntry> $timeEntries
     */
    public function markTimeEntriesInvoiced(
        iterable $timeEntries,
        bool $unmark = false
    ): void;

}
