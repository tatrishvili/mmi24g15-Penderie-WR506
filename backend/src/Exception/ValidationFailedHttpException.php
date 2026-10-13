<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ValidationFailedHttpException extends UnprocessableEntityHttpException
{
    /**
     * @param array<string, string> $violations
     */
    public function __construct(
        private array $violations = [],
        string $message = 'Validation failed.',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }

    public static function fromViolations(ConstraintViolationListInterface $violations): self
    {
        $mapped = [];

        foreach ($violations as $violation) {
            $mapped[(string) $violation->getPropertyPath()] = (string) $violation->getMessage();
        }

        return new self($mapped);
    }
}
