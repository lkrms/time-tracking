<?php declare(strict_types=1);

namespace Lkrms\Time\Entity\Provider;

use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Time\Entity\Workspace;

/**
 * Syncs Workspace objects with a backend
 *
 * @method Workspace getWorkspace(ISyncContext $ctx, int|string|null $id)
 * @method iterable<Workspace> getWorkspaces(ISyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --magic --op='get,get-list' 'Lkrms\Time\Entity\Workspace'
 */
interface WorkspaceProvider extends ISyncProvider
{
}
