<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\Support\DeferredRelationship;
use Salient\Sync\AbstractSyncEntity;
use DateTimeInterface;

/**
 * Represents the state of an Invoice entity in a backend
 *
 * @generated
 */
class Invoice extends AbstractSyncEntity
{
    /** @var int|string|null */
    public $Id;
    /** @var string|null */
    public $Number;
    /** @var DateTimeInterface|null */
    public $Date;
    /** @var DateTimeInterface|null */
    public $DueDate;
    /** @var Client|DeferredEntity<Client>|null */
    public $Client;
    /** @var array<InvoiceLineItem|DeferredEntity<InvoiceLineItem>>|DeferredRelationship<InvoiceLineItem>|null */
    public $LineItems;
    /** @var string|null */
    public $Status;
    /** @var string|null */
    public $Currency;
    /** @var bool|null */
    public $Sent;
    /** @var float|null */
    public $SubTotal;
    /** @var float|null */
    public $TotalTax;
    /** @var float|null */
    public $Total;

    /**
     * @internal
     */
    public static function getRelationships(): array
    {
        return [
            'Client' => [self::ONE_TO_ONE => Client::class],
            'LineItems' => [self::ONE_TO_MANY => InvoiceLineItem::class],
        ];
    }

    /**
     * @internal
     */
    public static function getDateProperties(): array
    {
        return [
            'Date',
            'DueDate',
        ];
    }
}
