# Roadmap

Features are listed in priority order. Each item is self-contained and intended to ship as its own pull request.

---

## 1. Value Removal

**`removeValue(mixed $value)`**

Remove a previously registered value from the generator at runtime. Currently the only way to change the pool is to build a new generator from scratch. This method allows values to be pruned dynamically — for example removing an item once it has been claimed, dropping a value whose weight has decayed to zero, or narrowing the pool based on game state. Several later roadmap items (cooldown, exclusion windows) are built on top of this.

---

## 2. Seeded Generation

**`setSeed(int $seed)`**

Inject a seed into the generator's random number source so that draws are fully deterministic and reproducible. Given the same seed and the same registered weights, the generator will always produce the same sequence of results. Useful for unit testing without mocking, replaying a sequence of draws for debugging, and previewing loot tables or probability outcomes before shipping them.

---

## 3. Seed Parameter on `/v1/generate`

**`seed` field in the HTTP API request body**

Exposes seeded generation (item 2) through the HTTP API. When a `seed` integer is included in a `/v1/generate` request the response is fully deterministic — the same seed, values, and count will always return the same results. Callers can use this to preview weighted distributions, write integration tests against the API, or replay a specific sequence for debugging.

---

## 4. Weighted Shuffle Endpoint

**`POST /v1/shuffle`**

A new HTTP API endpoint that accepts a weighted set and returns the full set reordered by probability. Unlike `/v1/generate`, which draws a subset with replacement, `/v1/shuffle` returns every registered value exactly once in a weighted random order — higher-weight values tend to appear earlier. Useful for ranked recommendations, ordered playlists, or priority queues where every item must appear but order should reflect weight.

---

## 5. Conditional Generation and Cooldowns

**`generateUntil(callable $predicate)` and `setCooldown(int $draws)`**

Two related features that ship together:

- **`generateUntil(callable $predicate)`** — draws values repeatedly until the predicate returns `true` for a result, then returns that value. Replaces the common userland pattern of looping `generate()` manually and checking the result each time.

- **`setCooldown(int $draws)`** — after a value is selected it is excluded from the pool for the next `$draws` calls. Softer than full uniqueness enforcement — values can repeat, but not in quick succession. Prevents streaks without requiring strict uniqueness. Built on top of `removeValue` (item 1) with an internal re-admission queue.

---

## 6. Weighted Shuffle

**`shuffle(array $items): array`**

Library-level weighted shuffle. Accepts an array of values and returns them reordered by weighted probability. Higher-weight values are biased toward the front of the result. Distinct from `generateMultipleWithoutDuplicates` — that method draws from registered values until a count is reached; this method reorders an arbitrary input array in a single pass. Backs the `/v1/shuffle` API endpoint (item 4).

---

## 7. Signed Stateful Snapshots

**`snapshot(): array`, `restore(array $snapshot): self`, and `state` field in the HTTP API**

Allows the full generator state — registered weights, selection counts, and any decay/boost adjustments — to be exported, stored, and restored later.

At the library level, `snapshot()` returns a plain serialisable array and `restore()` rebuilds the generator from one.

At the HTTP API level, a `state` object is optionally returned alongside the generate response and can be sent back in the next request to continue from where the previous call left off. To prevent clients from tampering with weights or selection counts, the state payload is signed with an HMAC using a `SECRET_KEY` environment variable. Requests that include a `state` with an invalid or missing signature are rejected with a `400` error. No sessions, no server-side storage — the client holds the state, the server remains stateless.
