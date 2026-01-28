<?php

namespace App\Controller;

use App\Entity\Task;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Teaching notes:
 * - PHP 8 attributes for routing (#[Route(...)]). Each method maps to a URL + HTTP method.
 * - Inject repositories and the EntityManager to talk to the database.
 * - Return either HTML (Twig templates) or JSON (for APIs).
 */
#[Route('/tasks')]
class TaskController extends AbstractController
{
    /**
     * LIST: GET /tasks
     * Shows all tasks (HTML) and returns JSON if requested via Accept: application/json.
     */
    #[Route('', name: 'task_index', methods: ['GET'])]
    public function index(
        TaskRepository $repo,
        Request $request,
        PaginatorInterface $paginator,
        TagAwareCacheInterface $tasksTaggedCache
    ): Response {
        $qb = $repo->createActiveOrderedQB();

        $page    = $request->query->getInt('page', 1);
        $perPage = $request->query->getInt('limit', 7);

        $pagination = $paginator->paginate($qb, $page, $perPage);

        // JSON response branch
        if ($request->headers->get('Accept') === 'application/json') {
            $key = sprintf('tasks:list:p%d:l%d', (int)$page, (int)$perPage);

            $json = $tasksTaggedCache->get($key, function (ItemInterface $item) use ($pagination) {
                $item->expiresAfter(180);                 // 3 minutes
                $item->tag(['tasks:all-active']);         // tag for bulk invalidation

                $items = [];
                foreach ($pagination->getItems() as $task) {
                    \assert($task instanceof Task);
                    $items[] = $this->taskToArray($task);
                }

                $payload = [
                    'items'        => $items,
                    'current_page' => $pagination->getCurrentPageNumber(),
                    'per_page'     => $pagination->getItemNumberPerPage(),
                    'total_items'  => $pagination->getTotalItemCount(),
                    'total_pages'  => (int) ceil(
                        $pagination->getTotalItemCount() / max(1, $pagination->getItemNumberPerPage())
                    ),
                ];

                return json_encode($payload, JSON_UNESCAPED_UNICODE);
            });

            // $json is already a JSON-encoded string; use JsonResponse with $json as raw.
            return new JsonResponse($json, 200, [], true);
        }

        // HTML response
        return $this->render('task/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    /**
     * CREATE (Form): GET+POST /tasks/new
     * Renders a form and handles submission.
     */
    #[Route('/new', name: 'task_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        TagAwareCacheInterface $tasksTaggedCache
    ): Response {
        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($task);
            $em->flush();

            // Invalidate all list pages
            $tasksTaggedCache->invalidateTags(['tasks:all-active']);

            $this->addFlash('success', 'Task created!');
            return $this->redirectToRoute('task_index');
        }

        return $this->render('task/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * SHOW: GET /tasks/{id}
     * Automatic ParamConverter fetches Task by id.
     */
    #[Route('/{id}', name: 'task_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        Task $task,
        Request $request,
        CacheItemPoolInterface $taskItemCache
    ): Response {
        if ($request->headers->get('Accept') === 'application/json') {
            $key = sprintf('task:%d', $task->getId());

            $cacheItem = $taskItemCache->getItem($key);
            if (!$cacheItem->isHit()) {
                $payload = $this->taskToArray($task);
                $cacheItem->set(json_encode($payload, JSON_UNESCAPED_UNICODE));
                $taskItemCache->save($cacheItem);
            }

            return new JsonResponse($cacheItem->get(), 200, [], true);
        }

        return $this->render('task/show.html.twig', [
            'task' => $task,
        ]);
    }

    /**
     * EDIT (Form): GET+POST /tasks/{id}/edit
     */
    #[Route('/{id}/edit', name: 'task_edit', methods: ['GET', 'POST'])]
    public function edit(
        Task $task,
        Request $request,
        EntityManagerInterface $em,
        TagAwareCacheInterface $tasksTaggedCache,
        CacheItemPoolInterface $taskItemCache
    ): Response {
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            // Invalidate list and the item cache
            $tasksTaggedCache->invalidateTags(['tasks:all-active']);
            $taskItemCache->deleteItem(sprintf('task:%d', $task->getId()));

            $this->addFlash('success', 'Task updated!');
            return $this->redirectToRoute('task_index');
        }

        return $this->render('task/edit.html.twig', [
            'task' => $task,
            'form' => $form,
        ]);
    }

    /**
     * DELETE (CSRF-protected): POST /tasks/{id}
     * Soft-delete using deletedAt.
     */
    #[Route('/{id}', name: 'task_delete', methods: ['POST'])]
    public function delete(
        Task $task,
        Request $request,
        EntityManagerInterface $em,
        TagAwareCacheInterface $tasksTaggedCache,
        CacheItemPoolInterface $taskItemCache
    ): Response {
        if (!$this->isCsrfTokenValid('delete_task_' . $task->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('task_index');
        }

        if (!$task->isDeleted()) {
            $task->setDeletedAt(new DateTimeImmutable());
            $em->flush();

            $tasksTaggedCache->invalidateTags(['tasks:all-active']);
            $taskItemCache->deleteItem(sprintf('task:%d', $task->getId()));

            $this->addFlash('info', 'Task deleted.');
        }

        return $this->redirectToRoute('task_index');
    }

    /**
     * Trash listing: GET /tasks/trash
     */
    #[Route('/trash', name: 'task_trash', methods: ['GET'])]
    public function trash(TaskRepository $repo): Response
    {
        return $this->render('task/trash.html.twig', [
            'tasks' => $repo->findTrashed(),
        ]);
    }

    /**
     * Restore: POST /tasks/{id}/restore
     */
    #[Route('/{id}/restore', name: 'task_restore', methods: ['POST'])]
    public function restore(
        Task $task,
        EntityManagerInterface $em,
        TagAwareCacheInterface $tasksTaggedCache,
        CacheItemPoolInterface $taskItemCache
    ): Response {
        if ($task->isDeleted()) {
            $task->setDeletedAt(null);
            $em->flush();

            $tasksTaggedCache->invalidateTags(['tasks:all-active']);
            $taskItemCache->deleteItem(sprintf('task:%d', $task->getId()));

            $this->addFlash('success', 'Task restored.');
        }
        return $this->redirectToRoute('task_trash');
    }

    /**
     * Purge forever (optional): POST /tasks/{id}/purge
     */
    #[Route('/{id}/purge', name: 'task_purge', methods: ['POST'])]
    public function purge(
        Task $task,
        Request $request,
        EntityManagerInterface $em,
        TagAwareCacheInterface $tasksTaggedCache,
        CacheItemPoolInterface $taskItemCache
    ): Response {
        if (!$this->isCsrfTokenValid('purge_task_' . $task->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('task_trash');
        }

        $em->remove($task);
        $em->flush();

        $tasksTaggedCache->invalidateTags(['tasks:all-active']);
        $taskItemCache->deleteItem(sprintf('task:%d', $task->getId()));

        $this->addFlash('info', 'Task permanently deleted.');

        return $this->redirectToRoute('task_trash');
    }

    /**
     * QUICK TOGGLE: POST /tasks/{id}/toggle
     * Mark done/undone without opening the edit form.
     */
    #[Route('/{id}/toggle', name: 'task_toggle', methods: ['POST'])]
    public function toggle(
        Task $task,
        EntityManagerInterface $em,
        TagAwareCacheInterface $tasksTaggedCache,
        CacheItemPoolInterface $taskItemCache
    ): Response {
        $task->setIsDone(!$task->isDone());
        $em->flush();

        $tasksTaggedCache->invalidateTags(['tasks-all-active']);
        $taskItemCache->deleteItem(sprintf('task-%d', $task->getId()));

        return $this->redirectToRoute('task_index');
    }

    /**
     * Helper to expose Task fields in JSON.
     */
    private function taskToArray(Task $t): array
    {
        return [
            'id'     => $t->getId(),
            'title'  => $t->getTitle(),
            'isDone' => $t->isDone(),
            'dueAt'  => $t->getDueAt()?->format('Y-m-d H:i:s'),
            // Add any fields you expose in JSON
        ];
    }
}