<?php

namespace Qdequippe\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;

class SymfonyConnect extends AbstractProvider
{
    protected string $api = 'https://connect.symfony.com';

    public function getBaseAuthorizationUrl(): string
    {
        return $this->api . '/oauth/authorize';
    }

    public function getBaseAccessTokenUrl(array $params): string
    {
        return $this->api . '/oauth/access_token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return $this->api . '/api?access_token='.$token->getToken();
    }

    protected function getScopeSeparator(): string
    {
        return ' ';
    }

    protected function getDefaultScopes(): array
    {
        return ['SCOPE_PUBLIC'];
    }

    protected function parseResponse(ResponseInterface $response): array
    {
        $type = $this->getContentType($response);

        if ($type !== 'application/vnd.com.symfony.connect+xml') {
            return parent::parseResponse($response);
        }

        return ['xml' => (string)$response->getBody()];
    }

    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if ($response->getStatusCode() >= 400) {
            throw new IdentityProviderException(
                $data['message'] ?? $response->getReasonPhrase(),
                $response->getStatusCode(),
                $data
            );
        }
    }

    protected function createResourceOwner(array $response, AccessToken $token): SymfonyConnectResourceOwner
    {
        return new SymfonyConnectResourceOwner($response);
    }
}
