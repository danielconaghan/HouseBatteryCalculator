<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\RecommendationDTO;
use App\Exceptions\EnergyServiceException;
use App\Services\Contracts\EnergyServiceClientInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;

class EnergyServiceClient implements EnergyServiceClientInterface
{
    public function __construct(private readonly PendingRequest $http) {}

    public function getRecommendation(): RecommendationDTO
    {
        try {
            $response = $this->http->get('/api/v1/recommendation');
        } catch (ConnectionException $e) {
            throw new EnergyServiceException(
                'Could not connect to energy service: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        if (! $response->successful()) {
            throw new EnergyServiceException(
                'Energy service returned an error',
                $response->status(),
            );
        }

        return RecommendationDTO::from($response->json('data'));
    }
}
