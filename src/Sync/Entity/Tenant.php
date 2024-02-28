<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Salient\Catalog\Core\Cardinality;
use Salient\Sync\AbstractSyncEntity;

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
     * @var User[]|null
     */
    public $Users;

    /**
     * @var array<string,mixed>|null
     */
    public $Settings;

    public static function getRelationships(): array
    {
        return [
            'Users' => [Cardinality::ONE_TO_MANY => User::class],
        ];
    }
}
