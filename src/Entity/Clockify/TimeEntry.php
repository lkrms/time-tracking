<?php declare(strict_types=1);

namespace Lkrms\Time\Entity\Clockify;

use Lkrms\Time\Entity\Project;
use Lkrms\Time\Entity\Task;
use Lkrms\Time\Entity\User;
use Lkrms\Utility\Convert;
use DateTimeImmutable;

class TimeEntry extends \Lkrms\Time\Entity\TimeEntry
{
    protected function _setUser($value)
    {
        if ($value) {
            $this->User = User::provide($value, $this->provider(), $this->requireContext()->push($this));
        }
    }

    protected function _setProject($value)
    {
        if ($value) {
            $this->Project = Project::provide($value, $this->provider(), $this->requireContext()->push($this));
        }
    }

    protected function _setTask($value)
    {
        if ($value) {
            $this->Task = Task::provide($value, $this->provider(), $this->requireContext()->push($this));
        }
    }

    protected function _setTimeInterval($value)
    {
        if (isset($value['start'])) {
            $this->Start = new DateTimeImmutable($value['start']);
        }

        if (isset($value['end'])) {
            $this->End = new DateTimeImmutable($value['end']);
        }

        if (is_int($value['duration'] ?? null)) {
            $this->Seconds = $value['duration'];
        } elseif (isset($value['duration'])) {
            $this->Seconds = Convert::intervalToSeconds($value['duration']);
        }
    }

    protected function _setRate($value)
    {
        if (!is_null($value)) {
            $this->BillableRate = $value / 100;
        }
    }

    protected function _setHourlyRate($value)
    {
        $this->_setRate($value);
    }

    protected function _setInvoicingInfo($value)
    {
        $this->IsInvoiced = !empty($value);
    }
}
