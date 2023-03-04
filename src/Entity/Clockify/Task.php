<?php declare(strict_types=1);

namespace Lkrms\Time\Entity\Clockify;

use Lkrms\Time\Entity\Project;

class Task extends \Lkrms\Time\Entity\Task
{
    protected function _setProject($value)
    {
        $this->Project = Project::provide($value, $this->provider(), $this->requireContext()->push($this));
    }
}
