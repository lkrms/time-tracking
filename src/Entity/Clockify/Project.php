<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity\Clockify;

use Lkrms\Time\Entity\Client;

class Project extends \Lkrms\Time\Entity\Project
{
    protected function _setClient($value)
    {
        $this->Client = Client::provide($value, $this->provider(), $this->requireContext()->push($this));
    }

}
