<?php

namespace App\Repository;

use App\Entity\Country;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * CityRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class CityRepository extends EntityRepository
{
    public function findSiteMap()
    {
        return $this->createQueryBuilder('c')
            ->select('c.slug')
            ->join('App:Place', 'p', 'WITH', 'p.city = c')
            ->join('App:Agenda', 'a', 'WITH', 'a.place = p')
            ->groupBy('c.slug')
            ->getQuery()
            ->iterate();
    }

    public function findRandomNames(Country $country = null, $limit = 5)
    {
        if ($country) {
            $results = $this
                ->createQueryBuilder('c')
                ->select('c.name, c.slug')
                ->join('c.country', 'c2')
                ->where('c2 = :country')
                ->setParameter('country', $country->getId())
                ->orderBy('c.population', 'DESC')
                ->setMaxResults(50)
                ->getQuery()
                ->getScalarResult();
        } else {
            $results = $this
                ->createQueryBuilder('c')
                ->select('c.name, c.slug')
                ->orderBy('c.population', 'DESC')
                ->setMaxResults(50)
                ->getQuery()
                ->getScalarResult();
        }

        \shuffle($results);
        return \array_slice($results, 0, $limit);
    }

    public function findLocations()
    {
        $results = $this->createQueryBuilder('c')
            ->select('c.latitude, c.longitude')
            ->where('c.latitude IS NOT NULL')
            ->andWhere('c.longitude IS NOT NULL')
            ->orderBy('c.population', 'DESC')
            ->getQuery()
            ->setMaxResults(50)
            ->getScalarResult();

        return $results;
    }

    public function findByName($city, $country = null)
    {
        $cities = [];
        $city = \preg_replace("#(^|\s)st\s#i", '$1saint ', $city);
        $city = \str_replace('’', "'", $city);
        $cities[] = $city;
        $cities[] = \str_replace(' ', '-', $city);
        $cities[] = \str_replace('-', ' ', $city);
        $cities[] = \str_replace("'", '', $city);
        $cities = \array_unique($cities);

        $qb = $this
            ->createQueryBuilder('c')
            ->where('c.name IN (:cities)')
            ->setParameter('cities', $cities);

        if ($country) {
            $qb
                ->andWhere('c.country = :country')
                ->setParameter('country', $country);
        }

        return $qb
            ->getQuery()
            ->setCacheable(true)
            ->setCacheMode(ClassMetadata::CACHE_USAGE_READ_ONLY)
            ->useResultCache(true)
            ->useQueryCache(true)
            ->getResult();
    }

    public function findTopPopulation($maxResults)
    {
        return $this
            ->createQueryBuilder('c')
            ->orderBy('c.population', 'DESC')
            ->setMaxResults($maxResults)
            ->getQuery()
            ->setCacheable(true)
            ->setCacheMode(ClassMetadata::CACHE_USAGE_READ_ONLY)
            ->useResultCache(true)
            ->useQueryCache(true)
            ->getResult();
    }

    public function findBySlug($slug)
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->setCacheable(true)
            ->setCacheMode(ClassMetadata::CACHE_USAGE_READ_ONLY)
            ->useResultCache(true)
            ->useQueryCache(true)
            ->getOneOrNullResult();
    }

    public function findAllCities()
    {
        $cities = $this
            ->createQueryBuilder('c')
            ->select('c.name')
            ->where('c.population > 10000')
            ->groupBy('c.name')
            ->getQuery()
            ->getScalarResult();

        return \array_unique(\array_filter(\array_column($cities, 'name')));
    }
}
