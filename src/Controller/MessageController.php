<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MessageController extends AbstractController
{
  #[Route('/chat', name: 'chat_list')]
  public function chatList(UserRepository $userRepository): Response
  {
      $currentUser = $this->getUser();

      // Todos los usuarios excepto tú
      $users = $userRepository->createQueryBuilder('u')
          ->where('u != :me')
          ->setParameter('me', $currentUser)
          ->getQuery()
          ->getResult();

      return $this->render('chat/list.html.twig', [
          'users' => $users,
      ]);
  }

    #[Route('/chat/{id}', name: 'chat')]
    public function chat(
        User $otherUser,
        MessageRepository $messageRepository,
        Request $request
    ): Response {
        $currentUser = $this->getUser();

        $messages = $messageRepository->createQueryBuilder('m')
            ->where('(m.sender = :me AND m.receiver = :other)')
            ->orWhere('(m.sender = :other AND m.receiver = :me)')
            ->setParameter('me', $currentUser)
            ->setParameter('other', $otherUser)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        if ($request->headers->get('X-Partial')) {
        return $this->render('chat/_messages.html.twig', [
            'messages' => $messages,
            'otherUser' => $otherUser
        ]);
    }

        return $this->render('chat/index.html.twig', [
            'otherUser' => $otherUser,
            'messages' => $messages,
        ]);
    }

    #[Route('/chat/{id}/send', name: 'chat_send', methods: ['POST'])]
    public function send(
        User $otherUser,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $content = trim($request->request->get('message'));

        if ($content) {
            $message = new Message();
            $message->setSender($this->getUser());
            $message->setReceiver($otherUser);
            $message->setContent($content);
            $message->setCreatedAt(new \DateTimeImmutable());

            $em->persist($message);
            $em->flush();
        }

        return $this->redirectToRoute('chat', ['id' => $otherUser->getId()]);
    }
}
