<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 04/03/2016
 * Time: 19:16
 */

namespace AppBundle\Handler;

use AppBundle\Entity\City;
use AppBundle\Entity\ZipCity;
use Doctrine\ORM\EntityManagerInterface;

use AppBundle\Entity\Agenda;
use AppBundle\Entity\Place;
use AppBundle\Entity\Site;
use AppBundle\Entity\Exploration;

use AppBundle\Parser\Common\FaceBookParser;
use AppBundle\Parser\ParserInterface;
use AppBundle\Reject\Reject;


use AppBundle\Utils\Firewall;
use AppBundle\Utils\Monitor;

class DoctrineEventHandler
{
    const BATCH_SIZE = 50;

    private $em;

    /**
     * @var \AppBundle\Repository\AgendaRepository
     */
    private $repoAgenda;

    /**
     * @var \AppBundle\Repository\PlaceRepository
     */
    private $repoPlace;

    /**
     * @var \AppBundle\Repository\CityRepository
     */
    private $repoCity;

    /**
     * @var \AppBundle\Repository\ZipCityRepository
     */
    private $repoZipCity;

    /**
     * @var EventHandler
     */
    private $handler;

    /**
     * @var Firewall
     */
    private $firewall;

    /**
     * @var array
     */
    private $sites;

    /**
     * @var array
     */
    private $villes;

    /**
     * @var EchantillonHandler
     */
    private $echantillonHandler;

    /**
     * @var ExplorationHandler
     */
    private $explorationHandler;

    public function __construct(EntityManagerInterface $em, EventHandler $handler, Firewall $firewall, EchantillonHandler $echantillonHandler)
    {
        $this->em = $em;
        $this->repoAgenda = $em->getRepository('AppBundle:Agenda');
        $this->repoPlace = $em->getRepository('AppBundle:Place');
        $this->repoSite = $em->getRepository('AppBundle:Site');
        $this->repoCity = $em->getRepository('AppBundle:City');
        $this->repoZipCity = $em->getRepository("AppBundle:ZipCity");
        $this->handler = $handler;
        $this->firewall = $firewall;
        $this->echantillonHandler = $echantillonHandler;
        $this->explorationHandler = new ExplorationHandler();

        $this->output = null;

        $this->villes = [];
        $this->sites = [];
        $this->stats = [];
    }

    /**
     * @return ExplorationHandler
     */
    public function getExplorationHandler()
    {
        return $this->explorationHandler;
    }

    /**
     * @param Agenda $event
     * @return Agenda
     */
    public function handleOne(Agenda $event)
    {
        return $this->handleMany([$event])[0];
    }

    /**
     * @param Agenda[] $events
     * @param ParserInterface $parser
     * @return Agenda[]
     */
    public function handleManyCLI(array $events, ParserInterface $parser)
    {
        $events = $this->handleMany($events);
        if ($parser instanceof FaceBookParser) {
            $this->handleIdsToMigrate($parser);
        }

        $historique = $this->explorationHandler->stop($parser->getNomData());
        $this->em->persist($historique);
        $this->em->flush();

        Monitor::writeln("");
        Monitor::displayStats();
        Monitor::displayTable([
            'NEWS' => $this->explorationHandler->getNbInserts(),
            'UPDATES' => $this->explorationHandler->getNbUpdates(),
            'BLACKLISTS' => $this->explorationHandler->getNbBlackLists(),
            'EXPLORATIONS' => $this->explorationHandler->getNbExplorations(),
        ]);

        return $events;
    }

    /**
     * @param Agenda[] $events
     * @return Agenda[]
     */
    public function handleMany(array $events)
    {
        $this->explorationHandler->start();

        if (!count($events)) {
            return [];
        }

//        $this->loadSites();
//        $this->loadVilles();

        //On récupère toutes les explorations existantes pour ces événements
        $this->loadExplorations($events);

        //Grace à ça, on peut déjà filtrer une bonne partie des événements
        $this->doFilterAndClean($events);

        //On met ensuite à jour le statut de ces explorations en base
        $this->flushExplorations();

        $allowedEvents = $this->getAllowedEvents($events);
        $notAllowedEvents = $this->getNotAllowedEvents($events);
        unset($events);

        $nbNotAllowedEvents = count($notAllowedEvents);
        for ($i = 0; $i < $nbNotAllowedEvents; $i++) {
            $this->explorationHandler->addBlackList();
        }
        return $notAllowedEvents + $this->mergeWithDatabase($allowedEvents);
    }

    private function handleIdsToMigrate(FaceBookParser $parser)
    {
        $ids = $parser->getIdsToMigrate();

        if (!count($ids)) {
            return;
        }

        $eventOwners = $this->repoAgenda->findBy([
            'facebookOwnerId' => array_keys($ids),
        ]);

        $events = $this->repoAgenda->findBy([
            'facebookEventId' => array_keys($ids),
        ]);

        $events = array_merge($events, $eventOwners);
        foreach ($events as $event) {
            if (isset($ids[$event->getFacebookEventId()])) {
                $event->setFacebookEventId($ids[$event->getFacebookEventId()]);
            }

            if (isset($ids[$event->getFacebookOwnerId()])) {
                $event->setFacebookOwnerId($ids[$event->getFacebookOwnerId()]);
            }
            $this->em->persist($event);
        }

        $places = $this->repoPlace->findBy([
            'facebookId' => array_keys($ids)
        ]);

        foreach ($places as $place) {
            if (isset($ids[$place->getFacebookId()])) {
                $place->setFacebookId($ids[$place->getFacebookId()]);
            }
            $this->em->persist($place);
        }

        $this->em->flush();
    }

    /**
     * @param Agenda[] $events
     * @return Agenda[]
     */
    private function getAllowedEvents(array $events)
    {
        return array_filter($events, [$this->firewall, 'isValid']);
//        usort($events, function (Agenda $a, Agenda $b) {
//            if ($a->getSite() === $b->getSite()) {
//                return 0;
//            }
//
//            return $a->getSite()->getId() - $b->getSite()->getId();
//        });
//
//        return $events;
    }

    /**
     * @param Agenda[] $events
     * @return Agenda[]
     */
    private function getNotAllowedEvents(array $events)
    {
        return array_filter($events, function ($event) {
            return !$this->firewall->isValid($event);
        });
    }

    /**
     * @param Agenda[] $events
     * @return array
     */
    private function getChunks(array $events)
    {
        $chunks = [];
        foreach ($events as $i => $event) {
            $place = $event->getPlace();

            if($place->getCity()) {
                $key = "city.". $place->getCity()->getId();
            }elseif($place->getZipCity()) {
                $key = "zip_city.".$place->getZipCity()->getId();
            }else {
                $key = null; //TODO: Add country
            }

            if($key) {
                $chunks[$key][$i] = $event;
            }
        }

        foreach ($chunks as $i => $chunk) {
            $chunks[$i] = array_chunk($chunk, self::BATCH_SIZE, true);
        }

        return $chunks;
    }

    /**
     * @param array $chunks
     * @return Agenda[]
     */
    private function unChunk(array $chunks)
    {
        $flat = [];
        foreach ($chunks as $chunk) {
            $flat = array_merge($flat, $chunk);
        }

        return $flat;
    }

    /**
     * @param Agenda[] $events
     * @return Agenda[]
     */
    private function mergeWithDatabase(array $events)
    {
        Monitor::createProgressBar(count($events));

        $chunks = $this->getChunks($events);

        //Par localisation
        foreach ($chunks as $chunk) {
            $this->echantillonHandler->prefetchPlaceEchantillons($this->unChunk($chunk));

            //Par n événements
            foreach ($chunk as $currentEvents) {
                $this->echantillonHandler->prefetchEventEchantillons($currentEvents);

                //Par événement
                foreach ($currentEvents as $i => $event) {
                    /**
                     * @var Agenda $event
                     */
                    $echantillonPlaces = $this->echantillonHandler->getPlaceEchantillons($event->getPlace());
                    $echantillonEvents = $this->echantillonHandler->getEventEchantillons($event);

                    $oldUser = $event->getUser();
                    $event = $this->handler->handle($echantillonEvents, $echantillonPlaces, $event);
                    $this->firewall->filterEventIntegrity($event, $oldUser);
                    if (!$this->firewall->isValid($event)) {
                        $this->explorationHandler->addBlackList();
                    } else {
                        Monitor::advanceProgressBar();
                        $event = $this->em->merge($event);
                        $this->echantillonHandler->addNewEvent($event);
                        if ($this->firewall->isPersisted($event)) {
                            $this->explorationHandler->addUpdate();
                        } else {
                            $this->explorationHandler->addInsert();
                        }
                    }
                    $events[$i] = $event;
                }
                $this->commit();
                $this->clearEvents();
                $this->firewall->deleteCache();
            }
            $this->clearPlaces();
        }
        Monitor::finishProgressBar();

        return $events;
    }

    private function clearPlaces()
    {
        $this->em->clear(Place::class);
        $this->em->clear(ZipCity::class);
        $this->em->clear(City::class);
        $this->echantillonHandler->clearPlaces();
    }

    private function clearEvents() {
        $this->em->clear(Agenda::class);
        $this->echantillonHandler->clearEvents();
    }

    private function commit()
    {
        try {
            $this->em->flush();
        } catch (\Exception $e) {
            Monitor::writeln(sprintf(
                "<error>%s</error>",
                $e->getMessage()
            ));
        }
    }

    private function loadExplorations(array $events)
    {
        $fb_ids = $this->getExplorationsFBIds($events);

        if (count($fb_ids)) {
            $this->firewall->loadExplorations($fb_ids);
        }
    }

    private function flushExplorations()
    {
        $explorations = $this->firewall->getExplorations();

        $batchSize = 200;
        $nbBatches = ceil(count($explorations) / $batchSize);

        for ($i = 0; $i < $nbBatches; $i++) {
            $currentExplorations = array_slice($explorations, $i * $batchSize, $batchSize);
            foreach ($currentExplorations as $exploration) {
                /**
                 * @var Exploration $exploration
                 */
                $exploration->setReason($exploration->getReject()->getReason());
                $this->explorationHandler->addExploration();
                $this->em->persist($exploration);
            }
            $this->em->flush();
        }
        $this->em->clear(Exploration::class);
        $this->firewall->flushExplorations();
    }

    /**
     * @param Agenda[] $events
     */
    private function doFilterAndClean(array $events)
    {
        foreach ($events as $event) {
            $event->setReject(new Reject);

            if ($event->getPlace()) {
                $event->getPlace()->setReject(new Reject);
            }

            if ($event->getFacebookEventId()) {
                $exploration = $this->firewall->getExploration($event->getFacebookEventId());

                //Une exploration a déjà eu lieu
                if ($exploration) {
                    $this->firewall->filterEventExploration($exploration, $event);
                    $reject = $exploration->getReject();

                    //Celle-ci a déjà conduit à l'élimination de l'événement
                    if (!$reject->isValid()) {
                        $event->getReject()->setReason($reject->getReason());
                        continue;
                    }
                }
            }

            //Même algorithme pour le lieu
            if ($event->getPlace() && $event->getPlace()->getFacebookId()) {
                $exploration = $this->firewall->getExploration($event->getPlace()->getFacebookId());

                if ($exploration && !$this->firewall->hasPlaceToBeUpdated($exploration) && !$exploration->getReject()->isValid()) {
                    $event->getReject()->addReason($exploration->getReject()->getReason());
                    $event->getPlace()->getReject()->setReason($exploration->getReject()->getReason());
                    continue;
                }
            }

            $this->firewall->filterEvent($event);
            if ($this->firewall->isValid($event)) {
                $this->guessEventLocation($event->getPlace());
                $this->firewall->filterEventLocation($event);
                $this->handler->cleanEvent($event);
            }
        }
    }

    /**
     * @param Place $place
     */
    public function guessEventLocation(Place $place)
    {
        //Pas besoin de trouver à nouveau un lieu déjà calculé
        if($place->getZipCity() || $place->getCity()) {
            return;
        }

        //Pas besoin de chercher un lieu dont le pays n'est même pas connu
        if(! $place->getId() && ! $place->getCountryName()) {
            return;
        }

        //Nom de lieu (avec ou sans coordoonnées GPS)
        if(!$place->getCodePostal() && !$place->getVille()) {
            //TODO: Geocoding
            $place->getReject()->addReason(Reject::BAD_PLACE_LOCATION);
            return;
            //Géocoding à partir du nom du lieu ou de l'adresse facebook. (Ex: Avenue de Castres, 31500 Toulouse, France
        }

        //Location fournie -> Vérification dans la base des villes existantes
        if($place->getCodePostal() || $place->getVille()) {
            $zipCity = null;
            $city = null;
            //Un code postal (et peut-être une ville) est fourni
            if($place->getCodePostal()) {
                $zipCities = $this->repoZipCity->findByPostalCodeAndCity($place->getCodePostal(), $place->getVille());

                if($place->getVille() && count($zipCities) === 0) {
                    $zipCities = $this->repoZipCity->findByPostalCode($place->getCodePostal());
                }

                //On tente de trouver un candidat si pas de strict match
                if($place->getVille() && count($zipCities) > 1) {
                    $zipCities = array_values(array_filter($zipCities, function(ZipCity $zipCity) use($place) {
                        return $this->handler->getComparator()->isSubInSub($zipCity->getName(), $place->getVille());
                    }));
                }

                //Aucun code postal n'a été trouvé -> Pas besoin de continuer plus loin
                if(count($zipCities) === 0) {
                    $place->getReject()->addReason(Reject::BAD_PLACE_LOCATION);
                    return;
                }elseif(count($zipCities) > 1) {
                    //Plusieurs villes ont été trouvées pour ce code postal -> Impossible de déterminer quelle ville choisir
                    $place->getReject()->addReason(Reject::AMBIGOUS_ZIP);
                    return;
                }

                $zipCity = $zipCities[0];
                $city = $zipCity->getParent();
            }else {
                //Un nom de ville est fourni
                $zipCities = $this->repoZipCity->findByPostalCodeAndCity($place->getCodePostal(), $place->getVille());

                //Aucune ville trouvée -> Pas besoin de continuer plus loin
                if(count($zipCities) === 0) {
                    $place->getReject()->addReason(Reject::BAD_PLACE_LOCATION);
                    return;
                }elseif(count($zipCities) === 1) {
                    //Une seule ville trouvée -> Cool
                    $zipCity = $zipCities[0];
                }

                //Pas de ville trouvée -> on va chercher dans les City
                if(! $zipCity) {
                    $cities = $this->repoCity->findByName($place->getVille());
                    if(count($cities) === 0) {
                        $place->getReject()->addReason(Reject::BAD_PLACE_LOCATION);
                        return;
                    }elseif(count($cities) > 1) {
                        $place->getReject()->addReason(Reject::AMBIGOUS_CITY);
                        return;
                    }
                    
                    $city = $cities[0];
                }else {
                    $city = $zipCity->getParent();
                }
            }
            
            $place->setCity($city)->setZipCity($zipCity);
            //TODO: Ajouter le country ici
        }

//        $key = $this->firewall->getVilleHash($event->getPlace()->getVille());
//
//        $site = null;
//        if (isset($this->sites[$key])) {
//            $site = $this->em->getReference(Site::class, $this->sites[$key]->getId());
//        } elseif (isset($this->villes[$key])) {
//            $site = $this->em->getReference(Site::class, $this->villes[$key]);
//        } else {
//            foreach ($this->sites as $testSite) {
//                if ($this->firewall->isLocationBounded($event->getPlace(), $testSite)) {
//                    $site = $this->em->getReference(Site::class, $testSite->getId());
//                    break;
//                } elseif ($this->firewall->isLocationBounded($event, $testSite)) {
//                    $site = $this->em->getReference(Site::class, $testSite->getId());
//                    break;
//                }
//            }
//        }
//
//        if ($site) {
//            $event->setSite($site);
//            $event->getPlace()->setSite($site);
//        }
    }

    /**
     * @param Agenda[] $events
     * @return int[]
     */
    private function getExplorationsFBIds(array $events)
    {
        $fbIds = [];
        foreach ($events as $event) {
            if ($event->getFacebookEventId()) {
                $fbIds[$event->getFacebookEventId()] = true;
            }

            if ($event->getPlace() && $event->getPlace()->getFacebookId()) {
                $fbIds[$event->getPlace()->getFacebookId()] = true;
            }
        }

        return array_keys($fbIds);
    }
}
