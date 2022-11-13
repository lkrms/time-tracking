<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

use Lkrms\Sync\Support\SyncContext;

/**
 * Syncs Invoice objects with a backend
 *
 * @method Invoice createInvoice(SyncContext $ctx, Invoice $invoice)
 * @method Invoice getInvoice(SyncContext $ctx, int|string|null $id)
 * @method iterable<Invoice> getInvoices(SyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --class='Lkrms\Time\Entity\Invoice' --extend='Lkrms\Time\Entity\ClientProvider' --magic --op='create,get,get-list'
 */
interface InvoiceProvider extends ClientProvider
{
}
