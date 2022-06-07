<?php

namespace Lkrms\Time\Command;

use DateTime;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOptionType;
use Lkrms\Console\Console;
use Lkrms\Time\Entity\InvoiceProvider;
use Lkrms\Time\Entity\TimeEntry;
use Lkrms\Time\Entity\TimeEntryProvider;
use Lkrms\Time\Support\TimeEntryCollection;
use Lkrms\Util\Convert;
use Lkrms\Util\Env;

class GenerateInvoices extends CliCommand
{
    /**
     * @var TimeEntryProvider
     */
    private $TimeEntryProvider;

    /**
     * @var InvoiceProvider
     */
    private $InvoiceProvider;

    /**
     * @var string
     */
    private $TimeEntryProviderName;

    /**
     * @var string
     */
    private $InvoiceProviderName;

    public function __construct(
        TimeEntryProvider $timeEntryProvider,
        InvoiceProvider $invoiceProvider
    ) {
        list (
            $this->TimeEntryProviderName,
            $this->InvoiceProviderName
        ) = array_map(
            fn($provider) => preg_replace(
                "/Provider$/", "", Convert::classToBasename(get_class($provider))
            ), [
                $this->TimeEntryProvider = $timeEntryProvider,
                $this->InvoiceProvider   = $invoiceProvider,
            ]
        );
    }

    protected function _getDescription(): string
    {
        return "Create invoices for unbilled time entries";
    }

    protected function _getOptions(): array
    {
        return [
            [
                "long"        => "client",
                "short"       => "c",
                "valueName"   => "client_id",
                "description" => "Create an invoice for a particular client",
                "optionType"  => CliOptionType::VALUE,
            ],
            [
                "long"        => "project",
                "short"       => "p",
                "valueName"   => "project_id",
                "description" => "Create an invoice for a particular project",
                "optionType"  => CliOptionType::VALUE,
            ],
            [
                "long"            => "hide",
                "short"           => "h",
                "valueName"       => "value",
                "description"     => "Exclude the given value from the invoice (may be used more than once)",
                "optionType"      => CliOptionType::ONE_OF,
                "allowedValues"   => ["date", "time", "project", "task", "user", "description"],
                "multipleAllowed" => true,
                "defaultValue"    => ["time", "user"],
            ],
            [
                "long"        => "dry-run",
                "short"       => "n",
                "description" => "Print line items and exit",
            ],
        ];
    }

    protected function _run(string ...$params)
    {
        if ($this->getOptionValue("dry-run"))
        {
            Env::dryRun(true);
        }

        Console::info("Retrieving unbilled time from", $this->TimeEntryProviderName);

        $times = $this->TimeEntryProvider->getTimeEntries(
            null,
            $this->getOptionValue("client"),
            $this->getOptionValue("project"),
            new DateTime("1 jan last year"),
            new DateTime("today"),
            true,
            false
        );

        /** @var TimeEntryCollection[] */
        $clientTimes    = [];
        $clientNames    = [];
        $timeEntryCount = 0;

        foreach ($times as $time)
        {
            $clientId  = $time->Project->Client->Id;
            $entries   = $clientTimes[$clientId] ?? ($clientTimes[$clientId] = new TimeEntryCollection());
            $entries[] = $time;
            $clientNames[$clientId] = $time->Project->Client->Name;
            $timeEntryCount++;
        }

        Console::info("Retrieving clients from", $this->InvoiceProviderName);
        $invProviderClients = Convert::listToMap(
            $this->InvoiceProvider->getClients(["name" => array_values($clientNames)]),
            "Name"
        );

        Console::info("Preparing " . Convert::numberToNoun(count($clientTimes), "client invoice", null, true)
            . " for " . Convert::numberToNoun($timeEntryCount, "time entry", "time entries", true));

        $showMap = [
            "date"        => TimeEntry::DATE,
            "time"        => TimeEntry::TIME,
            "project"     => TimeEntry::PROJECT,
            "task"        => TimeEntry::TASK,
            "user"        => TimeEntry::USER,
            "description" => TimeEntry::DESCRIPTION,
        ];
        $show = array_reduce(
            $this->getOptionValue("hide"),
            fn($prev, $value) => $prev & ~$showMap[$value],
            TimeEntry::ALL
        );

        foreach ($clientTimes as $clientId => $entries)
        {
            $name    = $clientNames[$clientId];
            $summary = sprintf("$%.2f (%.2f hours)", $entries->BillableAmount, $entries->BillableHours);

            if (!($invProviderClient = $invProviderClients[$name] ?? null))
            {
                Console::error("Skipping $name (not found in {$this->InvoiceProviderName}):", $summary);
                continue;
            }
            Console::log("$name:", $summary);

            $entries = $entries->groupBy(
                $show,
                // Even if they're excluded from the invoice, don't merge
                // entries with different project IDs and/or billable rates
                fn(TimeEntry $t) => [$t->Project->Id ?? null, $t->BillableRate]
            );

            if (Env::dryRun())
            {
                foreach ($entries as $entry)
                {
                    printf("==> \$%.2f (%.2f hours):\n  %s\n\n",
                        $entry->getBillableAmount(),
                        $entry->getBillableHours(),
                        str_replace("\n", "\n  ", $entry->Description));
                }
                continue;
            }
        }

        if (Env::dryRun())
        {
            return;
        }
    }
}
