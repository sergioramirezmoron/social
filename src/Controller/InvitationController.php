<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\User;
use App\Form\InvitationType;
use App\Repository\InvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/invitation')]
final class InvitationController extends AbstractController
{
    #[Route(name: 'app_invitation_index', methods: ['GET'])]
    public function index(InvitationRepository $invitationRepository): Response
    {
        $invitaciones=$this->getUser()->getInvitations();
        return $this->render('invitation/index.html.twig', [
            'invitations' => $invitaciones,
        ]);
    }

    #[Route('/new/{id}', name: 'app_invitation_new', methods: ['GET'])]
    public function new(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);
        // Crear la invitación
        $invitation = new Invitation();
        $invitation->setInvitated($user); // o setReceiver($user) según tu entidad
        $invitation->setUser($this->getUser());

        $entityManager->persist($invitation);
        $entityManager->flush();

        // Redirigir a donde quieras después de crear la invitación
        return $this->redirectToRoute('app_invitation_index');
    }

    #[Route('/{id}', name: 'app_invitation_show', methods: ['GET'])]
    public function show(Invitation $invitation): Response
    {
        return $this->render('invitation/show.html.twig', [
            'invitation' => $invitation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_invitation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Invitation $invitation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(InvitationType::class, $invitation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_invitation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invitation/edit.html.twig', [
            'invitation' => $invitation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_invitation_delete', methods: ['POST'])]
    public function delete(Request $request, Invitation $invitation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$invitation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($invitation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_invitation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/accept/{id}', name: 'app_invitation_accept', methods: ['GET'])]
    public function accept(int $id,Invitation $invitation, EntityManagerInterface $em): Response
    {
        /** @var User $userFromSession */
        $userFromSession = $this->getUser();

        if (!$userFromSession instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // 1. RE-CAPTURA EL USUARIO CON EL ENTITY MANAGER
        // Esto asegura que Doctrine sepa que este objeto existe y rastree sus cambios.
        $currentUser = $em->getRepository(User::class)->find($userFromSession->getId());

        // 2. SEGURIDAD: Verificar que quien acepta es realmente el invitado
        // Asumiendo que tu entidad Invitation tiene un método getInvitated() o getReceiver()
        if ($invitation->getInvitated() !== $currentUser) {
            throw $this->createAccessDeniedException('No puedes aceptar una invitación que no es para ti.');
        }

        $sender = $invitation->getUser(); 

        // 3. LÓGICA: Evitar duplicados (opcional pero recomendado)
        if (!$currentUser->getFollowing()->contains($sender)) {
            $currentUser->addFollowing($sender);
            
            // 4. PERSISTIR: Avisar explícitamente a Doctrine que guarde al usuario
            $em->persist($currentUser); 
        }

        // Eliminar la invitación
        $em->remove($invitation);
        
        // Ejecutar todos los cambios (INSERT en following, DELETE en invitation)
        $em->flush();

        $this->addFlash('success', 'Ahora sigues a ' . $sender->getUsername());

        return $this->redirectToRoute('app_invitation_index');
    }

    #[Route('/reject/{id}', name: 'app_invitation_reject', methods: ['GET'])]
    public function reject(Invitation $invitation, EntityManagerInterface $em): Response
    {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Simplemente eliminar la invitación
        $em->remove($invitation);
        $em->flush();

        $this->addFlash('info', 'Invitation rejected.');

        return $this->redirectToRoute('app_invitation_index');
    }
}
