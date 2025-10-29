<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin\Profiling;

/**
 * Auto instrument cache retrieval, saving and removing
 */
class Cache extends \Magento\Framework\Cache\Frontend\Decorator\Bare
{
    /**
     * Instrument the cache.get operation around the load
     *
     * @param string $identifier
     *
     * @return string|bool
     */
    public function load($identifier)
    {
        $parentSpan = \Sentry\SentrySdk::getCurrentHub()->getSpan();
        if ($parentSpan === null) {
            return parent::load($identifier);
        }

        $context = \Sentry\Tracing\SpanContext::make()
            ->setOp('cache.get')
            ->setData([
                'cache.key' => $identifier,
            ])
            ->setDescription($identifier)
            ->setOrigin('auto.cache');
        $span = $parentSpan->startChild($context);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($span);

        $result = parent::load($identifier);

        if ($result === null || $result === false) {
            $span->setData([
                'cache.hit' => false,
            ]);
        } else {
            $span->setData([
                'cache.hit' => true,
                'cache.item_size' => is_string($result) ? strlen($result) : null,
            ]);
        }
        
        $span->finish();
        \Sentry\SentrySdk::getCurrentHub()->setSpan($parentSpan);

        return $result;
    }

    /**
     * Instrument the cache.put operation around the save
     *
     * @param string $data
     * @param string $identifier
     * @param array $tags
     * @param int|bool|null $lifeTime
     *
     * @return bool
     */
    public function save($data, $identifier, array $tags = [], $lifeTime = null)
    {
        $parentSpan = \Sentry\SentrySdk::getCurrentHub()->getSpan();
        if ($parentSpan === null) {
            return parent::save($data, $identifier, $tags, $lifeTime);
        }

        $context = \Sentry\Tracing\SpanContext::make()
            ->setOp('cache.put')
            ->setData([
                'cache.key' => $identifier,
                'cache.tags' => $tags,
                'cache.ttl' => $lifeTime
            ])
            ->setDescription($identifier)
            ->setOrigin('auto.cache');

        $span = $parentSpan->startChild($context);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($span);

        $result = parent::save($data, $identifier, $tags, $lifeTime);

        $span->finish();
        \Sentry\SentrySdk::getCurrentHub()->setSpan($parentSpan);

        return $result;
    }

    /**
     * Instrument the cache.remove operation around the remove
     *
     * @param string $identifier
     *
     * @return bool
     */
    public function remove($identifier)
    {
        $parentSpan = \Sentry\SentrySdk::getCurrentHub()->getSpan();
        if ($parentSpan === null) {
            return parent::remove($identifier);
        }

        $context = \Sentry\Tracing\SpanContext::make()
            ->setOp('cache.remove')
            ->setData([
                'cache.key' => $identifier,
            ])
            ->setDescription($identifier)
            ->setOrigin('auto.cache');

        $span = $parentSpan->startChild($context);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($span);

        $result = parent::remove($identifier);

        $span->finish();
        \Sentry\SentrySdk::getCurrentHub()->setSpan($parentSpan);

        return $result;
    }

    /**
     * Instrument the cache.remove operation around the clean
     *
     * @param string $mode
     * @param array $tags
     * @return bool
     */
    public function clean($mode = \Zend_Cache::CLEANING_MODE_ALL, array $tags = [])
    {
        $parentSpan = \Sentry\SentrySdk::getCurrentHub()->getSpan();
        if ($parentSpan === null) {
            return parent::clean($mode, $tags);
        }
        
        $context = \Sentry\Tracing\SpanContext::make()
            ->setOp('cache.remove')
            ->setData([
                'cache.mode' => $mode,
                'cache.tags' => $tags,
            ])
            ->setDescription($mode . ' ' . implode(',', $tags))
            ->setOrigin('auto.cache');

        $span = $parentSpan->startChild($context);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($span);

        $result = parent::clean($mode, $tags);

        $span->finish();
        \Sentry\SentrySdk::getCurrentHub()->setSpan($parentSpan);

        return $result;
    }
}
