<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

class TimeEntry extends \Lkrms\Sync\SyncEntity
{
    /**
     * @var int|string
     */
    public $Id;

    /**
     * @var string
     */
    public $Description;

    /**
     * @var User
     */
    public $User;

    /**
     * @var bool
     */
    public $Billable;

    /**
     * @var Task
     */
    public $Task;

    /**
     * @var Project
     */
    public $Project;

    /**
     * @var DateTime
     */
    public $Start;

    /**
     * @var DateTime
     */
    public $End;

    /**
     * @var int
     */
    public $Seconds;

    /**
     * @var Workspace
     */
    public $Workspace;

    /**
     * @var float
     */
    public $BillableRate;

    /**
     * @var bool
     */
    public $IsLocked;

    public function getBillableAmount(): float
    {
        return $this->Billable
            ? round(($this->BillableRate ?: 0) * ($this->Seconds ?: 0) / 3600, 2, PHP_ROUND_HALF_UP)
            : 0;
    }

    public function getBillableHours(): float
    {
        return $this->Billable
            ? round(($this->Seconds ?: 0) / 3600, 2, PHP_ROUND_HALF_UP)
            : 0;
    }

}
