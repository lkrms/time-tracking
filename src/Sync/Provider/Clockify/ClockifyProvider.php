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
use Salient\Contract\Container\SingletonInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Http\HttpRequestMethod;
use Salient\Contract\Sync\SyncContextInterface as Context;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation as OP;
use Salient\Core\Facade\Console;
use Salient\Core\DateFormatter;
use Salient\Sync\Http\HttpSyncDefinition as HttpDef;
use Salient\Sync\Http\HttpSyncProvider;
use Salient\Utility\Arr;
use Salient\Utility\Date;
use Salient\Utility\Env;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use UnexpectedValueException;

/**
 * @method TimeEntry getTimeEntry(Context $ctx, int|string|null $id)
 * @method TimeEntry updateTimeEntry(Context $ctx, TimeEntry $timeEntry)
 * @method iterable<array-key,TimeEntry> getTimeEntries(Context $ctx)
 * @method Client getClient(Context $ctx, int|string|null $id)
 * @method iterable<array-key,Client> getClients(Context $ctx)
 * @method Project getProject(Context $ctx, int|string|null $id)
 * @method iterable<array-key,Project> getProjects(Context $ctx)
 * @method Task getTask(Context $ctx, int|string|null $id)
 * @method iterable<array-key,Task> getTasks(Context $ctx)
 * @method User getUser(Context $ctx, int|string|null $id)
 * @method iterable<array-key,User> getUsers(Context $ctx)
 * @method Tenant getTenant(Context $ctx, int|string|null $id)
 * @method iterable<array-key,Tenant> getTenants(Context $ctx)
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
    public function getName(): string
    {
        return 'Clockify';
    }

    /**
     * @inheritDoc
     */
    public function getContext(): Context
    {
        return parent::getContext()
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
        $user = $this->with(User::class)->online()->get(null);

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
        if ($path !== null && strpos($path, '/reports/') !== false) {
            return Env::get(
                'clockify_reports_api_base_url',
                'https://reports.api.clockify.me/v1',
            );
        }

        return Env::get(
            'clockify_api_base_url',
            'https://api.clockify.me/api/v1',
        );
    }

    /**
     * @inheritDoc
     */
    protected function getHeaders(string $path): ?HttpHeadersInterface
    {
        return $this->headers()->set('X-Api-Key', Env::get('clockify_api_key'));
    }

    /**
     * @inheritDoc
     */
    protected function getExpiry(string $path): ?int
    {
        $expiry = Env::getNullableInt('clockify_cache_expiry', null);
        return $expiry < 0 ? null : $expiry;
    }

    /**
     * @inheritDoc
     */
    protected function createDateFormatter(): DateFormatter
    {
        static $pending = false;

        // Return a user-specific date formatter except when servicing the user
        // endpoint request
        if ($pending) {
            return new DateFormatter(self::DATE_FORMAT);
        }

        $pending = true;
        try {
            /** @var array{timeZone:string} */
            $settings = $this->with(User::class)->online()->get(null)->Settings;
            return new DateFormatter(self::DATE_FORMAT, $settings['timeZone']);
        } finally {
            $pending = false;
        }
    }

    /**
     * @template TEntity of SyncEntityInterface
     *
     * @param class-string<TEntity> $entity
     * @return HttpDef<TEntity,$this>
     * @phpstan-ignore method.childReturnType
     */
    protected function getHttpDefinition(string $entity): HttpDef
    {
        $defB = $this->builderFor($entity);
        $pipelineFrom = $this->pipelineFrom($entity);

        return match ($entity) {
            Tenant::class => $defB
                ->operations([OP::READ, OP::READ_LIST])
                ->path('/workspaces')
                ->keyMap(self::ENTITY_PROPERTY_MAP[Tenant::class])
                ->readFromList()
                ->build(),

            User::class => $defB
                ->operations([OP::READ, OP::READ_LIST])
                ->path('/workspaces/:workspaceId/users')
                ->pipelineFromBackend(
                    $pipelineFrom->throughClosure(Closure::fromCallable([$this, 'normaliseUser']))
                )
                ->keyMap(self::ENTITY_PROPERTY_MAP[User::class])
                ->readFromList()
                ->overrides([
                    OP::READ => function (HttpDef $def, $op, Context $ctx, $id = null, ...$args) {
                        /** @var HttpDef<TEntity,$this> $def */
                        return (
                            $id === null
                                ? $def
                                    ->withPath('/user')
                                    ->withReadFromList(false)
                                : $def
                        )->getFallbackClosure(OP::READ)($ctx, $id, ...$args);
                    }
                ])
                ->build(),

            Client::class => $defB
                ->operations([OP::READ, OP::READ_LIST])
                ->path('/workspaces/:workspaceId/clients')
                ->keyMap(self::ENTITY_PROPERTY_MAP[Client::class])
                ->build(),

            Project::class => $defB
                ->operations([OP::READ, OP::READ_LIST])
                ->path('/workspaces/:workspaceId/projects')
                ->query(['hydrated' => true])
                ->keyMap(self::ENTITY_PROPERTY_MAP[Project::class])
                ->callback(
                    fn(HttpDef $def, $op, Context $ctx) =>
                        match ($op) {
                            OP::READ_LIST => $def->withQuery($def->Query + [
                                'clients' => implode(',', (array) $ctx->claimFilter('client'))
                            ]),

                            default => $def,
                        }
                )
                ->build(),

            Task::class => $defB
                ->operations([OP::READ, OP::READ_LIST])
                ->path('/workspaces/:workspaceId/projects/:projectId/tasks')
                ->build(),

            TimeEntry::class => $defB
                ->operations([OP::READ, OP::READ_LIST])
                ->path([
                    '/workspaces/:workspaceId/time-entries/:id',
                    '/workspaces/:workspaceId/reports/detailed',
                ])
                ->pipelineFromBackend(
                    $pipelineFrom->throughClosure(Closure::fromCallable([$this, 'normaliseTimeEntry']))
                )
                ->callback(
                    fn(HttpDef $def, $op, Context $ctx) =>
                        match ($op) {
                            OP::READ => $def->withQuery(['hydrated' => true]),

                            OP::READ_LIST => $def
                                ->withPager(new ClockifyPager('timeentries'))
                                ->withMethodMap([OP::READ_LIST => HttpRequestMethod::POST])
                                ->withCurlerCallback(fn(CurlerInterface $curler) => $curler->withPostResponseCache())
                                ->withArgs([$this->detailedReportQuery($ctx)]),

                            default => $def,
                        }
                )
                ->build(),

            default => $defB->build(),
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
        $client = ($entry['clientId'] ?? null) !== null
            ? [
                'id' => $entry['clientId'],
                'name' => $entry['clientName'],
            ]
            : null;

        $user = ($entry['userId'] ?? null) !== null
            ? [
                'id' => $entry['userId'],
                'name' => $entry['userName'],
                'email' => $entry['userEmail'],
            ]
            : null;

        $project = ($entry['projectId'] ?? null) !== null
            ? [
                'id' => $entry['projectId'],
                'name' => $entry['projectName'],
                'color' => $entry['projectColor'],
                'client' => $client,
            ]
            : null;

        $task = ($entry['taskId'] ?? null) !== null
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
        $entry['seconds'] = ($entry['timeInterval'] ?? null) !== null
            ? $this->getTimeInterval($entry['timeInterval'], $entry['start'], $entry['end'])
            : null;

        $entry['billableRate'] = ($entry['hourlyRate'] ?? null) !== null
            ? $this->getRate($entry['hourlyRate'])
            : (($entry['rate'] ?? null) !== null
                ? $this->getRate($entry['rate'])
                : null);

        $entry['isInvoiced'] = ($entry['invoicingInfo'] ?? null) !== null
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
        $user['isActive'] = $user['status'] === 'ACTIVE';

        unset($user['status']);

        return $user;
    }

    /**
     * @inheritDoc
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
