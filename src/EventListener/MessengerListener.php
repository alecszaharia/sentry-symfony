<?php

declare(strict_types=1);

namespace Sentry\SentryBundle\EventListener;

use Sentry\Breadcrumb;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

final class MessengerListener
{
    /**
     * @var HubInterface The current hub
     */
    private $hub;

    /**
     * @var bool Whether to capture errors thrown while processing a message that
     *           will be retried
     */
    private $captureSoftFails;

    /**
     * @param HubInterface $hub              The current hub
     * @param bool         $captureSoftFails Whether to capture errors thrown
     *                                       while processing a message that
     *                                       will be retried
     */
    public function __construct(HubInterface $hub, bool $captureSoftFails = true)
    {
        $this->hub = $hub;
        $this->captureSoftFails = $captureSoftFails;
    }

    /**
     * This method is called for each message that failed to be handled.
     *
     * @param WorkerMessageFailedEvent $event The event
     */
    public function handleWorkerMessageFailedEvent(WorkerMessageFailedEvent $event): void
    {
        if (!$this->captureSoftFails && $event->willRetry()) {
            return;
        }

        $this->hub->withScope(function (Scope $scope) use ($event): void {
            $envelope = $event->getEnvelope();
            $exception = $event->getThrowable();

            $scope->setTag('messenger.receiver_name', $event->getReceiverName());
            $scope->setTag('messenger.message_class', \get_class($envelope->getMessage()));

            /** @var BusNameStamp|null $messageBusStamp */
            $messageBusStamp = $envelope->last(BusNameStamp::class);

            if (null !== $messageBusStamp) {
                $scope->setTag('messenger.message_bus', $messageBusStamp->getBusName());
            }

            if ($exception instanceof HandlerFailedException) {
                foreach ($exception->getNestedExceptions() as $nestedException) {
                    $this->hub->captureException($nestedException);
                }
            } else {
                $this->hub->captureException($exception);
            }
        });

        $this->flushClient();
    }

    /**
     * This method is called for each handled message.
     *
     * @param WorkerMessageHandledEvent $event The event
     */
    public function handleWorkerMessageHandledEvent(WorkerMessageHandledEvent $eventArgs): void
    {
        $span = $this->hub->getSpan();

        $this->hub->addBreadcrumb(
            new Breadcrumb(
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::TYPE_DEFAULT,
                'php',
                null,
                [
                    'memory_get_peak_usage'=>memory_get_peak_usage(true),
                    'memory_get_usage'=>memory_get_usage(true)
                ]
            )
        );

        if (null !== $span) {
            $span->finish();
        }
    }

    public function handleWorkerMessageReceivedEvent(WorkerMessageHandledEvent $eventArgs): void
    {
        $currentSpan = $this->hub->getSpan();

        if (null === $currentSpan) {
            $transactionContext = new TransactionContext();
            $refClass = new \ReflectionClass($eventArgs->getEnvelope()->getMessage());
            $transactionContext->setOp('messenger.handle');
            $transactionContext->setName($refClass->getShortName());

            $span = $this->hub->startTransaction($transactionContext);
        } else {
            $spanContext = new SpanContext();
            $spanContext->setOp('messenger.handle');
            $spanContext->setDescription('Message: '.get_class($eventArgs->getEnvelope()->getMessage()));

            $span = $currentSpan->startChild($spanContext);
        }

        $envelope = $eventArgs->getEnvelope();
        $span->setTags(
            [
                'messenger.receiver_name' => $eventArgs->getReceiverName(),
                'messenger.message_class' => \get_class($envelope->getMessage()),
            ]
        );

        /** @var BusNameStamp|null $messageBusStamp */
        $messageBusStamp = $envelope->last(BusNameStamp::class);

        if (null !== $messageBusStamp) {
            $span->setTags(['messenger.message_bus'=> $messageBusStamp->getBusName()]);
        }

        $this->hub->setSpan($span);

    }



    private function flushClient(): void
    {
        $client = $this->hub->getClient();

        if (null !== $client) {
            $client->flush();
        }
    }
}
