<?php

declare(strict_types=1);

namespace App\\EventSubscriber;

use Symfony\\Component\\EventDispatcher\\EventSubscriberInterface;
use Symfony\\Component\\HttpKernel\\Event\\RequestEvent;
use Symfony\\Component\\HttpKernel\\Event\\ResponseEvent;
use Symfony\\Component\\HttpKernel\\KernelEvents;
use Symfony\\Component\\Uid\\Uuid;

final class RequestTraceSubscriber implements EventSubscriberInterface
{
    private const REQUEST_ID_ATTRIBUTE = '_request_trace_id';
    private const STARTED_AT_ATTRIBUTE = '_request_trace_started_at';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 1000],
            KernelEvents::RESPONSE => ['onResponse', -1000],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $candidate = trim((string) $request->headers->get('X-Request-Id', ''));
        $requestId = preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $candidate) === 1
            ? $candidate
            : Uuid::v7()->toRfc4122();

        $request->attributes->set(self::REQUEST_ID_ATTRIBUTE, $requestId);
        $request->attributes->set(self::STARTED_AT_ATTRIBUTE, hrtime(true));
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $requestId = (string) $request->attributes->get(self::REQUEST_ID_ATTRIBUTE, Uuid::v7()->toRfc4122());
        $startedAt = (int) $request->attributes->get(self::STARTED_AT_ATTRIBUTE, hrtime(true));
        $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 2);
        $response = $event->getResponse();
        $response->headers->set('X-Request-Id', $requestId);

        error_log(json_encode([
            'event' => 'http.request.completed',
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'route' => $request->attributes->get('_route'),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
