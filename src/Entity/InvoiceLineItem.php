<?php declare(strict_types=1);

namespace Lkrms\Time\Entity;

use Lkrms\Sync\Concept\SyncEntity;

class InvoiceLineItem extends SyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var string|null
     */
    public $Description;

    /**
     * @var float|null
     */
    public $Quantity;

    /**
     * @var float|null
     */
    public $UnitAmount;

    /**
     * @var string|null
     */
    public $ItemCode;

    /**
     * @var string|null
     */
    public $AccountCode;

    /**
     * @var array|null
     */
    public $Tracking;

    protected static function getRemovablePrefixes(): ?array
    {
        return ['LineItem', 'InvoiceLineItem'];
    }
}
