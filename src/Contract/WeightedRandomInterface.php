<?php

namespace mschandr\WeightedRandom\Contract;

interface WeightedRandomInterface
{
    public function registerValue(mixed $value, float $weight): self;
    public function registerValues(array $valueCollection): self;
    public function registerGroup(array $values, float $weight): self;

    public function generate(): mixed;
    public function generateMultiple(int $count): iterable;
    public function generateMultipleWithoutDuplicates(int $count): iterable;
}