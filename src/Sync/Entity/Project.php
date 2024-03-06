<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Salient\Contract\Core\Cardinality;
use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\Support\DeferredRelationship;
use Salient\Sync\AbstractSyncEntity;

/**
 * Represents the state of a Project entity in a backend
 *
 * @generated
 */
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
     * @var array<Task|DeferredEntity<Task>>|DeferredRelationship<Task>|null
     */
    public $Tasks;

    /**
     * @var Client|DeferredEntity<Client>|null
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

    /**
     * @internal
     */
    public static function getRelationships(): array
    {
        return [
            'Tasks' => [Cardinality::ONE_TO_MANY => Task::class],
            'Client' => [Cardinality::ONE_TO_ONE => Client::class],
        ];
    }
}
