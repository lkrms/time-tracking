<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity\Xero;

use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Time\Entity\Client;
use Lkrms\Time\Entity\InvoiceLineItem;

class Invoice extends \Lkrms\Time\Entity\Invoice
{
    protected function _setClient($value)
    {
        $this->Client = Client::provide($value, $this->provider(), $this->requireContext()->push($this));
    }

    protected function _setLineItems($value)
    {
        $this->LineItems = InvoiceLineItem::provideList($value, $this->provider(), $this->requireContext()->getConformity(), $this->requireContext()->push($this));
    }

}
