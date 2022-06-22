<?php

namespace Lkrms\Time\Concept;

use DateTime;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOptionType;
use Lkrms\Container\AppContainer;
use Lkrms\Time\Entity\InvoiceProvider;
use Lkrms\Time\Entity\TimeEntry;
use Lkrms\Time\Entity\TimeEntryProvider;
use Lkrms\Util\Convert;

abstract class Command extends CliCommand
{
    /**
     * @var AppContainer
     */
    protected $App;

    /**
     * @var TimeEntryProvider
     */
    protected $TimeEntryProvider;

    /**
     * @var InvoiceProvider
     */
    protected $InvoiceProvider;

    /**
     * @var string
     */
    protected $TimeEntryProviderName;

    /**
     * @var string
     */
    protected $InvoiceProviderName;

    public function __construct(
        AppContainer $app,
        TimeEntryProvider $timeEntryProvider,
        InvoiceProvider $invoiceProvider
    ) {
        parent::__construct($app);
        $this->App = $app;
        $this->TimeEntryProvider     = $timeEntryProvider;
        $this->InvoiceProvider       = $invoiceProvider;
        $this->TimeEntryProviderName = Convert::classToBasename(get_class($timeEntryProvider), "Provider");
        $this->InvoiceProviderName   = Convert::classToBasename(get_class($invoiceProvider), "Provider");
    }

    final public function app(): AppContainer
    {
        return $this->App;
    }

    protected function getTimeEntryOptions(
        string $action       = "List time entries",
        bool $requireDates   = true,
        bool $addForceOption = true,
        bool $addHideOption  = false
    ): array
    {
        $options = [
            [
                "long"         => "from",
                "short"        => "s",
                "valueName"    => "start_date",
                "description"  => "$action from <start_date>",
                "optionType"   => CliOptionType::VALUE,
                "required"     => $requireDates,
                "defaultValue" => $requireDates ? null : "1 jan last year",
            ],
            [
                "long"         => "to",
                "short"        => "e",
                "valueName"    => "end_date",
                "description"  => "$action to <end_date>",
                "optionType"   => CliOptionType::VALUE,
                "required"     => $requireDates,
                "defaultValue" => $requireDates ? null : "today",
            ],
            [
                "long"        => "client",
                "short"       => "c",
                "valueName"   => "client_id",
                "description" => "$action for a particular client",
                "optionType"  => CliOptionType::VALUE,
            ],
            [
                "long"        => "project",
                "short"       => "p",
                "valueName"   => "project_id",
                "description" => "$action for a particular project",
                "optionType"  => CliOptionType::VALUE,
            ],
        ];
        if ($addHideOption)
        {
            $options[] = [
                "long"            => "hide",
                "short"           => "h",
                "valueName"       => "value",
                "description"     => "Exclude the given value (may be used more than once)",
                "optionType"      => CliOptionType::ONE_OF,
                "allowedValues"   => ["date", "time", "project", "task", "user", "description"],
                "multipleAllowed" => true,
                "defaultValue"    => ["time", "user"],
            ];
        }
        if ($addForceOption)
        {
            $options[] = [
                "long"        => "force",
                "short"       => "f",
                "description" => "Disable dry-run mode",
            ];
        }
        return $options;
    }

    /**
     * @return iterable<TimeEntry>
     */
    protected function getTimeEntries(
        bool $billable = null,
        bool $billed   = null
    ): iterable
    {
        return $this->TimeEntryProvider->getTimeEntries(
            null,
            $this->getOptionValue("client"),
            $this->getOptionValue("project"),
            new DateTime($this->getOptionValue("from")),
            new DateTime($this->getOptionValue("to")),
            $billable,
            $billed
        );
    }

    protected function getTimeEntryMask(): int
    {
        $showMap = [
            "date"        => TimeEntry::DATE,
            "time"        => TimeEntry::TIME,
            "project"     => TimeEntry::PROJECT,
            "task"        => TimeEntry::TASK,
            "user"        => TimeEntry::USER,
            "description" => TimeEntry::DESCRIPTION,
        ];
        return array_reduce(
            $this->getOptionValue("hide"),
            fn($prev, $value) => $prev & ~$showMap[$value],
            TimeEntry::ALL
        );
    }

    protected function getBillableSummary($amount, $hours): string
    {
        return sprintf("\$%.2f (%.2f hours)", $amount, $hours);
    }
}
