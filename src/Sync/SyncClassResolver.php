<?php declare(strict_types=1);

namespace Lkrms\Time\Sync;

use Salient\Contract\Sync\SyncClassResolverInterface;

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
    public static function entityToProvider(string $entity): string
    {
        return preg_replace(
            ['/(?<=^Lkrms\\\\Time\\\\Sync\\\\)Entity(?=\\\\)/', '/(?<=\\\\)([^\\\\]+)$/'],
            ['Contract', 'Provides$1'],
            $entity
        );
    }

    public static function providerToEntity(string $provider): array
    {
        $entity = preg_replace(
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
