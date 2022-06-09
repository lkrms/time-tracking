<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

/**
 * Synchronises User objects with a backend
 *
 */
interface UserProvider extends \Lkrms\Sync\Provider\ISyncProvider
{
    /**
     * @param int|string|null $id
     * @return User
     */
    public function getUser($id = null): User;

    /**
     * @return iterable<User>
     */
    public function getUsers(): iterable;

}
