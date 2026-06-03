<?php

declare(strict_types=1);

namespace Fulcrum\Foundation\Exceptions;

use Fulcrum\Foundation\Config;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Top-level exception handler.
 *
 * In debug mode (APP_DEBUG=true) the full stack trace is emitted as a
 * JSON error response. In production, only a safe generic message is sent
 * so internal details are never leaked to clients.
 */
class Handler
{
    private bool $debug;

    public function __construct(
        Config $config,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->debug = (bool) $config->get('app.debug', false);
    }

    public function report(Throwable $e): void
    {
        if ($this->logger === null) {
            return;
        }

        try {
            $this->logger->critical($e->getMessage(), [
                'exception' => $e,
            ]);
        } catch (Throwable) {
        }
    }

    /**
     * Render a Throwable as a JSON HTTP response and exit.
     */
    public function handle(Throwable $e): never
    {
        $this->report($e);

        $payload = $this->debug
            ? [
                'message' => $e->getMessage(),
                'class'   => $e::class,
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => explode("\n", $e->getTraceAsString()),
              ]
            : ['message' => 'An internal server error occurred.'];

        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['errors' => [$payload]], JSON_UNESCAPED_SLASHES);
        exit(1);
    }

    /**
     * Register this handler as the PHP-level uncaught exception handler.
     */
    public function register(): void
    {
        set_exception_handler(function (Throwable $e): void {
            $this->handle($e);
        });
    }
}
