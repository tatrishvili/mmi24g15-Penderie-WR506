<?php

declare(strict_types=1);

namespace App\Tests\Permission;

use App\Enum\AccessLevel;
use App\Enum\Capability;
use App\Enum\ResourceStatus;
use App\Permission\Decision;
use App\Permission\PermissionResolver;
use App\Permission\ResolutionContext;
use App\Permission\ResourceType;
use App\Permission\SaleStage;
use App\Permission\ViewerRelation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Matrix v3 encoded as tests — THE source of truth for who sees what.
 * Any product change to the matrix MUST start by changing this file.
 */
final class PermissionMatrixTest extends TestCase
{
    private PermissionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PermissionResolver();
    }

    private function level(ResourceType $type, ResourceStatus $status, ViewerRelation $viewer, ?ResolutionContext $context = null): AccessLevel
    {
        return $this->resolver->resolve($type, $status, $viewer, $context ?? new ResolutionContext())->level;
    }

    /**
     * @return iterable<string, array{ResourceType, ResourceStatus, ViewerRelation, AccessLevel}>
     */
    public static function objectRows(): iterable
    {
        yield 'owner sees own private object' => [ResourceType::Item, ResourceStatus::Private, ViewerRelation::Owner, AccessLevel::Admin];
        yield 'adult edits private object' => [ResourceType::Item, ResourceStatus::Private, ViewerRelation::HouseholdAdult, AccessLevel::Edit];
        yield 'child denied on parent private object' => [ResourceType::Item, ResourceStatus::Private, ViewerRelation::HouseholdChild, AccessLevel::Denied];
        yield 'friend denied on private object' => [ResourceType::Item, ResourceStatus::Private, ViewerRelation::Friend, AccessLevel::Denied];
        yield 'follower denied on private object' => [ResourceType::Item, ResourceStatus::Private, ViewerRelation::Follower, AccessLevel::Denied];
        yield 'stranger denied on private object' => [ResourceType::Item, ResourceStatus::Private, ViewerRelation::Stranger, AccessLevel::Denied];

        yield 'friend views friends-shared object' => [ResourceType::Item, ResourceStatus::Friends, ViewerRelation::Friend, AccessLevel::View];
        yield 'follower denied on friends-shared object' => [ResourceType::Item, ResourceStatus::Friends, ViewerRelation::Follower, AccessLevel::Denied];
        yield 'stranger denied on friends-shared object' => [ResourceType::Item, ResourceStatus::Friends, ViewerRelation::Stranger, AccessLevel::Denied];
        yield 'child views friends-shared object' => [ResourceType::Item, ResourceStatus::Friends, ViewerRelation::HouseholdChild, AccessLevel::View];

        yield 'friend views followers-shared object' => [ResourceType::Item, ResourceStatus::Followers, ViewerRelation::Friend, AccessLevel::View];
        yield 'follower views followers-shared object' => [ResourceType::Item, ResourceStatus::Followers, ViewerRelation::Follower, AccessLevel::View];
        yield 'stranger denied on followers-shared object' => [ResourceType::Item, ResourceStatus::Followers, ViewerRelation::Stranger, AccessLevel::Denied];

        yield 'custom without grant stays closed' => [ResourceType::Item, ResourceStatus::Custom, ViewerRelation::Friend, AccessLevel::Denied];
        yield 'stranger views public object' => [ResourceType::Item, ResourceStatus::Public, ViewerRelation::Stranger, AccessLevel::View];
        yield 'child views public object' => [ResourceType::Item, ResourceStatus::Public, ViewerRelation::HouseholdChild, AccessLevel::View];
    }

    #[DataProvider('objectRows')]
    public function testObjectRows(ResourceType $type, ResourceStatus $status, ViewerRelation $viewer, AccessLevel $expected): void
    {
        $this->assertSame($expected, $this->level($type, $status, $viewer));
    }

    /**
     * @return iterable<string, array{ResourceType, ResourceStatus, ViewerRelation, AccessLevel}>
     */
    public static function personalVetoRows(): iterable
    {
        $types = [ResourceType::Item, ResourceType::Category, ResourceType::Room, ResourceType::Look, ResourceType::Sale];
        $viewers = [ViewerRelation::HouseholdAdult, ViewerRelation::HouseholdChild, ViewerRelation::Friend, ViewerRelation::Follower, ViewerRelation::Stranger, ViewerRelation::LinkHolder];

        foreach ($types as $type) {
            yield "owner keeps {$type->value} personal" => [$type, ResourceStatus::Personal, ViewerRelation::Owner, AccessLevel::Admin];

            foreach ($viewers as $viewer) {
                yield "personal {$type->value} denied for {$viewer->value}" => [$type, ResourceStatus::Personal, $viewer, AccessLevel::Denied];
            }
        }
    }

    #[DataProvider('personalVetoRows')]
    public function testPersonalVetoIsAbsolute(ResourceType $type, ResourceStatus $status, ViewerRelation $viewer, AccessLevel $expected): void
    {
        $decision = $this->resolver->resolve($type, $status, $viewer, new ResolutionContext(grantedCaps: [Capability::Comment, Capability::Lend, Capability::Buy]));

        $this->assertSame($expected, $decision->level);
        $this->assertSame([], $decision->capabilities);
    }

    /**
     * @return iterable<string, array{ResourceType, ResourceStatus, ViewerRelation, AccessLevel}>
     */
    public static function structureRows(): iterable
    {
        foreach ([ResourceType::Category, ResourceType::Storage, ResourceType::Room] as $type) {
            yield "{$type->value} private: child views structure" => [$type, ResourceStatus::Private, ViewerRelation::HouseholdChild, AccessLevel::View];
            yield "{$type->value} private: adult edits" => [$type, ResourceStatus::Private, ViewerRelation::HouseholdAdult, AccessLevel::Edit];
            yield "{$type->value} private: friend denied" => [$type, ResourceStatus::Private, ViewerRelation::Friend, AccessLevel::Denied];
            yield "{$type->value} shared: friend views" => [$type, ResourceStatus::Friends, ViewerRelation::Friend, AccessLevel::View];
            yield "{$type->value} shared: follower denied" => [$type, ResourceStatus::Friends, ViewerRelation::Follower, AccessLevel::Denied];
            // C5 rule: rooms / storages / categories are NEVER public to strangers.
            yield "{$type->value} public: stranger denied" => [$type, ResourceStatus::Public, ViewerRelation::Stranger, AccessLevel::Denied];
            yield "{$type->value} public: friend denied" => [$type, ResourceStatus::Public, ViewerRelation::Friend, AccessLevel::Denied];
        }

        yield 'home: adult edits' => [ResourceType::Home, ResourceStatus::Private, ViewerRelation::HouseholdAdult, AccessLevel::Edit];
        yield 'home: child views' => [ResourceType::Home, ResourceStatus::Private, ViewerRelation::HouseholdChild, AccessLevel::View];
        yield 'home: friend denied' => [ResourceType::Home, ResourceStatus::Friends, ViewerRelation::Friend, AccessLevel::Denied];
        yield 'home: stranger denied' => [ResourceType::Home, ResourceStatus::Public, ViewerRelation::Stranger, AccessLevel::Denied];
    }

    #[DataProvider('structureRows')]
    public function testStructureRows(ResourceType $type, ResourceStatus $status, ViewerRelation $viewer, AccessLevel $expected): void
    {
        $this->assertSame($expected, $this->level($type, $status, $viewer));
    }

    /**
     * @return iterable<string, array{ResourceType, ResourceStatus, ViewerRelation, AccessLevel}>
     */
    public static function lookRows(): iterable
    {
        yield 'private look: friend denied' => [ResourceType::Look, ResourceStatus::Private, ViewerRelation::Friend, AccessLevel::Denied];
        yield 'private look: follower denied' => [ResourceType::Look, ResourceStatus::Private, ViewerRelation::Follower, AccessLevel::Denied];
        yield 'shared look: friend views' => [ResourceType::Look, ResourceStatus::Friends, ViewerRelation::Friend, AccessLevel::View];
        yield 'shared look: follower views' => [ResourceType::Look, ResourceStatus::Friends, ViewerRelation::Follower, AccessLevel::View];
        yield 'shared look: stranger denied' => [ResourceType::Look, ResourceStatus::Friends, ViewerRelation::Stranger, AccessLevel::Denied];
        yield 'public look: stranger views' => [ResourceType::Look, ResourceStatus::Public, ViewerRelation::Stranger, AccessLevel::View];
        yield 'public look: adult edits' => [ResourceType::Look, ResourceStatus::Public, ViewerRelation::HouseholdAdult, AccessLevel::Edit];
    }

    #[DataProvider('lookRows')]
    public function testLookRows(ResourceType $type, ResourceStatus $status, ViewerRelation $viewer, AccessLevel $expected): void
    {
        $this->assertSame($expected, $this->level($type, $status, $viewer));
    }

    /**
     * @return iterable<string, array{ResourceType, ResourceStatus, ViewerRelation, bool, AccessLevel}>
     */
    public static function loanRows(): iterable
    {
        yield 'owner administers own loan' => [ResourceType::Loan, ResourceStatus::Private, ViewerRelation::Owner, false, AccessLevel::Admin];
        yield 'adult views household loan' => [ResourceType::Loan, ResourceStatus::Private, ViewerRelation::HouseholdAdult, false, AccessLevel::View];
        yield 'child views own loan' => [ResourceType::Loan, ResourceStatus::Private, ViewerRelation::HouseholdChild, true, AccessLevel::View];
        yield 'child denied on sibling loan' => [ResourceType::Loan, ResourceStatus::Private, ViewerRelation::HouseholdChild, false, AccessLevel::Denied];
        yield 'friend never sees loans' => [ResourceType::Loan, ResourceStatus::Private, ViewerRelation::Friend, false, AccessLevel::Denied];
        yield 'stranger never sees loans' => [ResourceType::Loan, ResourceStatus::Private, ViewerRelation::Stranger, false, AccessLevel::Denied];
    }

    #[DataProvider('loanRows')]
    public function testLoanRows(ResourceType $type, ResourceStatus $status, ViewerRelation $viewer, bool $isOwn, AccessLevel $expected): void
    {
        $this->assertSame($expected, $this->level($type, $status, $viewer, new ResolutionContext($isOwn)));
    }

    /**
     * @return iterable<string, array{ResourceStatus, ViewerRelation, AccessLevel}>
     */
    public static function saleRows(): iterable
    {
        yield 'private sale: friend denied' => [ResourceStatus::Private, ViewerRelation::Friend, AccessLevel::Denied];
        yield 'private sale: follower denied' => [ResourceStatus::Private, ViewerRelation::Follower, AccessLevel::Denied];
        yield 'shared sale: friend views' => [ResourceStatus::Friends, ViewerRelation::Friend, AccessLevel::View];
        yield 'shared sale: follower views' => [ResourceStatus::Followers, ViewerRelation::Follower, AccessLevel::View];
        yield 'shared sale: stranger denied' => [ResourceStatus::Followers, ViewerRelation::Stranger, AccessLevel::Denied];
        yield 'public sale: stranger views listing' => [ResourceStatus::Public, ViewerRelation::Stranger, AccessLevel::View];
        yield 'adult views sale' => [ResourceStatus::Private, ViewerRelation::HouseholdAdult, AccessLevel::View];
    }

    #[DataProvider('saleRows')]
    public function testSaleRows(ResourceStatus $status, ViewerRelation $viewer, AccessLevel $expected): void
    {
        $this->assertSame($expected, $this->level(ResourceType::Sale, $status, $viewer));
    }

    public function testShareLinkHolderSeesPerimeterReadOnly(): void
    {
        $decision = $this->resolver->resolve(
            ResourceType::Item,
            ResourceStatus::Friends,
            ViewerRelation::LinkHolder,
            new ResolutionContext(grantedCaps: [Capability::Comment]),
        );

        $this->assertSame(AccessLevel::View, $decision->level);
        $this->assertTrue($decision->can(Capability::Comment));
        $this->assertFalse($decision->allows(AccessLevel::Edit));
    }

    public function testShareLinkNeverCrossesPersonal(): void
    {
        $decision = $this->resolver->resolve(
            ResourceType::Item,
            ResourceStatus::Personal,
            ViewerRelation::LinkHolder,
            new ResolutionContext(grantedCaps: [Capability::Comment]),
        );

        $this->assertSame(AccessLevel::Denied, $decision->level);
    }

    public function testLendCapabilityIsFriendsOnly(): void
    {
        $caps = [Capability::Lend];

        $this->assertSame([Capability::Lend], $this->resolver->filterCapabilities(ViewerRelation::Friend, $caps));
        $this->assertSame([], $this->resolver->filterCapabilities(ViewerRelation::Follower, $caps));
        $this->assertSame([], $this->resolver->filterCapabilities(ViewerRelation::Stranger, $caps));
    }

    public function testCommentCapabilityIsGrantedToFriendsFollowersAndLinkHolders(): void
    {
        $caps = [Capability::Comment];

        $this->assertSame([Capability::Comment], $this->resolver->filterCapabilities(ViewerRelation::Friend, $caps));
        $this->assertSame([Capability::Comment], $this->resolver->filterCapabilities(ViewerRelation::Follower, $caps));
        $this->assertSame([Capability::Comment], $this->resolver->filterCapabilities(ViewerRelation::Stranger, $caps));
    }

    public function testBuyCapabilityFollowsSaleStages(): void
    {
        $caps = [Capability::Buy];

        // Friends buy as soon as the friends stage is open.
        $this->assertSame([Capability::Buy], $this->resolver->filterCapabilities(ViewerRelation::Friend, $caps, SaleStage::Friends));
        // Followers buy only once the followers stage is open.
        $this->assertSame([], $this->resolver->filterCapabilities(ViewerRelation::Follower, $caps, SaleStage::Friends));
        $this->assertSame([Capability::Buy], $this->resolver->filterCapabilities(ViewerRelation::Follower, $caps, SaleStage::Followers));
        // Strangers never buy (default), and no stage means no purchase at all.
        $this->assertSame([], $this->resolver->filterCapabilities(ViewerRelation::Stranger, $caps, SaleStage::Followers));
        $this->assertSame([], $this->resolver->filterCapabilities(ViewerRelation::Friend, $caps, null));
    }

    // ---- Séance situations ----

    public function testSituation1SharedSummerWardrobeWithPersonalItem(): void
    {
        // Camille shares her summer items with Sophie (friend): Sophie sees them…
        $this->assertSame(
            AccessLevel::View,
            $this->level(ResourceType::Item, ResourceStatus::Friends, ViewerRelation::Friend),
        );
        // …but the PERSONAL item stays invisible, with no trace of it.
        $this->assertSame(
            AccessLevel::Denied,
            $this->level(ResourceType::Item, ResourceStatus::Personal, ViewerRelation::Friend),
        );
    }

    public function testSituation2LinkForwardedToSister(): void
    {
        // Sophie forwards her link to her sister (a stranger to Camille):
        // same perimeter, read-only, no edit.
        $decision = $this->resolver->resolve(
            ResourceType::Item,
            ResourceStatus::Friends,
            ViewerRelation::LinkHolder,
            new ResolutionContext(grantedCaps: [Capability::Comment, Capability::Lend]),
        );

        $this->assertSame(AccessLevel::View, $decision->level);
        $this->assertTrue($decision->can(Capability::Comment));
        // Transferred links never carry lending rights for strangers.
        $this->assertFalse($decision->can(Capability::Lend));
        $this->assertFalse($decision->allows(AccessLevel::Edit));
    }

    public function testSituation3DowngradedFriendStillSeesFollowersItems(): void
    {
        // Sophie is downgraded from friend to follower: she keeps followers-shared
        // items but loses friends-only ones.
        $this->assertSame(
            AccessLevel::View,
            $this->level(ResourceType::Item, ResourceStatus::Followers, ViewerRelation::Follower),
        );
        $this->assertSame(
            AccessLevel::Denied,
            $this->level(ResourceType::Item, ResourceStatus::Friends, ViewerRelation::Follower),
        );
    }

    public function testSituation4PasserbySeesNothingPrivate(): void
    {
        $this->assertSame(
            AccessLevel::Denied,
            $this->level(ResourceType::Item, ResourceStatus::Private, ViewerRelation::Stranger),
        );
        $this->assertSame(
            AccessLevel::Denied,
            $this->level(ResourceType::Room, ResourceStatus::Private, ViewerRelation::Stranger),
        );
        $this->assertSame(
            AccessLevel::View,
            $this->level(ResourceType::Item, ResourceStatus::Public, ViewerRelation::Stranger),
        );
    }

    public function testHigherLevelNeverCrossesADeniedRow(): void
    {
        // Even an adult EDIT never crosses PERSONAL: the row wins over the level.
        $this->assertSame(
            AccessLevel::Denied,
            $this->level(ResourceType::Item, ResourceStatus::Personal, ViewerRelation::HouseholdAdult),
        );

        $decision = new Decision(AccessLevel::View);
        $this->assertTrue($decision->allows(AccessLevel::View));
        $this->assertFalse($decision->allows(AccessLevel::Edit));
    }
}
