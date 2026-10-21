<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core\Exceptions;

final class TokenRequestException extends FaydaException
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromResponse(array $data, int $statusCode, string $body): self
    {
        $error = isset($data['error']) ? (string) $data['error'] : null;
        $description = isset($data['error_description']) ? (string) $data['error_description'] : null;

        $message = 'Fayda token request failed';
        if ($error !== null) {
            $message .= ': '.$error;
        }
        if ($description !== null && $description !== '') {
            $message .= ' ('.$description.')';
        }

        return new self($message, $error, $description, $statusCode, $body);
    }

    public static function invalidResponse(int $statusCode, string $body, ?\Throwable $previous = null): self
    {
        return new self(
            'Fayda token endpoint returned an invalid response (HTTP '.$statusCode.').',
            statusCode: $statusCode,
            responseBody: $body,
            previous: $previous,
        );
    }

    public function __construct(
        string $message,
        private readonly ?string $error = null,
        private readonly ?string $errorDescription = null,
        private readonly ?int $statusCode = null,
        private readonly ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function errorDescription(): ?string
    {
        return $this->errorDescription;
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    public function responseBody(): ?string
    {
        return $this->responseBody;
    }
}
