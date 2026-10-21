<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core\Exceptions;

final class AuthenticationFailedException extends FaydaException
{
    public function __construct(
        string $message,
        private readonly ?string $error = null,
        private readonly ?string $errorDescription = null,
    ) {
        parent::__construct($message);
    }

    public static function fromIdp(string $error, ?string $description = null): self
    {
        $message = 'Fayda authentication failed: '.$error;

        if ($description !== null && $description !== '') {
            $message .= ' ('.$description.')';
        }

        return new self($message, $error, $description);
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function errorDescription(): ?string
    {
        return $this->errorDescription;
    }
}
