<?php declare(strict_types=1);

namespace Lkrms\Time\Command;

use Lkrms\Time\Sync\ContractGroup\BillableTimeProvider;
use Lkrms\Time\Sync\ContractGroup\InvoiceProvider;
use Lkrms\Time\Sync\Entity\Client;
use Lkrms\Time\Sync\Entity\Project;
use Lkrms\Time\Sync\TimeEntity\TimeEntry;
use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliApplication;
use Salient\Cli\CliCommand;
use Salient\Cli\CliOption;
use Salient\Cli\CliOptionBuilder;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Cli\CliOptionValueType;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Facade\Console;
use Salient\Sync\Exception\SyncEntityNotFoundException;
use Salient\Utility\Get;
use Salient\Utility\Test;
use DateTimeImmutable;

abstract class AbstractCommand extends CliCommand
{
    protected ?DateTimeImmutable $StartDate;
    protected ?DateTimeImmutable $EndDate;
    protected ?string $ClientId;
    protected ?string $ProjectId;
    protected ?bool $Billable = null;
    protected ?bool $Unbilled = null;
    /** @var string[]|null */
    protected ?array $Hide = null;
    protected ?bool $Force = null;

    // --

    protected BillableTimeProvider $TimeEntryProvider;
    protected InvoiceProvider $InvoiceProvider;
    protected string $TimeEntryProviderName;
    protected string $InvoiceProviderName;
    /** @var int|string|null */
    private $ResolvedClientId;
    /** @var int|string|null */
    private $ResolvedProjectId;

    public function __construct(
        CliApplication $container,
        BillableTimeProvider $timeEntryProvider,
        InvoiceProvider $invoiceProvider
    ) {
        parent::__construct($container);

        $this->TimeEntryProvider = $timeEntryProvider;
        $this->InvoiceProvider = $invoiceProvider;
        $this->TimeEntryProviderName = $timeEntryProvider->getName();
        $this->InvoiceProviderName = $invoiceProvider->getName();
    }

    /**
     * Get standard options for TimeEntry-related commands
     *
     * @param iterable<CliOption|CliOptionBuilder> $customOptions
     * @return iterable<CliOption|CliOptionBuilder>
     */
    protected function getTimeEntryOptions(
        string $action = 'List time entries',
        bool $requireDates = true,
        bool $addForceOption = true,
        bool $addHideOption = false,
        bool $addBillableOption = false,
        bool $addUnbilledOption = false,
        iterable $customOptions = []
    ): iterable {
        yield from [
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
                ->valueName('client')
                ->description("$action for a particular client")
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->ClientId),
            CliOption::build()
                ->long('project')
                ->short('p')
                ->valueName('project')
                ->description("$action for a particular project")
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->ProjectId),
        ];

        if ($addBillableOption) {
            yield CliOption::build()
                ->long('billable')
                ->short('b')
                ->description("$action that are billable")
                ->bindTo($this->Billable);
        }

        if ($addUnbilledOption) {
            yield CliOption::build()
                ->long('unbilled')
                ->short('B')
                ->description("$action that have not been billed")
                ->bindTo($this->Unbilled);
        }

        if ($addHideOption) {
            yield CliOption::build()
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

        yield from $customOptions;

        if ($addForceOption) {
            yield CliOption::build()
                ->long('force')
                ->short('f')
                ->description('Disable dry-run mode')
                ->bindTo($this->Force);
        }
    }

    /**
     * @return iterable<array-key,TimeEntry>
     */
    protected function getTimeEntries(
        ?bool $billable = null,
        ?bool $billed = null
    ): iterable {
        $filter = [
            'client_id' => $this->getClientId(),
            'project_id' => $this->getProjectId(),
            'start_date' => $this->StartDate,
            'end_date' => $this->EndDate,
            'billable' => $billable ?? ($this->Billable ? true : null),
            'billed' => $billed ?? ($this->Unbilled ? false : null),
        ];

        return $this
            ->TimeEntryProvider
            ->with(TimeEntry::class)
            ->getList($filter);
    }

    /**
     * Convert values passed to --hide to a bitmask
     *
     * @return int-mask-of<TimeEntry::*>
     */
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

        $mask = 0;
        foreach ($this->Hide ?? [] as $value) {
            $mask |= $showMap[$value];
        }

        return ~$mask & TimeEntry::ALL;
    }

    /**
     * @param int|float $amount
     * @param int|float $hours
     */
    protected function getBillableSummary($amount, $hours): string
    {
        return sprintf('$%.2f (%.2f hours)', $amount, $hours);
    }

    /**
     * @return int|string|null
     */
    protected function getClientId()
    {
        if ($this->ResolvedClientId !== null) {
            return $this->ResolvedClientId;
        }

        return $this->ResolvedClientId = $this->getEntityId($this->ClientId, Client::class);
    }

    /**
     * @return int|string|null
     */
    protected function getProjectId()
    {
        if ($this->ResolvedProjectId !== null) {
            return $this->ResolvedProjectId;
        }

        $clientId = $this->getClientId();
        if ($clientId !== null) {
            return $this->ResolvedProjectId = $this->getEntityId(
                $this->ProjectId,
                Project::class,
                $this
                    ->TimeEntryProvider
                    ->getContext()
                    ->withValue('client_id', $clientId),
            );
        }

        return $this->ResolvedProjectId = $this->getEntityId(
            $this->ProjectId,
            Project::class,
        );
    }

    /**
     * @param class-string<SyncEntityInterface> $entity
     * @param SyncProviderInterface|SyncContextInterface|null $providerOrContext
     * @return int|string|null
     */
    protected function getEntityId(
        ?string $nameOrId,
        string $entity,
        $providerOrContext = null,
        ?string $propertyName = null
    ) {
        if (Test::isInteger($nameOrId)) {
            $nameOrId = (int) $nameOrId;
        }

        $uncertainty = null;
        try {
            $id = [$entity, 'idFromNameOrId'](
                $nameOrId,
                $providerOrContext ?: $this->TimeEntryProvider,
                null,
                $propertyName,
                $uncertainty,
            );
        } catch (SyncEntityNotFoundException $ex) {
            throw new CliInvalidArgumentsException($ex->getMessage());
        }

        if ($id === $nameOrId) {
            return $id;
        }

        Console::debug(sprintf(
            "'%s' resolved to %s '%s' with uncertainty %.2f",
            $nameOrId,
            Get::basename($entity),
            $id,
            $uncertainty,
        ));

        return $id;
    }
}
