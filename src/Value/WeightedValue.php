<?php
declare(strict_types=1);

namespace mschandr\WeightedRandom\Value;

/**
 * WeightedValue
 *
 * Immutable value-object representing a single value with its associated weight.
 */
final class WeightedValue
{
    /** @var mixed */
    private mixed $value;

    private float $weight;

    public function __construct(mixed $value, float $weight)
    {
        if ($weight <= 0) {
            throw new \InvalidArgumentException('Weight must be greater than zero.');
        }
        $this->value  = $value;
        $this->weight = $weight;
    }

    /**
     * Get the stored value.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Get the assigned weight.
     */
    public function getWeight(): float
    {
        return $this->weight;
    }

    /**
     * Export to array for debugging/serialization.
     *
     * @return array{value:mixed, weight:float}
     */
    public function getArrayCopy(): array
    {
        return [
            'value'  => $this->value,
            'weight' => $this->weight,
        ];
    }
}
