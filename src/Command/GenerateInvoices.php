<?php

namespace Lkrms\Time\Command;

use DateTime;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOptionType;
use Lkrms\Console\Console;
use Lkrms\Time\Entity\TimeEntryProvider;
use Lkrms\Time\Support\TimeEntryCollection;
use Lkrms\Util\Convert;

class GenerateInvoices extends CliCommand
{
    /**
     * @var TimeEntryProvider
     */
    private $TimeEntryProvider;

    /**
     * @var string
     */
    private $TimeEntryProviderName;

    public function __construct(
        TimeEntryProvider $timeEntryProvider
    ) {
        $this->TimeEntryProvider     = $timeEntryProvider;
        $this->TimeEntryProviderName = preg_replace("/Provider$/", "", Convert::classToBasename(get_class($timeEntryProvider)));
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
        ];
    }

    protected function _run(string ...$params)
    {
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

        /**
         * @var TimeEntryCollection[]
         */
        $clientTimes = [];

        $timeEntryCount = 0;

        foreach ($times as $time)
        {
            $clientId  = $time->Project->Client->Id;
            $entries   = $clientTimes[$clientId] ?? ($clientTimes[$clientId] = new TimeEntryCollection());
            $entries[] = $time;
            $timeEntryCount++;
        }

        Console::info("Preparing " . Convert::numberToNoun(count($clientTimes), "client invoice", null, true)
            . " for " . Convert::numberToNoun($timeEntryCount, "time entry", "time entries", true));

        foreach ($clientTimes as $clientId => $entries)
        {
            $client = $this->TimeEntryProvider->getClient($clientId);
            Console::log("{$client->Name}:", sprintf("$%.2f (%.2f hours)", $entries->BillableAmount, $entries->BillableHours));
        }
    }
}
