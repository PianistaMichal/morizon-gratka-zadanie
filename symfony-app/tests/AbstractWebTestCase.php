<?php

declare(strict_types=1);

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\AuthToken;
use App\Entity\Photo;
use App\Entity\User;
use App\Service\PhoenixClient;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        static::getContainer()->set(PhoenixClient::class, $this->createMock(PhoenixClient::class));
        $this->loadFixtures();
    }

    protected function loadFixtures(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $purger = new ORMPurger($em);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_DELETE);

        $executor = new ORMExecutor($em, $purger);
        $executor->execute([new AppFixtures()]);
    }

    /**
     * Loguje użytkownika przez endpoint POST /login używając tokenu przypisanego do tego użytkownika w bazie.
     * Każdy użytkownik ma swój własny token — nie można zalogować się cudzym tokenem.
     */
    protected function loginAs(string $username = 'demo'): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $em->getRepository(User::class)->findOneBy(['username' => $username]);

        if ($user === null) {
            $this->fail("User '{$username}' not found in database. Did you load fixtures?");
        }

        $authToken = $em->getRepository(AuthToken::class)->findOneBy(['user' => $user]);

        if ($authToken === null) {
            $this->fail("No AuthToken found for user '{$username}'. Did you load fixtures?");
        }

        $this->client->request('POST', '/auth', [
            'username' => $username,
            'token' => $authToken->getToken(),
        ]);
    }

    /**
     * Zwraca treść odpowiedzi HTTP jako string (Response::getContent() zwraca string|false).
     */
    protected function getResponseContent(): string
    {
        $content = $this->client->getResponse()->getContent();
        self::assertNotFalse($content, 'Response content must not be false.');

        return $content;
    }

    /**
     * Zwraca pierwsze zdjęcie z bazy — używane w testach zamiast zakładania konkretnego ID.
     */
    protected function getFirstPhoto(): Photo
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $photo = $em->getRepository(Photo::class)->findOneBy([], ['id' => 'ASC']);

        if ($photo === null) {
            $this->fail('No photos in database. Did you load fixtures?');
        }

        return $photo;
    }
}
