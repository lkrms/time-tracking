<?php

declare(strict_types=1);

namespace Lkrms\Time;

use DateTime;
use Lkrms\Container\DI;
use Lkrms\Curler\CachingCurler;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Exception\SyncOperationNotImplementedException;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Provider\HttpSyncProvider;
use Lkrms\Sync\SyncEntity;
use Lkrms\Sync\SyncOperation;
use Lkrms\Time\Entity\Client;
use Lkrms\Time\Entity\Project;
use Lkrms\Time\Entity\Task;
use Lkrms\Time\Entity\TimeEntry;
use Lkrms\Time\Entity\TimeEntryProvider;
use Lkrms\Time\Entity\User;
use Lkrms\Time\Entity\UserProvider;
use Lkrms\Time\Entity\Workspace;
use Lkrms\Time\Entity\WorkspaceProvider;
use Lkrms\Util\Convert;
use Lkrms\Util\Env;
use RuntimeException;

class ClockifyProvider extends HttpSyncProvider implements WorkspaceProvider, UserProvider, TimeEntryProvider
{
    public static function register(string ...$interfaces): void
    {
        parent::register(...$interfaces);
        DI::bind(TimeEntry::class, \Lkrms\Time\Entity\Clockify\TimeEntry::class);
        DI::bind(Project::class, \Lkrms\Time\Entity\Clockify\Project::class);
        DI::bind(Task::class, \Lkrms\Time\Entity\Clockify\Task::class);
    }

    protected function getBackendIdentifier(): array
    {
        return [$this->getBaseUrl(), $this->getWorkspaceId()];
    }

    protected function getBaseUrl(string $path = null): string
    {
        if ($path && strpos($path, "/reports/") !== false)
        {
            return Env::get("clockify_reports_api_base_endpoint", "https://reports.api.clockify.me/v1");
        }

        return Env::get("clockify_api_base_endpoint", "https://api.clockify.me/api/v1");
    }

    protected function getHeaders(?string $path): ?CurlerHeaders
    {
        $headers = new CurlerHeaders();
        $headers->setHeader("X-Api-Key", Env::get("clockify_api_key"));

        return $headers;
    }

    protected function getCacheExpiry(): ?int
    {
        return 600;
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
            if ($this->getEndpointUrl("/user") == $curler->Url)
            {
                return;
            }
            self::$DateFormatter = new DateFormatter(
                "Y-m-d\TH:i:s.v\Z",
                $this->getUser()->Settings["timeZone"]
            );
        }
        $curler->DateFormatter = self::$DateFormatter;
    }

    protected function getWorkspaceId(): string
    {
        return Env::get("clockify_workspace_id");
    }

    /**
     * Get all my workspaces
     *
     * @return Workspace[]
     */
    public function getWorkspaces(): array
    {
        return Workspace::listFromArrays($this, $this->getCurler("/workspaces")->getJson());
    }

    /**
     * Get workspace by ID
     *
     * @param int|string $id
     * @return Workspace
     */
    public function getWorkspace($id): Workspace
    {
        return Convert::listToMap($this->getWorkspaces(), "id")[$id];
    }

    /**
     * Find all users on workspace
     *
     * @return User[]
     */
    public function getUsers(): array
    {
        $workspaceId = $this->getWorkspaceId();
        return User::listFromArrays($this, $this->getCurler("/workspaces/$workspaceId/users")->getJson());
    }

    /**
     * Get user by ID, or get currently logged in user's info
     *
     * @param int|string|null $id
     * @return User
     */
    public function getUser($id = null): User
    {
        if (!is_null($id))
        {
            return Convert::listToMap($this->getUsers(), "id")[$id];
        }
        else
        {
            return User::fromArray($this, $this->getCurler("/user")->getJson());
        }
    }

    /**
     * Find clients on workspace
     *
     * @return Client[]
     */
    public function getClients(): array
    {
        $workspaceId = $this->getWorkspaceId();
        return Client::listFromArrays($this, $this->getCurler("/workspaces/$workspaceId/clients")->getJson());
    }

    /**
     * Get client by ID
     *
     * @param int|string $id
     * @return Client
     */
    public function getClient($id): Client
    {
        $workspaceId = $this->getWorkspaceId();
        return Client::fromArray($this, $this->getCurler("/workspaces/$workspaceId/clients/$id")->getJson());
    }

    /**
     * Get all projects on workspace
     *
     * @return Project[]
     */
    public function getProjects(): array
    {
        $workspaceId = $this->getWorkspaceId();
        $query       = ["hydrated" => true];
        return Project::listFromArrays($this, $this->getCurler("/workspaces/$workspaceId/projects")->getJson($query));
    }

    /**
     * Find project by ID
     *
     * @param int|string $id
     * @return Project
     */
    public function getProject($id): Project
    {
        $workspaceId = $this->getWorkspaceId();
        $query       = ["hydrated" => true];
        return Project::fromArray($this, $this->getCurler("/workspaces/$workspaceId/projects/$id")->getJson($query));
    }

    /**
     * Find tasks on project
     *
     * @param int|string|null $projectId
     * @return Task[]
     */
    public function getTasks($projectId): array
    {
        if (!$projectId)
        {
            return [];
        }
        $workspaceId = $this->getWorkspaceId();
        return Task::listFromArrays($this, $this->getCurler("/workspaces/$workspaceId/projects/$projectId/tasks")->getJson());
    }

    /**
     * Find task on project by ID
     *
     * @param int|string $id
     * @param int|string|null $projectId
     * @return Task
     */
    public function getTask($id, $projectId): Task
    {
        if (!$projectId)
        {
            throw new RuntimeException("Invalid projectId");
        }
        $workspaceId = $this->getWorkspaceId();
        return Task::fromArray($this, $this->getCurler("/workspaces/$workspaceId/projects/$projectId/tasks/$id")->getJson());
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
     * @return TimeEntry[]
     */
    public function getTimeEntries(
        $user          = null,
        $client        = null,
        $project       = null,
        DateTime $from = null,
        DateTime $to   = null,
        bool $billable = null,
        bool $billed   = null
    ): array
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

        return TimeEntry::listFromArraysVia($this, $this->getPostCachingCurler(
            "/workspaces/$workspaceId/reports/detailed"
        )->postJson($query)["timeentries"], function (array $entry)
        {
            $entry = Convert::toNestedArrays($entry, [
                "user"       => [
                    "id"     => "userId",
                    "name"   => "userName",
                    "email"  => "userEmail",
                ], "client"  => [
                    "id"     => "clientId",
                    "name"   => "clientName",
                ], "project" => [
                    "id"     => "projectId",
                    "name"   => "projectName",
                    "color"  => "projectColor",
                    "client" => "client",
                ], "task"    => [
                    "id"     => "taskId",
                    "name"   => "taskName",
                ]
            ]);
            if (($entry["project"] ?? null) &&
                is_array($entry["task"] ?? null) &&
                !array_key_exists("project", $entry["task"]))
            {
                $entry["task"]["project"] = $entry["project"];
            }
            return $entry;
        });
    }

    /**
     * @param int|string $id
     * @return TimeEntry
     */
    public function getTimeEntry($id): TimeEntry
    {
        $workspaceId = $this->getWorkspaceId();
        $query       = ["hydrated" => true];

        return TimeEntry::fromArray(
            $this,
            $this->getCurler("/workspaces/$workspaceId/time-entries/$id")->getJson($query)
        );
    }

    /**
     * @param TimeEntry $timeEntry
     * @return TimeEntry
     */
    public function createTimeEntry(TimeEntry $timeEntry): TimeEntry
    {
        throw new SyncOperationNotImplementedException(static::class, TimeEntry::class, SyncOperation::CREATE);
    }

    /**
     * @param TimeEntry $timeEntry
     * @return TimeEntry
     */
    public function updateTimeEntry(TimeEntry $timeEntry): TimeEntry
    {
        throw new SyncOperationNotImplementedException(static::class, TimeEntry::class, SyncOperation::UPDATE);
    }

    /**
     * @param TimeEntry $timeEntry
     * @return null|TimeEntry
     */
    public function deleteTimeEntry(TimeEntry $timeEntry): ?TimeEntry
    {
        throw new SyncOperationNotImplementedException(static::class, TimeEntry::class, SyncOperation::DELETE);
    }

}