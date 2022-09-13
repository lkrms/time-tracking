<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

/**
 * Synchronises Workspace objects with a backend
 *
 */
interface WorkspaceProvider extends \Lkrms\Sync\Contract\ISyncProvider
{
    /**
     * @param int|string $id
     * @return Workspace
     */
    public function getWorkspace($id): Workspace;

    /**
     * @return iterable<Workspace>
     */
    public function getWorkspaces(): iterable;

}
