<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

/**
 * Synchronises Invoice objects with a backend
 *
 */
interface InvoiceProvider extends ClientProvider
{
    /**
     * @param Invoice $invoice
     * @return Invoice
     */
    public function createInvoice(Invoice $invoice): Invoice;

    /**
     * @param int|string $id
     * @return Invoice
     */
    public function getInvoice($id): Invoice;

    /**
     * @return iterable<Invoice>
     */
    public function getInvoices(): iterable;

}
