<?php

namespace App\Services\Wordstat;

class WordstatException extends \RuntimeException
{
    private bool $retryable;

    public function __construct(string $message, bool $retryable)
    {
        parent::__construct($message);
        $this->retryable = $retryable;
    }

    public static function retryable(string $message): self
    {
        return new self($message, true);
    }

    public static function fatal(string $message): self
    {
        return new self($message, false);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
