<?php
declare(strict_types=1);

namespace mschandr\WeightedRandom\Value;

/**
 * WeightedGroup
 *
 * Represents a group of values that share a single weight.
 * When the group is selected by a generator, one member is chosen uniformly at random.
 */
final class WeightedGroup
{
    /** @var array */
    private array $members;

    public function __construct(array $members)
    {
        if (empty($members)) {
            throw new \InvalidArgumentException('Group must contain at least one member.');
        }
        $this->members = array_values($members);
    }

    /**
     * Pick a random member uniformly.
     */
    public function pickOne(): mixed
    {
        $index = random_int(0, count($this->members) - 1);
        return $this->members[$index];
    }

    /**
     * Get all members of the group.
     *
     * @return array<mixed>
     */
    public function getMembers(): array
    {
        return $this->members;
    }

    /**
     * Count how many members are in the group.
     */
    public function countMembers(): int
    {
        return count($this->members);
    }
}
