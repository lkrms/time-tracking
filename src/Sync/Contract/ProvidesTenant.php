<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Contract;

use Lkrms\Time\Sync\Entity\Tenant;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncProviderInterface;

/**
 * Syncs Tenant objects with a backend
 *
 * @method Tenant getTenant(SyncContextInterface $ctx, int|string|null $id)
 * @method iterable<array-key,Tenant> getTenants(SyncContextInterface $ctx)
 *
 * @generated
 */
interface ProvidesTenant extends SyncProviderInterface {}
