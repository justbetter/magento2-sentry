<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

// phpcs:disable Magento2.Functions.DiscouragedFunction

use JustBetter\Sentry\Helper\Data;
use Laminas\Http\Response;
use Magento\Framework\App\Area;
use Magento\Framework\App\Http;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\State;
use Magento\Framework\AppInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionSource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function Sentry\startTransaction;

class SentryPerformance
{
    /**
     * @var Transaction|null
     */
    private ?Transaction $transaction = null;

    /**
     * SentryPerformance constructor.
     *
     * @param HttpRequest            $request
     * @param ObjectManagerInterface $objectManager
     * @param Data                   $helper
     */
    public function __construct(
        private HttpRequest $request,
        private ObjectManagerInterface $objectManager,
        private Data $helper
    ) {
    }

    /**
     * Starts a new transaction.
     *
     * @param Command|AppInterface $app
     * @param mixed                $args
     *
     * @return void
     */
    public function startTransaction(Command|AppInterface $app, ...$args): void
    {
        if ($this->transaction !== null) {
            // Do not start a transaction if one is already runnning.
            return;
        }

        if ($app instanceof Http) {
            $this->startHttpTransaction($app, ...$args);

            return;
        }
        if ($app instanceof Command) {
            $this->startCommandTransaction($app, ...$args);

            return;
        }
    }

    /**
     * Starts a new HTTP transaction.
     *
     * @param Http $app
     *
     * @return void
     */
    public function startHttpTransaction(Http $app): void
    {
        $requestStartTime = $this->request->getServer('REQUEST_TIME_FLOAT', microtime(true));

        $context = TransactionContext::fromHeaders(
            $this->request->getHeader('sentry-trace') ?: '',
            $this->request->getHeader('baggage') ?: ''
        );

        $requestPath = '/'.ltrim($this->request->getRequestUri(), '/');

        $context->setName($requestPath);
        $context->setSource(TransactionSource::url());
        $context->setStartTimestamp($requestStartTime);

        $context->setData([
            'url'    => $requestPath,
            'method' => strtoupper($this->request->getMethod()),
        ]);

        // Start the transaction
        $transaction = startTransaction($context);

        // If this transaction is not sampled, don't set it either and stop doing work from this point on
        if (!$transaction->getSampled()) {
            return;
        }

        $transaction->setOrigin('auto.http.server');

        $this->transaction = $transaction;
        SentrySdk::getCurrentHub()->setSpan($transaction);
    }

    /**
     * Starts a new Command transaction.
     *
     * @param Command         $command
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     */
    public function startCommandTransaction(Command $command, InputInterface $input, OutputInterface $output): void
    {
        $requestStartTime = microtime(true);
        $context = TransactionContext::make();
        $context->setName('bin/magento '.($input->__toString() ?: $command->getName()));
        $context->setSource(TransactionSource::task());
        $context->setStartTimestamp($requestStartTime);

        $context->setData([
            'command'   => $command->getName(),
            'arguments' => $input->getArguments(),
            'options'   => $input->getOptions(),
        ]);

        // Start the transaction
        $transaction = startTransaction($context);

        // Do not sample long running tasks, individual jobs are sampled if the initiator was sampled.
        if (in_array($command->getName(), ['queue:consumers:start'])) {
            $transaction->setSampled(false);
        }

        // If this transaction is not sampled, don't set it either and stop doing work from this point on
        if (!$transaction->getSampled()) {
            return;
        }

        $this->transaction = $transaction;
        SentrySdk::getCurrentHub()->setSpan($transaction);
    }

    /**
     * Finish the transaction. this will send the transaction (and the profile) to Sentry.
     *
     * @param ResponseInterface|int|null $statusCode
     *
     * @throws LocalizedException
     *
     * @return void
     */
    public function finishTransaction(ResponseInterface|int|null $statusCode = null): void
    {
        if ($this->transaction === null) {
            return;
        }

        try {
            $state = $this->objectManager->get(State::class);
            $areaCode = $state->getAreaCode();
        } catch (LocalizedException $e) {
            // Default area is global.
            $areaCode = Area::AREA_GLOBAL;
        }

        if (in_array($areaCode, $this->helper->getPerformanceTrackingExcludedAreas())) {
            return;
        }

        if ($statusCode instanceof Response) {
            $statusCode = (int) $statusCode->getStatusCode();
        }

        if (is_numeric($statusCode)) {
            $this->transaction->setHttpStatus($statusCode);
        }

        if (in_array($areaCode, [Area::AREA_FRONTEND, Area::AREA_ADMINHTML, Area::AREA_WEBAPI_REST, Area::AREA_WEBAPI_SOAP, Area::AREA_GRAPHQL])) {
            if (!empty($this->request->getFullActionName())) {
                $this->transaction->setName(strtoupper($this->request->getMethod()).' '.$this->request->getFullActionName('/'));
            }

            $this->transaction->setOp('http');

            $this->transaction->setData(array_merge(
                $this->transaction->getData(),
                $this->request->__debugInfo(),
                [
                    'module' => $this->request->getModuleName(),
                    'action' => $this->request->getFullActionName(),
                ]
            ));
        } else {
            $this->transaction->setOp($areaCode);
        }

        try {
            // Finish the transaction, this submits the transaction and it's span to Sentry
            $this->transaction->finish();
        } catch (Throwable) { // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
        }

        $this->transaction = null;
    }

    /**
     * Helper function to create a new span. returns a DTO which holds the important information about the span, and the span itself.
     *
     * @param SpanContext $context
     *
     * @return PerformanceTracingDto
     */
    public static function traceStart(SpanContext $context): PerformanceTracingDto // phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction
    {
        $scope = SentrySdk::getCurrentHub()->pushScope();
        $span = null;

        $parentSpan = $scope->getSpan();
        if ($parentSpan !== null && $parentSpan->getSampled()) {
            $span = $parentSpan->startChild($context);
            $scope->setSpan($span);
        }

        return new PerformanceTracingDto($scope, $parentSpan, $span);
    }

    /**
     * Method close the given span. a DTO object, which has been created by `::traceStart` must be passed.
     *
     * @param PerformanceTracingDto $context
     *
     * @return void
     */
    public static function traceEnd(PerformanceTracingDto $context): void // phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction
    {
        if ($context->getSpan()) {
            $context->getSpan()->finish();
            $context->getScope()->setSpan($context->getParentSpan());
        }
        SentrySdk::getCurrentHub()->popScope();
    }
}
