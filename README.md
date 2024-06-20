# SymfonyConnect Provider for OAuth 2.0 Client

This package provides [SymfonyConnect](https://connect.symfony.com) OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

Inspired by https://github.com/symfonycorp/connect and https://github.com/hwi/HWIOAuthBundle.

## Installation

To install, use composer:

```
composer require qdequippe/oauth2-symfony-connect
```

## Usage

Usage is the same as The League's OAuth client, using `\Qdequippe\OAuth2\Client\Provider\SymfonyConnect` as the provider.

### Authorization Code Flow

```php
$provider = new Qdequippe\OAuth2\Client\Provider\SymfonyConnect([
    'clientId'          => '{symfony-connect-client-id}',
    'clientSecret'      => '{symfony-connect-client-secret}',
    'redirectUri'       => 'https://example.com/callback-url'
]);

if (!isset($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: '.$authUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Optional: Now you have a token you can look up a users profile data
    try {

        // We got an access token, let's now get the user's details
        $user = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        printf('Hello %s!', $user->getNickname());

    } catch (Exception $e) {

        // Failed to get user details
        exit('Oh dear...');
    }

    // Use this to interact with an API on the users behalf
    echo $token->getToken();
}
```

### Managing Scopes

When creating your SymfonyConnect authorization URL, you can specify the state and scopes your application may authorize.

```php
$options = [
    'state' => 'OPTIONAL_CUSTOM_CONFIGURED_STATE',
    'scope' => ['SCOPE_PUBLIC', 'SCOPE_EMAIL'] // array or string ['SCOPE_PUBLIC SCOPE_EMAIL']
];

$authorizationUrl = $provider->getAuthorizationUrl($options);
```

## Testing

``` bash
$ ./vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](https://github.com/qdequippe/oauth2-symfony-connect/blob/master/CONTRIBUTING.md) for details.


## Credits

- [Quentin Dequippe](https://github.com/qdequippe)
- [All Contributors](https://github.com/qdequippe/oauth2-symfony-connect/contributors)


## Code license

The MIT License (MIT). Please see [License File](https://github.com/qdequippe/oauth2-symfony-connect/blob/master/LICENSE) for more information.

## Thanks

- https://github.com/symfonycorp/connect
- https://github.com/hwi/HWIOAuthBundle

## Symfony Trademark & Licenses

Symfony ™ is a trademark of Symfony SAS. All rights reserved. See [Trademark & Licenses](https://symfony.com/license) details.
