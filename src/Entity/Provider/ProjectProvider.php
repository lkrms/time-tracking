<?php declare(strict_types=1);

namespace Lkrms\Time\Entity\Provider;

use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Time\Entity\Project;

/**
 * Syncs Project objects with a backend
 *
 * @method Project getProject(SyncContext $ctx, int|string|null $id)
 * @method iterable<Project> getProjects(SyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --class='Lkrms\Time\Entity\Project' --magic --op='get,get-list'
 */
interface ProjectProvider extends ISyncProvider
{
}
