<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Contract;

use Lkrms\Time\Sync\Entity\Invoice;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncProviderInterface;

/**
 * Syncs Invoice objects with a backend
 *
 * @method Invoice createInvoice(SyncContextInterface $ctx, Invoice $invoice)
 * @method Invoice getInvoice(SyncContextInterface $ctx, int|string|null $id)
 * @method iterable<array-key,Invoice> getInvoices(SyncContextInterface $ctx)
 *
 * @generated
 */
interface ProvidesInvoice extends SyncProviderInterface {}
