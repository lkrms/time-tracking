<?php declare(strict_types=1);

namespace Lkrms\Time\Entity;

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
}
