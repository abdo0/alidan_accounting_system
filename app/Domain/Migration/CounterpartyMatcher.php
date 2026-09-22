<?php

declare(strict_types=1);

namespace App\Domain\Migration;

use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\CounterpartyAlias;
use App\Domain\MasterData\Enums\CounterpartyType;

/**
 * Matches the ledger's free-text funding recipients to counterparties (Document B
 * §4.1): exact code, exact name, then a known alias. A name that matches nothing
 * is reported, never guessed. An approved mapping file resolves the rest -- to an
 * existing code, or "NEW:<code>" to open a counterparty that keeps the source name
 * as its alias.
 */
final class CounterpartyMatcher
{
    /** @var array<string, int|null> */
    private array $cache = [];

    /** @var array<string, string> source name => code or NEW:code */
    private array $mapping = [];

    /** @var array<string, true> */
    private array $unresolved = [];

    public function loadMapping(?string $path): void
    {
        if ($path === null || ! is_file($path)) {
            return;
        }

        $handle = fopen($path, 'rb');
        fgetcsv($handle, escape: '');
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if (isset($row[0], $row[1]) && trim($row[0]) !== '') {
                $this->mapping[self::normalise($row[0])] = trim($row[1]);
            }
        }
        fclose($handle);
    }

    public function match(string $name, bool $create = false): ?int
    {
        $key = self::normalise($name);

        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $id = Counterparty::query()->where('code', $name)->value('id')
            ?? Counterparty::query()->whereRaw('lower(trim(name)) = ?', [$key])->orWhereRaw('trim(name_ar) = ?', [trim($name)])->value('id')
            ?? CounterpartyAlias::query()->whereRaw('lower(trim(alias)) = ?', [$key])->value('counterparty_id')
            ?? Counterparty::query()->whereRaw('lower(trim(source_alias)) = ?', [$key])->value('id')
            ?? $this->fromMapping($key, $name, $create);

        if ($id === null) {
            $this->unresolved[$name] = true;
        }

        return $this->cache[$key] = $id;
    }

    /** @return list<string> */
    public function unresolved(): array
    {
        return array_keys($this->unresolved);
    }

    private function fromMapping(string $key, string $name, bool $create): ?int
    {
        $target = $this->mapping[$key] ?? null;

        if ($target === null) {
            return null;
        }

        if (! str_starts_with($target, 'NEW:')) {
            return Counterparty::query()->where('code', $target)->value('id');
        }

        if (! $create) {
            return -1;
        }

        return Counterparty::query()->firstOrCreate(
            ['code' => substr($target, 4)],
            ['name' => $name, 'name_ar' => $name, 'cp_type' => CounterpartyType::Other, 'source_alias' => $name, 'notes' => 'Created by the migration from the source name', 'is_active' => true],
        )->id;
    }

    private static function normalise(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name));
    }
}
