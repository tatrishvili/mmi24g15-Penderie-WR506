<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Household;
use App\Entity\HouseholdMember;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\ProfileRole;
use App\Service\CurrentActor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * CurrentActor decides who may act (user / household / adult profile) from the
 * token and the session. Every branch is exercised with mocked collaborators —
 * the four decisions are too risky to leave to HTTP-layer happy paths.
 */
final class CurrentActorTest extends TestCase
{
    private User $user;
    private Household $household;

    private ?Household $householdFind = null;
    private ?HouseholdMember $memberFindOneBy = null;
    private ?Profile $profileFindOneBy = null;
    private ?Profile $profileFind = null;

    private SessionInterface $session;

    protected function setUp(): void
    {
        $this->user = new User('camille@example.com', 'Camille');
        $this->household = new Household('Mon dressing', 'mon-dressing');
    }

    public function testProfileSigninIsRefused(): void
    {
        $actor = $this->actor(new Profile($this->household, 'Léo', ProfileRole::Child));

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('no longer supported');
        $actor->tokenUser();
    }

    public function testRequireUserRejectsAnonymousCallers(): void
    {
        $actor = $this->actor(null);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('account holder');
        $actor->requireUser();
    }

    public function testHouseholdIsNullableWithoutAUser(): void
    {
        self::assertNull($this->actor(null)->household());
    }

    public function testHouseholdListIsEmptyWithoutAUser(): void
    {
        self::assertSame([], $this->actor(null)->households());
    }

    public function testProfileIsNullWithoutAUser(): void
    {
        self::assertNull($this->actor(null)->profile());
    }

    public function testProfileIsNullWhenNoHouseholdIsActive(): void
    {
        $actor = $this->actor($this->user);

        self::assertNull($actor->profile());
    }

    public function testProfileIsNullWhenNoProfileIsActive(): void
    {
        $actor = $this->actor($this->user);
        $this->memberFindOneBy = new HouseholdMember($this->user, $this->household);
        $this->householdFind = $this->household;
        $this->session->set('penderie.active_household_id', 5);

        self::assertNull($actor->profile());
    }

    public function testProfileIsNullWhenTheActiveProfileIsUnknown(): void
    {
        $actor = $this->actor($this->user);
        $this->memberFindOneBy = new HouseholdMember($this->user, $this->household);
        $this->householdFind = $this->household;
        $this->session->set('penderie.active_household_id', 5);
        $this->session->set('penderie.active_profile_id', 9);

        self::assertNull($actor->profile());
    }

    public function testRequireAdultFallsBackToTheUsersOwnAdultProfile(): void
    {
        $actor = $this->actor($this->user);
        $own = new Profile($this->household, 'Camille', ProfileRole::Parent, $this->user);
        $this->memberFindOneBy = new HouseholdMember($this->user, $this->household);
        $this->householdFind = $this->household;
        $this->profileFindOneBy = $own;
        $this->session->set('penderie.active_household_id', 5);

        self::assertSame($own, $actor->requireAdultProfile());
    }

    public function testSelectedChildNeverFallsBackToAdultAuthority(): void
    {
        $actor = $this->actor($this->user);
        $this->householdFind = $this->household;
        $this->memberFindOneBy = new HouseholdMember($this->user, $this->household);
        $this->profileFind = new Profile($this->household, 'Léo', ProfileRole::Child);
        $this->profileFindOneBy = new Profile($this->household, 'Camille', ProfileRole::Parent, $this->user);
        $this->session->set('penderie.active_household_id', 5);
        $this->session->set('penderie.active_profile_id', 9);

        $this->expectException(AccessDeniedHttpException::class);
        $actor->requireAdultProfile();
    }

    public function testMissingSelectedProfileCannotRestoreTheAdultAccount(): void
    {
        $actor = $this->actor($this->user);
        $this->householdFind = $this->household;
        $this->memberFindOneBy = new HouseholdMember($this->user, $this->household);
        $this->session->set('penderie.active_household_id', 5);
        $this->session->set('penderie.active_profile_id', 999);

        self::assertNull($actor->household());

        $this->expectException(AccessDeniedHttpException::class);
        $actor->requireUser();
    }

    public function testRequireAdultRejectsAChildProfile(): void
    {
        $actor = $this->actor($this->user);
        $this->memberFindOneBy = new HouseholdMember($this->user, $this->household);
        $this->householdFind = $this->household;
        $this->profileFindOneBy = new Profile($this->household, 'Léo', ProfileRole::Child);
        $this->session->set('penderie.active_household_id', 5);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('adult profile');
        $actor->requireAdultProfile();
    }

    public function testRequireAdultRejectsAnonymousCallers(): void
    {
        $actor = $this->actor(null);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('adult profile');
        $actor->requireAdultProfile();
    }

    private function actor(?object $tokenUser): CurrentActor
    {
        $tokens = $this->createStub(TokenStorageInterface::class);
        $token = null;
        if (null !== $tokenUser) {
            $token = $this->createStub(TokenInterface::class);
            $token->method('getUser')->willReturn($tokenUser);
        }
        $tokens->method('getToken')->willReturn($token);

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->session = $request->getSession();
        $stack = new RequestStack();
        $stack->push($request);

        $householdRepo = $this->createStub(EntityRepository::class);
        $householdRepo->method('find')->willReturnCallback(fn (): ?Household => $this->householdFind instanceof Household ? $this->householdFind : null);

        $memberRepo = $this->createStub(EntityRepository::class);
        $memberRepo->method('findOneBy')->willReturnCallback(fn (): ?HouseholdMember => $this->memberFindOneBy instanceof HouseholdMember ? $this->memberFindOneBy : null);

        $profileRepo = $this->createStub(EntityRepository::class);
        $profileRepo->method('find')->willReturnCallback(fn (): ?Profile => $this->profileFind);
        $profileRepo->method('findOneBy')->willReturnCallback(fn (): ?Profile => $this->profileFindOneBy instanceof Profile ? $this->profileFindOneBy : null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class): EntityRepository => match ($class) {
                Household::class => $householdRepo,
                HouseholdMember::class => $memberRepo,
                Profile::class => $profileRepo,
                default => $this->createStub(EntityRepository::class),
            },
        );

        return new CurrentActor($tokens, $stack, $em);
    }
}
