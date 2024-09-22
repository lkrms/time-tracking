<?php declare(strict_types=1);

namespace Lkrms\Time\Support;

use Lkrms\Time\Sync\TimeEntity\TimeEntry;
use Salient\Collection\AbstractTypedCollection;
use Salient\Utility\Arr;
use Salient\Utility\Env;
use Salient\Utility\Get;
use Salient\Utility\Test;
use LogicException;

/**
 * @property-read float $BillableAmount
 * @property-read float $BillableHours
 *
 * @extends AbstractTypedCollection<array-key,TimeEntry>
 */
final class TimeEntryCollection extends AbstractTypedCollection
{
    /**
     * @param TimeEntry $a
     * @param TimeEntry $b
     */
    protected function compareItems($a, $b, bool $strict = false): int
    {
        // Sort entries by start time if possible
        if ($a->Start && $b->Start) {
            return $a->Start <=> $b->Start;
        }

        // If not, sort by ID if both entries have integer IDs
        if (Test::isInteger($a->Id) && Test::isInteger($b->Id)) {
            return (int) $a->Id <=> (int) $b->Id;
        }

        // Otherwise leave them as-is
        return 0;
    }

    /**
     * Sort and merge time entries based on the values being displayed and used
     *
     * @param int-mask-of<TimeEntry::*> $show Passed to
     * {@see TimeEntry::getSummary()} when populating the `Description` of
     * merged entries.
     * @param (callable(TimeEntry): mixed[])|null $callback A callback to return
     * values other entries must match--in addition to the display values
     * enabled by `$show`--to merge with the given entry.
     *
     * This callback, for example, prevents entries with different project IDs
     * or billable rates being merged:
     *
     * ```php
     * fn(TimeEntry $entry) => [$entry->Project->Id ?? null, $entry->BillableRate]
     * ```
     */
    public function groupBy(
        int $show = TimeEntry::ALL,
        ?callable $callback = null,
        bool $markdown = false
    ): self {
        $dateFormat = Env::get('time_entry_date_format', 'd/m/Y');
        $timeFormat = Env::get('time_entry_time_format', 'g.ia');

        /** @var array<string,TimeEntry> */
        $groupTime = [];
        $groupSummary = [];
        foreach ($this->sort() as $t) {
            $summary = $t->getSummary(
                $dateFormat,
                $timeFormat,
                // `& TimeEntry::ALL` is to satisfy PHPStan
                $show & ~TimeEntry::DESCRIPTION & TimeEntry::ALL,
                $markdown,
            );

            $groupBy = $callback !== null ? $callback($t) : [];
            $groupBy[] = $summary;
            $groupBy = Get::hash(implode("\0", $groupBy));

            if (!array_key_exists($groupBy, $groupTime)) {
                $groupTime[$groupBy] = $t;
                $groupSummary[$groupBy] = $summary;
                continue;
            }

            $groupTime[$groupBy] = $groupTime[$groupBy]->mergeWith($t);
        }

        [$separator, $marker] = $markdown ? ["\n\n", '*'] : ["\n", null];

        $grouped = new self();
        foreach ($groupTime as $groupBy => $time) {
            $time->Description = Arr::implode($separator, [
                $groupSummary[$groupBy],
                $show & TimeEntry::DESCRIPTION
                    ? $time->description($separator, $marker)
                    : null,
            ]);

            $grouped[] = $time;
        }

        return $grouped;
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'BillableAmount':
            case 'BillableHours':
                $method = 'get' . $name;
                break;

            default:
                throw new LogicException(sprintf('Invalid property: %s', $name));
        }

        $sum = 0.0;
        foreach ($this as $item) {
            $sum += $item->$method();
        }
        return $sum;
    }
}
