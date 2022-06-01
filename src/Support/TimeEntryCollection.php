<?php

namespace Lkrms\Time\Support;

use ArrayAccess;
use Countable;
use Iterator;
use Lkrms\Time\Entity\TimeEntry;
use UnexpectedValueException;

/**
 * @property-read float $BillableAmount
 * @property-read float $BillableHours
 * @todo Add this to lkrms/util as a generic collection
 */
class TimeEntryCollection implements Iterator, ArrayAccess, Countable
{
    /**
     * @var TimeEntry[]
     */
    private $Items = [];

    /**
     * @var int
     */
    private $Pointer = 0;

    public function current(): mixed
    {
        return $this->Items[$this->Pointer] ?? false;
    }

    public function key(): mixed
    {
        return array_key_exists($this->Pointer, $this->Items)
            ? $this->Pointer
            : null;
    }

    public function next(): void
    {
        $this->Pointer++;
    }

    public function rewind(): void
    {
        $this->Pointer = 0;
    }

    public function valid(): bool
    {
        return array_key_exists($this->Pointer, $this->Items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->Items);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->Items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!($value instanceof TimeEntry))
        {
            throw new UnexpectedValueException("Expected an instance of: " . TimeEntry::class);
        }

        if (is_null($offset))
        {
            $this->Items[] = $value;
        }
        else
        {
            $this->Items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->Items[$offset]);
    }

    public function count(): int
    {
        return count($this->Items);
    }

    public function __get(string $name)
    {
        switch ($name)
        {
            case "BillableAmount":
                return array_reduce(
                    $this->Items,
                    fn($prev, TimeEntry $item) => $prev + $item->getBillableAmount(),
                    0
                );

            case "BillableHours":
                return array_reduce(
                    $this->Items,
                    fn($prev, TimeEntry $item) => $prev + $item->getBillableHours(),
                    0
                );

            default:
                throw new UnexpectedValueException("Undefined property: $name");
        }
    }
}
