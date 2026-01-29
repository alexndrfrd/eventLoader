<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Validator;

use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class RequestValidator
{
    public function __construct(
        private ValidatorInterface $validator
    ) {
    }

    public function validate(object $request): array
    {
        $violations = $this->validator->validate($request);

        if (count($violations) === 0) {
            return [];
        }

        $errors = [];
        foreach ($violations as $violation) {
            $property = $violation->getPropertyPath();
            if (!isset($errors[$property])) {
                $errors[$property] = [];
            }
            $errors[$property][] = $violation->getMessage();
        }

        return $errors;
    }

    public function isValid(object $request): bool
    {
        return count($this->validate($request)) === 0;
    }
}
