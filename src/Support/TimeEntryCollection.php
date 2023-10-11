<?php declare(strict_types=1);

namespace Lkrms\Time\Support;

use Lkrms\Concept\TypedCollection;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\ReceivesContainer;
use Lkrms\Contract\ReturnsContainer;
use Lkrms\Facade\Compute;
use Lkrms\Time\Entity\TimeEntry;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Env;
use Lkrms\Utility\Test;
use UnexpectedValueException;

/**
 * @property-read float $BillableAmount
 * @property-read float $BillableHours
 *
 * @extends TypedCollection<array-key,TimeEntry>
 * @implements ReturnsContainer<IContainer>
 */
final class TimeEntryCollection extends TypedCollection implements ReceivesContainer
{
    protected const ITEM_CLASS = TimeEntry::class;

    /**
     * @var IContainer|null
     */
    private $App;

    public function setContainer(IContainer $container)
    {
        $this->App = $container;
        return $this;
    }

    /**
     * @param TimeEntry $a
     * @param TimeEntry $b
     * @return int
     */
    protected function compareItems($a, $b, bool $strict = false): int
    {
        // Sort entries by start time if possible
        if ($a->Start && $b->Start) {
            return $a->Start <=> $b->Start;
        }

        // If not, sort by ID if both entries have integer IDs
        if (Test::isIntValue($a->Id) && Test::isIntValue($b->Id)) {
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
     * @return TimeEntryCollection
     */
    public function groupBy(
        int $show = TimeEntry::ALL,
        ?callable $callback = null,
        bool $markdown = false
    ): TimeEntryCollection {
        $dateFormat = Env::get('time_entry_date_format', 'd/m/Y');
        $timeFormat = Env::get('time_entry_time_format', 'g.ia');

        $times = $this->sort()->all();

        /** @var array<string,TimeEntry> */
        $groupTime = [];
        $groupSummary = [];
        foreach ($times as $t) {
            $summary = $t->getSummary(
                $show & ~TimeEntry::DESCRIPTION,
                $dateFormat,
                $timeFormat,
                $markdown
            );

            $groupBy = $callback !== null ? $callback($t) : [];
            $groupBy[] = $summary;
            $groupBy = Compute::hash(...$groupBy);

            if (!array_key_exists($groupBy, $groupTime)) {
                $groupTime[$groupBy] = $t;
                $groupSummary[$groupBy] = $summary;
                continue;
            }

            $groupTime[$groupBy] = $groupTime[$groupBy]->mergeWith($t);
        }

        [$separator, $marker] = $markdown ? ["\n\n", '*'] : ["\n", null];

        $grouped = $this->app()->get(static::class);
        foreach ($groupTime as $groupBy => $time) {
            $time->Description = Convert::sparseToString($separator, [
                $groupSummary[$groupBy],
                $show & TimeEntry::DESCRIPTION
                    ? $time->description($separator, $marker)
                    : null,
            ]);

            $grouped[] = $time;
        }

        return $grouped;
    }

    public function __get(string $name)
    {
        switch ($name) {
            case 'BillableAmount':
                return array_reduce(
                    $this->all(),
                    fn($prev, TimeEntry $item) => $prev + $item->getBillableAmount(),
                    0
                );

            case 'BillableHours':
                return array_reduce(
                    $this->all(),
                    fn($prev, TimeEntry $item) => $prev + $item->getBillableHours(),
                    0
                );

            default:
                throw new UnexpectedValueException("Undefined property: $name");
        }
    }

    private function app(): IContainer
    {
        return $this->App
            ?: ($this->App = Container::getGlobalContainer());
    }
}
