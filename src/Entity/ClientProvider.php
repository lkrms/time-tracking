<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

/**
 * Synchronises Client objects with a backend
 *
 */
interface ClientProvider extends \Lkrms\Sync\Contract\ISyncProvider
{
    /**
     * @param int|string $id
     * @return Client
     */
    public function getClient($id): Client;

    /**
     * @return iterable<Client>
     */
    public function getClients(): iterable;

}
