<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Contract;

use Lkrms\Time\Sync\Entity\Client;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncProviderInterface;

/**
 * Syncs Client objects with a backend
 *
 * @method Client getClient(SyncContextInterface $ctx, int|string|null $id)
 * @method iterable<array-key,Client> getClients(SyncContextInterface $ctx)
 *
 * @generated
 */
interface ProvidesClient extends SyncProviderInterface {}
