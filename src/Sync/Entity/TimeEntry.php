<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Lkrms\Support\Catalog\RelationshipType;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Convert;
use DateTimeInterface;

class TimeEntry extends SyncEntity
{
    public const DATE = 1;
    public const TIME = 2;
    public const PROJECT = 4;
    public const TASK = 8;
    public const USER = 16;
    public const DESCRIPTION = 32;
    public const ALL = TimeEntry::DATE | TimeEntry::TIME | TimeEntry::PROJECT | TimeEntry::TASK | TimeEntry::USER | TimeEntry::DESCRIPTION;

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

    public ?DateTimeInterface $Start = null;

    public ?DateTimeInterface $End = null;

    /**
     * @var int|null
     */
    public $Seconds;

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

    public static function getRelationships(): array
    {
        return [
            'User' => [RelationshipType::ONE_TO_ONE => User::class],
            'Task' => [RelationshipType::ONE_TO_ONE => Task::class],
            'Project' => [RelationshipType::ONE_TO_ONE => Project::class],
        ];
    }

    public function getBillableAmount(): float
    {
        return $this->Billable
            ? round(($this->BillableRate ?? 0) * ($this->Seconds ?? 0) / 3600, 2, PHP_ROUND_HALF_UP)
            : 0;
    }

    public function getBillableHours(): float
    {
        return $this->Billable
            ? round(($this->Seconds ?? 0) / 3600, 2, PHP_ROUND_HALF_UP)
            : 0;
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
     * @param int-mask-of<TimeEntry::*> $show
     */
    public function getSummary(
        int $show = TimeEntry::ALL,
        string $dateFormat = 'd/m/Y',
        string $timeFormat = 'g.ia',
        bool $markdown = false
    ): string {
        $esc = $markdown ? '\\' : '';
        $tag = [
            $markdown ? ['### ', ''] : '',
            self::PROJECT => $markdown ? '**' : '',
            self::TASK => $markdown ? '' : '',
            self::USER => $markdown ? '*' : '',
        ];

        $parts1 = [];
        $parts2 = [];

        // "[<date> <start> - <end>]" => $parts2
        if (($show & self::DATE) && $this->Start) {
            $parts1[] = $this->Start->format($dateFormat);
        }
        if (($show & self::TIME) && $this->Start && $this->End) {
            $parts1[] = "{$this->Start->format($timeFormat)} - {$this->End->format($timeFormat)}";
        }
        if ($parts1) {
            $parts2[] = "{$esc}[" . implode(' ', $parts1) . ']';
            $parts1 = [];
        }

        // "<project> - <task>" => $parts2
        if (($show & self::PROJECT) && ($this->Project->Name ?? null)) {
            $parts1[] = $this->enclose($this->Project->Name, $tag[self::PROJECT]);
        }
        if (($show & self::TASK) && ($this->Task->Name ?? null)) {
            $parts1[] = $this->enclose($this->Task->Name, $tag[self::TASK]);
        }
        if ($parts1) {
            $parts2[] = implode(' - ', $parts1);
            $parts1 = [];
        }

        // "(<user>)" => $parts2
        if (($show & self::USER) && ($this->User->Name ?? null)) {
            $parts2[] = $this->enclose("({$this->User->Name})", $tag[self::USER]);
        }

        // "[<date> <start> - <end>] <project> - <task> (<user>)" => $parts1
        if ($parts2) {
            $parts1[] = $this->enclose(implode(' ', $parts2), $tag[0]);
        }

        if (($show & self::DESCRIPTION) && ($this->Description)) {
            $parts1[] = $this->Description;
        }

        return implode($markdown ? "\n\n" : "\n", $parts1);
    }

    public function description(string $separator = "\n", ?string $marker = null): string
    {
        return Convert::linesToLists(
            $this->Description,
            $separator,
            $marker,
            '/^(?<indent>\h*[-*] )/',
            true
        );
    }

    /**
     * @return TimeEntry[]|null
     */
    final public function getMerged(): ?array
    {
        return $this->Merged;
    }

    final public function mergeWith(TimeEntry $entry, string $delimiter = "\n\n"): TimeEntry
    {
        if ($this->Merged === null) {
            $merged = clone $this;
            $merged->Merged = [$this];
        } else {
            $merged = $this;
        }

        // Clear properties with conflicting values
        foreach (['Project', 'Task', 'User', 'Billable', 'BillableRate'] as $prop) {
            if ($merged->$prop !== null && $merged->$prop !== $entry->$prop) {
                $merged->$prop = null;
            }
        }

        // Combine properties that can be joined or aggregated
        $merged->Description = Arr::implode($delimiter, [$merged->Description, $entry->Description]);
        $merged->Start = $merged->Start && $entry->Start ? min($merged->Start, $entry->Start) : null;
        $merged->End = null;
        $merged->Seconds = $merged->Seconds !== null && $entry->Seconds !== null ? $merged->Seconds + $entry->Seconds : null;
        $merged->Merged[] = $entry;

        return $merged;
    }

    /**
     * Enclose a string between delimiters
     *
     * @param string|array{string,string} $tag
     */
    private function enclose(string $string, $tag): string
    {
        if (is_array($tag)) {
            return $tag[0] . $string . $tag[1];
        }
        return trim($tag . $string . $tag);
    }
}
