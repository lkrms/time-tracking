<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Salient\Contract\Core\Cardinality;
use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\AbstractSyncEntity;

/**
 * Represents the state of a User entity in a backend
 *
 * @generated
 */
class User extends AbstractSyncEntity
{
    /** @var int|string|null */
    public $Id;
    /** @var string|null */
    public $Name;
    /** @var string|null */
    public $Email;
    /** @var string|null */
    public $PhotoUrl;
    /** @var bool|null */
    public $IsActive;
    /** @var Tenant|DeferredEntity<Tenant>|null */
    public $ActiveTenant;
    /** @var array<string,mixed>|null */
    public $Settings;

    /**
     * @internal
     */
    public static function getRelationships(): array
    {
        return [
            'ActiveTenant' => [Cardinality::ONE_TO_ONE => Tenant::class],
        ];
    }
}
