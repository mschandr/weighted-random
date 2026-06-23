# HTTP API & Docker

A thin, **stateless** JSON HTTP layer over the `mschandr/weighted-random` library.
Every request fully describes the set of weighted values, so the service keeps no
state between calls and scales horizontally with no coordination.

The HTTP layer lives in [`app/`](app/) (namespace `mschandr\WeightedRandom\Api`) and is
served by the front controller in [`public/index.php`](public/index.php). The library
in `src/` is untouched and remains usable as a plain Composer package.

---

## Running

### With Docker

```bash
docker compose up --build
# or
docker build -t weighted-random-api .
docker run --rm -p 8080:8080 weighted-random-api
```

The container runs as an unprivileged user, exposes port `8080` (override with the
`PORT` env var), and defines a `HEALTHCHECK` against `/health`.

### Without Docker

```bash
composer install
php -S 0.0.0.0:8080 -t public public/index.php
```

---

## Endpoints

| Method | Path                 | Description                                  |
|--------|----------------------|----------------------------------------------|
| GET    | `/health`            | Liveness/readiness probe.                    |
| POST   | `/v1/generate`       | Draw one or more weighted-random samples.    |
| POST   | `/v1/distribution`   | Inspect probabilities and statistics.        |
| GET    | `/v1/openapi.json`   | OpenAPI 3.1 description of the API.           |

All responses are JSON. Client errors return HTTP `422` (invalid input) or `404`
(unknown route) with an `{"error": "..."}` body.

---

## Describing the weighted set

Every generating endpoint accepts the same input shape. Supply values in any
combination of these three keys:

- **`values`** — an object map of `value => weight`. JSON object keys are strings
  (numeric strings are treated as numbers), so this is the most convenient form for
  string/number values.
- **`items`** — a list of `{"value": <any>, "weight": <number>}`. Use this when you
  need to preserve the exact JSON type of a value (e.g. real integers or booleans).
- **`groups`** — a list of `{"members": [...], "weight": <number>}`. The whole group
  shares one weight; when selected, one member is chosen uniformly.

`generator` selects the model:

- `"float"` (default) — classic probabilistic sampling.
- `"bag"` — fair bag/urn model that yields exact weight ratios over a full cycle.

---

## `POST /v1/generate`

Request:

```json
{
  "generator": "float",
  "values": { "common": 7, "uncommon": 2.5, "rare": 0.5 },
  "groups": [ { "members": ["bronze", "silver", "gold"], "weight": 5 } ],
  "count": 5,
  "unique": false
}
```

| Field       | Type    | Default | Notes                                            |
|-------------|---------|---------|--------------------------------------------------|
| `generator` | string  | `float` | `float` or `bag`.                                |
| `count`     | integer | `1`     | Number of samples (1 – 100000).                  |
| `unique`    | boolean | `false` | No duplicates (requires `count` ≤ value count).  |

Response:

```json
{
  "generator": "float",
  "unique": false,
  "count": 5,
  "results": ["common", "rare", "common", "uncommon", "common"]
}
```

The bag model guarantees exact ratios over a complete cycle:

```bash
curl -s localhost:8080/v1/generate \
  -H 'Content-Type: application/json' \
  -d '{"generator":"bag","values":{"rare":1,"common":9},"count":10}'
# => exactly 1 "rare" and 9 "common"
```

---

## `POST /v1/distribution`

Returns the normalized distribution plus statistics. Always uses the probabilistic
model (that is where the introspection helpers live). Numeric statistics are `null`
when no numeric values are registered.

Request:

```json
{ "values": { "1": 1, "2": 2, "3": 1 } }
```

Response:

```json
{
  "totalValues": 3,
  "distribution": [
    { "value": 1, "probability": 0.25 },
    { "value": 2, "probability": 0.5 },
    { "value": 3, "probability": 0.25 }
  ],
  "entropy": 1.5,
  "expectedValue": 2.0,
  "variance": 0.5,
  "standardDeviation": 0.7071067811865476
}
```

---

## Errors

```json
{ "error": "\"count\" must be greater than 0." }
```

| Status | Meaning                                                        |
|--------|----------------------------------------------------------------|
| `400`  | Malformed JSON body.                                           |
| `404`  | Unknown route.                                                 |
| `422`  | Validation error (bad generator, non-numeric weight, etc.).    |
| `500`  | Unexpected server error.                                       |
