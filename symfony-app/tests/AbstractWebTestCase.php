<?php

declare(strict_types=1);

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Photo;
use App\Infrastructure\Http\PhoenixClient;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractWebTestCase extends WebTestCase
{
    private const DEMO_TOKEN = 'demo1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab';

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
     * Loguje użytkownika przez endpoint /auth korzystając ze znaneego tokenu demo.
     * Ponieważ AuthService weryfikuje tylko istnienie tokenu (nie jego powiązanie z userem),
     * można zalogować dowolnego istniejącego użytkownika za pomocą tokenu demo.
     */
    protected function loginAs(string $username = 'demo'): void
    {
        $this->client->request('GET', "/auth/{$username}/" . self::DEMO_TOKEN);
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
