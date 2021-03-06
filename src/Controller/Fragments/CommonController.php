<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Fragments;

use App\Annotation\ReverseProxy;
use App\App\CityManager;
use App\Controller\TBNController;
use App\Entity\Country;
use App\Repository\CityRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommonController extends TBNController
{
    const LIFE_TIME_CACHE = 86_400;

    /**
     * @Route("/_private/header/{id<%patterns.id%>}", name="app_private_header", methods={"GET"})
     * @ReverseProxy(expires="+1 day")
     */
    public function header(CityManager $cityManager, CityRepository $cityRepository, ?int $id = null): Response
    {
        $city = null;
        if ($id) {
            $city = $cityRepository->find($id);
        }

        $city = $city ?: $cityManager->getCity();

        return $this->render('fragments/menu.html.twig', [
            'city' => $city,
        ]);
    }

    public function footer(CityRepository $cityRepository, Country $country = null): Response
    {
        $params = [];
        $repo = $cityRepository;
        $params['cities'] = $repo->findRandomNames($country);

        return $this->render('fragments/footer.html.twig', $params);
    }
}
