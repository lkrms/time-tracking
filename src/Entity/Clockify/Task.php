<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity\Clockify;

use Lkrms\Time\Entity\Project;

class Task extends \Lkrms\Time\Entity\Task
{
    protected function _setProject($value)
    {
        $this->Project = Project::fromProvider($this->provider(), $value);
    }

}
