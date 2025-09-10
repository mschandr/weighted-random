<?php
declare(strict_types=1);

namespace mschandr\WeightedRandom;

use Assert\InvalidArgumentException;
use JsonSerializable;

final class WeightedValue implements JsonSerializable
{
    /**
     * @var mixed
     */
    private mixed $value;

    /**
     * @var float
     */
    private float $weight;

    /**
     * @param mixed $value
     * @param int|float $weight
     * @throws InvalidArgumentException
     */
    public function __construct(mixed $value, int|float $weight)
    {
        if ($weight <= 0) {
            throw new \InvalidArgumentException('Weight must be greater than zero.');
        }
        $this->value  = $value;
        $this->weight = (float)$weight;
    }

    /**
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @return float
     */
    public function getWeight(): float
    {
        return $this->weight;
    }

    /**
     * @return array
     */
    public function getArrayCopy(): array
    {
        return [
            'value'  => $this->exportValue($this->value),
            'weight' => $this->weight,
        ];
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->getArrayCopy();
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function exportValue(mixed $value): mixed
    {
        // Scalars and null → safe to return directly
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        // Arrays → recurse
        if (is_array($value)) {
            return array_map([$this, 'exportValue'], $value);
        }

        // Objects → handle special cases
        if ($value instanceof JsonSerializable) {
            return $value->jsonSerialize();
        }

        if (method_exists($value, '__toString')) {
            return (string)$value;
        }

        // Generic object → represent class + object hash
        return sprintf('[object:%s#%s]', get_class($value), spl_object_id($value));
    }
}