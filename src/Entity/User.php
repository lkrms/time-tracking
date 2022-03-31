<?php

declare(strict_types=1);

namespace Lkrms\Clockify\Entity;

/**
 *
 * @package Lkrms\Clockify
 */
class User extends \Lkrms\Sync\SyncEntity
{
    /**
     * @var string
     */
    public $Id;

    /**
     * @var string
     */
    public $Email;

    /**
     * @var string
     */
    public $Name;

    /**
     * @var array
     */
    public $Memberships;

    /**
     * @var string
     */
    public $ProfilePicture;

    /**
     * @var string
     */
    public $ActiveWorkspace;

    /**
     * @var string
     */
    public $DefaultWorkspace;

    /**
     * @var array
     */
    public $Settings;

    /**
     * @var string
     */
    public $Status;

}

