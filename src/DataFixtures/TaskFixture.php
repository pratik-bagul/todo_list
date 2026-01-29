<?php

namespace App\DataFixtures;

use App\Entity\Task;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TaskFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $task = new Task();
            $task->setTitle('Dummy Task ' . $i);
            $task->setIsDone(false);

            $manager->persist($task);
        }

        $manager->flush();
    }
}
