<?php
declare(strict_types=1);

namespace Ad2210\MultiProjectTeamPlanning\Command;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AsCommand(
    name: 'mptp:make:demo',
    description: 'Génère une page /planning et une API de démo (session) dans l’app hôte'
)]
final class MptpMakeDemoCommand extends Command
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Filesystem $fs = new Filesystem(),
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $root = $this->kernel->getProjectDir();

        // 1) Vue Twig
        $twigPath = $root.'/templates/planning/index.html.twig';
        $this->fs->mkdir(\dirname($twigPath));
        $this->fs->dumpFile($twigPath, $this->renderTwig());
        $io->success('Vue : templates/planning/index.html.twig');

        // 2) Controller de page + API session
        $ctrlPath = $root.'/src/Controller/PlanningDemoController.php';
        $this->fs->mkdir(\dirname($ctrlPath));
        $this->fs->dumpFile($ctrlPath, $this->renderController());
        $io->success('Controller : src/Controller/PlanningDemoController.php');

        $io->note('Lance le serveur et ouvre /api/planning');
        return Command::SUCCESS;
    }

    private function renderTwig(): string
    {
        return <<<'TWIG'
{% extends "base.html.twig" %}
{% block title %}Planning{% endblock %}
{% block stylesheets %}
    {{ parent() }}
{% endblock %}
{% block body %}
    {{ component('planner_user_view', {
        mode: 'user',
        userId: 1,
        period: 'week',
        anchor: 'today'
    }) }}
{% endblock %}

{% block javascripts %}
    {{ parent() }}
{% endblock %}
TWIG;
    }

    private function renderController(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/planning')]
final class PlanningDemoController extends AbstractController
{
    #[Route('', name: 'planning_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('planning/index.html.twig');
    }

    #[Route('/slots', name: 'planning_slots_list', methods: ['GET'])]
    public function list(Request $req): JsonResponse
    {
        $s = $req->getSession();
        $slots = $s->get('mptp_slots', []);
        if (!$slots) {
            $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
            $slots = [
                [ 'id' => '1', 'date' => $today, 'start_minute' => 9*60, 'end_minute' => 10*60+30, 'title' => 'Init',  'user_label' => 'User 1', 'project_label' => 'Projet A', 'status' => 'planned'   ],
                [ 'id' => '2', 'date' => $today, 'start_minute' => 14*60, 'end_minute' => 15*60,   'title' => 'Brief', 'user_label' => 'User 1', 'project_label' => 'Projet B', 'status' => 'confirmed' ],
            ];
            $s->set('mptp_slots', $slots);
        }
        $rs = $req->query->get('range_start');
        $re = $req->query->get('range_end');
        if ($rs && $re) {
            $slots = array_values(array_filter($slots, fn($x) => $x['date'] >= $rs && $x['date'] <= $re));
        }
        return $this->json($slots);
    }

    #[Route('/slots', name: 'planning_slots_create', methods: ['POST'])]
    public function create(Request $req): JsonResponse
    {
        $s = $req->getSession();
        $slots = $s->get('mptp_slots', []);
        $p = json_decode($req->getContent(), true) ?? [];
        $id = (string) (max(array_map(fn($x)=> (int)$x['id'], $slots) ?: [0]) + 1);
        $start = new \DateTimeImmutable($p['start_at'] ?? 'now');
        $end   = new \DateTimeImmutable($p['end_at']   ?? '+1 hour');

        $slots[] = [
            'id' => $id,
            'date' => $start->format('Y-m-d'),
            'start_minute' => (int)$start->format('H')*60 + (int)$start->format('i'),
            'end_minute'   => (int)$end->format('H')*60 + (int)$end->format('i'),
            'title' => (string)($p['title'] ?? 'New'),
            'user_label' => isset($p['user_id']) ? ('User '.$p['user_id']) : 'User',
            'project_label' => isset($p['project_id']) ? ('Projet '.$p['project_id']) : 'Projet',
            'status' => (string)($p['status'] ?? 'planned'),
        ];
        $s->set('mptp_slots', $slots);
        return $this->json(['id' => $id], 201);
    }

    #[Route('/slots/{id}', name: 'planning_slots_update', methods: ['PUT','PATCH'])]
    public function update(string $id, Request $req): Response
    {
        $s = $req->getSession();
        $slots = $s->get('mptp_slots', []);
        $p = json_decode($req->getContent(), true) ?? [];
        foreach ($slots as &$x) {
            if ($x['id'] === $id) {
                if (isset($p['start_at'])) { $d = new \DateTimeImmutable($p['start_at']); $x['date'] = $d->format('Y-m-d'); $x['start_minute'] = (int)$d->format('H')*60 + (int)$d->format('i'); }
                if (isset($p['end_at']))   { $d = new \DateTimeImmutable($p['end_at']);   $x['end_minute']   = (int)$d->format('H')*60 + (int)$d->format('i'); }
                if (isset($p['title']))     $x['title'] = (string)$p['title'];
                if (isset($p['status']))    $x['status'] = (string)$p['status'];
            }
        }
        unset($x);
        $s->set('mptp_slots', $slots);
        return new Response('', 204);
    }

    #[Route('/slots/{id}/duplicate', name: 'planning_slots_duplicate', methods: ['POST'])]
    public function duplicate(string $id, Request $req): JsonResponse
    {
        $s = $req->getSession();
        $slots = $s->get('mptp_slots', []);
        $p = json_decode($req->getContent(), true) ?? [];
        $idx = array_search($id, array_column($slots, 'id'));
        if ($idx === false) { return $this->json(['error' => 'Not found'], 404); }
        $src = $slots[$idx];
        $newId = (string) (max(array_map(fn($x)=> (int)$x['id'], $slots) ?: [0]) + 1);
        $start = new \DateTimeImmutable($p['start_at'] ?? ($src['date'].' 00:00:00'));
        $end   = new \DateTimeImmutable($p['end_at']   ?? ($src['date'].' 01:00:00'));

        $slots[] = [
            'id' => $newId,
            'date' => $start->format('Y-m-d'),
            'start_minute' => (int)$start->format('H')*60 + (int)$start->format('i'),
            'end_minute'   => (int)$end->format('H')*60 + (int)$end->format('i'),
            'title' => $src['title'].' (copie)',
            'user_label' => $src['user_label'],
            'project_label' => $src['project_label'],
            'status' => $src['status'],
        ];
        $s->set('mptp_slots', $slots);
        return $this->json(['id' => $newId], 201);
    }

    #[Route('/slots/{id}', name: 'planning_slots_delete', methods: ['DELETE'])]
    public function delete(string $id, Request $req): Response
    {
        $s = $req->getSession();
        $slots = array_values(array_filter($s->get('mptp_slots', []), fn($x) => $x['id'] !== $id));
        $s->set('mptp_slots', $slots);
        return new Response('', 204);
    }
}

PHP;
    }
}
