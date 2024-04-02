<?php

namespace App\Service;



use GuzzleHttp\Client;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class ApiService
{
    private HttpClientInterface $client;
    private string $baseUrl;

    public function __construct(HttpClientInterface $client, string $baseUrl)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;

    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function fetchData(string $endpoint, string $jwtToken)
    {
        $response = $this->client->request('GET', $this->baseUrl.$endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $jwtToken,
            ],
        ]);
        return $response->toArray();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function postData(string $endpoint, array $data, string $jwtToken): \Psr\Http\Message\ResponseInterface
    {
        $client = new Client();
        $response = $client->request('POST', $this->baseUrl . $endpoint, [
            'json' => $data,
            'headers' => [
                'Authorization' => 'Bearer ' . $jwtToken,
            ],
        ]);
        return $response;
    }

    public function patchData(string $endpoint, array $data, string $jwtToken): \Psr\Http\Message\ResponseInterface
    {
        $client = new Client();
        $response = $client->request('PATCH', $this->baseUrl . $endpoint, [
            'json' => $data,
            'headers' => [
                'Authorization' => 'Bearer ' . $jwtToken,
                'Content-Type' => 'application/merge-patch+json'
            ],
        ]);
        return $response;
    }
}