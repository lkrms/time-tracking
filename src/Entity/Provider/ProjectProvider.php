<?php declare(strict_types=1);

namespace Lkrms\Time\Entity\Provider;

use Lkrms\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Time\Entity\Project;

/**
 * Syncs Project objects with a backend
 *
 * @method Project getProject(ISyncContext $ctx, int|string|null $id)
 * @method FluentIteratorInterface<array-key,Project> getProjects(ISyncContext $ctx)
 *
 * @generated by lk-util
 * @salient-generate-command generate sync provider --magic --op='get,get-list' 'Lkrms\Time\Entity\Project'
 */
interface ProjectProvider extends ISyncProvider {}
