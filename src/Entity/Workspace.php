<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

class Workspace extends \Lkrms\Sync\Concept\SyncEntity
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
     * @var array|null
     */
    public $Memberships;

    /**
     * @var array|null
     */
    public $WorkspaceSettings;

    /**
     * @var string|null
     */
    public $ImageUrl;

    /**
     * @var string|null
     */
    public $FeatureSubscriptionType;

}
