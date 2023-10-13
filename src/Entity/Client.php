<?php declare(strict_types=1);

namespace Lkrms\Time\Entity;

use Lkrms\Sync\Concept\SyncEntity;

class Client extends SyncEntity
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
     * @var string|null
     */
    public $Description;

    /**
     * @var string|null
     */
    public $Email;

    /**
     * @var bool|null
     */
    public $Archived;
}
