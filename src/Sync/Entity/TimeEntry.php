<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Entity;

use Salient\Contract\Core\Cardinality;
use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\AbstractSyncEntity;
use DateTimeInterface;

/**
 * Represents the state of a TimeEntry entity in a backend
 *
 * @generated
 */
class TimeEntry extends AbstractSyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var string|null
     */
    public $Description;

    /**
     * @var User|DeferredEntity<User>|null
     */
    public $User;

    /**
     * @var bool|null
     */
    public $Billable;

    /**
     * @var Task|DeferredEntity<Task>|null
     */
    public $Task;

    /**
     * @var Project|DeferredEntity<Project>|null
     */
    public $Project;

    /**
     * @var DateTimeInterface|null
     */
    public $Start;

    /**
     * @var DateTimeInterface|null
     */
    public $End;

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
     * @internal
     */
    public static function getRelationships(): array
    {
        return [
            'User' => [Cardinality::ONE_TO_ONE => User::class],
            'Task' => [Cardinality::ONE_TO_ONE => Task::class],
            'Project' => [Cardinality::ONE_TO_ONE => Project::class],
        ];
    }

    /**
     * @internal
     */
    public static function getDateProperties(): array
    {
        return [
            'Start',
            'End',
        ];
    }
}
