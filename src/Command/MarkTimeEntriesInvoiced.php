<?php declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Cli\CliOption;
use Lkrms\Facade\Console;
use Lkrms\Time\Command\Concept\Command;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Env;

class MarkTimeEntriesInvoiced extends Command
{
    protected ?bool $MarkUninvoiced;

    public function description(): string
    {
        return 'Mark time entries as invoiced or uninvoiced';
    }

    protected function getOptionList(): array
    {
        return $this->getTimeEntryOptions(
            'Mark time entries',
            true,
            true,
            false,
            false,
            false,
            [
                CliOption::build()
                    ->long('mark-uninvoiced')
                    ->short('u')
                    ->description(<<<'EOF'
                        Mark invoiced time entries as uninvoiced

                        The command's default behaviour is to find uninvoiced time entries and mark them
                        as invoiced. Use this option to achieve the opposite.
                        EOF)
                    ->bindTo($this->MarkUninvoiced),
            ]
        );
    }

    protected function run(string ...$params)
    {
        if (!$this->Force) {
            Env::dryRun(true);
        }

        Console::info('Retrieving time entries from', $this->TimeEntryProviderName);

        $state = $this->MarkUninvoiced ? 'uninvoiced' : 'invoiced';

        $markInvoiced = [];
        $totalAmount = 0;
        $totalHours = 0;
        foreach ($this->getTimeEntries(true, $this->MarkUninvoiced) as $entry) {
            $markInvoiced[] = $entry;
            $totalAmount += $entry->getBillableAmount();
            $totalHours += $entry->getBillableHours();
        }

        $count = Convert::plural(count($markInvoiced), 'time entry', 'time entries', true);
        $total = $this->getBillableSummary($totalAmount, $totalHours);

        if (Env::dryRun()) {
            foreach ($markInvoiced as $entry) {
                printf(
                    "Would mark %s as %s: %.2f hours on %s ('%s', %s)\n",
                    $entry->Id,
                    $state,
                    $entry->getBillableHours(),
                    $entry->Start->format('d/m/Y'),
                    $entry->Project->Name ?? '<no project>',
                    $entry->Project->Client->Name ?? '<no client>'
                );
            }
            Console::info("$count would be marked as $state:", $total);

            return;
        }

        Console::info("Marking $count in " . $this->TimeEntryProviderName . " as $state:", $total);
        $this->TimeEntryProvider->markTimeEntriesInvoiced($markInvoiced, $this->MarkUninvoiced);
    }
}
