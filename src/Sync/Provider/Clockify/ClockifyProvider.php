<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Provider\Clockify;

use Lkrms\Time\Sync\Contract\ProvidesTenant;
use Lkrms\Time\Sync\ContractGroup\BillableTimeProvider;
use Lkrms\Time\Sync\Entity\Client;
use Lkrms\Time\Sync\Entity\Project;
use Lkrms\Time\Sync\Entity\Task;
use Lkrms\Time\Sync\Entity\Tenant;
use Lkrms\Time\Sync\Entity\TimeEntry;
use Lkrms\Time\Sync\Entity\User;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Container\SingletonInterface;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Http\HttpRequestMethod;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncContextInterface as Context;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation as OP;
use Salient\Core\Exception\UnexpectedValueException;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Date;
use Salient\Core\Utility\Env;
use Salient\Core\Utility\Get;
use Salient\Core\DateFormatter;
use Salient\Curler\Catalog\CurlerProperty;
use Salient\Curler\Pager\QueryPager;
use Salient\Http\HttpHeaders;
use Salient\Sync\HttpSyncDefinition as HttpDef;
use Salient\Sync\HttpSyncDefinitionBuilder as HttpDefB;
use Salient\Sync\HttpSyncProvider;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * @method TimeEntry getTimeEntry(SyncContextInterface $ctx, int|string|null $id)
 * @method TimeEntry updateTimeEntry(SyncContextInterface $ctx, TimeEntry $timeEntry)
 * @method FluentIteratorInterface<array-key,TimeEntry> getTimeEntries(SyncContextInterface $ctx)
 * @method Client getClient(SyncContextInterface $ctx, int|string|null $id)
 * @method FluentIteratorInterface<array-key,Client> getClients(SyncContextInterface $ctx)
 * @method Project getProject(SyncContextInterface $ctx, int|string|null $id)
 * @method FluentIteratorInterface<array-key,Project> getProjects(SyncContextInterface $ctx)
 * @method Task getTask(SyncContextInterface $ctx, int|string|null $id)
 * @method FluentIteratorInterface<array-key,Task> getTasks(SyncContextInterface $ctx)
 * @method User getUser(SyncContextInterface $ctx, int|string|null $id)
 * @method FluentIteratorInterface<array-key,User> getUsers(SyncContextInterface $ctx)
 * @method Tenant getTenant(SyncContextInterface $ctx, int|string|null $id)
 * @method FluentIteratorInterface<array-key,Tenant> getTenants(SyncContextInterface $ctx)
 */
final class ClockifyProvider extends HttpSyncProvider implements
    SingletonInterface,
    BillableTimeProvider,
    ProvidesTenant
{
    /**
     * Entity => input key => property
     *
     * @var array<class-string<SyncEntityInterface>,array<string,string>>
     */
    private const ENTITY_PROPERTY_MAP = [
        Client::class => [
            'note' => 'Description',
        ],
        Project::class => [
            'note' => 'Description',
            'hourlyRate' => 'BillableRate',
            'color' => 'Colour',
        ],
        Tenant::class => [
            'imageUrl' => 'LogoUrl',
            'memberships' => 'Users',
            'workspaceSettings' => 'Settings',
        ],
        User::class => [
            'profilePicture' => 'PhotoUrl',
            'activeWorkspace' => 'ActiveTenant',
        ],
    ];

    private const DATE_FORMAT = 'Y-m-d\TH:i:s.v\Z';

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return sprintf('Clockify { %s }', $this->workspaceId());
    }

    /**
     * @inheritDoc
     */
    public function getContext(?ContainerInterface $container = null): Context
    {
        return
            parent::getContext($container)
                ->withValue('workspace_id', $this->workspaceId());
    }

    /**
     * @inheritDoc
     */
    public function getBackendIdentifier(): array
    {
        return [
            $this->getBaseUrl(),
            $this->workspaceId(),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getHeartbeat()
    {
        $user = $this->with(User::class)
                     ->online()
                     ->get(null);

        Console::debug(sprintf(
            "Connected to Clockify workspace '%s' (%s) as '%s' (%s)",
            $user->ActiveTenant->Name ?? '<unknown>',
            $user->ActiveTenant->Id ?? '<unknown>',
            $user->Name,
            $user->Id,
        ));

        return $user;
    }

    /**
     * @inheritDoc
     */
    protected function getBaseUrl(?string $path = null): string
    {
        if ($path && strpos($path, '/reports/') !== false) {
            return Env::get(
                'clockify_reports_api_base_url', 'https://reports.api.clockify.me/v1'
            );
        }

        return Env::get(
            'clockify_api_base_url', 'https://api.clockify.me/api/v1'
        );
    }

    /**
     * @inheritDoc
     */
    protected function getHeaders(?string $path): HttpHeaders
    {
        return (new HttpHeaders())
            ->set('X-Api-Key', Env::get('clockify_api_key'));
    }

    /**
     * @inheritDoc
     */
    protected function getExpiry(?string $path): ?int
    {
        return Env::getInt('clockify_cache_expiry', null);
    }

    /**
     * @inheritDoc
     */
    protected function getDateFormatter(?string $path = null): DateFormatterInterface
    {
        static $pending = false;

        // The purpose of the following hijinks is to return a user-specific
        // date formatter while returning a generic one if necessary to service
        // the user endpoint request
        if ($this->hasDateFormatter()) {
            return $this->dateFormatter();
        }

        if ($pending) {
            return new DateFormatter(self::DATE_FORMAT);
        }

        $pending = true;
        try {
            /** @var array{timeZone:string} */
            $settings = $this->with(User::class)
                             ->get(null)
                             ->Settings;
            return new DateFormatter(
                self::DATE_FORMAT,
                $settings['timeZone'],
            );
        } finally {
            $pending = false;
        }
    }

    /**
     * @inheritDoc
     */
    protected function buildHttpDefinition(string $entity, HttpDefB $defB): HttpDefB
    {
        return match ($entity) {
            Tenant::class =>
                $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/workspaces')
                    ->keyMap(self::ENTITY_PROPERTY_MAP[Tenant::class])
                    ->readFromReadList(),

            User::class =>
                $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/workspaces/:workspaceId/users')
                    ->pipelineFromBackend(
                        $this->pipelineFrom(User::class)
                             ->throughClosure(Closure::fromCallable([$this, 'normaliseUser']))
                    )
                    ->keyMap(self::ENTITY_PROPERTY_MAP[User::class])
                    ->readFromReadList()
                    ->overrides([
                        OP::READ =>
                            $defB->bindOverride(
                                fn(HttpDef $def, $op, Context $ctx, $id = null, ...$args) =>
                                    Get::notNull((
                                        $id === null
                                            ? $def->withPath('/user')
                                                  ->withReadFromReadList(false)
                                            : $def
                                    )->getFallbackClosure($op))($ctx, ...[$id, ...$args])
                            )
                    ]),

            Client::class =>
                $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/workspaces/:workspaceId/clients')
                    ->keyMap(self::ENTITY_PROPERTY_MAP[Client::class]),

            Project::class =>
                $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/workspaces/:workspaceId/projects')
                    ->query(['hydrated' => true])
                    ->keyMap(self::ENTITY_PROPERTY_MAP[Project::class])
                    ->callback(
                        fn(HttpDef $def, $op, Context $ctx) =>
                            match ($op) {
                                OP::READ_LIST =>
                                    $def->withQuery($def->Query + [
                                        'clients' =>
                                            implode(',', (array) $ctx->claimFilter('client'))
                                    ]),

                                default =>
                                    $def,
                            }
                    ),

            Task::class =>
                $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/workspaces/:workspaceId/projects/:projectId/tasks'),

            TimeEntry::class =>
                $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path([
                        '/workspaces/:workspaceId/time-entries/:id',
                        '/workspaces/:workspaceId/reports/detailed',
                    ])
                    ->pipelineFromBackend(
                        $this->pipelineFrom(TimeEntry::class)
                             ->throughClosure(Closure::fromCallable([$this, 'normaliseTimeEntry']))
                    )
                    ->callback(
                        fn(HttpDef $def, $op, Context $ctx) =>
                            match ($op) {
                                OP::READ =>
                                    $def->withQuery(['hydrated' => true]),

                                OP::READ_LIST =>
                                    $def->withPager(new QueryPager(null, 'timeentries'))
                                        ->withMethodMap([OP::READ_LIST => HttpRequestMethod::POST])
                                        ->withCurlerProperties([CurlerProperty::CACHE_POST_RESPONSE => true])
                                        ->withArgs($this->detailedReportQuery($ctx)),

                                default =>
                                    $def,
                            }
                    ),

            default =>
                $defB,
        };
    }

    /**
     * @param array{amount:int,currency:string}|int|null $value
     */
    private function getRate($value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return $value['amount'] / 100;
        }
        return $value / 100;
    }

    /**
     * @param array{start?:string,end?:string,duration?:int|string}|null $value
     */
    private function getTimeInterval(
        $value,
        ?DateTimeInterface &$start = null,
        ?DateTimeInterface &$end = null
    ): ?int {
        if ($value === null) {
            return null;
        }

        if (isset($value['start']) && isset($value['end'])) {
            $start = new DateTimeImmutable($value['start']);
            $end = new DateTimeImmutable($value['end']);
        }

        if (!isset($value['duration'])) {
            return null;
        }

        $duration = $value['duration'];

        if (is_int($duration)) {
            return $duration;
        }

        return Date::duration($duration);
    }

    /**
     * @return array<string,mixed>
     */
    private function detailedReportQuery(Context $ctx): array
    {
        $query = [
            'dateRangeStart' => $this->dateTime($ctx->claimFilter('start_date')),
            'dateRangeEnd' => $this->dateTime($ctx->claimFilter('end_date')),
            'sortOrder' => 'ASCENDING',
            'detailedFilter' => [
                'sortColumn' => 'DATE',
                'page' => 1,
                'pageSize' => 1000,
                'options' => [
                    'totals' => 'EXCLUDE',
                ],
            ],
            'users' => $this->reportFilter($ctx->claimFilter('user_id')),
            'clients' => $this->reportFilter($ctx->claimFilter('client_id')),
            'projects' => $this->reportFilter($ctx->claimFilter('project_id')),
        ];

        $billable = $ctx->claimFilter('billable');
        if ($billable !== null) {
            $query['billable'] = $billable;
        }

        $billed = $ctx->claimFilter('billed');
        if ($billed !== null) {
            $query['invoicingState'] = $billed ? 'INVOICED' : 'UNINVOICED';
        }

        return $query;
    }

    /**
     * @param mixed[] $entry
     * @return mixed[]
     */
    function normaliseTimeEntry(array $entry): array
    {
        $client =
            ($entry['clientId'] ?? null) !== null
                ? [
                    'id' => $entry['clientId'],
                    'name' => $entry['clientName'],
                ]
                : null;

        $user =
            ($entry['userId'] ?? null) !== null
                ? [
                    'id' => $entry['userId'],
                    'name' => $entry['userName'],
                    'email' => $entry['userEmail'],
                ]
                : null;

        $project =
            ($entry['projectId'] ?? null) !== null
                ? [
                    'id' => $entry['projectId'],
                    'name' => $entry['projectName'],
                    'color' => $entry['projectColor'],
                    'client' => $client,
                ]
                : null;

        $task =
            ($entry['taskId'] ?? null) !== null
                ? [
                    'id' => $entry['taskId'],
                    'name' => $entry['taskName'],
                ] + (
                    $project !== null
                        ? ['project' => $project]
                        : []
                )
                : null;

        $entry['start'] = null;
        $entry['end'] = null;
        $entry['seconds'] =
            ($entry['timeInterval'] ?? null) !== null
                ? $this->getTimeInterval($entry['timeInterval'], $entry['start'], $entry['end'])
                : null;

        $entry['billableRate'] =
            ($entry['hourlyRate'] ?? null) !== null
                ? $this->getRate($entry['hourlyRate'])
                : (($entry['rate'] ?? null) !== null
                    ? $this->getRate($entry['rate'])
                    : null);

        $entry['isInvoiced'] =
            ($entry['invoicingInfo'] ?? null) !== null
                ? (bool) $entry['invoicingInfo']
                : false;

        $entry = array_merge($entry, [
            'user' => $user,
            'project' => $project,
            'task' => $task,
        ]);

        unset(
            $entry['clientId'],
            $entry['clientName'],
            $entry['userId'],
            $entry['userName'],
            $entry['userEmail'],
            $entry['projectId'],
            $entry['projectName'],
            $entry['projectColor'],
            $entry['taskId'],
            $entry['taskName'],
            $entry['timeInterval'],
            $entry['hourlyRate'],
            $entry['rate'],
            $entry['invoicingInfo'],
        );

        return $entry;
    }

    /**
     * @param mixed[] $user
     * @return mixed[]
     */
    public function normaliseUser(array $user): array
    {
        $user['isActive'] =
            $user['status'] === 'ACTIVE';

        unset($user['status']);

        return $user;
    }

    /**
     * Mark time entries as invoiced
     *
     * @param iterable<TimeEntry> $timeEntries
     */
    public function markTimeEntriesInvoiced(
        iterable $timeEntries,
        bool $unmark = false
    ): void {
        $workspaceId = $this->workspaceId();
        $data = [
            'timeEntryIds' => [],
            'invoiced' => !$unmark,
        ];

        foreach ($timeEntries as $time) {
            $data['timeEntryIds'][] = $time->Id;
        }

        if (!$data['timeEntryIds']) {
            return;
        }

        $this->getCurler("/workspaces/$workspaceId/time-entries/invoiced")->patch($data);
    }

    private function workspaceId(): string
    {
        return Env::get('clockify_workspace_id');
    }

    /**
     * @param mixed $value
     * @return array<string,string|string[]>|null
     */
    private function reportFilter($value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) || Arr::ofString($value)) {
            return [
                'ids' => (array) $value,
                'contains' => 'CONTAINS',
                'status' => 'ALL',
            ];
        }

        throw new UnexpectedValueException('Invalid report filter value');
    }

    /**
     * @param mixed $value
     */
    private function dateTime($value): ?DateTimeInterface
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new UnexpectedValueException('Invalid date and time value');
    }
}
