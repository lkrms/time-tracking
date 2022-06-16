<?php

namespace Lkrms\Time\Command;

use DateTime;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOptionType;
use Lkrms\Console\Console;
use Lkrms\Container\AppContainer;
use Lkrms\Time\Entity\TimeEntryProvider;
use Lkrms\Util\Convert;
use Lkrms\Util\Env;

class MarkTimeEntriesInvoiced extends CliCommand
{
    /**
     * @var AppContainer
     */
    private $App;

    /**
     * @var TimeEntryProvider
     */
    private $TimeEntryProvider;

    /**
     * @var string
     */
    private $TimeEntryProviderName;

    public function __construct(
        AppContainer $app,
        TimeEntryProvider $timeEntryProvider
    ) {
        parent::__construct($app);
        $this->App = $app;
        $this->TimeEntryProvider     = $timeEntryProvider;
        $this->TimeEntryProviderName = preg_replace(
            "/Provider$/", "", Convert::classToBasename(get_class($timeEntryProvider))
        );
    }

    protected function _getDescription(): string
    {
        return "Mark time entries as invoiced";
    }

    protected function _getOptions(): array
    {
        return [
            [
                "long"        => "from",
                "short"       => "s",
                "valueName"   => "start_date",
                "description" => "Mark time entries as invoiced from <start_date>",
                "optionType"  => CliOptionType::VALUE,
                "required"    => true,
            ], [
                "long"        => "to",
                "short"       => "e",
                "valueName"   => "end_date",
                "description" => "Mark time entries as invoiced to <end_date>",
                "optionType"  => CliOptionType::VALUE,
                "required"    => true,
            ], [
                "long"        => "client",
                "short"       => "c",
                "valueName"   => "client_id",
                "description" => "Mark time entries as invoiced for a particular client",
                "optionType"  => CliOptionType::VALUE,
            ], [
                "long"        => "project",
                "short"       => "p",
                "valueName"   => "project_id",
                "description" => "Mark time entries as invoiced for a particular project",
                "optionType"  => CliOptionType::VALUE,
            ], [
                "long"        => "force",
                "short"       => "f",
                "description" => "Actually mark time entries as invoiced",
            ],
        ];
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
        foreach ($this->TimeEntryProvider->getTimeEntries(
            null,
            $this->getOptionValue("client"),
            $this->getOptionValue("project"),
            new DateTime($this->getOptionValue("from")),
            new DateTime($this->getOptionValue("to")),
            true,
            false
        ) as $entry)
        {
            $markInvoiced[] = $entry;
            $totalAmount   += $entry->getBillableAmount();
            $totalHours    += $entry->getBillableHours();
        }

        $count = Convert::numberToNoun(count($markInvoiced), "time entry", "time entries", true);
        $total = sprintf("\$%.2f (%.2f hours)", $totalAmount, $totalHours);

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
