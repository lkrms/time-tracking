<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

/**
 * Synchronises Project objects with a backend
 *
 */
interface ProjectProvider extends \Lkrms\Sync\Provider\ISyncProvider
{
    /**
     * @param int|string $id
     * @return Project
     */
    public function getProject($id): Project;

    /**
     * @return Project[]
     */
    public function getProjects(): array;

}
