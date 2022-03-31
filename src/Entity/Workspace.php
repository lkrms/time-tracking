<?php

declare(strict_types=1);

namespace Lkrms\Clockify\Entity;

/**
 *
 * @package Lkrms\Clockify
 */
class Workspace extends \Lkrms\Sync\SyncEntity
{
    /**
     * @var string
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

