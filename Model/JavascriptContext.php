<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Model;

use JustBetter\Sentry\Helper\Data as DataHelper;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;

class JavascriptContext
{
    public const FRAME_FILENAME_FORMAT = '/<%s>';

    public const TAG_PREFIX = 'magento.';

    public const MAX_VALUE_LENGTH = 64;

    /**
     * @var bool
     */
    private bool $resolved = false;

    /**
     * @var ?array<string, mixed>
     */
    private ?array $data = null;

    /**
     * JavascriptContext constructor.
     *
     * @param HttpRequest           $request
     * @param DataHelper            $dataHelper
     * @param DataObjectFactory     $dataObjectFactory
     * @param EventManagerInterface $eventManager
     */
    public function __construct(
        private HttpRequest $request,
        private DataHelper $dataHelper,
        private DataObjectFactory $dataObjectFactory,
        private EventManagerInterface $eventManager
    ) {
    }

    /**
     * Get the Magento request context for the browser SDK, or null when disabled or unavailable.
     *
     * @return ?array<string, mixed>
     */
    public function get(): ?array
    {
        if ($this->resolved) {
            return $this->data;
        }

        $this->resolved = true;

        if (!$this->dataHelper->addActionContext()) {
            return null;
        }

        $route = $this->sanitize($this->request->getRouteName());
        $controller = $this->sanitize($this->request->getControllerName());
        $action = $this->sanitize($this->request->getActionName());

        if ($route === '' || $controller === '' || $action === '') {
            // Rendered outside of a dispatched request (email, cli, cron), nothing meaningful to report.
            return null;
        }

        $fullAction = $route.'_'.$controller.'_'.$action;

        /** @var DataObject $context */
        $context = $this->dataObjectFactory->create();
        $context->setData([
            'transaction'    => $fullAction,
            'frame_filename' => sprintf(static::FRAME_FILENAME_FORMAT, $fullAction),
            'tags'           => [
                static::TAG_PREFIX.'route'       => $route,
                static::TAG_PREFIX.'controller'  => $controller,
                static::TAG_PREFIX.'action'      => $action,
                static::TAG_PREFIX.'full_action' => $fullAction,
            ],
        ]);

        $this->eventManager->dispatch('sentry_javascript_context', ['context' => $context]);

        $this->data = $context->getData() ?: null;

        return $this->data;
    }

    /**
     * Reduce a routing value to a bounded, safe token.
     *
     * @param ?string $value
     *
     * @return string
     */
    private function sanitize(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return substr((string) preg_replace('/[^a-z0-9_\-]/', '', $value), 0, static::MAX_VALUE_LENGTH);
    }
}
