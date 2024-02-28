<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Salient\Catalog\Core\Cardinality;
use Salient\Sync\AbstractSyncEntity;

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
     * @var Project|null
     */
    public $Project;

    public static function getRelationships(): array
    {
        return [
            'Project' => [Cardinality::ONE_TO_ONE => Project::class],
        ];
    }
}
