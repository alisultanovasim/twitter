<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LoginRateLimiterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'limiter.login_attempts')]
        private RateLimiterFactory $loginLimiter,
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9], // Priority 9 (before firewall)
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Yalnız login route-da və POST method-da yoxla
        if ($request->attributes->get('_route') !== 'app_login') {
            return;
        }

        if (!$request->isMethod('POST')) {
            return;
        }

        // Rate limiter check
        $limiter = $this->loginLimiter->create($request->getClientIp());

        if (false === $limiter->consume(1)->isAccepted()) {
            // Flash message əlavə et
            $session = $this->requestStack->getSession();
            $session->getFlashBag()->add('error', 'Çox login cəhdi. 15 dəqiqə gözləyin.');

            // Redirect to login page
            $response = new RedirectResponse($this->urlGenerator->generate('app_login'));
            $event->setResponse($response);
        }
    }
}