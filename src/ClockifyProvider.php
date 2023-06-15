<?php declare(strict_types=1);

namespace Lkrms\Time;

use DateTimeInterface;
use Lkrms\Contract\IServiceShared;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerBuilder;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Facade\Console;
use Lkrms\Facade\Env;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Catalog\SyncOperation as OP;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncContext as Context;
use Lkrms\Sync\Support\HttpSyncDefinition as Definition;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder;
use Lkrms\Time\Entity\Client;
use Lkrms\Time\Entity\Project;
use Lkrms\Time\Entity\Provider\BillableTimeEntryProvider;
use Lkrms\Time\Entity\Provider\UserProvider;
use Lkrms\Time\Entity\Provider\WorkspaceProvider;
use Lkrms\Time\Entity\Task;
use Lkrms\Time\Entity\TimeEntry;
use Lkrms\Time\Entity\User;
use Lkrms\Time\Entity\Workspace;
use RuntimeException;

/**
 * @method Workspace getWorkspace(SyncContext $ctx, int|string|null $id)
 * @method iterable<Workspace> getWorkspaces(SyncContext $ctx)
 * @method User getUser(SyncContext $ctx, int|string|null $id)
 * @method iterable<User> getUsers(SyncContext $ctx)
 * @method Client getClient(SyncContext $ctx, int|string|null $id)
 * @method iterable<Client> getClients(SyncContext $ctx)
 * @method Project getProject(SyncContext $ctx, int|string|null $id)
 * @method iterable<Project> getProjects(SyncContext $ctx)
 * @method TimeEntry createTimeEntry(SyncContext $ctx, TimeEntry $timeEntry)
 * @method TimeEntry updateTimeEntry(SyncContext $ctx, TimeEntry $timeEntry)
 * @method TimeEntry deleteTimeEntry(SyncContext $ctx, TimeEntry $timeEntry)
 */
class ClockifyProvider extends HttpSyncProvider implements
    IServiceShared,
    WorkspaceProvider,
    UserProvider,
    BillableTimeEntryProvider
{
    /**
     * @var int|null
     */
    private $CacheExpiry;

    public static function getContextualBindings(): array
    {
        return [
            TimeEntry::class => \Lkrms\Time\Entity\Clockify\TimeEntry::class,
            Project::class => \Lkrms\Time\Entity\Clockify\Project::class,
            Task::class => \Lkrms\Time\Entity\Clockify\Task::class,
        ];
    }

    public function name(): ?string
    {
        return sprintf('Clockify { %s }', $this->getWorkspaceId());
    }

    public function getBackendIdentifier(): array
    {
        return [$this->getBaseUrl(), $this->getWorkspaceId()];
    }

    protected function getDateFormatter(): DateFormatter
    {
        return new DateFormatter();
    }

    protected function getBaseUrl(?string $path = null): string
    {
        if ($path && strpos($path, '/reports/') !== false) {
            return Env::get('clockify_reports_api_base_endpoint', 'https://reports.api.clockify.me/v1');
        }

        return Env::get('clockify_api_base_endpoint', 'https://api.clockify.me/api/v1');
    }

    protected function getHeaders(?string $path): ?ICurlerHeaders
    {
        return CurlerHeaders::create()
            ->setHeader('X-Api-Key', Env::get('clockify_api_key'));
    }

    protected function getExpiry(?string $path): ?int
    {
        return !is_null($this->CacheExpiry)
            ? $this->CacheExpiry
            : Env::getInt('clockify_cache_expiry', 600);
    }

    /**
     * @var DateFormatter|null
     */
    private static $DateFormatter;

    protected function buildCurler(CurlerBuilder $curlerB): CurlerBuilder
    {
        if (!self::$DateFormatter) {
            // Prevent recursion
            if ($this->getEndpointUrl('/user') === $curlerB->get('baseUrl')) {
                return $curlerB;
            }
            self::$DateFormatter = new DateFormatter(
                'Y-m-d\TH:i:s.v\Z',
                $this->getCurrentUser()->Settings['timeZone']
            );
        }

        return $curlerB->dateFormatter(self::$DateFormatter);
    }

    protected function getHttpDefinition(string $entity, HttpSyncDefinitionBuilder $defB): HttpSyncDefinitionBuilder
    {
        if ($entity === Workspace::class) {
            return $defB
                ->path('/workspaces')
                ->operations([OP::READ, OP::READ_LIST])
                ->overrides([
                    OP::READ =>
                        fn(Definition $def, int $op, Context $ctx, $id) =>
                            $this->with(Workspace::class, $ctx)
                                 ->getList()
                                 ->nextWithValue('Id', $id)
                ]);
        }

        $workspaceId = $this->getWorkspaceId();
        switch ($entity) {
            case User::class:
                return $defB
                    ->path("/workspaces/$workspaceId/users")
                    ->operations([OP::READ, OP::READ_LIST])
                    ->overrides([
                        OP::READ =>
                            fn(Definition $def, int $op, Context $ctx, $id) =>
                                is_null($id)
                                    ? $def->withPath('/user')
                                          ->getFallbackSyncOperationClosure(OP::READ)($ctx, null)
                                    : $this->with(User::class, $ctx)
                                           ->getList()
                                           ->nextWithValue('Id', $id)
                    ]);

            case Client::class:
                return $defB
                    ->path("/workspaces/$workspaceId/clients")
                    ->operations([OP::READ, OP::READ_LIST]);

            case Project::class:
                return $defB
                    ->path("/workspaces/$workspaceId/projects")
                    ->operations([OP::READ, OP::READ_LIST])
                    ->query(['hydrated' => true]);
        }

        return $defB;
    }

    public function checkHeartbeat(int $ttl = 300)
    {
        $this->CacheExpiry = $ttl ?: null;
        try {
            $user = $this->getCurrentUser();
        } finally {
            $this->CacheExpiry = null;
        }
        Console::debugOnce(
            sprintf(
                "Connected to Clockify workspace '%s' as %s ('%s')",
                $user->ActiveWorkspace,
                $user->Name,
                $user->Id
            )
        );

        return $this;
    }

    protected function getWorkspaceId(): string
    {
        return Env::get('clockify_workspace_id');
    }

    /**
     * Get currently logged in user's info
     *
     * @return User
     */
    public function getCurrentUser(): User
    {
        /** @var User */
        return $this->with(User::class)->get(null);
    }

    /**
     * Find tasks on project
     *
     * @param int|string|null $projectId
     * @return iterable<Task>
     */
    public function getTasks(Context $ctx, $projectId): iterable
    {
        if (!$projectId) {
            return [];
        }
        $workspaceId = $this->getWorkspaceId();

        return Task::provideList($this->getCurler("/workspaces/$workspaceId/projects/$projectId/tasks")->get(), $this, ArrayKeyConformity::NONE, $ctx);
    }

    /**
     * Find task on project by ID
     *
     * @param int|string $id
     * @param int|string|null $projectId
     * @return Task
     */
    public function getTask(Context $ctx, $id, $projectId): Task
    {
        if (!$projectId) {
            throw new RuntimeException('Invalid projectId');
        }
        $workspaceId = $this->getWorkspaceId();

        return Task::provide($this->getCurler("/workspaces/$workspaceId/projects/$projectId/tasks/$id")->get(), $this, $ctx);
    }

    /**
     * @param SyncEntity|int|string $entity
     * @return array
     */
    private function getReportFilter($entity): array
    {
        return [
            'ids' => [$entity instanceof SyncEntity ? $entity->Id : $entity],
            'contains' => 'CONTAINS',
            'status' => 'ALL',
        ];
    }

    /**
     * @param mixed ...$args
     */
    private function getCurlerWithPostCache(...$args): Curler
    {
        $curler = $this->getCurler(...$args);
        $curler->CachePostResponse = true;

        return $curler;
    }

    /**
     * @return iterable<TimeEntry>
     */
    public function getTimeEntries(Context $ctx, $user = null, $client = null, $project = null, DateTimeInterface $from = null, DateTimeInterface $to = null, bool $billable = null, bool $billed = null): iterable
    {
        $workspaceId = $this->getWorkspaceId();

        $query = [
            'dateRangeStart' => $from,
            'dateRangeEnd' => $to,
            'sortOrder' => 'ASCENDING',
            'detailedFilter' => [
                'sortColumn' => 'DATE',
                'page' => 1,
                'pageSize' => 1000,
                'options' => [
                    'totals' => 'EXCLUDE',
                ],
            ],
            'users' => $user ? $this->getReportFilter($user) : null,
            'clients' => $client ? $this->getReportFilter($client) : null,
            'projects' => $project ? $this->getReportFilter($project) : null,
        ];

        if (!is_null($billable)) {
            $query['billable'] = $billable;
        }

        if (!is_null($billed)) {
            $query['invoicingState'] = $billed ? 'INVOICED' : 'UNINVOICED';
        }

        $pipeline = $this->pipeline()->throughCallback(function (array $entry): array {
            $client = ($entry['clientId'] ?? null) ? [
                'id' => $entry['clientId'],
                'name' => $entry['clientName'],
            ] : null;
            $user = ($entry['userId'] ?? null) ? [
                'id' => $entry['userId'],
                'name' => $entry['userName'],
                'email' => $entry['userEmail'],
            ] : null;
            $project = ($entry['projectId'] ?? null) ? [
                'id' => $entry['projectId'],
                'name' => $entry['projectName'],
                'color' => $entry['projectColor'],
                'client' => $client,
            ] : null;
            $task = ($entry['taskId'] ?? null) ? [
                'id' => $entry['taskId'],
                'name' => $entry['taskName'],
            ] : null;
            if ($project && $task) {
                $task['project'] = $project;
            }
            $entry = array_merge($entry, [
                'user' => $user,
                'project' => $project,
                'task' => $task
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
                $entry['taskName']
            );

            return $entry;
        });

        return TimeEntry::provideList($pipeline->stream(
            $this->getCurlerWithPostCache("/workspaces/$workspaceId/reports/detailed")->post($query)['timeentries']
        )->start(), $this, ArrayKeyConformity::NONE, $ctx);
    }

    /**
     * @param int|string $id
     * @return TimeEntry
     */
    public function getTimeEntry(Context $ctx, $id): TimeEntry
    {
        $workspaceId = $this->getWorkspaceId();
        $query = ['hydrated' => true];

        return TimeEntry::provide(
            $this->getCurler("/workspaces/$workspaceId/time-entries/$id")->get($query), $this, $ctx
        );
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
}
