<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Salient\Sync\AbstractSyncEntity;

class InvoiceLineItem extends AbstractSyncEntity
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
     * @var array<string,string>|null
     */
    public $Tracking;

    protected static function getRemovablePrefixes(): ?array
    {
        return ['LineItem', 'InvoiceLineItem'];
    }
}
