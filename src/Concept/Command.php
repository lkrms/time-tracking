<?php

declare(strict_types=1);

namespace Lkrms\Time\Concept;

use DateTime;
use Lkrms\Cli\CliAppContainer;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Concept\CliCommand;
use Lkrms\Facade\Convert;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Time\Entity\Provider\BillableTimeEntryProvider;
use Lkrms\Time\Entity\Provider\InvoiceProvider;
use Lkrms\Time\Entity\TimeEntry;

abstract class Command extends CliCommand
{
    /**
     * @var BillableTimeEntryProvider
     */
    protected $TimeEntryProvider;

    /**
     * @var InvoiceProvider
     */
    protected $InvoiceProvider;

    /**
     * @var ISyncProvider[]
     */
    protected $UniqueProviders;

    /**
     * @var string
     */
    protected $TimeEntryProviderName;

    /**
     * @var string
     */
    protected $InvoiceProviderName;

    /**
     * @var string[]
     */
    protected $UniqueProviderNames;

    public function __construct(CliAppContainer $container, BillableTimeEntryProvider $timeEntryProvider, InvoiceProvider $invoiceProvider)
    {
        parent::__construct($container);
        $this->TimeEntryProvider     = $timeEntryProvider;
        $this->InvoiceProvider       = $invoiceProvider;
        $this->UniqueProviders       = Convert::toUniqueList([$this->TimeEntryProvider, $this->InvoiceProvider]);
        $this->TimeEntryProviderName = Convert::classToBasename(get_class($timeEntryProvider), "Provider");
        $this->InvoiceProviderName   = Convert::classToBasename(get_class($invoiceProvider), "Provider");
        $this->UniqueProviderNames   = Convert::stringsToUniqueList([$this->TimeEntryProviderName, $this->InvoiceProviderName]);
    }

    protected function getTimeEntryOptions(
        string $action       = "List time entries",
        bool $requireDates   = true,
        bool $addForceOption = true,
        bool $addHideOption  = false
    ): array
    {
        $options = [
            (CliOption::build()
                ->long("from")
                ->short("s")
                ->valueName("start_date")
                ->description("$action from <start_date>")
                ->optionType(CliOptionType::VALUE)
                ->required($requireDates)
                ->defaultValue($requireDates ? null : "1 jan last year")
                ->go()),
            (CliOption::build()
                ->long("to")
                ->short("e")
                ->valueName("end_date")
                ->description("$action to <end_date>")
                ->optionType(CliOptionType::VALUE)
                ->required($requireDates)
                ->defaultValue($requireDates ? null : "today")
                ->go()),
            (CliOption::build()
                ->long("client")
                ->short("c")
                ->valueName("client_id")
                ->description("$action for a particular client")
                ->optionType(CliOptionType::VALUE)
                ->go()),
            (CliOption::build()
                ->long("project")
                ->short("p")
                ->valueName("project_id")
                ->description("$action for a particular project")
                ->optionType(CliOptionType::VALUE)
                ->go()),
        ];
        if ($addHideOption)
        {
            $options[] = (CliOption::build()
                ->long("hide")
                ->short("h")
                ->valueName("value")
                ->description("Exclude the given value (may be used more than once)")
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(["date", "time", "project", "task", "user", "description"])
                ->multipleAllowed(true)
                ->defaultValue(["time", "user"])
                ->go());
        }
        if ($addForceOption)
        {
            $options[] = (CliOption::build()
                ->long("force")
                ->short("f")
                ->description("Disable dry-run mode")
                ->go());
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
