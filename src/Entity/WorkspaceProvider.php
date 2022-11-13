<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;

/**
 * Syncs Workspace objects with a backend
 *
 * @method Workspace getWorkspace(SyncContext $ctx, int|string|null $id)
 * @method iterable<Workspace> getWorkspaces(SyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --class='Lkrms\Time\Entity\Workspace' --extend='' --magic --op='get,get-list'
 */
interface WorkspaceProvider extends ISyncProvider
{
}
