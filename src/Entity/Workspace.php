<?php

declare(strict_types=1);

namespace Lkrms\Clockify\Entity;

use Lkrms\Sync\AbstractEntity;

class Workspace extends AbstractEntity
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
    public $WorkspaceSettings;
}

