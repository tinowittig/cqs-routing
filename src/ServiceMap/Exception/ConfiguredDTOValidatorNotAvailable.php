<?php

declare(strict_types=1);

namespace DigitalCraftsman\CQRS\ServiceMap\Exception;

/** @psalm-immutable */
final class ConfiguredDTOValidatorNotAvailable extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The configured DTO validator is not available');
    }
}
