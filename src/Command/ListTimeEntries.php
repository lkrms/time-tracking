<?php declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Time\Command\Concept\Command;
use Lkrms\Time\Support\TimeEntryCollection;
use Lkrms\Time\Sync\TimeEntity\TimeEntry;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\Inflect;

class ListTimeEntries extends Command
{
    public function description(): string
    {
        return 'Summarise time entries in ' . $this->TimeEntryProviderName;
    }

    protected function getOptionList(): array
    {
        return $this->getTimeEntryOptions('List time entries', true, false, true, true, true);
    }

    protected function run(string ...$params)
    {
        Console::info("Retrieving time entries from {$this->TimeEntryProviderName}");

        /** @var TimeEntryCollection */
        $times = $this->App->get(TimeEntryCollection::class);
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
