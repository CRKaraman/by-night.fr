<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security;

use App\OAuth\TwitterAccessToken;
use App\OAuth\TwitterOAuth;
use App\OAuth\TwitterUser;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\FacebookUser;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class OAuthDataProvider
{
    private ClientRegistry $clientRegistry;

    private TwitterOAuth $twitterOAuth;

    public function __construct(ClientRegistry $clientRegistry, TwitterOAuth $twitterOAuth)
    {
        $this->clientRegistry = $clientRegistry;
        $this->twitterOAuth = $twitterOAuth;
    }

    public function getDatasFromToken(string $serviceName, AccessToken $token): array
    {
        $datas = [
            'accessToken' => $token->getToken(),
            'refreshToken' => $token->getRefreshToken(),
            'expires' => $token->getExpires(),
        ];

        if ($token instanceof TwitterAccessToken) {
            $user = $this->twitterOAuth->fetchUserFromToken($token);
        } else {
            $client = $this->clientRegistry->getClient($serviceName);
            $user = $client->fetchUserFromToken($token);
        }

        switch (true) {
            case $user instanceof FacebookUser:
                $datas += [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'profilePicture' => $user->isDefaultPicture() ? null : $user->getPictureUrl(),
                    'realname' => $user->getName(),
                ];
                break;
            case $user instanceof GoogleUser:
                $datas += [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'profilePicture' => $user->getAvatar(),
                    'realname' => $user->getName(),
                ];
                break;
            case $user instanceof TwitterUser:
                $datas += [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'profilePicture' => $user->getProfilePicture(),
                    'realname' => $user->getName(),
                    'nickname' => $user->getScreenName(),
                ];
                break;
            default:
                throw new AuthenticationException(sprintf('Unable to guess how to find user for service "%s"', $serviceName));
        }

        //So ugly...
        if (empty($datas['email'])) {
            $datas['email'] = sprintf('john.doe-%s@by-night.fr', uniqid('', true));
        }

        return $datas;
    }
}
