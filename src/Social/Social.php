<?php

namespace App\Social;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * && open the template in the editor.
 */

use App\App\SocialManager;
use App\Entity\Event;
use App\Entity\Info;
use App\Entity\User;
use App\Exception\SocialException;
use App\Picture\EventProfilePicture;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Description of Twitter.
 *
 * @author guillaume
 */
abstract class Social
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $secret;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EventProfilePicture
     */
    protected $eventProfilePicture;

    /**
     * @var SocialManager
     */
    protected $socialManager;

    protected $isInitialized;

    public function __construct(array $config, TokenStorageInterface $tokenStorage, RouterInterface $router, SessionInterface $session, RequestStack $requestStack, LoggerInterface $logger, EventProfilePicture $eventProfilePicture, SocialManager $socialManager)
    {
        if (!isset($config['id'])) {
            throw new SocialException("Le paramètre 'id' est absent");
        }

        if (!isset($config['secret'])) {
            throw new SocialException("Le paramètre 'secret' est absent");
        }

        $this->id = $config['id'];
        $this->secret = $config['secret'];
        $this->config = $config;
        $this->tokenStorage = $tokenStorage;
        $this->router = $router;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->eventProfilePicture = $eventProfilePicture;
        $this->socialManager = $socialManager;
        $this->isInitialized = false;
    }

    protected function init()
    {
        if (!$this->isInitialized) {
            $this->isInitialized = true;
            $this->constructClient();
        }
    }

    public function disconnectUser(User $user)
    {
        $social_name = $this->getName(); //On récupère le nom du child (Twitter, Google, Facebook)

        $user->removeRole('ROLE_' . \mb_strtolower($social_name)); //Suppression du role ROLE_TWITTER
        $this->disconnectInfo($user->getInfo());
    }

    protected function disconnectInfo(Info $info)
    {
        if (null !== $info) {
            $social_name = $this->getName(); //On récupère le nom du child (Twitter, Google, Facebook)
            $methods = ['Id', 'AccessToken', 'RefreshToken', 'TokenSecret', 'Nickname', 'RealName', 'Email', 'ProfilePicture'];
            foreach ($methods as $methode) {
                $setter = 'set' . \ucfirst($social_name) . \ucfirst($methode);
                $info->$setter(null);
            }
        }
    }

    public function disconnectSite()
    {
        $this->disconnectInfo($this->socialManager->getSiteInfo());
    }

    protected function connectInfo(Info $info, UserResponseInterface $response)
    {
        $social_name = $this->getName(); //On récupère le nom du child (Twitter, Google, Facebook)
        if (null !== $info) {
            $methods = ['AccessToken', 'RefreshToken', 'TokenSecret', 'ExpiresIn', 'Nickname', 'RealName', 'Email', 'ProfilePicture'];
            foreach ($methods as $methode) {
                $setter = 'set' . \ucfirst($social_name) . \ucfirst($methode); // setSocialUsername
                $getter = 'get' . \ucfirst($methode); //getSocialUsername

                $info->$setter($response->$getter());
            }

            $setter_id = 'set' . \ucfirst($social_name) . 'Id';
            $info->$setter_id($response->getUsername());
        }
    }

    public function connectUser(User $user, UserResponseInterface $response)
    {
        $social_name = $this->getName(); //On récupère le nom du child (Twitter, Google, Facebook)

        $user->addRole('ROLE_' . \mb_strtolower($social_name)); //Ajout du role ROLE_TWITTER
        $this->connectInfo($user->getInfo(), $response);
    }

    public function connectSite(UserResponseInterface $response)
    {
        $this->connectInfo($this->socialManager->getSiteInfo(), $response);
    }

    protected function getLinkPicture(Event $event)
    {
        return $this->eventProfilePicture->getOriginalPicture($event);
    }

    protected function getLink(Event $event)
    {
        return $this->router->generate('app_event_details', [
            'slug' => $event->getSlug(),
            'id' => $event->getSlug(),
            'location' => $event->getLocationSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    protected function getMembreLink(User $user)
    {
        return $this->router->generate('app_user_details', ['id' => $user->getId(), 'slug' => $user->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    abstract public function getNumberOfCount();

    abstract protected function constructClient();

    abstract protected function getName();
}
