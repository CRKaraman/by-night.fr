<?php

namespace App\Parser\Common;

use App\Parser\EventParser;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use function GuzzleHttp\Promise\all;
use GuzzleHttp\Promise\FulfilledPromise;
use function GuzzleHttp\Psr7\copy_to_string;
use Psr\Http\Message\ResponseInterface;

/**
 * @author Guillaume SAINTHILLIER
 */
class OpenAgendaParser extends EventParser
{
    private const AGENDA_IDS = [
        93184572, // https://openagenda.com/fetedelascience2019_hautsdefrance?lang=fr
        49405812, // https://openagenda.com/saison-culturelle-en-france?lang=fr
        7430297, // https://openagenda.com/agenda-culturel-grand-est?lang=fr
        1108324, // https://openagenda.com/un-air-de-bordeaux?lang=fr
        92445297, // https://openagenda.com/fetedelascience2019_occitanie?lang=fr
        13613180, // https://openagenda.com/grand-chatellerault?lang=fr
        87948516, // https://openagenda.com/agenda-different-seine-maritime?lang=fr
        93184572, // https://openagenda.com/fetedelascience2019_hautsdefrance?lang=fr
        41148947, // https://openagenda.com/terres-de-montaigu?lang=fr
        22126321, // https://openagenda.com/tootsweet?lang=fr
        43896350, // https://openagenda.com/iledefrance?lang=fr
        70171993, // https://openagenda.com/territoire-labaule-presquile-guerande-atlantique?lang=fr
        88167337, // https://openagenda.com/mediatheque-bibliotheques-st-denis-reunion?lang=fr
        69653526, // https://openagenda.com/france-numerique?lang=fr
        89904399, // https://openagenda.com/metropole-europeenne-de-lille?lang=fr
    ];

    private const EVENT_BATCH_SIZE = 300;

    private $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    private function getInfoEvent(array $event)
    {
        $dateDebut = DateTime::createFromFormat('Y-m-d H:i', $event['firstDate'] . ' ' . $event['firstTimeStart']);
        $dateFin = DateTime::createFromFormat('Y-m-d H:i', $event['lastDate'] . ' ' . $event['lastTimeEnd']);

        $horaires = null;
        if ($dateDebut instanceof DateTime && $dateFin instanceof DateTime && $dateDebut->format('YmdHi') !== $dateFin->format('YmdHi')) {
            $horaires = \sprintf('De %s à %s', $dateDebut->format("H\hi"), $dateFin->format("H\hi"));
        } elseif ($dateDebut instanceof DateTime) {
            $horaires = \sprintf('A %s', $dateDebut->format("H\hi"));
        }

        return [
            'nom' => $event['title']['fr'],
            'descriptif' => $event['html']['fr'],
            'source' => $event['canonicalUrl'],
            'external_id' => 'OA-' . $event['uid'],
            'url' => $event['originalImage'],
            'fb_date_modification' => new \DateTime($event['updatedAt']),
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'horaires' => $horaires,
            'latitude' => $event['latitude'],
            'longitude' => $event['longitude'],
            'adresse' => $event['address'],
            'placeStreet' => str_replace($event['postalCode'] . ' ' . $event['city'], '', $event['address']),
            'placeName' => $event['locationName'],
            'placePostalCode' => $event['postalCode'],
            'placeCity' => $event['city'],
            'placeCountryName' => $event['location']['countryCode'],
            'placeExternalId' => $event['locationUid'],
        ];
    }

    public function getRawEvents()
    {
        $promises = [];
        foreach (self::AGENDA_IDS as $id) {
            $promises[] = $this->makeRequest($id);
        }
        $events = all($promises)->wait();
        $events = array_merge(...$events);
        foreach ($events as $i => $event) {
            $events[$i] = $this->getInfoEvent($event);
        }

        return $events;
    }

    private function makeRequest(int $agendaId, int $page = 0)
    {
        return $this->sendRequest($agendaId, $page)
            ->then(function (array $result) use ($agendaId) {
                $events = $this->formatArray($result['events']);

                $nbPages = ceil($result['total'] / self::EVENT_BATCH_SIZE);

                if ($nbPages > 1) {
                    $requests = function ($nbPages) use ($agendaId) {
                        for ($page = 1; $page <= $nbPages - 1; ++$page) {
                            yield function () use ($agendaId, $page) {
                                return $this->sendRequest($agendaId, $page);
                            };
                        }
                    };

                    $pool = new Pool($this->client, $requests($nbPages), [
                        'concurrency' => 5,
                        'fulfilled' => function (array $results) use (&$events) {
                            $events += $this->formatArray($results['events']);
                        },
                    ]);
                    $pool->promise()->wait();
                }

                return new FulfilledPromise($events);
            });
    }

    private function formatArray(array $events)
    {
        $formated = [];
        foreach ($events as $event) {
            $formated[$event['uid']] = $event;
        }

        return $formated;
    }

    private function sendRequest(int $agendaId, int $page)
    {
        //https://openagenda.zendesk.com/hc/fr/articles/203034982-L-export-JSON-d-un-agenda
        $uri = sprintf('https://openagenda.com/agendas/%s/events.json?oaq[lang]=fr&limit=%d&offset=%d', $agendaId, self::EVENT_BATCH_SIZE, $page * self::EVENT_BATCH_SIZE);

        return $this->client
            ->getAsync($uri)
            ->then(function (ResponseInterface $result) {
                return json_decode(copy_to_string($result->getBody()), true);
            });
    }

    public function getNomData()
    {
        return 'Open Agenda';
    }
}
