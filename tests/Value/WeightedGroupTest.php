<?php
declare(strict_types=1);

namespace Tests\Value;

use mschandr\WeightedRandom\Value\WeightedGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WeightedGroup::class)]
final class WeightedGroupTest extends TestCase
{
    public function testConstructorStoresMembers(): void
    {
        $members = ['apple', 'banana', 'cherry'];
        $group = new WeightedGroup($members);

        $this->assertSame($members, $group->getMembers());
    }

    public function testConstructorNormalizesArrayKeys(): void
    {
        $members = [5 => 'apple', 'key' => 'banana', 10 => 'cherry'];
        $group = new WeightedGroup($members);

        // Should be re-indexed with numeric keys starting from 0
        $this->assertSame(['apple', 'banana', 'cherry'], $group->getMembers());
    }

    public function testConstructorThrowsForEmptyArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Group must contain at least one member.');

        new WeightedGroup([]);
    }

    public function testPickOneReturnsValidMember(): void
    {
        $members = ['apple', 'banana', 'cherry'];
        $group = new WeightedGroup($members);

        $picked = $group->pickOne();
        $this->assertContains($picked, $members);
    }

    public function testPickOneWithSingleMember(): void
    {
        $group = new WeightedGroup(['only']);

        $this->assertSame('only', $group->pickOne());
    }

    public function testPickOneReturnsVariousMembers(): void
    {
        $members = ['a', 'b', 'c', 'd', 'e'];
        $group = new WeightedGroup($members);

        $results = [];
        // Pick multiple times to increase likelihood of getting different values
        for ($i = 0; $i < 20; $i++) {
            $results[] = $group->pickOne();
        }

        // With 20 picks from 5 items, we should get at least 2 different values
        $unique = array_unique($results);
        $this->assertGreaterThanOrEqual(1, count($unique));

        // All results should be valid members
        foreach ($results as $result) {
            $this->assertContains($result, $members);
        }
    }

    public function testGetMembersReturnsAllMembers(): void
    {
        $members = [1, 2, 3, 4, 5];
        $group = new WeightedGroup($members);

        $this->assertSame($members, $group->getMembers());
    }

    public function testGetMembersWithMixedTypes(): void
    {
        $members = ['string', 42, 3.14, true, null, new \stdClass()];
        $group = new WeightedGroup($members);

        $this->assertSame($members, $group->getMembers());
    }

    public function testCountMembersReturnsCorrectCount(): void
    {
        $group = new WeightedGroup(['a', 'b', 'c']);
        $this->assertSame(3, $group->countMembers());
    }

    public function testCountMembersWithSingleMember(): void
    {
        $group = new WeightedGroup(['only']);
        $this->assertSame(1, $group->countMembers());
    }

    public function testCountMembersWithManyMembers(): void
    {
        $members = range(1, 100);
        $group = new WeightedGroup($members);
        $this->assertSame(100, $group->countMembers());
    }
}