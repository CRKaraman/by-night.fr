<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;

/**
 * SiteRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class SiteRepository extends EntityRepository
{
    public function findStats()
    {
        return $this
            ->createQueryBuilder('s')
            ->select('s.nom, s.subdomain, count(DISTINCT u.id) AS count_users, count(DISTINCT a.id) as count_events')
            ->leftJoin('AppBundle:User', 'u', Expr\Join::WITH, 'u.site = s')
            ->leftJoin('AppBundle:Agenda', 'a', Expr\Join::WITH, 'a.site = s')
            ->orderBy('s.nom')
            ->groupBy('s.id')
            ->getQuery()
            ->getScalarResult();
    }

    public function findRandomNames($limit = 5)
    {
        $results = $this
            ->createQueryBuilder('s')
            ->select('s.nom, s.subdomain')
//            ->where('s.id != :id')
//            ->setParameter('id', $site->getId())
            ->getQuery()
            ->getScalarResult();

        shuffle($results);
        return array_slice($results, 0, $limit);
    }

    public function findLocations()
    {
        $results = $this->createQueryBuilder('s')
            ->select('s.latitude, s.longitude, s.distanceMax')
            ->where('s.latitude IS NOT NULL')
            ->andWhere('s.longitude IS NOT NULL')
            ->andWhere('s.distanceMax IS NOT NULL')
            ->getQuery()
            ->getScalarResult();

        return $results;
    }
}
