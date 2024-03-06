<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Salient\Sync\AbstractSyncEntity;

/**
 * Represents the state of a Client entity in a backend
 *
 * @generated
 */
class Client extends AbstractSyncEntity
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
    public $FirstName;

    /**
     * @var string|null
     */
    public $LastName;

    /**
     * @var string|null
     */
    public $Email;

    /**
     * @var bool|null
     */
    public $Archived;
}
