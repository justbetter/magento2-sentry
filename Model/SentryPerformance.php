<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

// phpcs:disable Magento2.Functions.DiscouragedFunction

use JustBetter\Sentry\Helper\Data;
use Laminas\Http\Response;
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
use Throwable;

use function Sentry\startTransaction;

class SentryPerformance
{
    private ?Transaction $transaction = null;

    public function __construct(
        private HttpRequest $request,
        private ObjectManagerInterface $objectManager,
        private Data $helper
    ) {
    }

    public function startTransaction(AppInterface $app): void
    {
        if (!$app instanceof Http) {
            // actually, we only support profiling of http requests.
            return;
        }

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

        $this->transaction = $transaction;
        SentrySdk::getCurrentHub()->setSpan($transaction);
    }

    public function finishTransaction(ResponseInterface|int $statusCode): void
    {
        if ($this->transaction === null) {
            return;
        }

        try {
            $state = $this->objectManager->get(State::class);
            $areaCode = $state->getAreaCode();
        } catch (LocalizedException) {
            // we wont track transaction without an area
            return;
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

        if (in_array($state->getAreaCode(), ['frontend', 'webapi_rest', 'adminhtml'])) {
            if (!empty($this->request->getFullActionName())) {
                $this->transaction->setName(strtoupper($this->request->getMethod()). ' ' .$this->request->getFullActionName());
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
        } elseif ($state->getAreaCode() === 'graphql') {
            $this->transaction->setOp('graphql');
        } else {
            $this->transaction->setOp($state->getAreaCode());
        }

        try {
            // Finish the transaction, this submits the transaction and it's span to Sentry
            $this->transaction->finish();
        } catch (Throwable) {
        }

        $this->transaction = null;
    }

    public static function traceStart(SpanContext $context): PerformanceTracingDto
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

    public static function traceEnd(PerformanceTracingDto $context): void
    {
        if ($context->getSpan()) {
            $context->getSpan()->finish();
            $context->getScope()->setSpan($context->getParentSpan());
        }
        SentrySdk::getCurrentHub()->popScope();
    }
}
