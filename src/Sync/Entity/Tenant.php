<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Salient\Contract\Core\Cardinality;
use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\Support\DeferredRelationship;
use Salient\Sync\AbstractSyncEntity;

/**
 * Represents the state of a Tenant entity in a backend
 *
 * @generated
 */
class Tenant extends AbstractSyncEntity
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
    public $LogoUrl;

    /**
     * @var array<User|DeferredEntity<User>>|DeferredRelationship<User>|null
     */
    public $Users;

    /**
     * @var array<string,mixed>|null
     */
    public $Settings;

    /**
     * @internal
     */
    public static function getRelationships(): array
    {
        return [
            'Users' => [Cardinality::ONE_TO_MANY => User::class],
        ];
    }
}
