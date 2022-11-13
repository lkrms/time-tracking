<?php

declare(strict_types=1);

namespace Lkrms\Time;

use DateTime;
use Lkrms\Curler\CachingCurler;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Support\Arr;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\DateFormatter;
use Lkrms\Support\PipelineImmutable;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder;
use Lkrms\Sync\Support\SyncContext as Context;
use Lkrms\Sync\Support\SyncOperation as OP;
use Lkrms\Time\Entity\BillableTimeEntryProvider;
use Lkrms\Time\Entity\Client;
use Lkrms\Time\Entity\Project;
use Lkrms\Time\Entity\Task;
use Lkrms\Time\Entity\TimeEntry;
use Lkrms\Time\Entity\User;
use Lkrms\Time\Entity\UserProvider;
use Lkrms\Time\Entity\Workspace;
use Lkrms\Time\Entity\WorkspaceProvider;
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
class ClockifyProvider extends HttpSyncProvider implements WorkspaceProvider, UserProvider, BillableTimeEntryProvider
{
    /**
     * @var int|null
     */
    private $CacheExpiry;

    public static function getBindings(): array
    {
        return [
            TimeEntry::class => \Lkrms\Time\Entity\Clockify\TimeEntry::class,
            Project::class   => \Lkrms\Time\Entity\Clockify\Project::class,
            Task::class      => \Lkrms\Time\Entity\Clockify\Task::class,
        ];
    }

    public function getBackendIdentifier(): array
    {
        return [$this->getBaseUrl(), $this->getWorkspaceId()];
    }

    protected function createDateFormatter(): DateFormatter
    {
        return new DateFormatter();
    }

    protected function getBaseUrl(?string $path = null): string
    {
        if ($path && strpos($path, "/reports/") !== false)
        {
            return Env::get("clockify_reports_api_base_endpoint", "https://reports.api.clockify.me/v1");
        }

        return Env::get("clockify_api_base_endpoint", "https://api.clockify.me/api/v1");
    }

    protected function getCurlerHeaders(?string $path): ?CurlerHeaders
    {
        $headers = new CurlerHeaders();
        $headers->setHeader("X-Api-Key", Env::get("clockify_api_key"));

        return $headers;
    }

    protected function getCurlerCacheExpiry(?string $path): ?int
    {
        return !is_null($this->CacheExpiry)
            ? $this->CacheExpiry
            : Env::getInt("clockify_cache_expiry", 600);
    }

    /**
     * @var DateFormatter|null
     */
    private static $DateFormatter;

    protected function prepareCurler(Curler $curler): void
    {
        if (!self::$DateFormatter)
        {
            // Prevent recursion
            if ($this->getEndpointUrl("/user") == $curler->BaseUrl)
            {
                return;
            }
            self::$DateFormatter = new DateFormatter(
                "Y-m-d\TH:i:s.v\Z",
                $this->getCurrentUser()->Settings["timeZone"]
            );
        }
        $curler->DateFormatter = self::$DateFormatter;
    }

    protected function getHttpDefinition(string $entity, HttpSyncDefinitionBuilder $define)
    {
        $workspaceId = $this->getWorkspaceId();

        switch ($entity)
        {
            case Workspace::class:
                return $define->operations([OP::READ, OP::READ_LIST])
                    ->path("/workspaces")
                    ->overrides([
                        OP::READ => (fn(Context $ctx, $id) =>
                            Convert::iterableToItem($this->with(Workspace::class, $ctx)->getList(), "Id", $id))
                    ]);

            case User::class:
                return $define->operations([OP::READ, OP::READ_LIST])
                    ->path("/workspaces/$workspaceId/users")
                    ->overrides([
                        OP::READ => fn(Context $ctx, $id) => (is_null($id)
                            ? User::provide($this->getCurler("/user")->get(), $this, $ctx)
                            : Convert::iterableToItem($this->with(User::class, $ctx)->getList(), "Id", $id))
                    ]);

            case Client::class:
                return $define->operations([OP::READ, OP::READ_LIST])
                    ->path("/workspaces/$workspaceId/clients");

            case Project::class:
                return $define->operations([OP::READ, OP::READ_LIST])
                    ->path("/workspaces/$workspaceId/projects")
                    ->query(["hydrated" => true]);
        }

        return null;
    }

    public function checkHeartbeat(int $ttl = 300)
    {
        $this->CacheExpiry = $ttl ?: null;
        try
        {
            $user = $this->getCurrentUser();
        }
        finally
        {
            $this->CacheExpiry = null;
        }
        Console::debugOnce(
            sprintf("Connected to Clockify workspace '%s' as %s ('%s')",
                $user->ActiveWorkspace,
                $user->Name,
                $user->Id)
        );

        return $this;
    }

    protected function getWorkspaceId(): string
    {
        return Env::get("clockify_workspace_id");
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
        if (!$projectId)
        {
            return [];
        }
        $workspaceId = $this->getWorkspaceId();

        return Task::provideList($this->getCurler("/workspaces/$workspaceId/projects/$projectId/tasks")->get(), $this, ArrayKeyConformity::PARTIAL, $ctx);
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
        if (!$projectId)
        {
            throw new RuntimeException("Invalid projectId");
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
            "ids"      => [$entity instanceof SyncEntity ? $entity->Id : $entity],
            "contains" => "CONTAINS",
            "status"   => "ALL",
        ];
    }

    private function getPostCachingCurler(...$args): Curler
    {
        $curler = $this->getCurler(...$args);
        if ($curler instanceof CachingCurler)
        {
            $curler->CachePostRequests = true;
        }
        return $curler;
    }

    /**
     * @return iterable<TimeEntry>
     */
    public function getTimeEntries(
        Context $ctx,
        $user          = null,
        $client        = null,
        $project       = null,
        DateTime $from = null,
        DateTime $to   = null,
        bool $billable = null,
        bool $billed   = null
    ): iterable
    {
        $workspaceId = $this->getWorkspaceId();

        $query = [
            "dateRangeStart" => $from,
            "dateRangeEnd"   => $to,
            "sortOrder"      => "ASCENDING",
            "detailedFilter" => [
                "sortColumn" => "DATE",
                "page"       => 1,
                "pageSize"   => 1000,
                "options"    => [
                    "totals" => "EXCLUDE",
                ],
            ],
            "users"    => $user ? $this->getReportFilter($user) : null,
            "clients"  => $client ? $this->getReportFilter($client) : null,
            "projects" => $project ? $this->getReportFilter($project) : null,
        ];

        if (!is_null($billable))
        {
            $query["billable"] = $billable;
        }

        if (!is_null($billed))
        {
            $query["invoicingState"] = $billed ? "INVOICED" : "UNINVOICED";
        }

        $pipeline = PipelineImmutable::create($this->container())
            ->throughCallback(static function (array $entry): array
            {
                $entry = (Arr::with($entry)->merge([
                    "user"      => [
                        "id"    => $entry["userId"],
                        "name"  => $entry["userName"],
                        "email" => $entry["userEmail"],
                    ],
                    "client"   => [
                        "id"   => $entry["clientId"],
                        "name" => $entry["clientName"],
                    ],
                    "project"    => [
                        "id"     => $entry["projectId"],
                        "name"   => $entry["projectName"],
                        "color"  => $entry["projectColor"],
                        "client" => $entry["client"],
                    ],
                    "task"     => [
                        "id"   => $entry["taskId"],
                        "name" => $entry["taskName"],
                    ],
                ])->diffKey(array_flip([
                    "userId",
                    "userName",
                    "userEmail",
                    "clientId",
                    "clientName",
                    "projectId",
                    "projectName",
                    "projectColor",
                    "client",
                    "taskId",
                    "taskName"
                ]))->toArray());
                if (($entry["project"] ?? null) &&
                    is_array($entry["task"] ?? null) &&
                    !array_key_exists("project", $entry["task"]))
                {
                    $entry["task"]["project"] = $entry["project"];
                }

                return $entry;
            });

            return TimeEntry::provideList($pipeline->stream(
                $this->getPostCachingCurler("/workspaces/$workspaceId/reports/detailed")->post($query)["timeentries"]
            )->start(), $this, ArrayKeyConformity::PARTIAL, $ctx);
    }

    /**
     * @param int|string $id
     * @return TimeEntry
     */
    public function getTimeEntry(Context $ctx, $id): TimeEntry
    {
        $workspaceId = $this->getWorkspaceId();
        $query       = ["hydrated" => true];

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
    ): void
    {
        $workspaceId = $this->getWorkspaceId();
        $data        = [
            "timeEntryIds" => [],
            "invoiced"     => !$unmark,
        ];

        foreach ($timeEntries as $time)
        {
            $data["timeEntryIds"][] = $time->Id;
        }

        if (!$data["timeEntryIds"])
        {
            return;
        }

        $this->getCurler("/workspaces/$workspaceId/time-entries/invoiced")->patch($data);
    }

}
