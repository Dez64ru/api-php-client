<?php

namespace SimaLand\API\Tests\Rest;

use GuzzleHttp\Psr7\Response;
use SimaLand\API\Rest\Client;
use SimaLand\API\Rest\Request;
use SimaLand\API\Tests\BaseCase;

class ClientTest extends BaseCase
{
    public function testCreateClient()
    {
        $url = 'http://example.com';
        $client = new Client(['baseUrl' => $url, 'login' => 'test', 'password' => 'password']);
        $this->assertEquals($url, $client->baseUrl);
        $this->assertInstanceOf('\GuzzleHttp\Client', $client->getHttpClient());
    }

    public function testGetOptions()
    {
        $client = new Client([
            'login' => 'test',
            'password' => 'password',
            'pathToken' => TEST_DIR . "data"
        ]);
        $request = new Request([
            'entity' => 'test',
            'getParams' => ['foo' => 'bar'],
            'postParams' => ['bar' => 'foo'],
        ]);
        $this->assertEquals(
            [
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => 'Sima-land api-php-client/0.1',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer token',
                ],
                'query' => ['foo' => 'bar'],
            ],
            $client->getOptions($request)
        );
    }

    /**
     * @expectedException \Exception
     */
    public function testEmptyLogin()
    {
        new Client();
    }

    /**
     * @expectedException \Exception
     */
    public function testEmptyPassword()
    {
        new Client(['login' => 'test']);
    }

    /**
     * @expectedException \Exception
     */
    public function testInvalidLoginPassword()
    {
        $client = $this->getClient();
        $this->setGuzzleHttpResponse(new Response(401, [], 'Unauthorized'));
        $oldPathToken = $client->pathToken;
        $client->pathToken = null;
        $client->get('user');
        $client->pathToken = $oldPathToken;
    }

    /**
     * @expectedException \Exception
     */
    public function testInvalidPathToken()
    {
        $client = new Client([
            'login' => 'test',
            'password' => 'password',
            'pathToken' => TEST_DIR . 'fake'
        ]);
        $client->get('user');
    }

    public function testDeleteToken()
    {
        $filename = TEST_DIR . 'output/token.txt';
        file_put_contents($filename, 'token');
        $client = new Client([
            'login' => 'test',
            'password' => 'password',
            'pathToken' => TEST_DIR . 'output'
        ]);
        $client->deleteToken();
        $this->assertFileNotExists($filename);
    }

    public function testGetToken()
    {
        $token = uniqid();
        $pathToken = TEST_DIR . 'output';
        $fileToken = $pathToken . "/token.txt";
        file_put_contents($fileToken, 'token');

        $this->setGuzzleHttpResponse(new Response(401, [], 'Unauthorized'));
        $this->setResponse(['jwt' => $token]);
        $this->setGuzzleHttpResponse(new Response(200, [], 'ok'));

        $client = $this->getClient();
        $oldPathToken = $client->pathToken;
        $client->pathToken = $pathToken;
        $response = $client->get('user');
        $client->pathToken = $oldPathToken;
        $this->assertEquals('ok', $response->rawBody);
        $this->assertEquals($token, file_get_contents($fileToken));
        @unlink($fileToken);
    }

    public function testQuery()
    {
        $client = $this->getClient();
        $body = ['items' => ['foo' => 'bar']];
        $this->setResponse($body);
        $response = $client->get('item');
        $this->assertInstanceOf('SimaLand\API\Rest\Response', $response);
        $this->assertEquals($body['items'], $response->body['items']);

        $body = 'raw body';
        $this->setGuzzleHttpResponse(new Response(200, [], $body));
        $response = $client->query('GET', 'item', ['key' => 'value']);
        $this->assertInstanceOf('SimaLand\API\Rest\Response', $response);
        $this->assertEmpty($response->body);
        $this->assertEquals($body, $response->rawBody);
    }

    /**
     * @expectedException \Exception
     */
    public function testInvalidBatchQuery()
    {
        $client = $this->getClient();
        $client->batchQuery(['test']);
    }

    public function testBatchQuery()
    {
        $client = $this->getClient();
        $body = [
            'item1' => ['items' => ['foo' => 'bar']],
            'item2' => ['items' => ['bar' => 'foo']]
        ];
        foreach ($body as $item) {
            $this->setResponse($item);
        }
        $responses = $client->batchQuery([
            'item1' => new Request([
                'entity' => 'item',
                'getParams' => ['id-mf' => '2,0']
            ]),
            'item2' => new Request([
                'entity' => 'item',
                'getParams' => ['id-mf' => '2,1']
            ]),
        ]);
        foreach ($responses as $key => $response) {
            $this->assertInstanceOf('SimaLand\API\Rest\Response', $response);
            $this->assertEquals($body[$key]['items'], $response->body['items']);
        }
    }
}
