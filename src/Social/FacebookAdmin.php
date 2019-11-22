<?php

namespace App\Social;

use App\App\SocialManager;
use App\Entity\SiteInfo;
use App\Picture\EventProfilePicture;
use App\Utils\Monitor;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\FacebookClient;
use Facebook\FacebookResponse;
use Facebook\GraphNodes\GraphNode;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Description of Facebook.
 *
 * @author guillaume
 */
class FacebookAdmin extends FacebookListEvents
{
    /**
     * @var SiteInfo
     */
    protected $siteInfo;

    /**
     * @var array
     */
    protected $cache;

    /**
     * @var EntityManagerInterface
     */
    protected $om;

    /**
     * @var array
     */
    protected $oldIds;

    /**
     * @var bool
     */
    protected $_isInitialized;

    /**
     * @var string
     */
    protected $pageAccessToken;

    public function __construct(array $config, TokenStorageInterface $tokenStorage, RouterInterface $router, SessionInterface $session, RequestStack $requestStack, LoggerInterface $logger, EventProfilePicture $eventProfilePicture, SocialManager $socialManager, EntityManagerInterface $om)
    {
        parent::__construct($config, $tokenStorage, $router, $session, $requestStack, $logger, $eventProfilePicture, $socialManager);

        $this->om = $om;
        $this->cache = [];
        $this->oldIds = [];
        $this->_isInitialized = false;
        $this->pageAccessToken = null;
    }

    public function postNews($title, $url, $imageUrl)
    {
        //Authentification
        $accessToken = $this->getPageAccessToken();

        $response = $this->client->post('/' . $this->socialManager->getFacebookIdPage() . '/feed/', [
            'message' => $title,
            'name' => 'By Night Magazine',
            'link' => $url,
            'picture' => $imageUrl,
            'description' => $title,
        ], $accessToken);

        $post = $response->getGraphNode();

        return $post->getField('id');
    }

    protected function getPageAccessToken()
    {
        $this->init();

        $accessToken = $this->siteInfo ? $this->siteInfo->getFacebookAccessToken() : null;
        $response = $this->client->get('/' . $this->socialManager->getFacebookIdPage() . '?fields=access_token', [], $accessToken);
        $datas = $response->getDecodedBody();

        return $datas['access_token'];
    }

    protected function init()
    {
        parent::init();

        if (!$this->_isInitialized) {
            $this->_isInitialized = true;
            $this->siteInfo = $this->socialManager->getSiteInfo();

            if ($this->siteInfo && $this->siteInfo->getFacebookAccessToken()) {
                $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
            }
            $this->guessAppAccessToken();
        }
    }

    protected function guessAppAccessToken()
    {
        $this->pageAccessToken = $this->client->getApp()->getAccessToken();
    }

    public function getNumberOfCount()
    {
        $this->init();

        try {
            $page = $this->getPageFromId($this->socialManager->getFacebookIdPage(), ['fields' => 'fan_count']);

            return $page->getField('fan_count');
        } catch (FacebookSDKException $ex) {
            $this->logger->error($ex);
        }

        return 0;
    }

    public function getPageFromId($id_page, $params = [])
    {
        $this->init();
        $key = 'pages.' . $id_page;
        if (!isset($this->cache[$key])) {
            $accessToken = $this->siteInfo ? $this->siteInfo->getFacebookAccessToken() : null;
            $request = $this->client->sendRequest('GET',
                '/' . $id_page,
                $params,
                $accessToken
            );

            $this->cache[$key] = $request->getGraphPage();
        }

        return $this->cache[$key];
    }

    public function getUserImagesFromIds(array $ids_users)
    {
        $urls = [];
        foreach ($ids_users as $id_user) {
            $urls[$id_user] = sprintf(
                '%s/%s/picture?width=1500&height=1500',
                FacebookClient::BASE_GRAPH_URL,
                $id_user
            );
        }

        return $urls;
    }

    public function getEventStatsFromIds(array $ids_event, $idsPerRequest = 50)
    {
        $this->init();
        $requestFunction = function (array $current_ids) {
            return $this->client->request('GET', '/', [
                'ids' => \implode(',', $current_ids),
                'fields' => self::STATS_FIELDS,
            ], $this->getAccessToken());
        };

        $dataHandlerFunction = function (GraphNode $data) {
            return [$data->getField('id') => [
                'participations' => $data->getField('attending_count'),
                'interets' => $data->getField('maybe_count'),
                'url' => $this->getPagePictureURL($data),
            ]];
        };

        return $this->getOjectsFromIds($ids_event, $requestFunction, $dataHandlerFunction, $idsPerRequest);
    }

    protected function getAccessToken()
    {
        return $this->pageAccessToken ?: ($this->siteInfo ? $this->siteInfo->getFacebookAccessToken() : null);
    }

    private function getOjectsFromIds(array $ids, callable $requestFunction, callable $dataHandlerFunction, $idsPerRequest = 50, $requestPerBatch = 50)
    {
        $idsPerBatch = $requestPerBatch * $idsPerRequest;
        $nbBatchs = \ceil(\count($ids) / $idsPerBatch);
        $finalDatas = [];
        for ($i = 0; $i < $nbBatchs; ++$i) {
            $requests = [];
            $batch_ids = \array_slice($ids, $i * $idsPerBatch, $idsPerBatch);
            $nbIterations = \ceil(\count($batch_ids) / $idsPerRequest);

            try {
                for ($j = 0; $j < $nbIterations; ++$j) {
                    $current_ids = \array_slice($batch_ids, $j * $idsPerRequest, $idsPerRequest);
                    $requests[] = $requestFunction($current_ids);
                }

                //Exécution du batch
                Monitor::bench('fb::getOjectsFromIds', function () use (&$requests, &$finalDatas, $dataHandlerFunction) {
                    $responses = $this->client->sendBatchRequest($requests, $this->getAccessToken());

                    //Traitement des réponses
                    foreach ($responses as $response) {
                        /*
                         * @var FacebookResponse
                         */
                        if ($response->isError()) {
                            $e = $response->getThrownException();
                            Monitor::writeln('<error>Erreur dans le batch de la récupération des objets FB : ' . ($e ? $e->getMessage() : 'Erreur Inconnue') . '</error>');
                        } else {
                            $datas = $this->findAssociativeEvents($response);
                            foreach ($datas as $data) {
                                $currentDatas = $dataHandlerFunction($data);
                                foreach ($currentDatas as $key => $currentData) {
                                    $finalDatas[$key] = $currentData;
                                }
                            }
                        }
                    }
                });
            } catch (FacebookSDKException $ex) {
                Monitor::writeln('<error>Erreur dans la récupération détaillée des objets : ' . $ex->getMessage() . '</error>');
            }
        }

        return $finalDatas;
    }

    public function getEventFromId($id_event, $fields = null)
    {
        $this->init();
        $key = 'events' . $id_event;
        if (!isset($this->cache[$key])) {
            $request = $this->client->sendRequest('GET', '/' . $id_event, [
                'fields' => $fields ?: self::FIELDS,
            ], $this->getAccessToken());

            $this->cache[$key] = $request->getGraphEvent();
        }

        return $this->cache[$key];
    }

    public function getEventMembres($id_event, $offset, $limit)
    {
        $this->init();
        $participations = [];
        $interets = [];

        try {
            $fields = \str_replace(
                ['%offset%', '%limit%'],
                [$offset, $limit],
                self::MEMBERS_FIELDS
            );

            $request = $this->client->sendRequest('GET', '/' . $id_event, [
                'fields' => $fields,
            ], $this->siteInfo ? $this->siteInfo->getFacebookAccessToken() : null);
            $graph = $request->getGraphPage();

            $participations = $this->findPaginated($graph->getField('attending'), $limit);
            $interets = $this->findPaginated($graph->getField('maybe'), $limit);
        } catch (FacebookSDKException $ex) {
            $this->logger->error($ex);
        }

        return [
            'interets' => $interets,
            'participations' => $participations,
        ];
    }

    public function getEventCountStats($id_event)
    {
        $this->init();
        $request = $this->client->sendRequest('GET', '/' . $id_event, [
            'fields' => 'attending_count,maybe_count',
        ], $this->getAccessToken());

        $graph = $request->getGraphPage();

        return [
            'participations' => $graph->getField('attending_count'),
            'interets' => $graph->getField('maybe_count'),
        ];
    }

    public function getIdsToMigrate()
    {
        return $this->oldIds;
    }

    public function getEventsFromUsers(array $id_users, DateTime $since)
    {
        $this->init();

        return $this->handleEdge($id_users, '/events', function (array $current_ids) use ($since) {
            return [
                'ids' => \implode(',', $current_ids),
                'since' => $since->format('Y-m-d'),
                'fields' => self::FIELDS,
                'limit' => 1000,
            ];
        }, function (FacebookResponse $response) {
            return $this->findPaginatedNodes($response);
        });
    }

    private function handleEdge(array $datas, $edge, callable $getParams, callable $responseToDatas, $idsPerRequest = 10, $requestsPerBatch = 50, $accessToken = null)
    {
        if (!$accessToken) {
            $accessToken = $this->getAccessToken();
        }

        $idsPerBatch = $requestsPerBatch * $idsPerRequest;
        $nbBatchs = \ceil(\count($datas) / $idsPerBatch);
        $finalNodes = [];

        for ($i = 0; $i < $nbBatchs; ++$i) {
            $requests = [];
            $batch_datas = \array_slice($datas, $i * $idsPerBatch, $idsPerBatch);
            $nbIterations = \ceil(\count($batch_datas) / $idsPerRequest);

            for ($j = 0; $j < $nbIterations; ++$j) {
                $current_datas = \array_slice($batch_datas, $j * $idsPerRequest, $idsPerRequest);
                $params = \call_user_func($getParams, $current_datas);
                $requests[] = $this->client->request('GET', $edge, $params, $accessToken);
            }

            //Exécution du batch
            $currentNodes = Monitor::bench(\sprintf('fb::handleEdge (%s)', $edge), function () use ($requests, $responseToDatas, $edge, $i, $nbBatchs, $accessToken) {
                $responses = $this->client->sendBatchRequest($requests, $accessToken);

                $currentNodes = [];
                //Traitement des réponses
                $fetchedNodes = 0;
                foreach ($responses as $response) {
                    /*
                     * @var FacebookResponse
                     */
                    if ($response->isError()) {
                        $e = $response->getThrownException();
                        Monitor::writeln(\sprintf(
                            "<error>Erreur dans le parcours de l'edge %s : %s</error>",
                            $edge,
                            $e ? $e->getMessage() : 'Erreur Inconnue'
                        ));

                        if ($e instanceof FacebookResponseException) {
                            $this->handleResponseException($e);
                        }
                    } else {
                        $datas = \call_user_func($responseToDatas, $response);
                        $fetchedNodes += \count($datas);
                        $currentNodes = \array_merge($currentNodes, $datas);
                    }
                }
                Monitor::writeln(\sprintf(
                    '%d / %d : Récupération de <info>%d</info> node(s)',
                    $i + 1,
                    $nbBatchs,
                    $fetchedNodes
                ));

                return $currentNodes;
            });

            $finalNodes = \array_merge($finalNodes, $currentNodes);
        }

        return $finalNodes;
    }

    private function handleResponseException(FacebookResponseException $e)
    {
        if (\preg_match("#ID (\d+) was migrated to \w+ ID (\d+)#i", $e->getMessage(), $matches)) {
            $this->oldIds[$matches[1]] = $matches[2];
        }
    }

    public function getEventsFromPlaces(array $id_places, DateTime $since)
    {
        $this->init();

        return $this->handleEdge($id_places, '/events', function (array $current_ids) use ($since) {
            return [
                'ids' => \implode(',', $current_ids),
                'since' => $since->format('Y-m-d'),
                'fields' => self::FIELDS,
                'limit' => 1000,
            ];
        }, function (FacebookResponse $response) {
            return $this->findPaginatedNodes($response);
        });
    }

    public function getEventsFromKeywords(array $keywords, DateTime $since)
    {
        $this->init();

        return $this->handleEdge($keywords, '/search', function (array $keywords) use ($since) {
            $keyword = $keywords[0];

            return [
                'q' => \sprintf('"%s"', $keyword),
                'type' => 'event',
                'fields' => self::FIELDS,
                'since' => $since->format('Y-m-d'),
                'limit' => 1000,
            ];
        }, function (FacebookResponse $response) {
            return $this->findPaginated($response->getGraphEdge());
        }, 1, 50, $this->siteInfo ? $this->siteInfo->getFacebookAccessToken() : null);
    }

    public function getPlacesFromGPS(array $coordonnees)
    {
        $this->init();

        return $this->handleEdge($coordonnees, '/search', function (array $coordonnees) {
            $coordonnee = $coordonnees[0];

            return [
                'q' => '',
                'type' => 'place',
                'center' => \sprintf('%s,%s', $coordonnee['latitude'], $coordonnee['longitude']),
                'distance' => 4000,
                'fields' => 'id',
                'limit' => 1000,
            ];
        }, function (FacebookResponse $response) {
            return $this->findPaginated($response->getGraphEdge());
        }, 1);
    }
}
