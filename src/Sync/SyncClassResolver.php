<?php declare(strict_types=1);

namespace Lkrms\Time\Sync;

use Salient\Contract\Sync\SyncClassResolverInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Utility\Pcre;

/**
 * Maps entities in the Lkrms\Time\Sync namespace to and from their provider
 * interfaces
 *
 * An entity class in `Lkrms\Time\Sync\Entity` is mapped to a provider interface
 * at the same location in `Lkrms\Time\Sync\Contract` with the name
 * `Provides<Entity>`.
 */
class SyncClassResolver implements SyncClassResolverInterface
{
    /**
     * @inheritDoc
     */
    public static function entityToProvider(string $entity): string
    {
        /** @var class-string<SyncProviderInterface> */
        $provider = Pcre::replace(
            ['/(?<=^Lkrms\\\\Time\\\\Sync\\\\)Entity(?=\\\\)/', '/(?<=\\\\)([^\\\\]+)$/'],
            ['Contract', 'Provides$1'],
            $entity
        );

        return $provider;
    }

    /**
     * @inheritDoc
     */
    public static function providerToEntity(string $provider): array
    {
        /** @var class-string<SyncEntityInterface> */
        $entity = Pcre::replace(
            ['/(?<=^Lkrms\\\\Time\\\\Sync\\\\)Contract(?=\\\\)/', '/(?<=\\\\)Provides([^\\\\]+)$/'],
            ['Entity', '$1'],
            $provider,
            -1,
            $count,
        );

        if ($count === 2) {
            return [$entity];
        }

        return [];
    }
}
