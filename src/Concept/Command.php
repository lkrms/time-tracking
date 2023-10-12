<?php declare(strict_types=1);

namespace Lkrms\Time\Concept;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Catalog\CliOptionValueType;
use Lkrms\Cli\CliApplication;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionBuilder;
use Lkrms\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Time\Entity\Provider\BillableTimeEntryProvider;
use Lkrms\Time\Entity\Provider\InvoiceProvider;
use Lkrms\Time\Entity\TimeEntry;
use Lkrms\Utility\Convert;
use DateTimeImmutable;

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

    /**
     * @var DateTimeImmutable|null
     */
    protected $StartDate;

    /**
     * @var DateTimeImmutable|null
     */
    protected $EndDate;

    /**
     * @var string|null
     */
    protected $ClientId;

    /**
     * @var string|null
     */
    protected $ProjectId;

    /**
     * @var bool|null
     */
    protected $Billable;

    /**
     * @var bool|null
     */
    protected $Unbilled;

    /**
     * @var string[]|null
     */
    protected $Hide;

    /**
     * @var bool|null
     */
    protected $Force;

    public function __construct(CliApplication $container, BillableTimeEntryProvider $timeEntryProvider, InvoiceProvider $invoiceProvider)
    {
        parent::__construct($container);
        $this->TimeEntryProvider = $timeEntryProvider;
        $this->InvoiceProvider = $invoiceProvider;
        $this->UniqueProviders = Convert::toUniqueList([$this->TimeEntryProvider, $this->InvoiceProvider]);
        $this->TimeEntryProviderName = Convert::classToBasename(get_class($timeEntryProvider), 'Provider');
        $this->InvoiceProviderName = Convert::classToBasename(get_class($invoiceProvider), 'Provider');
        $this->UniqueProviderNames = Convert::stringsToUniqueList([$this->TimeEntryProviderName, $this->InvoiceProviderName]);
    }

    protected function getLongDescription(): ?string
    {
        return null;
    }

    protected function getHelpSections(): ?array
    {
        return null;
    }

    /**
     * Get standard options for TimeEntry-related commands
     *
     * @return array<CliOption|CliOptionBuilder>
     */
    protected function getTimeEntryOptions(
        string $action = 'List time entries',
        bool $requireDates = true,
        bool $addForceOption = true,
        bool $addHideOption = false,
        bool $addBillableOption = false,
        bool $addUnbilledOption = false
    ): array {
        $options = [
            CliOption::build()
                ->long('from')
                ->short('s')
                ->valueName('start_date')
                ->description("$action starting from this date")
                ->optionType($requireDates ? CliOptionType::VALUE_POSITIONAL : CliOptionType::VALUE)
                ->valueType(CliOptionValueType::DATE)
                ->required($requireDates)
                ->defaultValue($requireDates ? null : '1 jan last year')
                ->bindTo($this->StartDate),
            CliOption::build()
                ->long('to')
                ->short('e')
                ->valueName('end_date')
                ->description("$action ending before this date")
                ->optionType($requireDates ? CliOptionType::VALUE_POSITIONAL : CliOptionType::VALUE)
                ->valueType(CliOptionValueType::DATE)
                ->required($requireDates)
                ->defaultValue($requireDates ? null : 'today')
                ->bindTo($this->EndDate),
            CliOption::build()
                ->long('client')
                ->short('c')
                ->valueName('client_id')
                ->description("$action for a particular client")
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->ClientId),
            CliOption::build()
                ->long('project')
                ->short('p')
                ->valueName('project_id')
                ->description("$action for a particular project")
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->ProjectId),
        ];
        if ($addBillableOption) {
            $options[] = CliOption::build()
                             ->long('billable')
                             ->short('b')
                             ->description("$action that are billable")
                             ->bindTo($this->Billable);
        }
        if ($addUnbilledOption) {
            $options[] = CliOption::build()
                             ->long('unbilled')
                             ->short('B')
                             ->description("$action that have not been billed")
                             ->bindTo($this->Unbilled);
        }
        if ($addHideOption) {
            $options[] = CliOption::build()
                             ->long('hide')
                             ->short('h')
                             ->valueName('value')
                             ->description('Exclude a value from time entry descriptions')
                             ->optionType(CliOptionType::ONE_OF)
                             ->allowedValues(['date', 'time', 'project', 'task', 'user', 'description'])
                             ->multipleAllowed(true)
                             ->defaultValue(['time', 'user'])
                             ->bindTo($this->Hide);
        }
        if ($addForceOption) {
            $options[] = CliOption::build()
                             ->long('force')
                             ->short('f')
                             ->description('Disable dry-run mode')
                             ->bindTo($this->Force);
        }

        return $options;
    }

    /**
     * @return FluentIteratorInterface<array-key,TimeEntry>
     */
    protected function getTimeEntries(
        ?bool $billable = null,
        ?bool $billed = null
    ): FluentIteratorInterface {
        $filter = [
            'client_id' => $this->ClientId,
            'project_id' => $this->ProjectId,
            'start_date' => $this->StartDate,
            'end_date' => $this->EndDate,
            'billable' => Convert::coalesce($billable, $this->Billable ?: null),
            'billed' => Convert::coalesce($billed, $this->Unbilled ? false : null),
        ];

        return $this->TimeEntryProvider
                    ->with(TimeEntry::class)
                    ->getList($filter);
    }

    protected function getTimeEntryMask(): int
    {
        $showMap = [
            'date' => TimeEntry::DATE,
            'time' => TimeEntry::TIME,
            'project' => TimeEntry::PROJECT,
            'task' => TimeEntry::TASK,
            'user' => TimeEntry::USER,
            'description' => TimeEntry::DESCRIPTION,
        ];

        return array_reduce(
            $this->Hide,
            fn(int $prev, string $value) => $prev & ~$showMap[$value],
            TimeEntry::ALL
        );
    }

    /**
     * @param int|float $amount
     * @param int|float $hours
     */
    protected function getBillableSummary($amount, $hours): string
    {
        return sprintf('$%.2f (%.2f hours)', $amount, $hours);
    }
}
