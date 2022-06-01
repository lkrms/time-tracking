<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

class Project extends \Lkrms\Sync\SyncEntity
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
     * @var array
     */
    public $HourlyRate;

    /**
     * @var Client
     */
    public $Client;

    /**
     * @var Workspace
     */
    public $Workspace;

    /**
     * @var bool
     */
    public $Billable;

    /**
     * @var string
     */
    public $Color;

    /**
     * @var bool
     */
    public $Archived;

    /**
     * @var Task[]
     */
    public $Tasks;

    /**
     * @var string
     */
    public $Note;

}
