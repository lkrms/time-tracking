<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\ContractGroup;

use Lkrms\Time\Sync\Contract\ProvidesClient;
use Lkrms\Time\Sync\Contract\ProvidesInvoice;

/**
 * Syncs invoices and related entities with a backend
 */
interface InvoiceProvider extends
    ProvidesInvoice,
    ProvidesClient {}
