<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[Test]
    public function it_treats_the_dinar_as_the_minor_unit_for_iqd(): void
    {
        $money = Money::fromDecimalString('1500', 'IQD', 0);

        $this->assertSame(1500, $money->minorUnits);
        $this->assertSame('1500', $money->toDecimalString());
    }

    #[Test]
    public function it_rejects_a_fractional_iqd_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no minor unit');

        Money::fromDecimalString('1500.50', 'IQD', 0);
    }

    #[Test]
    public function it_accepts_a_trailing_zero_fraction_for_iqd(): void
    {
        // "1500.00" from an import file is still a whole number of dinars.
        $this->assertSame(1500, Money::fromDecimalString('1500.00', 'IQD', 0)->minorUnits);
    }

    #[Test]
    public function it_handles_two_decimal_currencies(): void
    {
        $money = Money::fromDecimalString('1500.25', 'USD', 2);

        $this->assertSame(150025, $money->minorUnits);
        $this->assertSame('1500.25', $money->toDecimalString());
    }

    #[Test]
    public function it_strips_thousands_separators(): void
    {
        $this->assertSame(1500000, Money::fromDecimalString('1,500,000', 'IQD', 0)->minorUnits);
    }

    #[Test]
    public function it_rejects_nonsense(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimalString('not money');
    }

    #[Test]
    public function it_refuses_to_mix_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot combine IQD with USD');

        Money::of(100, 'IQD')->plus(Money::of(100, 'USD', 2));
    }

    /**
     * The property that matters: an allocation must always sum back to the original.
     * A cost split that loses a dinar unbalances the journal entry it belongs to.
     *
     * @param  list<int>  $weights
     */
    #[Test]
    #[DataProvider('allocationCases')]
    public function allocation_always_sums_to_the_original(int $amount, array $weights): void
    {
        $money = Money::of($amount, 'IQD', 0);
        $parts = $money->allocate($weights);

        $this->assertCount(count($weights), $parts);
        $this->assertSame(
            $amount,
            array_sum(array_map(fn (Money $m): int => $m->minorUnits, $parts)),
            'Allocated parts must sum back to the original amount.'
        );
    }

    /** @return array<string, array{int, list<int>}> */
    public static function allocationCases(): array
    {
        return [
            'even split' => [3_000_000, [50, 30, 20]],
            'indivisible by three' => [1_000_000, [1, 1, 1]],
            'single target' => [7, [1]],
            'heavy skew' => [100, [99, 1]],
            'zero amount' => [0, [1, 1, 1]],
            'negative amount' => [-1_000_000, [1, 1, 1]],
            'many targets' => [1_000_001, array_fill(0, 7, 1)],
            'one dinar, three ways' => [1, [1, 1, 1]],
        ];
    }

    #[Test]
    public function it_gives_the_residue_to_the_largest_weight(): void
    {
        // 100 split 50/30/20 is exact; 101 is not. The extra dinar follows the
        // biggest share so the result reads the way an accountant expects.
        $parts = Money::of(101, 'IQD', 0)->allocate([50, 30, 20]);

        $this->assertSame([51, 30, 20], array_map(fn (Money $m): int => $m->minorUnits, $parts));
    }

    #[Test]
    public function it_rejects_an_empty_allocation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of(100)->allocate([]);
    }

    #[Test]
    public function arithmetic_is_exact(): void
    {
        $a = Money::fromDecimalString('0.1', 'USD', 2);
        $b = Money::fromDecimalString('0.2', 'USD', 2);

        // The canonical float trap: 0.1 + 0.2 !== 0.3
        $this->assertSame('0.30', $a->plus($b)->toDecimalString());
        $this->assertTrue($a->plus($b)->equals(Money::fromDecimalString('0.30', 'USD', 2)));
    }

    #[Test]
    public function it_round_trips_large_ledger_amounts(): void
    {
        $money = Money::fromDecimalString('999999999999', 'IQD', 0);

        $this->assertSame('999999999999', $money->toDecimalString());
    }
}
