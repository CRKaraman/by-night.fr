<?php

namespace TBN\AgendaBundle\Controller;

use TBN\MainBundle\Controller\TBNController as Controller;

class OldEventController extends Controller {

    public function detailsAction($slug)
    {
        return $this->redirectToRoute('tbn_agenda_details', [
            'slug' => $slug
        ]);
    }
    
    public function tendancesAction($slug)
    {
        return $this->redirectToRoute('tbn_agenda_soirees_tendances', [
            'slug' => $slug
        ]);
    }
    
    public function fbMembresAction($slug, $page)
    {
        return $this->redirectToRoute('tbn_agenda_soirees_membres', [
            'slug' => $slug,
            'page' => $page
        ]);
    }
    
    public function soireesSimilairesAction($slug, $page)
    {
        return $this->redirectToRoute('tbn_agenda_soirees_similaires', [
            'slug' => $slug,
            'page' => $page
        ]);
    }
    
    public function listAction($page)
    {
        return $this->redirectToRoute('tbn_agenda_pagination', [
            'page' => $page
        ]);
    }
}