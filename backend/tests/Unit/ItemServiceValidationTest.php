<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dto\UpdateItemDto;
use App\Entity\Household;
use App\Entity\Item;
use App\Entity\Profile;
use App\Enum\ProfileRole;
use App\Service\ItemService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The service-level enum guards of update(): over HTTP the DTO Choice
 * constraints reject unknown values first, so these last-resort checks are
 * exercised directly against the service with a permissive validator.
 */
final class ItemServiceValidationTest extends TestCase
{
    public function testUnknownConditionIsRejectedByTheService(): void
    {
        $service = $this->service();

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Invalid condition.');
        $service->update($this->item(), UpdateItemDto::fromArray(['condition' => 'catastrophique']));
    }

    public function testUnknownStatusIsRejectedByTheService(): void
    {
        $service = $this->service();

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Invalid status.');
        $service->update($this->item(), UpdateItemDto::fromArray(['status' => 'perdu']));
    }

    private function service(): ItemService
    {
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        return new ItemService(
            $this->createStub(EntityManagerInterface::class),
            $validator,
            sys_get_temp_dir(),
            new \App\Service\CollectionSharing($this->createStub(EntityManagerInterface::class), new \App\Service\CommunityIdentityService($this->createStub(EntityManagerInterface::class))),
        );
    }

    private function item(): Item
    {
        $household = new Household('Mon dressing', 'mon-dressing');

        return new Item($household, new Profile($household, 'Moi', ProfileRole::Parent), 'Pull noir');
    }
}
