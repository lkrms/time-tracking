<?php declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Time\Concept\Command;

class MarkTimeEntriesInvoiced extends Command
{
    public function description(): string
    {
        return 'Mark time entries as invoiced';
    }

    protected function getOptionList(): array
    {
        return $this->getTimeEntryOptions($this->description());
    }

    protected function run(string ...$params)
    {
        if (!$this->Force) {
            Env::dryRun(true);
        }

        Console::info('Retrieving time entries from', $this->TimeEntryProviderName);

        $markInvoiced = [];
        $totalAmount = 0;
        $totalHours = 0;
        foreach ($this->getTimeEntries(true, false) as $entry) {
            $markInvoiced[] = $entry;
            $totalAmount += $entry->getBillableAmount();
            $totalHours += $entry->getBillableHours();
        }

        $count = Convert::plural(count($markInvoiced), 'time entry', 'time entries', true);
        $total = $this->getBillableSummary($totalAmount, $totalHours);

        if (Env::dryRun()) {
            foreach ($markInvoiced as $entry) {
                printf(
                    "Would mark %s as invoiced: %.2f hours on %s ('%s', %s)\n",
                    $entry->Id,
                    $entry->getBillableHours(),
                    $entry->Start->format('d/m/Y'),
                    $entry->Project->Name ?? '<no project>',
                    $entry->Project->Client->Name ?? '<no client>'
                );
            }
            Console::info("$count would be marked as invoiced:", $total);

            return;
        }

        Console::info("Marking $count in " . $this->TimeEntryProviderName . ' as invoiced:', $total);
        $this->TimeEntryProvider->markTimeEntriesInvoiced($markInvoiced);
    }
}
