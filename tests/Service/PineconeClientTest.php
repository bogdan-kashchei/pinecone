<?php

namespace App\Tests\Service;

use App\PineconeBundle\Service\PineconeClient;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class PineconeClientTest extends TestCase
{
    private PineconeClient $pineconeClient;

    protected function setUp(): void
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->willReturnMap([
            ['pinecone.api_key', 'test_api_key'],
            ['apinecone.env', 'test_env'],
        ]);

        $this->pineconeClient = new PineconeClient($params);
    }

    public function testUpsertVectors(): void
    {
        $client = $this->createMock(Client::class);
        $this->pineconeClient->setClient($client);

        $client->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://test_host/vectors/upsert',
                $this->callback(function (array $options) {
                    if (!isset($options['headers']) || !is_array($options['headers'])) {
                        throw new \RuntimeException('Invalid headers.');
                    }
                    return $options['headers']['Api-Key'] === 'test_api_key';
                })
            );

        $this->pineconeClient->upsertVectors('test_host', [], 'test_namespace');
    }

    public function testQueryVectors(): void
    {
        $client = $this->createMock(Client::class);
        $this->pineconeClient->setClient($client);

        $client->expects($this->once())
            ->method('post')
            ->with(
                'https://test_env.pinecone.io/vectors/query',
                $this->callback(function (array $options) {
                    if (!isset($options['headers']) || !is_array($options['headers'])) {
                        throw new \RuntimeException('Invalid headers.');
                    }
                    return $options['headers']['Authorization'] === 'Bearer test_api_key';
                })
            )
            ->willReturn($this->createMockResponse(['result' => 'data']));

        $result = $this->pineconeClient->queryVectors('test_index', [], 10);
        $this->assertEquals(['result' => 'data'], $result);
    }

    public function testGetIndexes(): void
    {
        $client = $this->createMock(Client::class);
        $this->pineconeClient->setClient($client);

        $client->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.pinecone.io/indexes',
                $this->callback(function (array $options) {
                    if (!isset($options['headers']) || !is_array($options['headers'])) {
                        throw new \RuntimeException('Invalid headers.');
                    }
                    return $options['headers']['Api-Key'] === 'test_api_key';
                })
            )
            ->willReturn($this->createMockResponse(['indexes' => ['index1', 'index2']]));

        $result = $this->pineconeClient->getIndexes();
        $this->assertEquals(['index1', 'index2'], $result);
    }

    public function testGetIndex(): void
    {
        $client = $this->createMock(Client::class);
        $this->pineconeClient->setClient($client);

        $client->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.pinecone.io/indexes/test_index',
                $this->callback(function (array $options) {
                    if (!isset($options['headers']) || !is_array($options['headers'])) {
                        throw new \RuntimeException('Invalid headers.');
                    }
                    return $options['headers']['Api-Key'] === 'test_api_key';
                })
            )
            ->willReturn($this->createMockResponse(['index' => 'details']));

        $result = $this->pineconeClient->getIndex('test_index');
        $this->assertEquals(['index' => 'details'], $result);
    }

    public function testCreateEmbedding(): void
    {
        $client = $this->createMock(Client::class);
        $this->pineconeClient->setClient($client);

        $client->expects($this->once())
            ->method('post')
            ->with(
                'https://api.pinecone.io/embed',
                $this->callback(function (array $options) {
                    if (!isset($options['headers']) || !is_array($options['headers'])) {
                        throw new \RuntimeException('Invalid headers.');
                    }
                    return $options['headers']['Api-Key'] === 'test_api_key';
                })
            )
            ->willReturn($this->createMockResponse(['data' => [['values' => 'embedding']]]));

        $result = $this->pineconeClient->createEmbedding('test_text');
        $this->assertEquals([], $result);
    }

    public function testSearchIndex(): void
    {
        $client = $this->createMock(Client::class);
        $this->pineconeClient->setClient($client);

        $client->expects($this->once())
            ->method('post')
            ->with(
                'https://test_host/query',
                $this->callback(function (array $options) {
                    if (!isset($options['headers']) || !is_array($options['headers'])) {
                        throw new \RuntimeException('Invalid headers.');
                    }
                    return $options['headers']['Api-Key'] === 'test_api_key';
                })
            )
            ->willReturn($this->createMockResponse(['matches' => 'data']));

        $result = $this->pineconeClient->searchIndex('test_host', [], 'test_namespace');
        $this->assertEquals(['matches' => ['']], $result);
    }

    public function testCreateVectors(): void
    {
        $client = $this->createMock(Client::class);
        $this->pineconeClient->setClient($client);

        $client->expects($this->once())
            ->method('post')
            ->with(
                'https://api.pinecone.io/embed',
                $this->callback(function (array $options) {
                    if (!isset($options['headers']) || !is_array($options['headers'])) {
                        throw new \RuntimeException('Invalid headers.');
                    }
                    return $options['headers']['Api-Key'] === 'test_api_key';
                })
            )
            ->willReturn($this->createMockResponse(['data' => 'vectors']));

        $result = $this->pineconeClient->createVectors([]);
        $this->assertEquals('vectors', $result);
    }

    public function testDescribeIndexStats(): void
    {
        $client = $this->createMock(Client::class);
        $this->pineconeClient->setClient($client);

        $client->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://test_host/describe_index_stats',
                $this->callback(function (array $options) {
                    if (!isset($options['headers']) || !is_array($options['headers'])) {
                        throw new \RuntimeException('Invalid headers.');
                    }
                    return $options['headers']['Api-Key'] === 'test_api_key';
                })
            )
            ->willReturn($this->createMockResponse(['stats' => 'details']));

        $result = $this->pineconeClient->describeIndexStats('test_host');
        $this->assertEquals([], $result);
    }

    public function testCreateIndex(): void
    {
        $client = $this->createMock(Client::class);
        $this->pineconeClient->setClient($client);

        $client->expects($this->once())
            ->method('post')
            ->with(
                'https://api.pinecone.io/indexes',
                $this->callback(function (array $options) {
                    if (!isset($options['headers']) || !is_array($options['headers'])) {
                        throw new \RuntimeException('Invalid headers.');
                    }
                    return $options['headers']['Api-Key'] === 'test_api_key';
                })
            )
            ->willReturn($this->createMockResponse([], 200));

        $this->pineconeClient->createIndex(['name' => 'test_index']);
    }

    public function testViewIndex(): void
    {
        $client = $this->createMock(Client::class);
        $this->pineconeClient->setClient($client);

        $client->expects($this->once())
            ->method('get')
            ->with(
                'https://api.pinecone.io/indexes/test_index',
                $this->callback(function (array $options) {
                    if (!isset($options['headers']) || !is_array($options['headers'])) {
                        throw new \RuntimeException('Invalid headers.');
                    }
                    return $options['headers']['Api-Key'] === 'test_api_key';
                })
            )
            ->willReturn($this->createMockResponse(['index' => 'details']));

        $result = $this->pineconeClient->viewIndex('test_index');
        $this->assertEquals([], $result);
    }

    public function testDeleteIndex(): void
    {
        $client = $this->createMock(Client::class);
        $this->pineconeClient->setClient($client);

        $client->expects($this->once())
            ->method('request')
            ->with(
                'DELETE',
                'https://api.pinecone.io/indexes/test_index',
                $this->callback(function (array $options) {
                    if (!isset($options['headers']) || !is_array($options['headers'])) {
                        throw new \RuntimeException('Invalid headers.');
                    }
                    return $options['headers']['Api-Key'] === 'test_api_key';
                })
            );

        $this->pineconeClient->deleteIndex('test_index');
    }

    /**
     * @param array<mixed> $data
     * @param int $statusCode
     * @return ResponseInterface
     */
    private function createMockResponse(array $data, int $statusCode = 200): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode($data));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);
        $response->method('getStatusCode')->willReturn($statusCode);

        return $response;
    }
}