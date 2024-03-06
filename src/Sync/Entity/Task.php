<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Salient\Contract\Core\Cardinality;
use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\AbstractSyncEntity;

/**
 * Represents the state of a Task entity in a backend
 *
 * @generated
 */
class Task extends AbstractSyncEntity
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
     * @var Project|DeferredEntity<Project>|null
     */
    public $Project;

    /**
     * @var bool|null
     */
    public $Billable;

    /**
     * @var float|null
     */
    public $BillableRate;

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
            'Project' => [Cardinality::ONE_TO_ONE => Project::class],
        ];
    }
}
