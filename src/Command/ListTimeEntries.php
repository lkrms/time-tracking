<?php

namespace Lkrms\Time\Command;

use Lkrms\Console\Console;
use Lkrms\Time\Concept\Command;
use Lkrms\Time\Entity\TimeEntry;
use Lkrms\Time\Support\TimeEntryCollection;
use Lkrms\Util\Convert;

class ListTimeEntries extends Command
{
    protected function _getDescription(): string
    {
        return "Summarise time entries in " . $this->TimeEntryProviderName;
    }

    protected function _getOptions(): array
    {
        return $this->getTimeEntryOptions("List time entries", true, false, true);
    }

    protected function _run(string ...$params)
    {
        Console::info("Retrieving time entries from", $this->TimeEntryProviderName);

        /** @var TimeEntryCollection */
        $times          = $this->App->get(TimeEntryCollection::class);
        $billableCount  = 0;
        $billableAmount = 0;
        $billableHours  = 0;
        foreach ($this->getTimeEntries() as $entry)
        {
            $times[] = $entry;
            if (!$entry->IsInvoiced)
            {
                $billableCount++;
                $billableAmount += $entry->getBillableAmount();
                $billableHours  += $entry->getBillableHours();
            }
        }
        $times = $times->groupBy($this->getTimeEntryMask(), null, true);

        /** @var TimeEntry $entry */
        foreach ($times as $entry)
        {
            printf("%s\n\n", $entry->Description);
        }

        $count = Convert::numberToNoun($billableCount, "time entry is", "time entries are", true);
        $total = $this->getBillableSummary($billableAmount, $billableHours);
        Console::info("$count uninvoiced:", $total);
    }
}
