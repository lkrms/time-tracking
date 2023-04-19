<?php declare(strict_types=1);

namespace Lkrms\Time\Entity\Provider;

use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Time\Entity\Invoice;

/**
 * Syncs Invoice objects with a backend
 *
 * @method Invoice createInvoice(ISyncContext $ctx, Invoice $invoice)
 * @method Invoice getInvoice(ISyncContext $ctx, int|string|null $id)
 * @method iterable<Invoice> getInvoices(ISyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --extend='Lkrms\Time\Entity\Provider\ClientProvider' --magic --op='create,get,get-list' 'Lkrms\Time\Entity\Invoice'
 */
interface InvoiceProvider extends ClientProvider
{
}
