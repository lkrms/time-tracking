<?php

declare(strict_types=1);

namespace Lkrms\Clockify\Entity;

/**
 * Synchronises User objects with a backend
 *
 * @package Lkrms\Clockify
 */
interface UserProvider extends \Lkrms\Sync\Provider\ISyncProvider
{
    /**
     * @param int|string|null $id
     * @return User
     */
    public function getUser($id = null): User;

    /**
     * @return User[]
     */
    public function getUsers(): array;

}

