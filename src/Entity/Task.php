<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

class Task extends \Lkrms\Sync\SyncEntity
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
