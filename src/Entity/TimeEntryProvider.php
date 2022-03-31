<?php

declare(strict_types=1);

namespace Lkrms\Clockify\Entity;

use DateTime;

/**
 * Synchronises TimeEntry objects with a backend
 *
 * @package Lkrms\Clockify
 */
interface TimeEntryProvider extends \Lkrms\Sync\Provider\ISyncProvider
{
    /**
     * @return TimeEntry[]
     */
    public function getTimeEntries(
        User $user     = null,
        $client        = null,
        $project       = null,
        DateTime $from = null,
        DateTime $to   = null,
        bool $isBilled = null
    ): array;

    /**
     * @param int|string $id
     * @return TimeEntry
     */
    public function getTimeEntry($id): TimeEntry;

    /**
     * @param TimeEntry $timeEntry
     * @return TimeEntry
     */
    public function createTimeEntry(TimeEntry $timeEntry): TimeEntry;

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

}

