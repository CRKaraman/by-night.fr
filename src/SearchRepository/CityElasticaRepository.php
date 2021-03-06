<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

use Elastica\Query\MultiMatch;
use FOS\ElasticaBundle\Paginator\PaginatorAdapterInterface;
use FOS\ElasticaBundle\Repository;

class CityElasticaRepository extends Repository
{
    /**
     * @param string $q
     *
     * @return PaginatorAdapterInterface
     */
    public function findWithSearch($q)
    {
        $query = new MultiMatch();
        $query
            ->setQuery($q)
            ->setFields([
                'name',
                'parent.name',
                'country.name',
            ]);

        return $this->createPaginatorAdapter($query);
    }
}
