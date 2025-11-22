<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpfsClient
{
    public function uploadString(string $content): string
    {
        // Örnek: Pinata için content upload mantığı
        $baseUrl = rtrim(config('services.ipfs.base_url', env('IPFS_API_BASE_URL')), '/');

        // Burayı seçtiğin provider’a göre özelleştireceksin
        $response = Http::withHeaders([
                'pinata_api_key'    => env('IPFS_API_KEY'),
                'pinata_secret_api_key' => env('IPFS_API_SECRET'),
            ])
            ->post($baseUrl . '/pinJSONToIPFS', [
                'pinataContent' => [
                    'data' => $content,
                ],
            ]);

        if (! $response->successful()) {
            Log::error('IPFS upload failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new \RuntimeException('IPFS upload failed');
        }

        $data = $response->json();

        // Pinata örneği: IpfsHash alanı geliyor
        $cid = $data['IpfsHash'] ?? null;

        if (! $cid) {
            throw new \RuntimeException('IPFS CID missing in response');
        }

        return $cid;
    }
}
