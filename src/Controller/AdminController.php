<?php

namespace App\Controller;

use App\Form\EntryFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Author;
use App\Entity\ArticlePost;
use App\Form\AuthorFormType;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/admin")
 */

class AdminController extends AbstractController
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var \Doctrine\Common\Persistence\ObjectRepository */
    private $authorRepository;

    /** @var \Doctrine\Common\Persistence\ObjectRepository */
    private $articlePostRepository;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->articlePostRepository = $entityManager->getRepository('App:ArticlePost');
        $this->authorRepository = $entityManager->getRepository('App:Author');
    }
    /**
     * @Route("/author/create", name="author_create")
     */
    public function createAuthorAction(Request $request)
    {
        if ($this->authorRepository->findOneByUsername($this->getUser()->getUserName())) {
            // Redirect to dashboard.
            $this->addFlash('error', 'Unable to create author, author already exists!');

            return $this->redirectToRoute('homepage');
        }

        $author = new Author();
        $author->setUsername($this->getUser()->getUserName());

        $form = $this->createForm(AuthorFormType::class, $author);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($author);
            $this->entityManager->flush($author);

            $request->getSession()->set('user_is_author', true);
            $this->addFlash('success', 'Congratulations! You are now an author.');

            return $this->redirectToRoute('homepage');
        }

        return $this->render('admin/create_author.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/{id}/edit", name="edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Author $admin): Response
    {
        $form = $this->createForm(AuthorFormType::class, $admin);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('admin_index');
        }

        return $this->render('/admin/edit_author.html.twig', [
            'author' => $admin,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/create-entry", name="admin_create_entry")
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createEntryAction(Request $request)
    {
        $articlePost = new ArticlePost();

        $author = $this->authorRepository->findOneByUsername($this->getUser()->getUserName());
        $articlePost->setAuthor($author);

        $form = $this->createForm(EntryFormType::class, $articlePost);
        $form->handleRequest($request);

        // Check is valid
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($articlePost);
            $this->entityManager->flush();

            $this->addFlash('success', 'Congratulations! Your post is created');

            return $this->redirectToRoute('admin_entries');
        }

        return $this->render('admin/entry_form.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/", name="admin_index")
     * @Route("/entries", name="admin_entries")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function entriesAction()
    {
        $author = $this->authorRepository->findOneByUsername($this->getUser()->getUserName());

        $articlePosts = [];

        if ($author) {
            $articlePosts = $this->articlePostRepository->findByAuthor($author);
        }

        return $this->render('admin/entries.html.twig', [
            'articlePosts' => $articlePosts
        ]);
    }

    /**
     * @Route("/delete-entry/{entryId}", name="admin_delete_entry")
     *
     * @param $entryId
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteEntryAction($entryId)
    {
        $articlePost = $this->articlePostRepository->findOneById($entryId);
        $author = $this->authorRepository->findOneByUsername($this->getUser()->getUserName());

        if (!$articlePost || $author !== $articlePost->getAuthor()) {
            $this->addFlash('error', 'Unable to remove entry!');

            return $this->redirectToRoute('admin_entries');
        }

        $this->entityManager->remove($articlePost);
        $this->entityManager->flush();

        $this->addFlash('success', 'Entry was deleted!');

        return $this->redirectToRoute('admin_entries');
    }
}
