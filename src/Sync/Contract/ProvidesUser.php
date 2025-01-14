<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Contract;

use Lkrms\Time\Sync\Entity\User;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncProviderInterface;

/**
 * Syncs User objects with a backend
 *
 * @method User getUser(SyncContextInterface $ctx, int|string|null $id)
 * @method iterable<array-key,User> getUsers(SyncContextInterface $ctx)
 *
 * @generated
 */
interface ProvidesUser extends SyncProviderInterface {}
