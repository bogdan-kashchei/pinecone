<?php

namespace App\PineconeBundle\Service;

use GuzzleHttp\Client;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class PineconeClient
{
    private Client $client;
    private string $apiKey;
    private string $environment;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(ContainerBagInterface $params)
    {
        $this->client = new Client();
        $apiKey = $params->get('pinecone.api_key');
        $environment = $params->get('apinecone.env');

        if (!is_string($apiKey)) {
            throw new \InvalidArgumentException('Expected pinecone.api_key to be a string, got ' . gettype($apiKey));
        }

        if (!is_string($environment)) {
            throw new \InvalidArgumentException('Expected pinecone.env to be a string, got ' . gettype($environment));
        }

        $this->apiKey = $apiKey;
        $this->environment = $environment;
    }

    /**
     * @param Client $client
     * @return void
     */
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * @param string $indexHost
     * @param array<mixed> $vectors An array of vectors, each represented as an associative array
     * @param string $namespace
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function upsertVectors(string $indexHost, array $vectors, string $namespace): void
    {
        $this->client->request('POST', "https://{$indexHost}/vectors/upsert", [
            'headers' => [
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'X-Pinecone-API-Version' => '2024-07',
            ],
            'json' => [
                'vectors' => $vectors,
                'namespace' => $namespace,
            ],
        ]);
    }

    /**
     * @param string $indexName
     * @param array<int, array<string, mixed>> $vector An array of vectors, each represented as an associative array
     * @param int $topK
     * @return array<mixed, mixed> An associative array representing the query response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function queryVectors(string $indexName, array $vector, int $topK = 10): array
    {
        $response = $this->client->post("https://{$this->environment}.pinecone.io/vectors/query", [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'index' => $indexName,
                'query' => $vector,
                'topK' => $topK,
            ],
        ]);

        // Decode the JSON response as an associative array
        $responseData = json_decode($response->getBody()->getContents(), true);

        // Ensure json_decode() did not fail
        if (!is_array($responseData)) {
            throw new \RuntimeException('Invalid JSON response from Pinecone API.');
        }

        return $responseData;
    }

    /**
     * @return array<mixed, mixed> An associative array representing the query response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getIndexes(): array
    {
        $response = $this->client->request('GET', "https://api.pinecone.io/indexes", [
            'headers' => [
                'Api-Key' => $this->apiKey,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        // Ensure json_decode() did not fail and the result is an array
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from Pinecone API.');
        }

        // Ensure the 'indexes' key exists and is an array
        if (!isset($data['indexes']) || !is_array($data['indexes'])) {
            throw new \RuntimeException('The response does not contain a valid "indexes" key.');
        }

        return $data['indexes'];
    }

    /**
     * @param string $indexName
     * @return array<mixed, mixed>
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getIndex(string $indexName): array
    {
        $response = $this->client->request('GET', "https://api.pinecone.io/indexes/{$indexName}", [
            'headers' => [
                'Api-Key' => $this->apiKey,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from Pinecone API.');
        }

        return $data;
    }

    /**
     * @param string $text
     * @param string $model
     * @return array<mixed, mixed>
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createEmbedding(string $text, string $model = 'multilingual-e5-large'): array
    {
        $text = $this->convertToUtf8($text); // Ensure the text is properly encoded in UTF-8

        $response = $this->client->post('https://api.pinecone.io/embed', [
            'headers' => [
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'X-Pinecone-API-Version' => '2024-10',
            ],
            'json' => [
                'model' => $model,
                'parameters' => [
                    'input_type' => 'query',
                    'truncate' => 'END',
                ],
                'inputs' => [
                    ['text' => $text],
                ],
            ],
        ]);

        $data = json_decode($response->getBody(), true) ?? ['data' => [0 => ['values' => []]]];

        if (!is_array($data) || !isset($data['data']) || !is_array($data['data']) || !isset($data['data'][0]) || !is_array($data['data'][0]) || !isset($data['data'][0]['values']) || !is_array($data['data'][0]['values'])) {
            throw new \RuntimeException('Invalid JSON response from Pinecone API.');
        }

        return $data['data'][0]['values'];
    }

    /**
     * @param string $string
     * @return string
     */
    private function convertToUtf8(string $string): string
    {
        return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    }

    /**
     * @param string $indexHost
     * @param array<mixed, mixed> $embedding
     * @param string $namespace
     * @param array<mixed, mixed> $filter
     * @param int $topK
     * @return array<mixed, mixed>
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function searchIndex(string $indexHost, array $embedding, string $namespace, array $filter = [], int $topK = 3): array
    {
        $response = $this->client->post("https://{$indexHost}/query", [
            'headers' => [
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'X-Pinecone-API-Version' => '2024-10',
            ],
            'json' => [
                'namespace' => $namespace,
                'vector' => $embedding,
                'topK' => $topK,
                'includeValues' => true,
                'includeMetadata' => true,
            ],
        ]);

        $data = json_decode($response->getBody(), true) ?? ['matches' => ['']];

        if (!is_array($data) || !isset($data['matches']) || !is_array($data['matches'])) {
            throw new \RuntimeException('Invalid JSON response from Pinecone API.');
        }

        return $data;
    }

    /**
     * @param array<mixed, array<mixed, mixed>> $inputs
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createVectors(array $inputs): string
    {
        $response = $this->client->post('https://api.pinecone.io/embed', [
            'headers' => [
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'X-Pinecone-API-Version' => '2024-10',
            ],
            'json' => [
                'model' => 'multilingual-e5-large',
                'parameters' => [
                    'input_type' => 'passage',
                    'truncate' => 'END',
                ],
                'inputs' => $inputs,
            ],
        ]);

        $data = json_decode($response->getBody(), true) ?? ['data' => 'vectors'];

        if (!is_array($data) || !isset($data['data']) || !is_string($data['data'])) {
            throw new \RuntimeException('Invalid JSON response from Pinecone API.');
        }

        return $data['data'];
    }

    /**
     * @param string $indexHost
     * @return array<mixed>
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function describeIndexStats(string $indexHost): array
    {
        $response = $this->client->request('POST', "https://{$indexHost}/describe_index_stats", [
            'headers' => [
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'X-Pinecone-API-Version' => '2024-10',
            ],
        ]);

        $data = json_decode($response->getBody(), true) ?? ['stats' => []];

        if (!is_array($data) || !isset($data['stats']) || !is_array($data['stats'])) {
            throw new \RuntimeException('Invalid JSON response from Pinecone API.');
        }

        return $data['stats'];
    }

    /**
     * @param array<mixed, mixed> $payload
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createIndex(array $payload): void
    {
        $response = $this->client->post('https://api.pinecone.io/indexes', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Api-Key' => $this->apiKey,
                'X-Pinecone-API-Version' => '2024-07',
            ],
            'json' => $payload,
        ]);

        if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 201) {
            throw new \Exception('Failed to create index. Status code: ' . $response->getStatusCode());
        }
    }

    /**
     * @param string $indexName
     * @return array<mixed, mixed>
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function viewIndex(string $indexName): array
    {
        $response = $this->client->get("https://api.pinecone.io/indexes/{$indexName}", [
            'headers' => [
                'Api-Key' => $this->apiKey,
                'X-Pinecone-API-Version' => '2024-07',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Failed to fetch index details. Status code: ' . $response->getStatusCode());
        }

        $data = json_decode($response->getBody(), true) ?? [];

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from Pinecone API.');
        }

        return $data;
    }

    /**
     * @param string $indexName
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function deleteIndex(string $indexName): void
    {
        $this->client->request('DELETE', "https://api.pinecone.io/indexes/{$indexName}", [
            'headers' => [
                'Accept' => 'application/json',
                'Api-Key' => $this->apiKey,
                'X-Pinecone-API-Version' => '2024-10',
            ],
        ]);
    }
}