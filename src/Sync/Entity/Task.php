<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Lkrms\Support\Catalog\RelationshipType;
use Lkrms\Sync\Concept\SyncEntity;

class Task extends SyncEntity
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
            'Project' => [RelationshipType::ONE_TO_ONE => Project::class],
        ];
    }
}
