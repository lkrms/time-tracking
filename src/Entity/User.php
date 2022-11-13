<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

class User extends \Lkrms\Sync\Concept\SyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var string|null
     */
    public $Email;

    /**
     * @var string|null
     */
    public $Name;

    /**
     * @var array|null
     */
    public $Memberships;

    /**
     * @var string|null
     */
    public $ProfilePicture;

    /**
     * @var string|null
     */
    public $ActiveWorkspace;

    /**
     * @var string|null
     */
    public $DefaultWorkspace;

    /**
     * @var array|null
     */
    public $Settings;

    /**
     * @var string|null
     */
    public $Status;

}
