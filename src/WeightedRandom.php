<?php

namespace mschandr\WeightedRandom;

use mschandr\WeightedRandom\Contract\WeightedRandomInterface;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;
use mschandr\WeightedRandom\Generator\WeightedBagRandomGenerator;

final class WeightedRandom
{
    public static function createFloat(): WeightedRandomInterface
    {
        return new WeightedRandomGenerator();
    }

    public static function createBag(): WeightedRandomInterface
    {
        return new WeightedBagRandomGenerator(new WeightedRandomGenerator());
    }
}
