<?php

namespace App\Services;

use App\Enums\ClientType;
use App\Enums\CreditProductType;
use App\Models\Client;
use InvalidArgumentException;

class CreditProductCatalog
{
    /**
     * @return list<array{
     *     code: string,
     *     label: string,
     *     description: string,
     *     category: string,
     *     client_type: string
     * }>
     */
    public function forClient(?Client $client): array
    {
        if ($client === null || $client->client_type === null) {
            return [];
        }

        return $this->forClientType($client->client_type);
    }

    /**
     * @return list<array{
     *     code: string,
     *     label: string,
     *     description: string,
     *     category: string,
     *     client_type: string
     * }>
     */
    public function forClientType(ClientType $clientType): array
    {
        return array_map(
            fn (CreditProductType $type): array => $this->toArray($type),
            CreditProductType::forClientType($clientType)
        );
    }

    /**
     * @return list<array{
     *     code: string,
     *     label: string,
     *     description: string,
     *     category: string,
     *     client_type: string
     * }>
     */
    public function all(): array
    {
        return array_map(
            fn (CreditProductType $type): array => $this->toArray($type),
            CreditProductType::cases()
        );
    }

    public function assertCompatible(CreditProductType $product, ClientType $clientType): void
    {
        if (! $product->isCompatibleWith($clientType)) {
            throw new InvalidArgumentException(
                "Le type de crédit {$product->value} n’est pas proposé pour un profil {$clientType->value}."
            );
        }
    }

    /**
     * @return array{
     *     code: string,
     *     label: string,
     *     description: string,
     *     category: string,
     *     client_type: string
     * }
     */
    public function toArray(CreditProductType $type): array
    {
        return [
            'code' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'category' => $type->category(),
            'client_type' => $type->clientType()->value,
        ];
    }
}
