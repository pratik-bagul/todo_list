<?php

namespace App\Tests\Controller;

use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TaskControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        static::createClient(); // boot kernel ONCE
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Clean DB before each test
        $this->em->createQuery('DELETE FROM App\Entity\Task t')->execute();
    }

    private function createTask(string $title, bool $done = false): Task
    {
        $task = new Task();
        $task->setTitle($title);
        $task->setIsDone($done);

        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }

    public function testIndexJsonReturnsTasks(): void
    {
        $this->createTask('Task A');
        $this->createTask('Task B', true);

        static::getClient()->request(
            'GET',
            '/tasks',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertCount(2, $data['items']);
    }

    public function testSoftDeleteAndTrash(): void
    {
        $task = $this->createTask('Delete me');

        $token = static::getContainer()
            ->get('security.csrf.token_manager')
            ->getToken('delete_task_' . $task->getId())
            ->getValue();

        static::getClient()->request('POST', "/tasks/{$task->getId()}", [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/tasks');

        $this->em->refresh($task);
        $this->assertNotNull($task->getDeletedAt());
    }

    public function testRestoreTask(): void
    {
        $task = $this->createTask('Restore me');
        $task->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();

        static::getClient()->request('POST', "/tasks/{$task->getId()}/restore");

        $this->assertResponseRedirects('/tasks/trash');

        $this->em->refresh($task);
        $this->assertNull($task->getDeletedAt());
    }

    public function testToggleTask(): void
    {
        $task = $this->createTask('Toggle me');

        static::getClient()->request('POST', "/tasks/{$task->getId()}/toggle");

        $this->assertResponseRedirects('/tasks');

        $this->em->refresh($task);
        $this->assertTrue($task->isDone());
    }

    public function testShowJson(): void
    {
        $task = $this->createTask('JSON Task');

        static::getClient()->request(
            'GET',
            "/tasks/{$task->getId()}",
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertSame($task->getTitle(), $data['title']);
    }
}
