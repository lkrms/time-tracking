<?php

declare(strict_types=1);

namespace Lkrms\Time\Support;

use Lkrms\Concept\TypedCollection;
use Lkrms\Concern\HasContainer;
use Lkrms\Facade\Compute;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\Test;
use Lkrms\Time\Entity\TimeEntry;
use UnexpectedValueException;

/**
 * @property-read float $BillableAmount
 * @property-read float $BillableHours
 * @method TimeEntry[] toArray()
 * @method TimeEntryCollection sort()
 */
class TimeEntryCollection extends TypedCollection
{
    use HasContainer;

    protected function getItemClass(): string
    {
        return TimeEntry::class;
    }

    /**
     * @param TimeEntry $a
     * @param TimeEntry $b
     * @return int
     */
    protected function compareItems($a, $b, bool $strict = false): int
    {
        // Sort entries by start time if possible
        if ($a->Start && $b->Start)
        {
            if ($a->Start < $b->Start) { return - 1; }
            elseif ($a->Start > $b->Start) { return 1; }
        }

        // If not, sort by ID if both entries have integer IDs
        if (Test::isIntValue($a->Id) && Test::isIntValue($b->Id))
        {
            return ((int)$a->Id) - ((int)$b->Id);
        }

        // Otherwise leave them as-is
        return 0;
    }

    /**
     * Sort and merge time entries based on the values being displayed and used
     *
     * @param int $show A bitmask of `TimeEntry::*` values. Passed to
     * {@see TimeEntry::getSummary()} when populating the `Description` of
     * merged entries.
     * @param callable|null $callback A callback to return values other entries
     * must match--in addition to the display values enabled by `$show`--to
     * merge with the given entry.
     *
     * This callback, for example, prevents entries with different project IDs
     * or billable rates being merged:
     *
     * ```php
     * fn(TimeEntry $entry) => [$entry->Project->Id ?? null, $entry->BillableRate]
     * ```
     * @return TimeEntryCollection
     */
    public function groupBy(
        $show = TimeEntry::ALL,
        callable $callback = null,
        bool $markdown     = false
    ): TimeEntryCollection
    {
        $dateFormat = Env::get("time_entry_date_format", "d/m/Y");
        $timeFormat = Env::get("time_entry_time_format", "g.ia");

        $times = $this->sort()->toArray();

        /** @var array<string,TimeEntry> */
        $groupTime    = [];
        $groupSummary = [];
        foreach ($times as $t)
        {
            $summary = $t->getSummary(
                $show & ~TimeEntry::DESCRIPTION,
                $dateFormat,
                $timeFormat,
                $markdown
            );

            $groupBy   = !is_null($callback) ? $callback($t) : [];
            $groupBy[] = $summary;
            $groupBy   = Compute::hash(...$groupBy);

            if (!array_key_exists($groupBy, $groupTime))
            {
                $groupTime[$groupBy]    = $t;
                $groupSummary[$groupBy] = $summary;
                continue;
            }

            $groupTime[$groupBy] = $groupTime[$groupBy]->mergeWith($t);
        }

        /** @var TimeEntryCollection */
        $grouped = $this->app()->get(static::class);
        list ($separator, $marker) = $markdown ? ["\n\n", "*"] : ["\n", null];
        foreach ($groupTime as $groupBy => $time)
        {
            $time->Description = Convert::sparseToString($separator, [
                $groupSummary[$groupBy],
                ($show & TimeEntry::DESCRIPTION
                    ? Convert::linesToLists($time->Description, $separator, $marker)
                    : null),
            ]);
            $grouped[] = $time;
        }
        return $grouped;
    }

    public function __get(string $name)
    {
        switch ($name)
        {
            case "BillableAmount":
                return array_reduce(
                    $this->toArray(),
                    fn($prev, TimeEntry $item) => $prev + $item->getBillableAmount(),
                    0
                );

            case "BillableHours":
                return array_reduce(
                    $this->toArray(),
                    fn($prev, TimeEntry $item) => $prev + $item->getBillableHours(),
                    0
                );

            default:
                throw new UnexpectedValueException("Undefined property: $name");
        }
    }
}
