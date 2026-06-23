<?php
declare(strict_types=1);

namespace mschandr\WeightedRandom\Api;

use mschandr\WeightedRandom\WeightedRandom;
use mschandr\WeightedRandom\Contract\WeightedRandomInterface;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;

/**
 * Application
 *
 * Tiny, framework-free HTTP layer that exposes the weighted-random library
 * over JSON. It is intentionally stateless: every request fully describes the
 * set of weighted values, so the API scales horizontally with no shared state.
 *
 * The {@see Application::handle()} method is pure (input -> output) which makes
 * the whole API trivially unit-testable without booting a web server.
 */
final class Application
{
    public const VERSION = '1.0.0';

    /** Hard cap on how many samples a single request may ask for. */
    private const MAX_COUNT = 100_000;

    /**
     * Dispatch a request and return a [statusCode, payload] tuple.
     *
     * @param string               $method HTTP verb (GET, POST, ...)
     * @param string               $path   Request path, without query string
     * @param array<string,mixed>  $body   Decoded JSON request body
     *
     * @return array{0:int,1:array<string,mixed>}
     */
    public function handle(string $method, string $path, array $body = []): array
    {
        $path = '/' . trim($path, '/');

        try {
            return match (true) {
                $method === 'GET'  && ($path === '/' || $path === '/health') => [200, $this->health()],
                $method === 'GET'  && $path === '/v1/openapi.json'           => [200, $this->openapi()],
                $method === 'POST' && $path === '/v1/generate'               => [200, $this->generate($body)],
                $method === 'POST' && $path === '/v1/distribution'           => [200, $this->distribution($body)],
                default                                                      => [404, $this->error('Not found: ' . $method . ' ' . $path)],
            };
        } catch (ApiException $e) {
            return [$e->getStatusCode(), $this->error($e->getMessage())];
        } catch (\InvalidArgumentException $e) {
            // Thrown by the library (and webmozart/assert) on bad input.
            return [422, $this->error($e->getMessage())];
        } catch (\RuntimeException $e) {
            // Generation-time failures (e.g. cannot satisfy unique sampling).
            return [422, $this->error($e->getMessage())];
        } catch (\Throwable $e) {
            return [500, $this->error('Internal error')];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function health(): array
    {
        return [
            'status'  => 'ok',
            'service' => 'weighted-random-api',
            'version' => self::VERSION,
        ];
    }
    /**
     * POST /v1/generate — draw one or more weighted-random samples.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function generate(array $body): array
    {
        $type      = $this->generatorType($body);
        $generator = $this->buildGenerator($body, $type);

        $count  = $this->intParam($body, 'count', 1);
        $unique = (bool)($body['unique'] ?? false);

        if ($count < 1) {
            throw new ApiException('"count" must be greater than 0.', 422);
        }
        if ($count > self::MAX_COUNT) {
            throw new ApiException('"count" exceeds the maximum of ' . self::MAX_COUNT . '.', 422);
        }

        $samples = $unique
            ? $generator->generateMultipleWithoutDuplicates($count)
            : $generator->generateMultiple($count);

        // The interface returns `iterable`: a Generator for the float model,
        // a plain array for the bag model. Normalise both to a list.
        $results = is_array($samples)
            ? array_values($samples)
            : iterator_to_array($samples, false);

        return [
            'generator' => $type,
            'unique'    => $unique,
            'count'     => count($results),
            'results'   => array_values($results),
        ];
    }

    /**
     * POST /v1/distribution — describe the distribution and its statistics.
     *
     * Always uses the probabilistic generator since the introspection helpers
     * (entropy, expected value, ...) live there.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function distribution(array $body): array
    {
        /** @var WeightedRandomGenerator $generator */
        $generator = $this->buildGenerator($body, 'float');

        $distribution = [];
        foreach ($generator->getDistribution() as $value => $probability) {
            $distribution[] = ['value' => $value, 'probability' => $probability];
        }

        return [
            'totalValues'       => count($distribution),
            'distribution'      => $distribution,
            'entropy'           => $generator->getEntropy(),
            'expectedValue'     => $generator->getExpectedValue(),
            'variance'          => $generator->getVariance(),
            'standardDeviation' => $generator->getStandardDeviation(),
        ];
    }

        /**
     * @param array<string,mixed> $body
     */
    private function generatorType(array $body): string
    {
        $type = $body['generator'] ?? 'float';
        if (!is_string($type) || !in_array($type, ['float', 'bag'], true)) {
            throw new ApiException('"generator" must be one of: "float", "bag".', 422);
        }
        return $type;
    }

    /**
     * Build and populate a generator from the request body.
     *
     * Two ways to supply values (combine freely):
     *   - "values": object map of value => weight (keys are JSON strings).
     *   - "items":  list of {"value": mixed, "weight": number} (preserves type).
     *   - "groups": list of {"members": [...], "weight": number}.
     *
     * @param array<string,mixed> $body
     */
    private function buildGenerator(array $body, string $type): WeightedRandomInterface
    {
        $generator = $type === 'bag'
            ? WeightedRandom::createBag()
            : WeightedRandom::createFloat();

        $registered = false;

        if (array_key_exists('values', $body)) {
            $values = $body['values'];
            if (!is_array($values)) {
                throw new ApiException('"values" must be an object of value => weight pairs.', 422);
            }
            if ($values !== []) {
                $generator->registerValues($values);
                $registered = true;
            }
        }

        if (array_key_exists('items', $body)) {
            $items = $body['items'];
            if (!is_array($items)) {
                throw new ApiException('"items" must be a list of {value, weight} objects.', 422);
            }
            foreach ($items as $i => $item) {
                if (!is_array($item) || !array_key_exists('value', $item) || !array_key_exists('weight', $item)) {
                    throw new ApiException("\"items[$i]\" must be an object with \"value\" and \"weight\".", 422);
                }
                $this->assertNumeric($item['weight'], "items[$i].weight");
                $generator->registerValue($item['value'], (float)$item['weight']);
                $registered = true;
            }
        }

        foreach (($body['groups'] ?? []) as $i => $group) {
            if (!is_array($group) || !isset($group['members']) || !array_key_exists('weight', $group)) {
                throw new ApiException("\"groups[$i]\" must be an object with \"members\" and \"weight\".", 422);
            }
            if (!is_array($group['members']) || $group['members'] === []) {
                throw new ApiException("\"groups[$i].members\" must be a non-empty list.", 422);
            }
            $this->assertNumeric($group['weight'], "groups[$i].weight");
            $generator->registerGroup(array_values($group['members']), (float)$group['weight']);
            $registered = true;
        }

        if (!$registered) {
            throw new ApiException('No values registered. Provide "values", "items", and/or "groups".', 422);
        }

        return $generator;
    }

    
    private function assertNumeric(mixed $value, string $field): void
    {
        if (!is_numeric($value)) {
            throw new ApiException("\"$field\" must be numeric.", 422);
        }
    }

    /**
     * @param array<string,mixed> $body
     */
    private function intParam(array $body, string $key, int $default): int
    {
        if (!array_key_exists($key, $body)) {
            return $default;
        }
        $value = $body[$key];
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value) && (float)$value === floor((float)$value)) {
            return (int)$value;
        }
        throw new ApiException("\"$key\" must be an integer.", 422);
    }

    /**
     * @return array<string,mixed>
     */
    private function error(string $message): array
    {
        return ['error' => $message];
    }

    /**
     * Minimal OpenAPI 3.1 description of the API.
     *
     * @return array<string,mixed>
     */
    private function openapi(): array
    {
        $weightedInput = [
            'type'       => 'object',
            'properties' => [
                'generator' => ['type' => 'string', 'enum' => ['float', 'bag'], 'default' => 'float'],
                'values'    => ['type' => 'object', 'additionalProperties' => ['type' => 'number'],
                                'description' => 'Map of value => weight.'],
                'items'     => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'required'   => ['value', 'weight'],
                        'properties' => [
                            'value'  => ['description' => 'Any JSON value.'],
                            'weight' => ['type' => 'number'],
                        ],
                    ],
                    'description' => 'List of {value, weight}; preserves the JSON type of each value.',
                ],
                'groups' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'required'   => ['members', 'weight'],
                        'properties' => [
                            'members' => ['type' => 'array', 'minItems' => 1],
                            'weight'  => ['type' => 'number'],
                        ],
                    ],
                ],
            ],
        ];

        return [
            'openapi' => '3.1.0',
            'info'    => [
                'title'       => 'Weighted Random API',
                'version'     => self::VERSION,
                'description' => 'Stateless HTTP API over the mschandr/weighted-random library.',
            ],
            'paths' => [
                '/health' => [
                    'get' => [
                        'summary'   => 'Health check',
                        'responses' => ['200' => ['description' => 'Service is healthy']],
                    ],
                ],
                '/v1/generate' => [
                    'post' => [
                        'summary'     => 'Generate weighted-random samples',
                        'requestBody' => [
                            'required' => true,
                            'content'  => ['application/json' => ['schema' => [
                                'allOf'      => [$weightedInput],
                                'properties' => [
                                    'count'  => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                                    'unique' => ['type' => 'boolean', 'default' => false],
                                ],
                            ]]],
                        ],
                        'responses' => ['200' => ['description' => 'Generated samples']],
                    ],
                ],
                '/v1/distribution' => [
                    'post' => [
                        'summary'     => 'Inspect the distribution and statistics',
                        'requestBody' => [
                            'required' => true,
                            'content'  => ['application/json' => ['schema' => $weightedInput]],
                        ],
                        'responses' => ['200' => ['description' => 'Distribution and statistics']],
                    ],
                ],
            ],
        ];
    }
}

