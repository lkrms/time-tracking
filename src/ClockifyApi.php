<?php

declare(strict_types=1);

namespace Lkrms\Clockify;

use Lkrms\Clockify\Entity\Workspace;
use Lkrms\Convert;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Env;

class ClockifyApi
{
    private function GetHeaders(): CurlerHeaders
    {
        $headers = new CurlerHeaders();
        $headers->SetHeader("X-Api-Key", Env::Get("clockify_api_key"));

        return $headers;
    }

    private function GetCurler(string $path): Curler
    {
        return new Curler(Env::Get("clockify_api_base_endpoint") . $path, $this->GetHeaders());
    }

    /**
     * Get all my workspaces
     *
     * @return Workspace[]
     */
    public function GetWorkspaces(): array
    {
        return Workspace::ListFrom($this->GetCurler("/workspaces")->GetJson());
    }

    /**
     * Get workspace by ID
     *
     * @param string $workspaceId
     * @return null|Workspace
     */
    public function GetWorkspace(string $workspaceId): ?Workspace
    {
        return Convert::ListToMap($this->GetWorkspaces(), "id")[$workspaceId] ?? null;
    }
}

