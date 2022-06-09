<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

/**
 * Synchronises Task objects with a backend
 *
 */
interface TaskProvider extends \Lkrms\Sync\Provider\ISyncProvider
{
    /**
     * @param int|string $id
     * @param int|string|null $projectId
     * @return Task
     */
    public function getTask($id, $projectId): Task;

    /**
     * @param int|string|null $projectId
     * @return iterable<Task>
     */
    public function getTasks($projectId): iterable;

}
