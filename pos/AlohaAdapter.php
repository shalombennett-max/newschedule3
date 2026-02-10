<?php
declare(strict_types=1);

require_once __DIR__ . '/PosAdapterInterface.php';
require_once __DIR__ . '/../_common.php';

final class AlohaAdapter implements PosAdapterInterface
{
    public function getProviderKey(): string
    {
        return 'aloha';
    }

    public function isConfigured(int $restaurantId): bool
    {
        return $this->getConnection($restaurantId) !== null;
    }

    public function getConnection(int $restaurantId): ?array
    {
        return schedule_fetch_one(
            'SELECT id, restaurant_id, provider, status, credentials_json, last_sync_at, updated_at
             FROM pos_connections WHERE restaurant_id=:restaurant_id AND provider=:provider LIMIT 1',
            [':restaurant_id' => $restaurantId, ':provider' => $this->getProviderKey()]
        );
    }

    public function listLocations(int $restaurantId): array
    {
        return schedule_fetch_all(
            'SELECT DISTINCT location_code FROM aloha_labor_punches_stage
             WHERE restaurant_id=:restaurant_id AND location_code IS NOT NULL AND location_code != ""
             ORDER BY location_code ASC',
            [':restaurant_id' => $restaurantId]
        );
    }

    public function syncEmployees(int $restaurantId, array $options = []): array
    {
        $countRow = schedule_fetch_one(
            'SELECT COUNT(*) AS c FROM aloha_employees_stage WHERE restaurant_id=:restaurant_id',
            [':restaurant_id' => $restaurantId]
        );

        return ['provider' => $this->getProviderKey(), 'mode' => 'csv_stage', 'employees_staged' => (int)($countRow['c'] ?? 0), 'options' => $options];
    }

    public function syncLaborActuals(int $restaurantId, array $options = []): array
    {
        $countRow = schedule_fetch_one(
            'SELECT COUNT(*) AS c FROM aloha_labor_punches_stage WHERE restaurant_id=:restaurant_id',
            [':restaurant_id' => $restaurantId]
        );

        return ['provider' => $this->getProviderKey(), 'mode' => 'csv_stage', 'punches_staged' => (int)($countRow['c'] ?? 0), 'options' => $options];
    }

    public function syncSales(int $restaurantId, array $options = []): array
    {
        $countRow = schedule_fetch_one(
            'SELECT COUNT(*) AS c FROM aloha_sales_daily_stage WHERE restaurant_id=:restaurant_id',
            [':restaurant_id' => $restaurantId]
        );

        return ['provider' => $this->getProviderKey(), 'mode' => 'csv_stage', 'sales_days_staged' => (int)($countRow['c'] ?? 0), 'options' => $options];
    }
}