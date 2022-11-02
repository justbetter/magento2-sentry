<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

// phpcs:disable Magento2.Functions.DiscouragedFunction

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionSource;

class SentryPerformance
{
    private $transaction;

    public function startTransaction(Http $request)
    {
        $requestStartTime = $request->getServer('REQUEST_TIME_FLOAT', microtime(true));

        $context = TransactionContext::fromHeaders(
            $request->getHeader('sentry-trace') ?: '',
            $request->getHeader('baggage') ?: ''
        );

        $requestPath = '/' . ltrim($request->getRequestUri(), '/');
        
        $context->setOp('http.server');
        $context->setName($requestPath);
        $context->setSource(TransactionSource::url());
        $context->setStartTimestamp($requestStartTime);

        $context->setData([
            'url' => $requestPath,
            'method' => strtoupper($request->getMethod()),
        ]);

        // Start the transaction
        $transaction = \Sentry\startTransaction($context);

        // If this transaction is not sampled, don't set it either and stop doing work from this point on
        if (!$transaction->getSampled()) {
            return;
        }

        $this->transaction = $transaction;

        // Set the current transaction as the current span so we can retrieve it later
        \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);
    }

    public function finishTransaction()
    {
        if ($this->transaction) {
            // Finish the transaction, this submits the transaction and it's span to Sentry
            $this->transaction->finish();

            $this->transaction = null;
        }
    }
}
