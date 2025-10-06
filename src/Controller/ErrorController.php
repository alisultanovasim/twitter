<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Twig\Environment;
use Throwable;

class ErrorController extends AbstractController
{
    public function __construct(private Environment $twig)
    {
    }

    public function show(Throwable $exception): Response
    {
        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        // Spesifik template yoxla
        $template = "error/{$statusCode}.html.twig";

        // Template mövcud deyilsə generic istifadə et
        if (!$this->twig->getLoader()->exists($template)) {
            $template = 'error/error.html.twig';
        }

        return $this->render($template, [
            'status_code' => $statusCode,
            'message' => $exception->getMessage(),
        ], new Response('', $statusCode));
    }
}