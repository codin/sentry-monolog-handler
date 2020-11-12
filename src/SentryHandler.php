<?php

declare(strict_types=1);

namespace Codin\SentryMonologHandler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

class SentryHandler extends AbstractProcessingHandler
{
    protected $hub;

    public function __construct(HubInterface $hub, $level = Logger::DEBUG, bool $bubble = true)
    {
        $this->hub = $hub;
        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record): void
    {
        $this->hub->withScope(function (Scope $scope) use ($record, $payload): void {
            $scope->setExtra('monolog.channel', $record['channel']);
            $scope->setExtra('monolog.level', $record['level_name']);

            foreach (array_diff_key($record['context'], array_flip(['exception', 'extra', 'tags'])) as $key => $value) {
                $scope->setExtra((string) $key, $value);
            }

            if (isset($record['context']['extra']) && \is_array($record['context']['extra'])) {
                foreach ($record['context']['extra'] as $key => $value) {
                    $scope->setExtra((string) $key, $value);
                }
            }

            if (isset($record['context']['tags']) && \is_array($record['context']['tags'])) {
                foreach ($record['context']['tags'] as $key => $value) {
                    $scope->setTag($key, $value);
                }
            }

            if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Throwable) {
                $this->hub->captureException($record['context']['exception']);
            } else {
                $this->hub->captureMessage($record['message'], self::getSeverityFromLevel($record['level']));
            }
        });
    }

    /**
     * Translates the Monolog level into the Sentry severity.
     */
    private static function getSeverityFromLevel(int $level): Severity
    {
        switch ($level) {
            case Logger::DEBUG:
                return Severity::debug();
            case Logger::INFO:
            case Logger::NOTICE:
                return Severity::info();
            case Logger::WARNING:
                return Severity::warning();
            case Logger::ERROR:
                return Severity::error();
            case Logger::CRITICAL:
            case Logger::ALERT:
            case Logger::EMERGENCY:
                return Severity::fatal();
            default:
                return Severity::info();
        }
    }
}