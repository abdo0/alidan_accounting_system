<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Rules;

use InvalidArgumentException;

/**
 * Normalises and parses the account selectors of Document C tabs 12-13.
 *
 * Grammar, after normalisation:
 *   selector := term ('|' term)*
 *   term     := code | code '-' code | digits '*' | alias
 *   alias    := '@final_economic' | '@original_debit' | '@any_posting'
 *
 * The spec writes ranges as "115001…115099", appends "(posting accounts only)"
 * (implied anyway by VR-04), and names some sides in prose ("final economic
 * account", "correct account"). Those are mapped here; anything else the parser
 * does not recognise is refused, so a new wording in a re-issued Document C fails
 * at seed time rather than matching nothing at posting time.
 */
final class SelectorParser
{
    /** @var array<string, string> prose => alias */
    private const PROSE = [
        'final economic account' => '@final_economic',
        'correct account' => '@any_posting',
        'final account per evidence' => '@any_posting',
        'originally debited account' => '@original_debit',
    ];

    /**
     * Expansions of the aliases that name account sets (database/data/shh/selector_aliases.csv).
     *
     * @original_debit is resolved against the linked journal at posting time.
     */
    private const EXPANSIONS = [
        '@final_economic' => '115001-115099|116001-116011|118001-118009|510010-510019|610001-610019|112001-112102|211001-211090',
    ];

    public function normalise(string $raw): string
    {
        $text = str_replace(['…', '...', '(posting accounts only)'], ['-', '-', ''], $raw);
        $terms = array_map('trim', explode('|', $text));

        $normalised = [];
        foreach ($terms as $term) {
            $term = preg_replace('/\s+/', ' ', $term) ?? $term;
            $normalised[] = self::PROSE[strtolower($term)] ?? $this->assertTerm($term);
        }

        return implode('|', $normalised);
    }

    public function parse(string $selector): AccountSet
    {
        $ranges = [];
        $aliases = [];

        foreach (explode('|', $this->normalise($selector)) as $term) {
            if (str_starts_with($term, '@')) {
                if (isset(self::EXPANSIONS[$term])) {
                    $ranges = [...$ranges, ...$this->parse(self::EXPANSIONS[$term])->ranges];
                } else {
                    $aliases[] = $term;
                }

                continue;
            }

            if (str_ends_with($term, '*')) {
                $prefix = rtrim($term, '*');
                $ranges[] = [str_pad($prefix, 6, '0'), str_pad($prefix, 6, '9')];

                continue;
            }

            if (str_contains($term, '-')) {
                [$from, $to] = array_map('trim', explode('-', $term, 2));
                $ranges[] = [$from, $to];

                continue;
            }

            $ranges[] = [$term, $term];
        }

        return new AccountSet($ranges, $aliases);
    }

    private function assertTerm(string $term): string
    {
        if (preg_match('/^(\d{6}(\s*-\s*\d{6})?|\d{1,5}\*|@(final_economic|original_debit|any_posting))$/', $term) !== 1) {
            throw new InvalidArgumentException("Unrecognised account selector term \"{$term}\".");
        }

        return str_replace(' ', '', $term);
    }
}
