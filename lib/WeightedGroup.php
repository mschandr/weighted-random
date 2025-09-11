<?php
declare(strict_types=1);

namespace mschandr\WeightedRandom;

final class WeightedGroup
{
    /** @var array<mixed> */
    private array $members;

    /**
     * @param array $members
     */
    public function __construct(array $members)
    {
        if (empty($members)) {
            throw new \InvalidArgumentException('Group cannot be empty');
        }
        $this->members = array_values($members);
    }

    /**
     * Pick one member uniformly from the group.
     *
     * @return mixed
     */
    public function pickOne(): mixed
    {
        $key = array_rand($this->members);
        return $this->members[$key];
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
}
