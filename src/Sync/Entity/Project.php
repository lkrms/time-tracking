<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Salient\Catalog\Core\Cardinality;
use Salient\Sync\AbstractSyncEntity;

class Project extends AbstractSyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var string|null
     */
    public $Name;

    /**
     * @var string|null
     */
    public $Description;

    /**
     * @var bool|null
     */
    public $Billable;

    /**
     * @var Task[]|null
     */
    public $Tasks;

    /**
     * @var Client|null
     */
    public $Client;

    /**
     * @var float|null
     */
    public $BillableRate;

    /**
     * @var string|null
     */
    public $Colour;

    /**
     * @var bool|null
     */
    public $Archived;

    public static function getRelationships(): array
    {
        return [
            'Tasks' => [Cardinality::ONE_TO_MANY => Task::class],
            'Client' => [Cardinality::ONE_TO_ONE => Client::class],
        ];
    }
}
