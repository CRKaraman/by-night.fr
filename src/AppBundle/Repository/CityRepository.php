<?php

namespace AppBundle\Repository;

/**
 * CityRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class CityRepository extends \Doctrine\ORM\EntityRepository
{
    public function findByName($city) {
        return $this
            ->createQueryBuilder("c")
            ->where("c.name = :city")
            ->setParameter("city", $city)
            ->getQuery()
            ->useResultCache(true)
            ->useQueryCache(true)
            ->getResult();
    }
}
