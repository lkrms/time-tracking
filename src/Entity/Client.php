<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

class Client extends \Lkrms\Sync\SyncEntity
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
     * @var Workspace
     */
    public $Workspace;

    /**
     * @var bool
     */
    public $Archived;

    /**
     * @var string
     */
    public $Note;

}
