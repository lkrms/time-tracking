<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Contract;

use Lkrms\Time\Sync\Entity\Task;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncProviderInterface;

/**
 * Syncs Task objects with a backend
 *
 * @method Task getTask(SyncContextInterface $ctx, int|string|null $id)
 * @method iterable<array-key,Task> getTasks(SyncContextInterface $ctx)
 *
 * @generated
 */
interface ProvidesTask extends SyncProviderInterface {}
