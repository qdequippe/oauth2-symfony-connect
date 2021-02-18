<?php

namespace Qdequippe\OAuth2\Client\Test\Provider;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Tool\QueryBuilderTrait;
use Qdequippe\OAuth2\Client\Provider\SymfonyConnect;
use PHPUnit\Framework\TestCase;
use Mockery as m;
use Qdequippe\OAuth2\Client\Provider\SymfonyConnectResourceOwner;

class SymfonyConnectTest extends TestCase
{
    use QueryBuilderTrait;

    protected $provider;

    public function setUp(): void
    {
        $this->provider = new SymfonyConnect([
            'clientId'      => 'mock_client_id',
            'clientSecret'  => 'mock_secret',
            'redirectUri'   => 'none',
        ]);
    }

    public function testAuthorizationUrl()
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

    public function testGetAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        $this->assertEquals('/oauth/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl()
    {
        $params = [];
        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);
        $this->assertEquals('/oauth/access_token', $uri['path']);
    }

    public function testGetAccessToken()
    {
        $testResponse = [
            'access_token' => 'mock_access_token',
        ];

        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn(\json_encode($testResponse));
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $this->assertEquals($testResponse['access_token'], $token->getToken());
    }

    public function testUserData()
    {
        $xml = file_get_contents(dirname(__FILE__, 3) .'/current_user_response.xml');

        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);
        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn($xml);
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'application/vnd.com.symfony.connect+xml']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $user = $this->provider->getResourceOwner($token);

        $this->assertInstanceOf(SymfonyConnectResourceOwner::class, $user);
        $this->assertEquals('39c049bb-9261-4d85-922c-15730d6fa8b1', $user->getId());
        $this->assertEquals('john@example.com', $user->getEmail());

        $this->assertEquals(
            [
                'id' => '39c049bb-9261-4d85-922c-15730d6fa8b1',
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'profilePicture' => null
            ],
            $user->toArray()
        );
        $this->assertInstanceOf(\DOMElement::class, $user->getData());
    }

    public function testExceptionThrownWhenErrorObjectReceived()
    {
        $this->expectException(IdentityProviderException::class);
        $message = uniqid();
        $status = rand(400, 600);
        $code = uniqid();
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"message": "'.$message.'", "error": "'.$code.'"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }
}
