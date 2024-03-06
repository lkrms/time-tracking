<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Contract;

use Lkrms\Time\Sync\Entity\Project;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncProviderInterface;

/**
 * Syncs Project objects with a backend
 *
 * @method Project getProject(SyncContextInterface $ctx, int|string|null $id)
 * @method iterable<Project> getProjects(SyncContextInterface $ctx)
 *
 * @generated
 */
interface ProvidesProject extends SyncProviderInterface {}
