<?php

declare(strict_types=1);

namespace App\Services\Users\Settings\Storage;

use App\Services\Users\Settings\Contracts\UserSettingsStorageInterface;

/**
 * Shared implementation of {@see UserSettingsStorageInterface} for the backends that
 * keep their rows in a plain nested array (namespace → property → raw value):
 * {@see RuntimeUserSettingsStorage} holds it in process memory,
 * {@see SessionUserSettingsStorage} in the session.
 *
 * The merge and unset semantics live here once so the two backends cannot drift apart;
 * the using classes only decide *where* the array is read from and written to, via
 * {@see readAll()} and {@see writeAll()}.
 *
 * Implemented as a trait rather than a base class on purpose: the project's cs-fixer
 * ruleset forces `final` onto the public methods of abstract classes, which would make
 * the storages unmockable in the {@see \App\Services\Users\Settings\UserSettingsService}
 * tests.
 */
trait ArrayUserSettingsStorageTrait
{
    /**
     * {@inheritDoc}
     */
    public function loadRaw(string $namespace): array
    {
        return $this->readAll()[$namespace] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function persist(string $namespace, array $changed, array $removed): void
    {
        if ([] === $changed && [] === $removed) {
            return;
        }

        $data = $this->readAll();
        $rows = array_merge($data[$namespace] ?? [], $changed);

        foreach ($removed as $key) {
            unset($rows[$key]);
        }

        if ([] === $rows) {
            // A namespace whose last row is gone is dropped entirely, so it can never
            // linger as an empty entry that getNamespaces() would still report.
            unset($data[$namespace]);
        } else {
            $data[$namespace] = $rows;
        }

        $this->writeAll($data);
    }

    /**
     * Returns every namespace that currently has at least one stored row.
     *
     * Not part of {@see UserSettingsStorageInterface} — the service never needs it.
     * It exists for the backends' own bookkeeping (see
     * {@see SessionUserSettingsStorage::promoteTo()}) and to make the pruning
     * behaviour of {@see persist()} observable.
     *
     * @return list<string>
     */
    public function getNamespaces(): array
    {
        return array_keys($this->readAll());
    }

    /**
     * Returns the full backing map (namespace → property → raw value) — an empty array
     * when nothing has been stored yet.
     *
     * @return array<string, array<string, null|string>>
     */
    abstract protected function readAll(): array;

    /**
     * Writes the full backing map back to the underlying medium.
     *
     * @param array<string, array<string, null|string>> $data
     */
    abstract protected function writeAll(array $data): void;
}
