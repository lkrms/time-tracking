<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Contract;

use Lkrms\Time\Sync\Entity\TimeEntry;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncProviderInterface;

/**
 * Syncs TimeEntry objects with a backend
 *
 * @method TimeEntry getTimeEntry(SyncContextInterface $ctx, int|string|null $id)
 * @method TimeEntry updateTimeEntry(SyncContextInterface $ctx, TimeEntry $timeEntry)
 * @method iterable<array-key,TimeEntry> getTimeEntries(SyncContextInterface $ctx)
 *
 * @generated
 */
interface ProvidesTimeEntry extends SyncProviderInterface {}
