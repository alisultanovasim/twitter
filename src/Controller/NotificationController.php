<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

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
    public function markAllAsRead(): Response
    {
        $this->notificationService->markAllAsRead($this->getUser());

        return $this->redirectToRoute('notifications_list');
    }

    #[Route('/notifications/unread-count', name: 'notifications_unread_count', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function unreadCount(): Response
    {
        $count = $this->notificationService->getUnreadCount($this->getUser());

        return $this->json(['count' => $count]);
    }
}