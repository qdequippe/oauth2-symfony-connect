<?php

namespace Qdequippe\OAuth2\Client\Test\Provider;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Tool\QueryBuilderTrait;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Qdequippe\OAuth2\Client\Provider\SymfonyConnect;
use Qdequippe\OAuth2\Client\Provider\SymfonyConnectResourceOwner;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\ClientInterface;

class SymfonyConnectTest extends TestCase
{
    use QueryBuilderTrait;

    protected SymfonyConnect $provider;

    public function setUp(): void
    {
        $this->provider = new SymfonyConnect([
            'clientId'      => 'mock_client_id',
            'clientSecret'  => 'mock_secret',
            'redirectUri'   => 'none',
        ]);
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);
        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }

    public function testGetAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        $this->assertEquals('/oauth/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl(): void
    {
        $params = [];
        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);
        $this->assertEquals('/oauth/access_token', $uri['path']);
    }

    public function testGetAccessToken(): void
    {
        $testResponse = [
            'access_token' => 'mock_access_token',
        ];

        $response = m::mock(ResponseInterface::class);
        $stream = m::mock(StreamInterface::class);
        $stream->allows('__toString')->andReturns(json_encode($testResponse, JSON_THROW_ON_ERROR));
        $response->allows('getBody')->andReturns($stream);
        $response->allows('getHeader')->andReturns(['content-type' => 'json']);
        $response->allows('getStatusCode')->andReturns(200);
        $client = m::mock(ClientInterface::class);
        $client->expects('send')->times(1)->andReturns($response);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $this->assertEquals($testResponse['access_token'], $token->getToken());
    }

    public function testUserData(): void
    {
        $xml = file_get_contents(__DIR__.'/current_user_response.xml');

        $postResponse = m::mock(ResponseInterface::class);
        $streamPost = m::mock(StreamInterface::class);
        $streamPost->allows('__toString')->andReturns('{"access_token":"mock_access_token"}');
        $postResponse->allows('getBody')->andReturns($streamPost);
        $postResponse->allows('getHeader')->andReturns(['content-type' => 'json']);
        $postResponse->allows('getStatusCode')->andReturns(200);

        $userResponse = m::mock(ResponseInterface::class);
        $streamUser = m::mock(StreamInterface::class);
        $streamUser->allows('__toString')->andReturns($xml);
        $userResponse->allows('getBody')->andReturns($streamUser);
        $userResponse->allows('getHeader')->andReturns(['content-type' => 'application/vnd.com.symfony.connect+xml']);
        $userResponse->allows('getStatusCode')->andReturns(200);
        $client = m::mock(ClientInterface::class);
        $client->expects('send')
            ->times(2)
            ->andReturns($postResponse, $userResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $user = $this->provider->getResourceOwner($token);

        $this->assertInstanceOf(SymfonyConnectResourceOwner::class, $user);
        $this->assertEquals('39c049bb-9261-4d85-922c-15730d6fa8b1', $user->getId());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertEquals('jdoe', $user->getUsername());
        $this->assertEquals('jdoe', $user->getName());

        $this->assertEquals(
            [
                'id' => '39c049bb-9261-4d85-922c-15730d6fa8b1',
                'name' => 'jdoe',
                'email' => 'john@example.com',
                'profilePicture' => null,
                'username' => 'jdoe',
                'realname' => 'John Doe',
                'biography' => 'My bio',
                'birthday' => null,
                'city' => null,
                'country' => null,
                'company' => null,
                'jobPosition' => null,
                'blogUrl' => 'https://example.com',
                'url' => 'https://example.com',
                'feedUrl' => null,
            ],
            $user->toArray()
        );
        $this->assertInstanceOf(\DOMElement::class, $user->getData());
    }

    public function testExceptionThrownWhenErrorObjectReceived(): void
    {
        $this->expectException(IdentityProviderException::class);
        $message = uniqid('', true);
        $status = random_int(400, 600);
        $code = uniqid('', true);

        $postResponse = m::mock(ResponseInterface::class);
        $streamPost = m::mock(StreamInterface::class);
        $streamPost->allows('__toString')->andReturns('{"message": "'.$message.'", "error": "'.$code.'"}');
        $postResponse->allows('getBody')->andReturns($streamPost);
        $postResponse->allows('getHeader')->andReturns(['content-type' => 'json']);
        $postResponse->allows('getStatusCode')->andReturns($status);
        $client = m::mock(ClientInterface::class);
        $client->expects('send')
            ->times(1)
            ->andReturns($postResponse);
        $this->provider->setHttpClient($client);
        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }
}
