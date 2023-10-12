<?php declare(strict_types=1);

namespace Lkrms\Time\Entity;

use Lkrms\Support\Catalog\RelationshipType;
use Lkrms\Sync\Concept\SyncEntity;

class Tenant extends SyncEntity
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
            'Users' => [RelationshipType::ONE_TO_MANY => User::class],
        ];
    }
}
