<?php

namespace Lkrms\Time\Command;

use Lkrms\Console\Console;
use Lkrms\Time\Concept\Command;
use Lkrms\Util\Convert;
use Lkrms\Util\Env;

class MarkTimeEntriesInvoiced extends Command
{
    protected function _getDescription(): string
    {
        return "Mark time entries as invoiced";
    }

    protected function _getOptions(): array
    {
        return $this->getTimeEntryOptions($this->_getDescription());
    }

    protected function _run(string ...$params)
    {
        if (!$this->getOptionValue("force"))
        {
            Env::dryRun(true);
        }

        Console::info("Retrieving time entries from", $this->TimeEntryProviderName);

        $markInvoiced = [];
        $totalAmount  = 0;
        $totalHours   = 0;
        foreach ($this->getTimeEntries(true, false) as $entry)
        {
            $markInvoiced[] = $entry;
            $totalAmount   += $entry->getBillableAmount();
            $totalHours    += $entry->getBillableHours();
        }

        $count = Convert::numberToNoun(count($markInvoiced), "time entry", "time entries", true);
        $total = $this->getBillableSummary($totalAmount, $totalHours);

        if (Env::dryRun())
        {
            foreach ($markInvoiced as $entry)
            {
                printf("Would mark %s as invoiced: %.2f hours on %s ('%s', %s)\n",
                    $entry->Id,
                    $entry->getBillableHours(),
                    $entry->Start->format("d/m/Y"),
                    $entry->Project->Name ?? "<no project>",
                    $entry->Project->Client->Name ?? "<no client>");
            }
            Console::info("$count would be marked as invoiced:", $total);
            return;
        }

        Console::info("Marking $count in " . $this->TimeEntryProviderName . " as invoiced:", $total);
        $this->TimeEntryProvider->markTimeEntriesInvoiced($markInvoiced);
    }
}
