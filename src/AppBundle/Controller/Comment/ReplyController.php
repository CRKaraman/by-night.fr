<?php

namespace AppBundle\Controller\Comment;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

use AppBundle\Form\Type\CommentType;
use AppBundle\Entity\Comment;
use AppBundle\Entity\Agenda;
use AppBundle\Repository\CommentRepository;


class ReplyController extends Controller
{
    /**
     * @Route("/{id}/reponses/{page}", name="tbn_comment_reponse_list", requirements={"id": "\d+", "page": "\d+"})
     * @param Comment $comment
     * @param $page
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction(Comment $comment, $page = 1)
    {
        $limit = 5;
        return $this->render('Comment/Reply/list.html.twig', [
            'comments' => $this->getReponses($comment, $page, $limit),
            "main_comment" => $comment,
            "nb_comments" => $this->getNbReponses($comment),
            "page" => $page,
            "offset" => $limit
        ]);
    }

    /**
     * @Route("/{id}/repondre", name="tbn_comment_reponse_new", requirements={"id": "\d+"})
     * @param Request $request
     * @param Comment $comment
     * @return JsonResponse|RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction(Request $request, Comment $comment)
    {
        $reponse = new Comment();
        $form = $this->getCreateForm($reponse, $comment);

        if ($request->getMethod() == "POST") {
            $user = $this->getUser();

            if (!$user) {
                $this->get('session')->getFlashBag()->add(
                    'warning',
                    "Vous devez vous connecter pour répondre à cet utilisateur"
                );

                return new RedirectResponse($this->generateUrl("fos_user_security_login"));
            }
            $reponse->setUser($user);
            $reponse->setAgenda($comment->getAgenda());

            $form->handleRequest($request);
            if ($form->isValid()) {
                $reponse->setParent($comment);
                $comment->addReponse($reponse);
                $em = $this->getDoctrine()->getManager();
                $em->persist($comment);
                $em->flush();

                return new JsonResponse([
                    "success" => true,
                    "comment" => $this->renderView("Comment/Reply/details.html.twig", [
                        "comment" => $reponse,
                        "success_confirmation" => true,
                    ]),
                    "nb_reponses" => $this->getNbReponses($comment)
                ]);
            } else {
                return new JsonResponse([
                    "success" => false,
                    "post" => $this->renderView("Comment/Reply/post.html.twig", [
                        "comment" => $comment,
                        "form" => $form->createView()
                    ])
                ]);
            }
        }
        return $this->render('Comment/Reply/post.html.twig', [
            'comment' => $comment,
            'form' => $form->createView()
        ]);
    }


    /**
     *
     * @return CommentRepository
     */
    protected function getCommentRepo()
    {
        $repo = $this->getDoctrine()->getRepository("AppBundle:Comment");
        return $repo;
    }

    protected function getCommentaires(Agenda $soiree, $page, $limit = 10)
    {
        return $this->getCommentRepo()->findAllByAgenda($soiree, $page, $limit);
    }

    protected function getReponses(Comment $comment, $page, $limit = 10)
    {
        return $this->getCommentRepo()->findAllReponses($comment, $page, $limit);
    }

    protected function getNbComments(Agenda $soiree)
    {
        return $this->getCommentRepo()->findNBCommentaires($soiree);
    }

    protected function getNbReponses(Comment $comment)
    {
        return $this->getCommentRepo()->findNBReponses($comment);
    }

    public function detailsAction(Comment $comment)
    {
        return $this->render("Comment/Reply/details.html.twig", [
            "comment" => $comment,
            "nb_reponses" => $this->getNbReponses($comment)
        ]);
    }

    protected function getCreateForm(Comment $reponse, Comment $comment)
    {
        return $this->createForm(CommentType::class, $reponse, [
            'action' => $this->generateUrl('tbn_comment_reponse_new', ["id" => $comment->getId()]),
            'method' => 'POST'
        ])
            ->add("poster", SubmitType::class, [
                "label" => "Répondre",
                "attr" => [
                    "class" => "btn btn-primary btn-submit btn-raised",
                    "data-loading-text" => "En cours..."
                ]
            ]);
    }
}