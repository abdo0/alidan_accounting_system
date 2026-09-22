<?php

declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use App\Support\Spec\SpecCsv;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Document B §11, criterion 5 and 10: every validation rule of tab 18 and every
 * UAT case of tab 25 has at least one test tagged with its code. A new rule in a
 * re-issued Document C fails here until something tests it.
 */
class TraceabilityTest extends TestCase
{
    /** @return array<string, true> */
    private static function tagged(): array
    {
        $tags = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2)));

        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                preg_match_all("/#\\[Group\\('([A-Z]+-[0-9]+)'\\)\\]/", (string) file_get_contents($file->getPathname()), $m);
                foreach ($m[1] as $code) {
                    $tags[$code] = true;
                }
            }
        }

        return $tags;
    }

    #[Test]
    public function every_validation_rule_is_tested(): void
    {
        $missing = array_values(array_filter(
            array_column(SpecCsv::rows('18_Validation_Rules', ['Rule ID']), 'Rule ID'),
            fn (string $rule): bool => ! isset(self::tagged()[$rule]),
        ));

        $this->assertSame([], $missing, 'Rules without a tagged test.');
    }

    #[Test]
    public function every_uat_case_is_tested(): void
    {
        $missing = array_values(array_filter(
            array_column(SpecCsv::rows('25_UAT_Cases', ['Test ID']), 'Test ID'),
            fn (string $case): bool => ! isset(self::tagged()[$case]),
        ));

        $this->assertSame([], $missing, 'UAT cases without a tagged test.');
    }
}
