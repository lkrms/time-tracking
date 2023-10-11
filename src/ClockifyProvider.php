<?php declare(strict_types=1);

namespace Lkrms\Time;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\IDateFormatter;
use Lkrms\Contract\IServiceSingleton;
use Lkrms\Curler\Catalog\CurlerProperty;
use Lkrms\Curler\Pager\QueryPager;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Facade\Console;
use Lkrms\Support\Catalog\HttpRequestMethod;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Catalog\SyncOperation as OP;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Contract\ISyncContext as Context;
use Lkrms\Sync\Support\HttpSyncDefinition as HttpDef;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder as HttpDefB;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Time\Entity\Provider\BillableTimeEntryProvider;
use Lkrms\Time\Entity\Provider\UserProvider;
use Lkrms\Time\Entity\Provider\WorkspaceProvider;
use Lkrms\Time\Entity\Client;
use Lkrms\Time\Entity\Project;
use Lkrms\Time\Entity\Task;
use Lkrms\Time\Entity\TimeEntry;
use Lkrms\Time\Entity\User;
use Lkrms\Time\Entity\Workspace;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Env;
use DateTimeImmutable;
use DateTimeInterface;
use UnexpectedValueException;

final class ClockifyProvider extends HttpSyncProvider implements
    IServiceSingleton,
    WorkspaceProvider,
    UserProvider,
    BillableTimeEntryProvider
{
    /**
     * Entity => input key => property
     *
     * @var array<class-string<ISyncEntity>,array<string,string>>
     */
    private const ENTITY_PROPERTY_MAP = [
        Project::class => [
            'note' => 'Description',
            'hourlyRate' => 'BillableRate',
            'color' => 'Colour',
        ],
    ];

    private const DATE_FORMAT = 'Y-m-d\TH:i:s.v\Z';

    /**
     * @var int|null
     */
    private $CacheExpiry;

    /**
     * @inheritDoc
     */
    public function name(): ?string
    {
        return sprintf('Clockify { %s }', $this->getWorkspaceId());
    }

    /**
     * @inheritDoc
     */
    public function getContext(?IContainer $container = null): SyncContext
    {
        return
            parent::getContext($container)
                ->withValue('workspace_id', $this->getWorkspaceId());
    }

    /**
     * @inheritDoc
     */
    public function getBackendIdentifier(): array
    {
        return [
            $this->getBaseUrl(),
            $this->getWorkspaceId(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function checkHeartbeat(int $ttl = 300)
    {
        $this->CacheExpiry = $ttl ?: null;

        try {
            $user = $this->with(User::class)
                         ->online()
                         ->get(null);
        } finally {
            $this->CacheExpiry = null;
        }

        Console::debugOnce(
            sprintf(
                "Connected to Clockify workspace '%s' as %s ('%s')",
                $user->ActiveWorkspace,
                $user->Name,
                $user->Id,
            )
        );

        return $this;
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
    protected function getHeaders(?string $path): CurlerHeaders
    {
        return
            CurlerHeaders::create()
                ->setHeader('X-Api-Key', Env::get('clockify_api_key'));
    }

    /**
     * @inheritDoc
     */
    protected function getExpiry(?string $path): ?int
    {
        return
            $this->CacheExpiry !== null
                ? $this->CacheExpiry
                : Env::getInt('clockify_cache_expiry', 600);
    }

    /**
     * @inheritDoc
     */
    protected function getDateFormatter(?string $path = null): IDateFormatter
    {
        if ('/user' === $path) {
            return new DateFormatter(self::DATE_FORMAT);
        }

        $user = $this->with(User::class)
                     ->get(null);

        return new DateFormatter(self::DATE_FORMAT, $user->Settings['timeZone']);
    }

    /**
     * @inheritDoc
     */
    protected function buildHttpDefinition(string $entity, HttpDefB $defB): HttpDefB
    {
        return match ($entity) {
            Workspace::class =>
                $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/workspaces')
                    ->readFromReadList(),

            User::class =>
                $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/workspaces/:workspaceId/users')
                    ->readFromReadList()
                    ->overrides([
                        OP::READ =>
                            fn(HttpDef $def, $op, Context $ctx, $id = null, ...$args) =>
                                ($id === null
                                        ? $def->withPath('/user')
                                              ->withReadFromReadList(false)
                                        : $def)
                                    ->getFallbackClosure($op)($ctx, $id, ...$args)
                    ]),

            Client::class =>
                $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/workspaces/:workspaceId/clients'),

            Project::class =>
                $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/workspaces/:workspaceId/projects')
                    ->query(['hydrated' => true])
                    ->keyMap(self::ENTITY_PROPERTY_MAP[Project::class]),

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
                    ->pipelineFromBackend($this->callbackPipeline([$this, 'normaliseTimeEntry']))
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
                            }
                    ),

            default => $defB,
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
     * @param array{start:string,end:string,duration:string|int}|null $value
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

        if (is_string($duration)) {
            return Convert::intervalToSeconds($duration);
        }

        throw new UnexpectedValueException(
            sprintf('Invalid duration: %s', $duration)
        );
    }

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

    function normaliseTimeEntry(array $entry): array
    {
        $client =
            array_key_exists('clientId', $entry)
                ? [
                    'id' => $entry['clientId'],
                    'name' => $entry['clientName'],
                ]
                : null;

        $user =
            array_key_exists('userId', $entry)
                ? [
                    'id' => $entry['userId'],
                    'name' => $entry['userName'],
                    'email' => $entry['userEmail'],
                ]
                : null;

        $project =
            array_key_exists('projectId', $entry)
                ? [
                    'id' => $entry['projectId'],
                    'name' => $entry['projectName'],
                    'color' => $entry['projectColor'],
                    'client' => $client,
                ]
                : null;

        $task =
            array_key_exists('taskId', $entry)
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
            array_key_exists('timeInterval', $entry)
                ? $this->getTimeInterval($entry['timeInterval'], $entry['start'], $entry['end'])
                : null;

        $entry['billableRate'] =
            array_key_exists('hourlyRate', $entry)
                ? $this->getRate($entry['hourlyRate'])
                : (array_key_exists('rate', $entry)
                    ? $this->getRate($entry['rate'])
                    : null);

        $entry['isInvoiced'] =
            array_key_exists('invoicingInfo', $entry)
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
     * Mark time entries as invoiced
     *
     * @param iterable<TimeEntry> $timeEntries
     * @param bool $unmark
     */
    public function markTimeEntriesInvoiced(
        iterable $timeEntries,
        bool $unmark = false
    ): void {
        $workspaceId = $this->getWorkspaceId();
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

    private function getWorkspaceId(): string
    {
        return Env::get('clockify_workspace_id');
    }

    /**
     * @param string|string[]|null $value
     * @return array<string,string|string[]>|null
     */
    private function reportFilter($value): ?array
    {
        if ($value === null) {
            return null;
        }

        return [
            'ids' => (array) $value,
            'contains' => 'CONTAINS',
            'status' => 'ALL',
        ];
    }

    /**
     * @param DateTimeInterface|string|null $value
     */
    private function dateTime($value): ?DateTimeInterface
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        return new DateTimeImmutable($value);
    }
}
