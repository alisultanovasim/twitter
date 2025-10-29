<?php

namespace App\Controller;

use App\Entity\Tweet;
use App\Form\TweetType;
use App\Repository\TweetRepository;
use App\Service\FileUploaderService;
use App\Service\TweetService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TweetController extends AbstractController
{
    public function __construct(
        private TweetService $tweetService,
        private TweetRepository $tweetRepository,
    ) {}

    #[Route('/tweets', name: 'tweet_list', methods: ['GET'])]
    public function list(Request $request, PaginatorInterface $paginator): Response
    {
        $user = $this->getUser();

        $query = $user
            ? $this->tweetService->getTimeline($user)
            : $this->tweetRepository->createQueryBuilder('t')
                ->orderBy('t.createdAt', 'DESC')
                ->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            20
        );

        // AJAX request
        if ($request->isXmlHttpRequest()) {
            return $this->render('tweet/_tweet_list_items.html.twig', [
                'pagination' => $pagination
            ]);
        }

        return $this->render('tweet/list.html.twig', ['pagination' => $pagination]);
    }

    #[Route('/tweet/new', name: 'tweet_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        Request $request,
        FileUploaderService $fileUploader,
        #[Autowire(service: 'limiter.tweet_create')] RateLimiterFactory $tweetCreateLimiter
    ): Response
    {
        $limiter = $tweetCreateLimiter->create($request->getClientIp());

        if (false === $limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Çox tweet yaradırsınız. 10 dəqiqə gözləyin.');
            return $this->redirectToRoute('tweet_list');
        }

        $tweet = new Tweet();
        $form = $this->createForm(TweetType::class, $tweet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imagePath = null;
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $imagePath = $fileUploader->upload($imageFile);
            }

            $this->tweetService->createTweet(
                user: $this->getUser(),
                content: $tweet->getContent(),
                imagePath: $imagePath
            );

            $this->addFlash('success', 'Tweet yaradıldı!');
            return $this->redirectToRoute('tweet_list');
        }

        return $this->render('tweet/create.html.twig', ['form' => $form]);
    }

    #[Route('/tweet/{id}/delete', name: 'tweet_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Tweet $tweet, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $tweet->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        try {
            $this->tweetService->deleteTweet($tweet, $this->getUser());
            $this->addFlash('success', 'Tweet silindi!');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('tweet_list');
    }

    #[Route('/tweet/{id}/like', name: 'tweet_like', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function like(
        Tweet $tweet,
        Request $request,
        #[Autowire(service: 'limiter.tweet_like')] RateLimiterFactory $likeLimiter
    ): Response
    {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('tweet_like', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $limiter = $likeLimiter->create($request->getClientIp());

        if (false === $limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Çox like edirsiniz. Gözləyin.'], 429);
        }

        $liked = $this->tweetService->toggleLike($this->getUser(), $tweet);

        return $this->json([
            'liked' => $liked,
            'count' => $tweet->getLikesCount()
        ]);
    }

    #[Route('/tweet/{id}', name: 'tweet_show', methods: ['GET'])]
    public function show(Tweet $tweet): Response
    {
        $replies = $this->tweetService->getReplies($tweet);

        return $this->render('tweet/show.html.twig', [
            'tweet' => $tweet,
            'replies' => $replies,
        ]);
    }

    #[Route('/tweet/{id}/reply', name: 'tweet_reply', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function reply(Tweet $parentTweet, Request $request): Response
    {
        $reply = new Tweet();
        $form = $this->createForm(\App\Form\ReplyType::class, $reply);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->tweetService->createTweet(
                user: $this->getUser(),
                content: $reply->getContent(),
                parent: $parentTweet
            );

            $this->addFlash('success', 'Cavab əlavə olundu!');
            return $this->redirectToRoute('tweet_show', ['id' => $parentTweet->getId()]);
        }

        return $this->render('tweet/reply.html.twig', [
            'form' => $form,
            'parent_tweet' => $parentTweet,
        ]);
    }

    #[Route('/tweet/{id}/retweet', name: 'tweet_retweet', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function retweet(
        Tweet $originalTweet,
        Request $request,
        #[Autowire(service: 'limiter.tweet_retweet')] RateLimiterFactory $retweetLimiter
    ): Response
    {
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('tweet_retweet', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $limiter = $retweetLimiter->create($request->getClientIp());

        if (false === $limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Çox retweet edirsiniz. Gözləyin.'], 429);
        }

        try {
            $result = $this->tweetService->toggleRetweet($this->getUser(), $originalTweet);
            return $this->json($result);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}