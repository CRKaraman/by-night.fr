<?php

namespace App\Security\Core\User;

use App\App\CityManager;
use App\Entity\Info;
use App\Entity\User;
use App\Entity\UserInfo;
use App\Social\Social;
use App\Social\SocialProvider;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider as BaseClass;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;
use Symfony\Component\Security\Core\User\UserInterface;

class FOSUBUserProvider extends BaseClass
{
    /**
     * @var SocialProvider
     */
    private $socialProvider;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var CityManager
     */
    private $cityManager;

    public function __construct(UserManagerInterface $userManager, array $properties, CityManager $cityManager, EntityManagerInterface $entityManager, SocialProvider $socialProvider)
    {
        parent::__construct($userManager, $properties);

        $this->cityManager = $cityManager;
        $this->entityManager = $entityManager;
        $this->socialProvider = $socialProvider;
    }

    public function connectSite(UserResponseInterface $response)
    {
        //on connect - get the access token and the user ID
        $service = $response->getResourceOwner()->getName(); //google, facebook,...

        $social = $this->socialProvider->getSocial($service);
        $social->connectSite($response);
    }

    /**
     * {@inheritdoc}
     */
    public function connect(UserInterface $user, UserResponseInterface $response)
    {
        $username = $response->getUsername(); //ID de l'user sur le réseau social

        //on connect - get the access token and the user ID
        $service = $response->getResourceOwner()->getName(); //google, facebook,...

        //On récupère le service gérant les infos
        $social = $this->socialProvider->getSocial($service);

        $previousUser = $this->findUserBySocialInfo($response, $username);
        if (null !== $previousUser) {
            $social->disconnectUser($previousUser);
            $this->userManager->updateUser($previousUser);
        }

        $this->hydrateUser($user, $response, $service);
        $this->userManager->updateUser($user);
    }

    protected function findUserBySocialInfo(UserResponseInterface $cle, $valeur)
    {
        $repo = $this->entityManager->getRepository(Info::class);

        $info = $repo->findOneBy([$this->getProperty($cle) => $valeur]);
        if (null !== $info) {
            return $this->entityManager->getRepository(User::class)->findOneBy([
                'info' => $info,
            ]);
        }

        return null;
    }

    protected function getProperty(UserResponseInterface $response)
    {
        if (\preg_match('/facebook/i', $response->getResourceOwner()->getName())) {
            $response->getResourceOwner()->setName('facebook');
        }

        return parent::getProperty($response);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $username = $response->getUsername();
        $service = $response->getResourceOwner()->getName();

        // Recherche de l'user par son id sur les réseaux sociaux (facebook_id)
        $user = $this->findUserBySocialInfo($response, $username);

        //Recherche de l'user par l'email du compte social associé
        if (null === $user) {
            $user = $this->userManager->findUserBy(['email' => $response->getEmail()]);
        }

        //Si l'utilisateur n'existe pas on le créé
        if (null === $user) {
            //
            // Création de l'utilisateur
            $user = $this->userManager->createUser();

            //Affectation des données primaires
            $user->setPassword($username); //Obligatoire
            $user->setEnabled(true);

            //On définit le profil de l'utilisateur
            $this->hydrateUser($user, $response, $service);
            $this->userManager->updateUser($user); // Mise à jour

            return $user;
        }

        if (null === $user) {
            //if user exists - go with the HWIOAuth way
            $user = parent::loadUserByOAuthUserResponse($response);
        }

        //On met à jour l'utilisateur
        $this->hydrateUser($user, $response, $service);
        $this->userManager->updateUser($user); // Mise à jour

        return $user;
    }

    protected function hydrateUser(UserInterface $user, UserResponseInterface $response, $service)
    {
        if (!$user instanceof User) {
            return;
        }

        if (null === $user->getInfo()) {
            $user->setInfo(new UserInfo());
        }

        if (null === $user->getCity() && $this->cityManager->getCurrentCity()) {
            $user->setCity($this->cityManager->getCurrentCity());
        }

        if (null === $user->getEmail()) {
            $user->setEmail(null === $response->getEmail() ? $response->getNickname() . '@' . $service . '.fr' : $response->getEmail());
        }

        if (null === $user->getFirstname() && null === $user->getLastname()) {
            $nom_prenoms = \preg_split('/ /', $response->getRealName());
            $user->setFirstname($nom_prenoms[0]);
            if (\count($nom_prenoms) > 0) {
                $user->setLastname(\implode(' ', \array_slice($nom_prenoms, 1)));
            }
        }

        if (null === $user->getUsername() || '' === $user->getUsername()) {
            $user->setUsername($response->getNickname());
        }

        $social = $this->socialProvider->getSocial($service);
        $social->connectUser($user, $response);
    }
}
