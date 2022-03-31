<?php

declare(strict_types=1);

namespace Lkrms\Clockify\Entity;

/**
 *
 * @package Lkrms\Clockify
 */
class TimeEntry extends \Lkrms\Sync\SyncEntity
{
    /**
     * @var string
     */
    public $Id;

    /**
     * @var string
     */
    public $Description;

    /**
     * @var array
     */
    public $Tags;

    /**
     * @var User
     */
    public $User;

    /**
     * @var bool
     */
    public $Billable;

    /**
     * @var array
     */
    public $Task;

    /**
     * @var array
     */
    public $Project;

    /**
     * @var array
     */
    public $TimeInterval;

    /**
     * @var Workspace
     */
    public $Workspace;

    /**
     * @var array
     */
    public $HourlyRate;

    /**
     * @var array
     */
    public $CustomFieldValues;

    /**
     * @var bool
     */
    public $IsLocked;

}

