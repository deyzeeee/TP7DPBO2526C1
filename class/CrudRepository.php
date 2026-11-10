<?php

declare(strict_types=1);

/**
 * Interface CrudRepository
 * Kontrak antarmuka kecil dan fokus (Interface Segregation Principle) untuk operasi CRUD dasar.
 */
interface CrudRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}
