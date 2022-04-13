<?php

declare(strict_types=1);

namespace Sentry\SentryBundle;

use Doctrine\DBAL\Result;
use Sentry\SentryBundle\EventListener\ErrorListenerExceptionEvent;
use Sentry\SentryBundle\EventListener\RequestListenerControllerEvent;
use Sentry\SentryBundle\EventListener\RequestListenerRequestEvent;
use Sentry\SentryBundle\EventListener\RequestListenerResponseEvent;
use Sentry\SentryBundle\EventListener\RequestListenerTerminateEvent;
use Sentry\SentryBundle\EventListener\SubRequestListenerRequestEvent;
use Sentry\SentryBundle\Tracing\Cache\TraceableCacheAdapter;
use Sentry\SentryBundle\Tracing\Cache\TraceableCacheAdapterForV2;
use Sentry\SentryBundle\Tracing\Cache\TraceableCacheAdapterForV3;
use Sentry\SentryBundle\Tracing\Cache\TraceableTagAwareCacheAdapter;
use Sentry\SentryBundle\Tracing\Cache\TraceableTagAwareCacheAdapterForV2;
use Sentry\SentryBundle\Tracing\Cache\TraceableTagAwareCacheAdapterForV3;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingDriver;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingDriverForV2;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingDriverForV3;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingStatement;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingStatementForV2;
use Sentry\SentryBundle\Tracing\Doctrine\DBAL\TracingStatementForV3;
use Sentry\SentryBundle\Tracing\HttpClient\TraceableHttpClient;
use Sentry\SentryBundle\Tracing\HttpClient\TraceableHttpClientForV5;
use Sentry\SentryBundle\Tracing\HttpClient\TraceableHttpClientForV6;
use Sentry\SentryBundle\Tracing\HttpClient\TraceableResponse;
use Sentry\SentryBundle\Tracing\HttpClient\TraceableResponseForV4;
use Sentry\SentryBundle\Tracing\HttpClient\TraceableResponseForV5;
use Sentry\SentryBundle\Tracing\HttpClient\TraceableResponseForV6;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\DoctrineProvider;
use Symfony\Component\HttpClient\Response\StreamableInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\HttpClient\ResponseInterface;

if (version_compare(Kernel::VERSION, '4.3.0', '>=')) {
    if (!class_exists(ErrorListenerExceptionEvent::class, false)) {
        class_alias(ExceptionEvent::class, ErrorListenerExceptionEvent::class);
    }

    if (!class_exists(RequestListenerRequestEvent::class, false)) {
        class_alias(RequestEvent::class, RequestListenerRequestEvent::class);
    }

    if (!class_exists(RequestListenerControllerEvent::class, false)) {
        class_alias(ControllerEvent::class, RequestListenerControllerEvent::class);
    }

    if (!class_exists(RequestListenerResponseEvent::class, false)) {
        class_alias(ResponseEvent::class, RequestListenerResponseEvent::class);
    }

    if (!class_exists(RequestListenerTerminateEvent::class, false)) {
        class_alias(TerminateEvent::class, RequestListenerTerminateEvent::class);
    }

    if (!class_exists(SubRequestListenerRequestEvent::class, false)) {
        class_alias(RequestEvent::class, SubRequestListenerRequestEvent::class);
    }
} else {
    if (!class_exists(ErrorListenerExceptionEvent::class, false)) {
        class_alias(GetResponseForExceptionEvent::class, ErrorListenerExceptionEvent::class);
    }

    if (!class_exists(RequestListenerRequestEvent::class, false)) {
        class_alias(GetResponseEvent::class, RequestListenerRequestEvent::class);
    }

    if (!class_exists(RequestListenerControllerEvent::class, false)) {
        class_alias(FilterControllerEvent::class, RequestListenerControllerEvent::class);
    }

    if (!class_exists(RequestListenerResponseEvent::class, false)) {
        class_alias(FilterResponseEvent::class, RequestListenerResponseEvent::class);
    }

    if (!class_exists(RequestListenerTerminateEvent::class, false)) {
        class_alias(PostResponseEvent::class, RequestListenerTerminateEvent::class);
    }

    if (!class_exists(SubRequestListenerRequestEvent::class, false)) {
        class_alias(GetResponseEvent::class, SubRequestListenerRequestEvent::class);
    }
}

if (interface_exists(AdapterInterface::class)) {
    if (!class_exists(DoctrineProvider::class, false) && version_compare(\PHP_VERSION, '8.0.0', '>=')) {
        if (!class_exists(TraceableCacheAdapter::class, false)) {
            class_alias(TraceableCacheAdapterForV3::class, TraceableCacheAdapter::class);
        }

        if (!class_exists(TraceableTagAwareCacheAdapter::class, false)) {
            class_alias(TraceableTagAwareCacheAdapterForV3::class, TraceableTagAwareCacheAdapter::class);
        }
    } else {
        if (!class_exists(TraceableCacheAdapter::class, false)) {
            class_alias(TraceableCacheAdapterForV2::class, TraceableCacheAdapter::class);
        }

        if (!class_exists(TraceableTagAwareCacheAdapter::class, false)) {
            class_alias(TraceableTagAwareCacheAdapterForV2::class, TraceableTagAwareCacheAdapter::class);
        }
    }
}

if (!class_exists(TracingStatement::class)) {
    if (class_exists(Result::class)) {
        class_alias(TracingStatementForV3::class, TracingStatement::class);
        class_alias(TracingDriverForV3::class, TracingDriver::class);
    } elseif (interface_exists(Result::class)) {
        class_alias(TracingStatementForV2::class, TracingStatement::class);
        class_alias(TracingDriverForV2::class, TracingDriver::class);
    }
}

if (!class_exists(TraceableResponse::class) && interface_exists(ResponseInterface::class)) {
    if (!interface_exists(StreamableInterface::class)) {
        class_alias(TraceableResponseForV4::class, TraceableResponse::class);
        class_alias(TraceableHttpClientForV5::class, TraceableHttpClient::class);
    } elseif (version_compare(\PHP_VERSION, '8.0', '>=')) {
        class_alias(TraceableResponseForV6::class, TraceableResponse::class);
        class_alias(TraceableHttpClientForV6::class, TraceableHttpClient::class);
    } else {
        class_alias(TraceableResponseForV5::class, TraceableResponse::class);
        class_alias(TraceableHttpClientForV5::class, TraceableHttpClient::class);
    }
}
