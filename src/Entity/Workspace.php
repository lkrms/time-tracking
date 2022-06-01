<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

class Workspace extends \Lkrms\Sync\SyncEntity
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
     * @var array
     */
    public $Memberships;

    /**
     * @var array
     */
    public $WorkspaceSettings;

    /**
     * @var string
     */
    public $ImageUrl;

    /**
     * @var string
     */
    public $FeatureSubscriptionType;

}
