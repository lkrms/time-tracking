<?php declare(strict_types=1);

namespace Lkrms\Time\Command;

use Salient\Cli\CliOption;
use Salient\Core\Facade\Console;
use Salient\Utility\Env;
use Salient\Utility\Format;
use Salient\Utility\Inflect;

final class MarkTimeEntriesInvoiced extends AbstractCommand
{
    protected bool $MarkUninvoiced = false;

    public function getDescription(): string
    {
        return 'Mark time entries as invoiced or uninvoiced';
    }

    protected function getOptionList(): iterable
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
            Env::setDryRun(true);
        }

        Console::info("Retrieving time entries from {$this->TimeEntryProviderName}");

        $state = $this->MarkUninvoiced ? 'uninvoiced' : 'invoiced';

        $markInvoiced = [];
        $totalAmount = 0;
        $totalHours = 0;
        foreach ($this->getTimeEntries(true, $this->MarkUninvoiced) as $entry) {
            $markInvoiced[] = $entry;
            $totalAmount += $entry->getBillableAmount();
            $totalHours += $entry->getBillableHours();
        }

        $count = Inflect::format($markInvoiced, '{{#}} time {{#:entry}}');
        $total = $this->getBillableSummary($totalAmount, $totalHours);

        foreach ($markInvoiced as $entry) {
            printf(
                "%s %s as %s: %.2f hours on %s ('%s', %s)\n",
                Env::getDryRun() ? 'Would mark' : 'Marking',
                $entry->Id,
                $state,
                $entry->getBillableHours(),
                $entry->Start ? Format::date($entry->Start) : '<no date>',
                $entry->Project->Name ?? '<no project>',
                $entry->Project->Client->Name ?? '<no client>',
            );
        }

        if (Env::getDryRun()) {
            Console::info("$count would be marked as $state:", $total);
            return;
        }

        Console::info("Marking $count in {$this->TimeEntryProviderName} as $state:", $total);
        $this->TimeEntryProvider->markTimeEntriesInvoiced($markInvoiced, (bool) $this->MarkUninvoiced);

        Console::summary("$count marked as $state");
    }
}
