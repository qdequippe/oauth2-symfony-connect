<?php

namespace Qdequippe\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;

class SymfonyConnect extends AbstractProvider
{

    protected $api = 'https://connect.symfony.com';

    public function getBaseAuthorizationUrl()
    {
        return $this->api . '/oauth/authorize';
    }

    public function getBaseAccessTokenUrl(array $params)
    {
        return $this->api . '/oauth/access_token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return $this->api . '/api?access_token='.$token->getToken();
    }

    protected function getScopeSeparator()
    {
        return ' ';
    }

    protected function getDefaultScopes()
    {
        return ['SCOPE_PUBLIC'];
    }

    protected function parseResponse(ResponseInterface $response)
    {
        $type = $this->getContentType($response);

        if ($type !== 'application/vnd.com.symfony.connect+xml') {
            return parent::parseResponse($response);
        }

        return ['xml' => (string)$response->getBody()];
    }

    protected function checkResponse(ResponseInterface $response, $data)
    {
        if ($response->getStatusCode() >= 400) {
            throw new IdentityProviderException(
                $data['message'] ?? $response->getReasonPhrase(),
                $response->getStatusCode(),
                $data
            );
        }
    }

    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new SymfonyConnectResourceOwner($response);
    }
}
