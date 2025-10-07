<?php

namespace App\Controller;

use App\Entity\Tweet;
use App\Entity\User;
use App\Form\TweetType;
use App\Repository\TweetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TweetController extends AbstractController
{
    #[Route('/tweets', name: 'tweet_list', methods: ['GET'])]
    public function list(TweetRepository $tweetRepository): Response
    {
        $tweets = $tweetRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            50  // Son 50 tweet
        );

        return $this->render('tweet/list.html.twig', [
            'tweets' => $tweets,
        ]);
    }

    #[Route('/tweet/new', name: 'tweet_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]  // Yalnız login user
    public function create(Request $request, EntityManagerInterface $em,LoggerInterface $logger): Response
    {
        $tweet = new Tweet();
        $form = $this->createForm(TweetType::class, $tweet);
        $form->handleRequest($request);

        $logger->info('Form submitted', [
            'data' => $form->getData()
        ]);

        if ($form->isSubmitted() && $form->isValid()) {
            $logger->error('Form validation failed', [
                'errors' => (string)$form->getErrors(true, false)
            ]);

            $tweet->setUser($this->getUser());

            $em->persist($tweet);
            $em->flush();

            $this->addFlash('success', 'Tweet yaradıldı!');

            return $this->redirectToRoute('tweet_list');
        }

        return $this->render('tweet/create.html.twig', [
            'form' => $form,
        ]);
    }
    #[Route('/tweet/{id}/delete', name: 'tweet_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Tweet $tweet, EntityManagerInterface $em,Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete'.$tweet->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
        // Yalnız öz tweet-ini silə bilər
        if ($tweet->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Bu tweet sənin deyil!');
        }

        $em->remove($tweet);
        $em->flush();

        $this->addFlash('success', 'Tweet silindi!');

        return $this->redirectToRoute('tweet_list');
    }

    #[Route('/tweet/{id}/like', name: 'tweet_like', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function like(Tweet $tweet, EntityManagerInterface $em,Request $request): Response
    {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('tweet_like', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $user = $this->getUser();

        if ($tweet->isLikedBy($user)) {
            $tweet->removeLike($user);
            $message = 'Unlike';
        } else {
            $tweet->addLike($user);
            $message = 'Liked';
        }

        $em->flush();

        return $this->json([
            'liked' => $tweet->isLikedBy($user),
            'count' => $tweet->getLikesCount()
        ]);
    }
}