<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

class Project extends \Lkrms\Sync\SyncEntity
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
     * @var array|null
     */
    public $HourlyRate;

    /**
     * @var Client|null
     */
    public $Client;

    /**
     * @var Workspace|null
     */
    public $Workspace;

    /**
     * @var bool|null
     */
    public $Billable;

    /**
     * @var string|null
     */
    public $Color;

    /**
     * @var bool|null
     */
    public $Archived;

    /**
     * @var Task[]|null
     */
    public $Tasks;

    /**
     * @var string|null
     */
    public $Note;

}
