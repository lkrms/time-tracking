<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Contract;

use Lkrms\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Time\Sync\Entity\TimeEntry;

/**
 * Syncs TimeEntry objects with a backend
 *
 * @method TimeEntry getTimeEntry(ISyncContext $ctx, int|string|null $id)
 * @method TimeEntry updateTimeEntry(ISyncContext $ctx, TimeEntry $timeEntry)
 * @method FluentIteratorInterface<array-key,TimeEntry> getTimeEntries(ISyncContext $ctx)
 *
 * @generated by lk-util
 * @salient-generate-command generate sync provider --magic --op='get,update,get-list' 'Lkrms\Time\Sync\Entity\TimeEntry'
 */
interface ProvidesTimeEntry extends ISyncProvider {}
