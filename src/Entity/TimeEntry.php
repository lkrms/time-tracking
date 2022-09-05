<?php

declare(strict_types=1);

namespace Lkrms\Time\Entity;

use DateTime;
use Lkrms\Facade\Convert;

class TimeEntry extends \Lkrms\Sync\SyncEntity
{
    public const DATE        = 1 << 0;
    public const TIME        = 1 << 1;
    public const PROJECT     = 1 << 2;
    public const TASK        = 1 << 3;
    public const USER        = 1 << 4;
    public const DESCRIPTION = 1 << 5;
    public const ALL         = (1 << 6) - 1;

    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var string|null
     */
    public $Description;

    /**
     * @var User|null
     */
    public $User;

    /**
     * @var bool|null
     */
    public $Billable;

    /**
     * @var Task|null
     */
    public $Task;

    /**
     * @var Project|null
     */
    public $Project;

    /**
     * @var DateTime|null
     */
    public $Start;

    /**
     * @var DateTime|null
     */
    public $End;

    /**
     * @var int|null
     */
    public $Seconds;

    /**
     * @var Workspace|null
     */
    public $Workspace;

    /**
     * @var float|null
     */
    public $BillableRate;

    /**
     * @var bool|null
     */
    public $IsInvoiced;

    /**
     * @var bool|null
     */
    public $IsLocked;

    /**
     * @var TimeEntry[]|null
     */
    private $Merged;

    /**
     * @return TimeEntry[]|null
     */
    final public function getMerged(): ?array
    {
        return $this->Merged;
    }

    public function getBillableAmount(): float
    {
        return $this->Billable
            ? round(($this->BillableRate ?: 0) * ($this->Seconds ?: 0) / 3600, 2, PHP_ROUND_HALF_UP)
            : 0;
    }

    public function getBillableHours(): float
    {
        return $this->Billable
            ? round(($this->Seconds ?: 0) / 3600, 2, PHP_ROUND_HALF_UP)
            : 0;
    }

    /**
     * @param string $string
     * @param string|string[] $element
     * @return string
     */
    private function enclose(string $string, $element): string
    {
        if (is_array($element))
        {
            return $element[0] . $string . $element[1];
        }
        else
        {
            return trim($element . $string . $element);
        }
    }

    /**
     * Get a report-friendly summary of the time entry
     *
     * The format of the summary is as follows. Suppressed values and empty
     * groups are collapsed automatically.
     *
     * ```
     * [<start_date> <start_time> - <end_time>] <project_name> - <task_name> (<user_name>)
     * <description>
     * ```
     *
     * @param int $show A bitmask of `TimeEntry::*` values.
     * @param string $dateFormat
     * @param string $timeFormat
     * @return string
     */
    public function getSummary(
        int $show          = TimeEntry::ALL,
        string $dateFormat = "d/m/Y",
        string $timeFormat = "g.ia",
        bool $markdown     = false
    ): string
    {
        $escape = $markdown ? "\\" : "";
        $format = [
            "project" => $markdown ? "**" : "",
            "task"    => $markdown ? "" : "",
            "user"    => $markdown ? "*" : "",
            "line1"   => $markdown ? ["### ", ""] : "",
        ];

        $parts1 = [];
        $parts2 = [];

        // "[<date> <start> - <end>]" => $parts2
        if (($show & self::DATE) && $this->Start)
        {
            $parts1[] = $this->Start->format($dateFormat);
        }
        if (($show & self::TIME) && $this->Start && $this->End)
        {
            $parts1[] = "{$this->Start->format($timeFormat)} - {$this->End->format($timeFormat)}";
        }
        if ($parts1)
        {
            $parts2[] = "{$escape}[" . implode(" ", $parts1) . "{$escape}]";
            $parts1   = [];
        }

        // "<project> - <task>" => $parts2
        if (($show & self::PROJECT) && ($this->Project->Name ?? null))
        {
            $parts1[] = $this->enclose($this->Project->Name, $format["project"]);
        }
        if (($show & self::TASK) && ($this->Task->Name ?? null))
        {
            $parts1[] = $this->enclose($this->Task->Name, $format["task"]);
        }
        if ($parts1)
        {
            $parts2[] = implode(" - ", $parts1);
            $parts1   = [];
        }

        // "(<user>)" => $parts2
        if (($show & self::USER) && ($this->User->Name ?? null))
        {
            $parts2[] = $this->enclose("({$this->User->Name})", $format["user"]);
        }

        // "[<date> <start> - <end>] <project> - <task> (<user>)" => $parts1
        if ($parts2)
        {
            $parts1[] = $this->enclose(implode(" ", $parts2), $format["line1"]);
        }

        if (($show & self::DESCRIPTION) && ($this->Description))
        {
            $parts1[] = $this->Description;
        }

        return implode($markdown ? "\n\n" : "\n", $parts1);
    }

    final public function mergeWith(TimeEntry $entry, string $delimiter = "\n\n"): TimeEntry
    {
        if (is_null($this->Merged))
        {
            $merged         = clone $this;
            $merged->Merged = [$this];
        }
        else
        {
            $merged = $this;
        }

        // Clear properties with different values
        foreach (["Project", "Task", "User", "Workspace", "Billable", "BillableRate"] as $prop)
        {
            if (!is_null($merged->$prop) && !$merged->propertyHasSameValueAs($prop, $entry))
            {
                $merged->$prop = null;
            }
        }

        // Combine properties that can be aggregated
        $merged->Description = Convert::sparseToString($delimiter, [$merged->Description, $entry->Description]);
        $merged->Start       = $merged->Start && $entry->Start ? min($merged->Start, $entry->Start) : null;
        $merged->End         = null;
        $merged->Seconds     = !is_null($merged->Seconds) && !is_null($entry->Seconds) ? $merged->Seconds + $entry->Seconds : null;
        $merged->Merged[]    = $entry;

        return $merged;
    }
}
