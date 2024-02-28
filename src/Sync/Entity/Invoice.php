<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Salient\Catalog\Core\Cardinality;
use Salient\Sync\AbstractSyncEntity;
use DateTimeInterface;

class Invoice extends AbstractSyncEntity
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

    public ?DateTimeInterface $Date;

    public ?DateTimeInterface $DueDate;

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

    public static function getRelationships(): array
    {
        return [
            'Client' => [Cardinality::ONE_TO_ONE => Client::class],
            'LineItems' => [Cardinality::ONE_TO_MANY => InvoiceLineItem::class],
        ];
    }
}
