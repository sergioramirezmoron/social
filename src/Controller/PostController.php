<?php

namespace App\Controller;

use App\Entity\Post;
use App\Form\PostType;
use App\Repository\PostRepository;
use App\Repository\StoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Entity\User;
use App\Entity\Invitation;

#[Route('/post')]
#[IsGranted('ROLE_USER')]
final class PostController extends AbstractController
{
    #[Route(name: 'app_post_index', methods: ['GET'])]
    public function index(PostRepository $postRepository, StoryRepository $storyRepository): Response
    {
        $user = $this->getUser();
        $following = [];
        if ($user instanceof User) {
            $following = $user->getFollowing()->toArray();
        }
        
        $stories = $storyRepository->findStoriesFromFollowing($following, $user);
        $posts = $postRepository->findBy([], ['postdate' => 'DESC']);
        
        // Crear array de IDs de posts originales que el usuario ha reposteado
        $userReposts = [];
        if ($user instanceof User) {
            foreach ($posts as $post) {
                // Determinar el post original
                $originalPost = $post->isRetweet() ? $post->getOriginalPost() : $post;
                
                // Verificar si el usuario ha reposteado este post original
                $hasReposted = $postRepository->findOneBy([
                    'author' => $user,
                    'originalPost' => $originalPost,
                    'deleted_at' => null
                ]);
                
                if ($hasReposted) {
                    $userReposts[] = $originalPost->getId();
                }
            }
        }

        return $this->render('post/index.html.twig', [
            'posts' => $posts,
            'stories' => $stories,
            'userReposts' => $userReposts,
        ]);
    }

    #[Route('/new', name: 'app_post_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $post = new Post();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $post->setAuthor($this->getUser());
            $post->setPostdate(new \DateTime());

            $imgFile = $form->get('img')->getData();

            if ($imgFile) {
                $directory = $this->getParameter('img_directory');
                $originalFilename = pathinfo($imgFile->getClientOriginalName(), PATHINFO_FILENAME);

                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imgFile->guessExtension();

                try {
                    $imgFile->move($directory, $newFilename);
                } catch (FileException $e) {
                }

                $post->setImg($newFilename);
            }

            $entityManager->persist($post);
            $entityManager->flush();

            return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('post/new.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_post_show', methods: ['GET'])]
    public function show(Post $post): Response
    {
        return $this->render('post/show.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_post_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Post $post, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('post/edit.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_post_delete', methods: ['POST'])]
    public function delete(Request $request, Post $post, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $post->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($post);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/like/{id}', name: 'app_post_like', methods: ['GET'])]
    public function like(Post $post, EntityManagerInterface $entityManager): Response
    {
        $post->addLike($this->getUser());
        $entityManager->persist($post);
        $entityManager->flush();

        return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/dislike/{id}', name: 'app_post_unlike', methods: ['GET'])]
    public function unlike(Post $post, EntityManagerInterface $entityManager): Response
    {
        $post->removeLike($this->getUser());
        $entityManager->persist($post);
        $entityManager->flush();

        return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/repost/{id}', name: 'app_post_repost', methods: ['GET'])]
    public function repost(Post $post, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        // Verificar que el usuario esté autenticado
        if (!$user) {
            $this->addFlash('error', 'Debes iniciar sesión para hacer repost');
            return $this->redirectToRoute('app_login');
        }
        
        // Verificar que el post no esté eliminado
        if ($post->getDeletedAt() !== null) {
            $this->addFlash('error', 'No puedes hacer repost de un post eliminado');
            return $this->redirectToRoute('app_post_index');
        }
        
        // Determinar el post original (por si es un retweet de un retweet)
        $originalPost = $post->isRetweet() ? $post->getOriginalPost() : $post;
        
        // Verificar que el usuario no sea el autor del post original
        if ($originalPost->getAuthor() === $user) {
            $this->addFlash('error', 'No puedes hacer repost de tu propio post');
            return $this->redirectToRoute('app_post_index');
        }
        
        // Verificar si el usuario ya hizo repost de este post
        $existingRepost = $entityManager->getRepository(Post::class)->findOneBy([
            'author' => $user,
            'originalPost' => $originalPost
        ]);
        
        if ($existingRepost && $existingRepost->getDeletedAt() === null) {
            $this->addFlash('info', 'Ya has hecho repost de este post');
            return $this->redirectToRoute('app_post_index');
        }
        
        // Si existe pero está eliminado, restaurarlo
        if ($existingRepost && $existingRepost->getDeletedAt() !== null) {
            $existingRepost->setDeletedAt(null);
            $existingRepost->setPostdate(new \DateTime());
        } else {
            // Crear nuevo repost
            $repost = new Post();
            $repost->setAuthor($user);
            $repost->setOriginalPost($originalPost);
            $repost->setPostdate(new \DateTime());
            // El texto e imagen serán null porque es un repost
            
            $entityManager->persist($repost);
        }
        
        $entityManager->flush();
        
        $this->addFlash('success', 'Repost realizado correctamente');
        return $this->redirectToRoute('app_post_index');
    }

    #[Route('/unrepost/{id}', name: 'app_post_unrepost', methods: ['GET'])]
    public function unrepost(Post $post, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        // Verificar que el usuario esté autenticado
        if (!$user) {
            $this->addFlash('error', 'Debes iniciar sesión');
            return $this->redirectToRoute('app_login');
        }
        
        // Determinar el post original
        $originalPost = $post->isRetweet() ? $post->getOriginalPost() : $post;
        
        // Buscar el repost del usuario
        $userRepost = $entityManager->getRepository(Post::class)->findOneBy([
            'author' => $user,
            'originalPost' => $originalPost,
            'deleted_at' => null
        ]);
        
        if (!$userRepost) {
            $this->addFlash('error', 'No has hecho repost de este post');
            return $this->redirectToRoute('app_post_index');
        }
        
        // Marcar como eliminado (soft delete)
        $userRepost->setDeletedAt(new \DateTime());
        
        // O si prefieres eliminar definitivamente:
        // $entityManager->remove($userRepost);
        
        $entityManager->flush();
        
        $this->addFlash('success', 'Repost eliminado correctamente');
        return $this->redirectToRoute('app_post_index');
    }
}