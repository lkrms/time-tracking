<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity\Xero;

use Lkrms\Time\Entity\Client;
use Lkrms\Time\Entity\InvoiceLineItem;

class Invoice extends \Lkrms\Time\Entity\Invoice
{
    protected function _setClient($value)
    {
        $this->Client = Client::fromProvider($this->getProvider(), $value);
    }

    protected function _setLineItems($value)
    {
        $this->LineItems = iterator_to_array(InvoiceLineItem::listFromProvider($this->getProvider(), $value));
    }

}
