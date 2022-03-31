<?php

declare(strict_types=1);

namespace Lkrms\Clockify;

use DateTime;
use Lkrms\Clockify\Entity\TimeEntry;
use Lkrms\Clockify\Entity\TimeEntryProvider;
use Lkrms\Clockify\Entity\User;
use Lkrms\Clockify\Entity\UserProvider;
use Lkrms\Clockify\Entity\Workspace;
use Lkrms\Clockify\Entity\WorkspaceProvider;
use Lkrms\Convert;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Env;
use Lkrms\Sync\Exception\SyncOperationNotImplementedException;
use Lkrms\Sync\Provider\HttpSyncProvider;
use Lkrms\Sync\SyncOperation;

class ClockifyApi extends HttpSyncProvider implements WorkspaceProvider, UserProvider, TimeEntryProvider
{
    protected function getBackendIdentifier(): array
    {
        return [$this->getBaseUrl(), $this->getWorkspaceId()];
    }

    protected function getBaseUrl(): string
    {
        return Env::get("clockify_api_base_endpoint", "https://api.clockify.me/api/v1");
    }

    protected function getWorkspaceId(): string
    {
        return Env::get("clockify_workspace_id");
    }

    protected function getHeaders(): CurlerHeaders
    {
        $headers = new CurlerHeaders();
        $headers->SetHeader("X-Api-Key", Env::get("clockify_api_key"));

        return $headers;
    }

    /**
     * Get all my workspaces
     *
     * @return Workspace[]
     */
    public function getWorkspaces(): array
    {
        return Workspace::listFrom($this->GetCurler("/workspaces")->GetJson());
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
        return User::listFrom($this->GetCurler("/workspaces/" . $this->getWorkspaceId() . "/users")->GetJson());
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
            return User::from($this->GetCurler("/user")->GetJson());
        }
    }

    public function getTimeEntries(
        User $user     = null,
        $client        = null,
        $project       = null,
        DateTime $from = null,
        DateTime $to   = null,
        bool $isBilled = null
    ): array
    {
        if (is_null($user))
        {
            $user = $this->getUser();
        }

        $workspaceId = $this->getWorkspaceId();
        $userId      = $user->Id;
        $query       = ["hydrated" => true];

        return TimeEntry::listFrom(
            $this->GetCurler("/workspaces/$workspaceId/user/$userId/time-entries")->GetJson($query)
        );
    }

    public function getTimeEntry($id): TimeEntry
    {
        $workspaceId = $this->getWorkspaceId();
        $query       = ["hydrated" => true];

        return TimeEntry::from(
            $this->GetCurler("/workspaces/$workspaceId/time-entries/$id")->GetJson($query)
        );
    }

    public function createTimeEntry(TimeEntry $timeEntry): TimeEntry
    {
        throw new SyncOperationNotImplementedException(static::class, TimeEntry::class, SyncOperation::CREATE);
    }

    public function updateTimeEntry(TimeEntry $timeEntry): TimeEntry
    {
        throw new SyncOperationNotImplementedException(static::class, TimeEntry::class, SyncOperation::UPDATE);
    }

    public function deleteTimeEntry(TimeEntry $timeEntry): ?TimeEntry
    {
        throw new SyncOperationNotImplementedException(static::class, TimeEntry::class, SyncOperation::DELETE);
    }
}

