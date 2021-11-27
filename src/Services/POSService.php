<?php

namespace PetrPetrovich\POS\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class POSService
{
    public Client $client;
    private ?string $token;

    public function getToken(string $clientId, string $clientSecret): string
    {
        $this->client = new Client(['headers' => [
            'Content-Type' => 'multipart/form-data',
        ]]);

        try {
            $response = $this->client->post(config('pos.url_get_token'), [
                'auth' => [
                    config('pos.basic_auth_name'),
                    config('pos.basic_auth_password')
                ],
                'form_params' => [
                    'username' => $clientId,
                    'password' => $clientSecret,
                    'scope' => 'any',
                    'grant_type' =>'password',
                ],
            ]);

            $responseBody = (string) $response->getBody();
            $this->token = json_decode($responseBody)->access_token;
        } catch (RequestException $e) {
            Log::error(sprintf('Ошибка при получении токена (ПОС): %s', $e));
        }

        return $this->token;
    }

    public function getAppeals(): ?object
    {
        empty($this->token) && $this->getToken();
        $this->client = new Client(['headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ]]);

        try {
            $response = $this->client->request('GET', config('pos.url_get_appeals'));
            $appeals = json_decode((string) $response->getBody())->content;
        } catch (RequestException $e) {
            Log::error(sprintf('Ошибка при получении сообщений ПОС: %s', $e));
        }

        return $appeals ?? null;
    }

    public function removeAppealsFromQueue(int $appealId): void
    {
        empty($this->token) && $this->getToken();
        $this->client = new Client(['headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => "Bearer {$this->token}",
        ]]);

        try {
            $this->client->post(str_ireplace('{id}', $appealId, config('pos.url_remove_form_queue')));
        } catch (RequestException $e) {
            Log::error(sprintf('Ошибка при удалении сообщения %d из очереди ПОС: %s', $appealId, $e));
        }
    }

    public function updateAppeal(int $appealId, array $appealData): void
    {
        empty($this->token) && $this->getToken();
        $this->client = new Client(['headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ]]);

        try {
            $response = $this->client->post(config('pos.url_update_appeal') . '/' . $appealId,
                ['body' => json_encode($appealData)]
            );
            $response->getBody();
        } catch (RequestException $e) {
            Log::error($e->getResponse()->getBody());
        }
    }

    public function uploadAppealFiles(array $files): array
    {
        empty($this->token) && $this->getToken();

        $result = [];
        $requestData = [
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
            ],
            'multipart' => [
                []
            ]
        ];

        foreach ($files as $file) {
            $requestData['multipart'][0] = [
                'name' => 'file',
                'contents' => $file['src'],
                'filename' => $file['name'],
            ];

            try {
                $response = $this->client->post($this->authority->source->url_upload_file, $requestData);
                $result[] = json_decode((string)$response->getBody())->id;
            } catch (RequestException $e) {
                Log::error(sprintf('Ошибка при загрузке файлов(%s) в ПОС: %s', $file['src'], $e->getResponse()->getBody()));
            }
        }

        return $result;
    }
}
