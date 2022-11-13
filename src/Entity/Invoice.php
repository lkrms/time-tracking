<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

class Invoice extends \Lkrms\Sync\Concept\SyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var string|null
     */
    public $Number;

    /**
     * @var string|null
     */
    public $Reference;

    /**
     * @var \DateTime|null
     */
    public $Date;

    /**
     * @var \DateTime|null
     */
    public $DueDate;

    /**
     * @var Client|null
     */
    public $Client;

    /**
     * @var InvoiceLineItem[]|null
     */
    public $LineItems;

    /**
     * @var string|null
     */
    public $Status;

    /**
     * @var string|null
     */
    public $Currency;

    /**
     * @var bool|null
     */
    public $SentToContact;

    /**
     * @var float|null
     */
    public $SubTotal;

    /**
     * @var float|null
     */
    public $TotalTax;

    /**
     * @var float|null
     */
    public $Total;

}
