<?php

namespace App\Controller;

use App\Entity\Task;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Teaching notes (read along):
 * - We use PHP 8 attributes for routing (#[Route(...)]). Each method maps to a URL + HTTP method.
 * - We inject repositories and the EntityManager to talk to the database.
 * - We return either HTML (Twig templates) or JSON (for APIs).
 */


#[Route('/tasks')]
class TaskController extends AbstractController
{
    /**
     * LIST: GET /tasks
     * Shows all tasks (HTML) and returns JSON if requested via Accept: application/json.
     */

   
    #[Route('', name: 'task_index', methods: ['GET'])]
    public function index(TaskRepository $repo, Request $request, PaginatorInterface $paginator): Response
    {
        $qb = $repo->createActiveOrderedQB(); // <-- now exists

        $page    = $request->query->getInt('page', 1);
        $perPage = $request->query->getInt('limit', 7 );

        $pagination = $paginator->paginate($qb, $page, $perPage);

        if ($request->headers->get('Accept') === 'application/json') {
            return $this->json([
                'items'         => iterator_to_array($pagination->getItems()),
                'current_page'  => $pagination->getCurrentPageNumber(),
                'per_page'      => $pagination->getItemNumberPerPage(),
                'total_items'   => $pagination->getTotalItemCount(),
                'total_pages'   => (int) ceil($pagination->getTotalItemCount() / max(1, $pagination->getItemNumberPerPage())),
            ], 200, [], ['groups' => ['task:list']]);
        }

        return $this->render('task/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }



    /**
     * CREATE (Form): GET+POST /tasks/new
     * Renders a form and handles submission.
     */
    #[Route('/new', name: 'task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($task);
            $em->flush();

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
    #[Route('/{id}', name: 'task_show', methods: ['GET'], requirements:['id'=>'\d+'])]
    public function show(Task $task, Request $request): Response
    {
        if ($request->headers->get('Accept') === 'application/json') {
            return $this->json($task, 200, [], ['groups' => ['task:detail']]);
        }

        return $this->render('task/show.html.twig', [
            'task' => $task,
        ]);
    }

    /**
     * EDIT (Form): GET+POST /tasks/{id}/edit
     */
    #[Route('/{id}/edit', name: 'task_edit', methods: ['GET', 'POST'])]
    public function edit(Task $task, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
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
     * Submit from HTML form with hidden _method=DELETE or POST route dedicated to delete.
     */
    
/**
     * Soft-delete: POST /tasks/{id}
     */
    #[Route('/{id}', name: 'task_delete', methods: ['POST'])]
    public function delete(Task $task, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_task_' . $task->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('task_index');
        }

        if (!$task->isDeleted()) {
            $task->setDeletedAt(new DateTimeImmutable());
            $em->flush();
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
    //      * Restore: POST /tasks/{id}/restore
     
    #[Route('/{id}/restore', name: 'task_restore', methods: ['POST'])]
    public function restore(Task $task, EntityManagerInterface $em): Response
    {
        if ($task->isDeleted()) {
            $task->setDeletedAt(null);
            $em->flush();
            $this->addFlash('success', 'Task restored.');
        }
        return $this->redirectToRoute('task_trash');
    }

    /**
     * Purge forever (optional): POST /tasks/{id}/purge
     */
    #[Route('/{id}/purge', name: 'task_purge', methods: ['POST'])]
    public function purge(Task $task, Request $request, EntityManagerInterface $em): Response
    {
        // Optional CSRF
        if (!$this->isCsrfTokenValid('purge_task_' . $task->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('task_trash');
        }

        $em->remove($task);
        $em->flush();
        $this->addFlash('info', 'Task permanently deleted.');

        return $this->redirectToRoute('task_trash');
    }
    /**
     * (Optional) QUICK TOGGLE: POST /tasks/{id}/toggle
     * Handy endpoint to mark done/undone without opening the edit form.
     */
    #[Route('/{id}/toggle', name: 'task_toggle', methods: ['POST'])]
    public function toggle(Task $task, EntityManagerInterface $em): Response
    {
        $task->setIsDone(!$task->isDone());
        $em->flush();
        return $this->redirectToRoute('task_index');
    }
}



//optionall;llllll



// /**
//      * Restore: POST /tasks/{id}/restore
//      */
//     #[Route('/{id}/restore', name: 'task_restore', methods: ['POST'])]
//     public function restore(Task $task, EntityManagerInterface $em): Response
//     {
//         if ($task->isDeleted()) {
//             $task->setDeletedAt(null);
//             $em->flush();
//             $this->addFlash('success', 'Task restored.');
//         }
//         return $this->redirectToRoute('task_trash');
//     }

//     /**
//      * Purge forever (optional): POST /tasks/{id}/purge
//      */
//     #[Route('/{id}/purge', name: 'task_purge', methods: ['POST'])]
//     public function purge(Task $task, Request $request, EntityManagerInterface $em): Response
//     {
//         // Optional CSRF
//         if (!$this->isCsrfTokenValid('purge_task_' . $task->getId(), $request->request->get('_token'))) {
//             $this->addFlash('error', 'Invalid CSRF token.');
//             return $this->redirectToRoute('task_trash');
//         }

//         $em->remove($task);
//         $em->flush();
//         $this->addFlash('info', 'Task permanently deleted.');

//         return $this->redirectToRoute('task_trash');
//     }
// }
