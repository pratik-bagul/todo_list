<?php

namespace App\Tests\Controller;

use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TaskControllerTest extends WebTestCase
{
    public function testIndexPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/tasks');

        $this->assertResponseIsSuccessful();
    }

    public function testCreateTaskPersistsInDatabase(): void
    {
        static::createClient(); // boots kernel once

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $task = new Task();
        $task->setTitle('Task from PHPUnit');

        $em->persist($task);
        $em->flush();

        $this->assertNotNull($task->getId());
    }
}
