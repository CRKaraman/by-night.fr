<?php


namespace App\Parser\Common;


use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use App\Social\EventBrite;
use Psr\Log\LoggerInterface;

class EventBriteParser extends AbstractParser
{
    private const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    /** @var EventBrite */
    private $client;

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer, EventBrite $client)
    {
        parent::__construct($logger, $eventProducer);

        $this->client = $client;
    }

    public function parse(bool $incremental): void
    {
        if ($incremental) {
            $searchParams['date_modified.range_start'] = (new \DateTime())->modify('-1 days')->setTime(0, 0, 1)->format(self::DATE_FORMAT);
        } else {
            $searchParams['start_date.range_start'] = (new \DateTime())->setTime(0, 0, 1)->format(self::DATE_FORMAT);
        }

        $hasNextEvents = true;
        $page = 1;
        while ($hasNextEvents) {
            $searchParams += [
                'page' => $page++
            ];

            $events = $this
                ->client
                ->getEventResults($searchParams)
                ->then(function (array $result) use (&$hasNextEvents) {
                    $hasNextEvents = $result['pagination']['has_more_items'];
                    return $result['events'];
                })
                ->wait();

            $venues = [];
            foreach ($events as $event) {
                $venues[] = $event['venue_id'];
            }
            $venues = $this->client->getEventVenues(array_unique(array_filter($venues)));

            foreach ($events as $event) {
                $event['venue'] = $venues[$event['venue_id']] ?? null;
                $event = $this->getInfoEvent($event);
                if (null === $event) {
                    continue;
                }
                $this->publish($event);
            }
        }
    }

    private function getInfoEvent(array $event): ?array
    {
        if (!$event['venue']) {
            return null;
        }

        $venue = $event['venue'];

        if (!$venue['name']) {
            return null;
        }

        $address = $venue['address'];

        $dateDebut = new \DateTime($event['start']['local']);
        $dateFin = new \DateTime($event['end']['local']);

        if ($dateDebut->getTimestamp() === $dateFin->getTimestamp()) {
            $horaires = sprintf('À %s', $dateDebut->format('H\hi'));
        } else {
            $horaires = sprintf('De %s à %s', $dateDebut->format('H\hi'), $dateFin->format('H\hi'));
        }

        $tab_infos = [
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'horaires' => $horaires,
            'nom' => $event['name']['text'],
            'descriptif' => $event['description']['html'],
            'source' => $event['url'],
            'external_id' => 'EB-' . $event['id'],
            'url' => $event['logo']['original']['url'],
            'fb_date_modification' => new \DateTime($event['changed']),
            'latitude' => (float)$address['latitude'],
            'longitude' => (float)$address['longitude'],
            'placeStreet' => trim(sprintf('%s %s', $address['address_1'], $address['address_2'])),
            'placePostalCode' => $address['postal_code'],
            'placeCity' => $address['city'],
            'placeCountryName' => $address['country'],
            'placeName' => $venue['name'],
            'placeExternalId' => 'EB-' . $venue['id'],
        ];

        if ($event['category_id']) {
            $category = $this->client->getEventCategory($event['category_id'], $event['locale']);
            $tab_infos['type_manifestation'] = $category['name_localized'];

            if ($event['subcategory_id']) {
                foreach ($category['subcategories'] as $subcategory) {
                    if ($subcategory['id'] === $event['subcategory_id']) {
                        $tab_infos['categorie_manifestation'] = $subcategory['name_localized'];
                    }
                }
            }
        }

        return $tab_infos;
    }

    public static function getParserName(): string
    {
        return 'EventBrite';
    }
}