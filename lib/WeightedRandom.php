<?php
declare(strict_types=1);

namespace mschandr\WeightedRandom;

use Random\Engine\Mt19937;
use Random\RandomException;
use Random\Randomizer;

final class WeightedRandom
{

    /**
     * One-shot pick (keeps current behavior, but as a clear name)
     *
     * @param array $weights
     * @return string|int
     * @throws RandomException
     */
    public static function pickKey(array $weights): string|int
    {
        $w = self::sanitize($weights);
        if ($w === [] || self::sum($w) <= 0.0) {
            return array_key_first($weights) ?? '';
        }

        // Non-seeded: use PHP’s RNG once
        $total = (int)ceil(self::sum($w));
        $r = random_int(1, $total); // swap to mt_rand() if you prefer speed over crypto
        foreach ($w as $key => $weight) {
            $r -= (int)ceil(max(0, (float)$weight));
            if ($r <= 0) return $key;
        }
        return array_key_first($w);
    }

    /**
     * Deterministic pick (per-call seed + optional namespace)
     *
     * @param array $weights
     * @param int $seed
     * @param string $ns
     * @return string|int
     */
    public static function pickKeySeeded(array $weights, int $seed, string $ns = ''): string|int
    {
        $w = self::sanitize($weights);
        if ($w === [] || self::sum($w) <= 0.0) {
            return array_key_first($weights) ?? '';
        }

        // PHP 8.2+ deterministic RNG (no global state touched)
        $engine = new Mt19937(self::salt($seed, $ns));
        $rng    = new Randomizer($engine);

        $total = (int)ceil(self::sum($w));
        $r = $rng->getInt(1, $total);
        foreach ($w as $key => $weight) {
            $r -= (int)ceil(max(0, (float)$weight));
            if ($r <= 0) return $key;
        }
        return array_key_first($w);
    }

    /* --- helpers --- */

    /**
     * @param array<string|int,int|float> $weights
     */
    private static function sanitize(array $weights): array
    {
        $out = [];
        foreach ($weights as $key => $value) {
            $num = is_numeric($value) ? (float)$value : 0.0;
            if (!is_string($key) && !is_int($key)) {
                continue;
            }  // enforce key type
            $out[$key] = $num > 0 ? $num : 0.0;
        }
        return $out;
    }

    /**
     * @param array $w
     * @return float
     */
    private static function sum(array $weights): float
    {
        $sum = 0.0;
        foreach ($weights as $values) {
            $sum += (float)$values;
        }
        return $sum;
    }

    /**
     * @param int $seed
     * @param string $ns
     * @return int
     */
    private static function salt(int $seed, string $namespace): int
    {
        return $namespace === '' ? $seed : ($seed ^ crc32($namespace));
    }
}
