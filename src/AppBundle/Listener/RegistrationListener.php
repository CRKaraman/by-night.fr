<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 05/05/2017
 * Time: 20:06
 */

namespace AppBundle\Listener;

use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\FOSUserEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RegistrationListener implements EventSubscriberInterface
{
    public function __construct()
    {
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            FOSUserEvents::REGISTRATION_INITIALIZE => 'onRegistrationInitialize'
        ];
    }

    public function onRegistrationInitialize(GetResponseUserEvent $event)
    {
        $event->getUser()->setFromLogin(true);
    }
}
