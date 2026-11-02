<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dto\RegisterDto;
use App\Entity\User;
use App\Service\AuthService;
use App\Service\CurrentActor;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The guards of AuthService that HTTP-level tests cannot reach honestly:
 * a date that passes the DTO constraints but cannot be parsed, and the
 * database unique-index race when a username is claimed concurrently.
 */
final class AuthServiceValidationTest extends TestCase
{
    public function testUnparsableBirthDateIsRejectedByTheService(): void
    {
        $auth = $this->authService();

        $dto = new RegisterDto(
            username: 'newuser',
            email: 'new@example.test',
            birthDate: 'not-a-date',
            password: 'secret123',
            passwordConfirm: 'secret123',
        );

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Enter a valid date of birth.');
        $auth->register($dto);
    }

    public function testUsernameClaimRaceBecomesAConflict(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $em->method('getRepository')->willReturn($repo);
        $em->method('flush')->willThrowException(
            new UniqueConstraintViolationException($this->createStub(DriverException::class), null),
        );

        $user = new User('camille@example.com', 'Camille');
        $user->setEmailVerified(true);

        $auth = $this->authService($em);
        $actor = $this->actorWith($user);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('already in use');
        $auth->setUsername($actor, 'free_username');
    }

    private function authService(?EntityManagerInterface $em = null): AuthService
    {
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        return new AuthService(
            $em ?? $this->repositoryMockingEm(),
            $validator,
            $this->createStub(UserPasswordHasherInterface::class),
            $this->createStub(Security::class),
            $this->createStub(MailerInterface::class),
            'https://penderie.test',
            'no-reply@penderie.test',
        );
    }

    private function repositoryMockingEm(): EntityManagerInterface
    {
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }

    private function actorWith(User $user): CurrentActor
    {
        $tokens = $this->createStub(TokenStorageInterface::class);
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $tokens->method('getToken')->willReturn($token);

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack = new RequestStack();
        $stack->push($request);

        $em = $this->createStub(EntityManagerInterface::class);

        return new CurrentActor($tokens, $stack, $em);
    }
}
