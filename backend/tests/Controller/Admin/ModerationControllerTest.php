<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\AccountSuspension;
use App\Entity\User;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class ModerationControllerTest extends ApiTestCase
{
    public function testUsersCanBeSearchedFilteredPaginatedSuspendedAndRestored(): void
    {
        $client = static::createClient();
        self::registerAccount($client, 'admin@example.com');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $em->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertInstanceOf(User::class, $admin);
        $admin->promote('ROLE_SUPER_ADMIN');

        for ($index = 0; $index < 27; ++$index) {
            $member = new User(sprintf('member-%02d@example.com', $index), 'Member', sprintf('member_%02d', $index));
            $member->setPassword('not-used');
            $em->persist($member);
        }
        $em->flush();
        $client->loginUser($admin);

        $client->request('GET', '/admin/utilisateurs', [
            'q' => 'member',
            'status' => 'enabled',
            'sort' => 'email',
            'direction' => 'asc',
            'page' => '2',
        ]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('member-25@example.com', (string) $client->getResponse()->getContent());
        self::assertMatchesRegularExpression('/Page 2 (?:of|sur) 2/', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('member-00@example.com', (string) $client->getResponse()->getContent());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $target = $em->getRepository(User::class)->findOneBy(['email' => 'member-00@example.com']);
        self::assertInstanceOf(User::class, $target);
        $targetId = $target->getId();
        self::assertNotNull($targetId);

        $client->request('GET', '/admin/utilisateurs', [
            'q' => 'member-00',
            'status' => 'enabled',
            'sort' => 'email',
            'direction' => 'asc',
        ]);
        self::assertResponseIsSuccessful();
        $token = $client->getCrawler()
            ->filterXPath('//form[@action="/admin/utilisateurs/'.$targetId.'/suspendre"]//input[@name="_token"]')
            ->attr('value');
        $client->request('POST', '/admin/utilisateurs/'.$targetId.'/suspendre', [
            '_token' => $token,
            'reason' => 'policy',
        ]);
        self::assertResponseRedirects('/admin/utilisateurs');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $suspended = $em->getRepository(User::class)->find($targetId);
        self::assertInstanceOf(User::class, $suspended);
        self::assertFalse($suspended->isEnabled());
        $suspension = $em->getRepository(AccountSuspension::class)->findOneBy(['user' => $suspended, 'restoredAt' => null]);
        self::assertInstanceOf(AccountSuspension::class, $suspension);
        self::assertSame('policy', $suspension->reason);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $em->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertInstanceOf(User::class, $admin);
        $client->loginUser($admin);
        $client->request('GET', '/admin/utilisateurs', [
            'q' => 'member-00',
            'status' => 'suspended',
            'sort' => 'email',
            'direction' => 'asc',
        ]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('member-00@example.com', (string) $client->getResponse()->getContent());

        $token = $client->getCrawler()
            ->filterXPath('//form[@action="/admin/utilisateurs/'.$targetId.'/restaurer"]//input[@name="_token"]')
            ->attr('value');
        $client->request('POST', '/admin/utilisateurs/'.$targetId.'/restaurer', [
            '_token' => $token,
        ]);
        self::assertResponseRedirects('/admin/utilisateurs');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $restored = $em->getRepository(User::class)->find($targetId);
        self::assertInstanceOf(User::class, $restored);
        self::assertTrue($restored->isEnabled());
        $restoredSuspension = $em->getRepository(AccountSuspension::class)->findOneBy(['user' => $restored], ['id' => 'DESC']);
        self::assertInstanceOf(AccountSuspension::class, $restoredSuspension);
        self::assertNotNull($restoredSuspension->restoredAt);
    }

    public function testNonSuperAdminCannotOpenModerationQueue(): void
    {
        $client = static::createClient();
        self::registerAccount($client, 'regular@example.com');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'regular@example.com']);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user);

        $client->request('GET', '/admin/signalements');

        self::assertResponseStatusCodeSame(403);
    }
}
