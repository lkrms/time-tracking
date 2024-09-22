<?php declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Time\Support\TimeEntryCollection;
use Lkrms\Time\Sync\TimeEntity\TimeEntry;
use Salient\Core\Facade\Console;
use Salient\Utility\Inflect;

final class ListTimeEntries extends AbstractCommand
{
    public function getDescription(): string
    {
        return 'Summarise time entries in ' . $this->TimeEntryProviderName;
    }

    protected function getOptionList(): iterable
    {
        return $this->getTimeEntryOptions('List time entries', true, false, true, true, true);
    }

    protected function run(string ...$params)
    {
        Console::info("Retrieving time entries from {$this->TimeEntryProviderName}");

        $times = new TimeEntryCollection();
        $billableCount = 0;
        $billableAmount = 0;
        $billableHours = 0;
        foreach ($this->getTimeEntries() as $entry) {
            $times[] = $entry;
            if (!$entry->IsInvoiced) {
                $billableCount++;
                $billableAmount += $entry->getBillableAmount();
                $billableHours += $entry->getBillableHours();
            }
        }
        $times = $times->groupBy($this->getTimeEntryMask(), null, true);

        /** @var TimeEntry $entry */
        foreach ($times as $entry) {
            printf("%s\n\n", $entry->Description);
        }

        $count = Inflect::format($billableCount, '{{#}} time {{#:entry}} {{#:is}}');
        $total = $this->getBillableSummary($billableAmount, $billableHours);
        Console::info("$count unbilled:", $total);
    }
}
