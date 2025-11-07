<?php

namespace Ad2210\MultiProjectTeamPlanning\Controller;

use DateInterval;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/demo', name: 'ad2210_mptp_demo_', methods: ['GET'])]
final class DemoController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(): Response
    {
        // Vue équipe (team)
        [$users, $projects, $events] = $this->mockData();

        return $this->render('@MultiProjectTeamPlanning/demo/demo.html.twig', [
            'mode'     => 'team',
            'users'    => $users,
            'projects' => $projects,
            'events'   => $this->eventsToJs($events),
        ]);
    }

    #[Route('/user/{id}', name: 'user', requirements: ['id' => '\d+'])]
    public function user(int $id): Response
    {
        [$users, $projects, $events] = $this->mockData();

        // Filtre initial côté serveur (la page charge directement la bonne vue)
        $events = array_values(array_filter($events, fn ($e) => $e['userId'] === $id));

        return $this->render('@MultiProjectTeamPlanning/demo/demo.html.twig', [
            'mode'          => 'user',
            'selectedUser'  => $id,
            'users'         => $users,
            'projects'      => $projects,
            'events'        => $this->eventsToJs($events),
        ]);
    }

    #[Route('/project/{id}', name: 'project', requirements: ['id' => '\d+'])]
    public function project(int $id): Response
    {
        [$users, $projects, $events] = $this->mockData();

        $events = array_values(array_filter($events, fn ($e) => $e['projectId'] === $id));

        return $this->render('@MultiProjectTeamPlanning/demo/demo.html.twig', [
            'mode'             => 'project',
            'selectedProject'  => $id,
            'users'            => $users,
            'projects'         => $projects,
            'events'           => $this->eventsToJs($events),
        ]);
    }

    /** @return array{0: array<int, array>, 1: array<int, array>, 2: array<int, array>} */
    private function mockData(): array
    {
        // Users
        $users = [
            ['id' => 1, 'name' => 'Alice', 'color' => '#3b82f6'],
            ['id' => 2, 'name' => 'Bob',   'color' => '#10b981'],
            ['id' => 3, 'name' => 'Chloé', 'color' => '#f59e0b'],
        ];

        // Projects (worksites)
        $projects = [
            ['id' => 101, 'name' => 'Rénovation Toiture', 'color' => '#e11d48'],
            ['id' => 102, 'name' => 'Extension Salon',     'color' => '#8b5cf6'],
        ];

        // Events (slots) — semaine courante
        $monday = (new DateTimeImmutable('monday this week'))->setTime(0, 0);
        $events = [
            $this->slot('Kickoff', $monday->setTime(9, 0), 90,  1, 101, $users, $projects),
            $this->slot('RDV Client', $monday->setTime(9, 30), 30, 2, 101, $users, $projects),
            $this->slot('Pose charpente', $monday->setTime(13, 0), 180, 3, 102, $users, $projects),

            $this->slot('Prépa chantier', $monday->modify('+1 day')->setTime(8, 0), 120, 1, 102, $users, $projects),
            $this->slot('Visite technique', $monday->modify('+1 day')->setTime(10, 30), 60, 2, 101, $users, $projects),

            $this->slot('Livraison', $monday->modify('+2 day')->setTime(11, 0), 60, 3, 102, $users, $projects),
            $this->slot('Montage', $monday->modify('+2 day')->setTime(13, 30), 150, 1, 101, $users, $projects),

            $this->slot('Suivi qualité', $monday->modify('+3 day')->setTime(9, 0), 120, 2, 102, $users, $projects),
            $this->slot('Finitions', $monday->modify('+4 day')->setTime(14, 0), 180, 3, 101, $users, $projects),
        ];

        return [$users, $projects, $events];
    }

    /** @return array<string,mixed> */
    private function slot(
        string $title,
        DateTimeImmutable $start,
        int $durationMinutes,
        int $userId,
        int $projectId,
        array $users,
        array $projects
    ): array {
        $end = $start->add(new DateInterval('PT' . $durationMinutes . 'M'));

        $user    = array_values(array_filter($users, fn ($u) => $u['id'] === $userId))[0];
        $project = array_values(array_filter($projects, fn ($p) => $p['id'] === $projectId))[0];

        // Couleur mixte (user en fond, project en bordure)
        return [
            'id'        => sprintf('%d-%d-%s', $userId, $projectId, $start->format('YmdHi')),
            'title'     => $title,
            'start'     => $start,
            'end'       => $end,
            'userId'    => $userId,
            'userName'  => $user['name'],
            'userColor' => $user['color'],
            'projectId' => $projectId,
            'project'   => $project['name'],
            'projColor' => $project['color'],
        ];
    }

    /** @param array<int, array> $events */
    private function eventsToJs(array $events): array
    {
        // FullCalendar event structure
        return array_map(function (array $e): array {
            return [
                'id'              => $e['id'],
                'title'           => sprintf('%s — %s', $e['title'], $e['userName']),
                'start'           => $e['start']->format(DATE_ATOM),
                'end'             => $e['end']->format(DATE_ATOM),
                'backgroundColor' => $e['userColor'],
                'borderColor'     => $e['projColor'],
                'extendedProps'   => [
                    'userId'    => $e['userId'],
                    'userName'  => $e['userName'],
                    'projectId' => $e['projectId'],
                    'project'   => $e['project'],
                ],
            ];
        }, $events);
    }
}
