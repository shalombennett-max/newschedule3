<?php
declare(strict_types=1);

interface PosAdapterInterface
{
    public function getProviderKey(): string;

    public function isConfigured(int $restaurantId): bool;

    public function getConnection(int $restaurantId): ?array;

    /** @return array<int,array<string,mixed>> */
    public function listLocations(int $restaurantId): array;

    /** @return array<string,mixed> */
    public function syncEmployees(int $restaurantId, array $options = []): array;

    /** @return array<string,mixed> */
    public function syncLaborActuals(int $restaurantId, array $options = []): array;

    /** @return array<string,mixed> */
    public function syncSales(int $restaurantId, array $options = []): array;
}