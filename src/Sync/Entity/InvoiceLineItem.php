<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Salient\Sync\AbstractSyncEntity;

/**
 * Represents the state of an InvoiceLineItem entity in a backend
 *
 * @generated
 */
class InvoiceLineItem extends AbstractSyncEntity
{
    /** @var int|string|null */
    public $Id;
    /** @var string|null */
    public $Description;
    /** @var float|null */
    public $Quantity;
    /** @var float|null */
    public $UnitAmount;
    /** @var string|null */
    public $ItemCode;
    /** @var string|null */
    public $AccountCode;

    /**
     * @internal
     *
     * @return string[]
     */
    protected static function getRemovablePrefixes(): array
    {
        return [
            'InvoiceLineItem',
            'LineItem',
        ];
    }
}
