<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

class Task extends \Lkrms\Sync\SyncEntity
{
    /**
     * @var int|string
     */
    public $Id;

    /**
     * @var string
     */
    public $Name;

    /**
     * @var Project
     */
    public $Project;

}
