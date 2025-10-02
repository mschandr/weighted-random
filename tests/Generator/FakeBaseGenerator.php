<?php

namespace Generator;

use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;

class FakeBaseGenerator extends WeightedRandomGenerator {
    public function getWeightedValues(): \Generator {
        yield "garbage"; // not a WeightedValue
    }
}