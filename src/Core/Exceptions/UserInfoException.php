<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core\Exceptions;

final class UserInfoException extends FaydaException
{
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
        private readonly ?string $responseBody = null,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(int $statusCode, string $body): self
    {
        return new self(
            'Fayda userinfo request failed with HTTP '.$statusCode.'.',
            $statusCode,
            $body,
        );
    }

    public static function invalidResponse(string $reason): self
    {
        return new self('Fayda userinfo returned an invalid response: '.$reason);
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
