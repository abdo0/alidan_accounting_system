<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An exact monetary amount, held as an integer count of minor units.
 *
 * For IQD the minor unit IS the dinar: the currency has no sub-unit in practice, so
 * decimal_places = 0 and "1000 minor units" means 1,000 dinars. For a 2-decimal
 * currency the minor unit is the hundredth. Never hold money in a float -- the
 * ledger columns are numeric(20,4) and a float round-trip is how a trial balance
 * ends up out by a fraction nobody can find.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    private function __construct(
        public int $minorUnits,
        public string $currency,
        public int $decimalPlaces,
    ) {}

    public static function of(int $minorUnits, string $currency = 'IQD', int $decimalPlaces = 0): self
    {
        return new self($minorUnits, strtoupper($currency), $decimalPlaces);
    }

    public static function zero(string $currency = 'IQD', int $decimalPlaces = 0): self
    {
        return new self(0, strtoupper($currency), $decimalPlaces);
    }

    /**
     * Parse a decimal string such as "1500.00" or "1,500". Deliberately string-only:
     * accepting a float here would reintroduce exactly the imprecision this class exists
     * to prevent.
     */
    public static function fromDecimalString(string $amount, string $currency = 'IQD', int $decimalPlaces = 0): self
    {
        $clean = str_replace([',', ' ', "\u{00A0}"], '', trim($amount));

        if ($clean === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $clean)) {
            throw new InvalidArgumentException("Not a valid decimal amount: [{$amount}]");
        }

        $negative = str_starts_with($clean, '-');
        $clean = ltrim($clean, '-');

        [$whole, $fraction] = array_pad(explode('.', $clean, 2), 2, '');

        if ($decimalPlaces === 0) {
            if ($fraction !== '' && rtrim($fraction, '0') !== '') {
                throw new InvalidArgumentException(
                    "{$currency} has no minor unit; [{$amount}] carries a fractional part."
                );
            }
            $minor = (int) $whole;
        } else {
            $fraction = substr(str_pad($fraction, $decimalPlaces, '0'), 0, $decimalPlaces);
            $minor = (int) ($whole.$fraction);
        }

        return new self($negative ? -$minor : $minor, strtoupper($currency), $decimalPlaces);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency, $this->decimalPlaces);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency, $this->decimalPlaces);
    }

    public function negated(): self
    {
        return new self(-$this->minorUnits, $this->currency, $this->decimalPlaces);
    }

    public function absolute(): self
    {
        return new self(abs($this->minorUnits), $this->currency, $this->decimalPlaces);
    }

    /**
     * Apportion this amount across the given integer weights so that the parts sum
     * back to the whole exactly. The remainder goes to the largest weights first,
     * which is what stops a 3-way split of an odd amount from losing a dinar.
     *
     * @param  list<int|float>  $weights
     * @return list<self>
     */
    public function allocate(array $weights): array
    {
        if ($weights === []) {
            throw new InvalidArgumentException('Cannot allocate across an empty set of weights.');
        }

        $total = array_sum($weights);

        if ($total <= 0) {
            throw new InvalidArgumentException('Allocation weights must sum to a positive value.');
        }

        $shares = [];
        $allocated = 0;

        foreach ($weights as $weight) {
            $share = intdiv((int) ($this->minorUnits * $weight), (int) $total);
            $shares[] = $share;
            $allocated += $share;
        }

        $remainder = $this->minorUnits - $allocated;

        // Hand the residue out one unit at a time, heaviest weight first.
        $order = array_keys($weights);
        usort($order, fn (int $a, int $b): int => $weights[$b] <=> $weights[$a]);

        $i = 0;
        while ($remainder !== 0) {
            $index = $order[$i % count($order)];
            $step = $remainder > 0 ? 1 : -1;
            $shares[$index] += $step;
            $remainder -= $step;
            $i++;
        }

        return array_map(
            fn (int $share): self => new self($share, $this->currency, $this->decimalPlaces),
            $shares
        );
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits
            && $this->currency === $other->currency;
    }

    /** The representation written to a numeric(20,4) column. */
    public function toDecimalString(): string
    {
        $negative = $this->minorUnits < 0;
        $digits = (string) abs($this->minorUnits);

        if ($this->decimalPlaces === 0) {
            $value = $digits;
        } else {
            $digits = str_pad($digits, $this->decimalPlaces + 1, '0', STR_PAD_LEFT);
            $value = substr($digits, 0, -$this->decimalPlaces).'.'.substr($digits, -$this->decimalPlaces);
        }

        return ($negative ? '-' : '').$value;
    }

    public function format(?string $locale = null): string
    {
        $formatted = number_format(
            (float) $this->toDecimalString(),
            $this->decimalPlaces,
            '.',
            ','
        );

        return $locale === 'ar'
            ? $formatted.' '.__('accounting.currency.'.strtolower($this->currency))
            : $formatted.' '.$this->currency;
    }

    /** @return array{amount: string, currency: string, decimal_places: int} */
    public function jsonSerialize(): array
    {
        return [
            'amount' => (string) $this->minorUnits,
            'currency' => $this->currency,
            'decimal_places' => $this->decimalPlaces,
        ];
    }

    public function __toString(): string
    {
        return $this->toDecimalString();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency} with {$other->currency}."
            );
        }
    }
}
