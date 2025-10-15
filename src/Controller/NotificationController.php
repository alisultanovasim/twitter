<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'notifications_list')]
    #[IsGranted('ROLE_USER')]
    public function list(NotificationRepository $notificationRepository): Response
    {
        $user = $this->getUser();

        $notifications = $notificationRepository->findBy(
            ['recipient' => $user],
            ['createdAt' => 'DESC'],
            50
        );

        return $this->render('notification/list.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[Route('/notifications/mark-read', name: 'notifications_mark_read', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function markAllAsRead(
        NotificationRepository $notificationRepository,
        EntityManagerInterface $em
    ): Response
    {
        $user = $this->getUser();

        $notifications = $notificationRepository->findBy([
            'recipient' => $user,
            'isRead' => false
        ]);

        foreach ($notifications as $notification) {
            $notification->setRead(true);
        }

        $em->flush();

        return $this->redirectToRoute('notifications_list');
    }

    #[Route('/notifications/unread-count', name: 'notifications_unread_count', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function unreadCount(NotificationRepository $notificationRepository): Response
    {
        $user = $this->getUser();

        $count = $notificationRepository->count([
            'recipient' => $user,
            'isRead' => false
        ]);

        return $this->json(['count' => $count]);
    }
}