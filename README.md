# Reduced cost of change through CQS in Symfony

[![Latest Stable Version](https://img.shields.io/badge/stable-1.0.0-blue)](https://packagist.org/packages/digital-craftsman/cqs-routing)
[![PHP Version Require](https://img.shields.io/badge/php-8.2|8.3-5b5d95)](https://packagist.org/packages/digital-craftsman/cqs-routing)
[![codecov](https://codecov.io/gh/digital-craftsman-de/cqs-routing/branch/main/graph/badge.svg?token=YUKRDW1L8G)](https://codecov.io/gh/digital-craftsman-de/cqs-routing)
![Packagist Downloads](https://img.shields.io/packagist/dt/digital-craftsman/cqs-routing)
![Packagist License](https://img.shields.io/packagist/l/digital-craftsman/cqs-routing)

## Installation and configuration

Install package through composer:

```shell
composer require digital-craftsman/cqs-routing
```

Then add the following `cqs-routing.php` file to your `config/packages` and replace it with your instances of the interfaces:

```php
<?php

declare(strict_types=1);

use DigitalCraftsman\CQSRouting\DTOConstructor\SerializerDTOConstructor;
use DigitalCraftsman\CQSRouting\RequestDecoder\JsonRequestDecoder;
use DigitalCraftsman\CQSRouting\ResponseConstructor\EmptyResponseConstructor;
use DigitalCraftsman\CQSRouting\ResponseConstructor\SerializerJsonResponseConstructor;
// Automatically generated by Symfony though a config builder (see https://symfony.com/doc/current/configuration.html#config-config-builder).
use Symfony\Config\CqsRoutingConfig;

return static function (CqsRoutingConfig $cqsRoutingConfig) {
    $cqsRoutingConfig->queryController()
        ->defaultRequestDecoderClass(JsonRequestDecoder::class)
        ->defaultDtoConstructorClass(SerializerDTOConstructor::class)
        ->defaultResponseConstructorClass(SerializerJsonResponseConstructor::class);

    $cqsRoutingConfig->commandController()
        ->defaultRequestDecoderClass(JsonRequestDecoder::class)
        ->defaultDtoConstructorClass(SerializerDTOConstructor::class)
        ->defaultResponseConstructorClass(EmptyResponseConstructor::class);
};
```

You can find the [full configuration here](./docs/configuration.md) (including an example configured with yaml). 

The package contains instances for request decoder, DTO constructor and response constructor. With this you can already use it. You only need to create your own DTO validators, request data transformers and handler wrappers when you want to use those. 

Where and how to use the instances, is described below.

## Why

It's very easy to build a CRUD and REST API with Symfony. There are components like parameter converter which are all geared towards getting data very quickly into a controller to handle the logic there. Unfortunately even though it's very fast to build endpoints with a REST mindset, it's very difficult to handle business logic in a matter that makes changes easy, independent and secure. In short, we have a **[low cost of introduction at the expense of the cost of change](https://www.youtube.com/watch?v=uQUxJObxTUs)**.

Using CQS means fewer dependencies between endpoints and data models and therefore less surface for breaking one endpoint when working on another one. Symfony recently has added more support of constructing DTOs directly in the controller, but there are still a few pieces missing.

The CQS routing bundle closes this gap and **drastically reduces the cost of change** with only slightly higher costs of introduction.

### Overarching goals

The bundle makes it easier to use CQS and has to following goals:

1. Make it very fast and easy to understand **what** is happening (from a business logic perspective).
2. Make the code safer through extensive use of value objects.
3. Make refactoring safer through the extensive use of types.
4. Add clear boundaries between business logic and application / infrastructure logic.

### How

The bundle consists of two starting points, the `CommandController` and the `QueryController` and the following components:

- **Request validator** ([Examples](./docs/examples/request-validator.md))  
*Validates request on an application level.*
- **Request decoder** ([Examples](./docs/examples/request-decoder.md))  
*Decodes the request and transforms it into request data as an array structure.*
- **Request data transformer** ([Examples](./docs/examples/request-data-transformer.md))  
*Transforms the previously generated request data.*
- **DTO constructor** ([Examples](./docs/examples/dto-constructor.md))  
*Generates a command or query from the request data.*
- **DTO validator** ([Examples](./docs/examples/dto-validator.md))  
*Validates the created command or query.*
- **Handler** ([Examples](./docs/examples/handler.md))  
*Command or query handler which contains the business logic.*
- **Handler wrapper** ([Examples](./docs/examples/handler-wrapper.md))  
*Wraps handler to execute logic as a prepare / try / catch logic.*
- **Response constructor** ([Examples](./docs/examples/response-constructor.md))  
*Transforms the gathered data of the handler into a response.*

The process how the controller handles a request can be and when to use which component is [described here](./docs/process.md).

**Routing**

Through the Symfony routing, we define which instances of the components (if relevant) are used for which route. We use PHP files for the routes instead of the default YAML for more type safety and so that renaming of components is easier through the IDE.

A route might look like this:

```php
return static function (RoutingConfigurator $routes) {

    RouteBuilder::addCommandRoute(
        $routes,
        path: '/api/news/create-news-article-command',
        dtoClass: CreateNewsArticleCommand::class,
        handlerClass: CreateNewsArticleCommandHandler::class,
        dtoValidatorClasses: [
            UserIdValidator::class => null,
        ],
    );
    
};
```

You only need to define the components that differ from the defaults configured in the `cqs-routing.php` configuration. Read more about [routing here](./docs/routing.md).

### Command example

Commands and queries are strongly typed value objects which already validate whatever they can. Here is an example command that is used to create a news article:

```php
<?php

declare(strict_types=1);

namespace App\Domain\News\WriteSide\CreateNewsArticle;

use App\Helper\HtmlHelper;
use App\ValueObject\UserId;
use Assert\Assertion;
use DigitalCraftsman\CQSRouting\Command\Command;

final readonly class CreateNewsArticleCommand implements Command
{
    public function __construct(
        public UserId $userId,
        public string $title,
        public string $content,
        public bool $isPublished,
    ) {
        Assertion::notBlank($this->title);
        Assertion::maxLength($this->title, 255);
        
        Assertion::notBlank($this->content);
        Assertion::maxLength($this->content, 1000);
        HtmlHelper::assertValidHtml($this->content);
    }
}

```

The structural validation is therefore already done through the creation of the command and the command handler only has to handle the business logic validation. A command handler might look like this: 

```php
<?php

declare(strict_types=1);

namespace App\Domain\News\WriteSide\CreateNewsArticle;

use App\DomainService\UserCollection;
use App\Entity\NewsArticle;
use App\Time\Clock\ClockInterface;
use App\ValueObject\NewsArticleId;
use DigitalCraftsman\CQSRouting\Command\Command;
use DigitalCraftsman\CQSRouting\Command\CommandHandlerInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CreateNewsArticleCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ClockInterface $clock,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreateProductNewsArticleCommand $command): void
    {
        $commandExecutedAt = $this->clock->now();

        // Validate
        $requestingUser = $this->userRepository->getOne($command->userId);
        $requestingUser->mustNotBeLocked();
        $requestingUser->mustHavePermissionToWriteArticle();

        // Apply
        $this->createNewsArticle(
            $command->title,
            $command->content,
            $command->isPublished,
            $commandExecutedAt,
        );
    }

    private function createNewsArticle(
        string $title,
        string $content,
        bool $isPublished,
        \DateTimeImmutable $commandExecutedAt,
    ): void {
        $newsArticle = new NewsArticle(
            NewsArticleId::generateRandom(),
            $title,
            $content,
            $isPublished,
            $commandExecutedAt,
        );

        $this->entityManager->persist($newsArticle);
        $this->entityManager->flush();
    }
}
```

## Sponsors

[![Blackfire](./sponsors/blackfire.png)](https://blackfire.io)
