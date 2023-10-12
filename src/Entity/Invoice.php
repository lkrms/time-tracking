<?php declare(strict_types=1);

namespace Lkrms\Time\Entity;

use Lkrms\Support\Catalog\RelationshipType;
use Lkrms\Sync\Concept\SyncEntity;
use DateTimeInterface;

class Invoice extends SyncEntity
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
            'Client' => [RelationshipType::ONE_TO_ONE => Client::class],
            'LineItems' => [RelationshipType::ONE_TO_MANY => InvoiceLineItem::class],
        ];
    }
}
