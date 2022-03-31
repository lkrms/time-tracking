<?php

declare(strict_types=1);

namespace Lkrms\Clockify\Entity;

/**
 * Synchronises Workspace objects with a backend
 *
 * @package Lkrms\Clockify
 */
interface WorkspaceProvider extends \Lkrms\Sync\Provider\ISyncProvider
{
    /**
     * @param int|string $id
     * @return Workspace
     */
    public function getWorkspace($id): Workspace;

    /**
     * @return Workspace[]
     */
    public function getWorkspaces(): array;

}

