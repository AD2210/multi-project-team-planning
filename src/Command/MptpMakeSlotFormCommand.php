<?php
declare(strict_types=1);

namespace Ad2210\MultiProjectTeamPlanning\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'mptp:make:slot-form',
    description: 'Scaffold d’un FormType/Controller/Twig pour le popup Planning'
)]
final class MptpMakeSlotFormCommand extends Command
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Filesystem $fs = new Filesystem(),
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'Nom “métier” (ex: Slot)', 'Slot')
            ->addOption('form-class', null, InputOption::VALUE_OPTIONAL, 'Nom du FormType', 'MptpSlotType')
            ->addOption('controller-class', null, InputOption::VALUE_OPTIONAL, 'Nom du Controller', 'MptpSlotController')
            ->addOption('target-ns', null, InputOption::VALUE_OPTIONAL, 'Namespace applicatif', 'App')
            ->addOption('forms-path', null, InputOption::VALUE_OPTIONAL, 'Dossier Form', 'src/Form')
            ->addOption('controllers-path', null, InputOption::VALUE_OPTIONAL, 'Dossier Controller', 'src/Controller')
            ->addOption('twig-path', null, InputOption::VALUE_OPTIONAL, 'Dossier Twig', 'templates/mptp')
            ->addOption('route-prefix', null, InputOption::VALUE_OPTIONAL, 'Préfixe de routes', '/planning/forms/slot')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $root = $this->kernel->getProjectDir();

        $name     = (string) $input->getArgument('name');
        $formCls  = (string) $input->getOption('form-class');
        $ctrlCls  = (string) $input->getOption('controller-class');
        $targetNs = trim((string) $input->getOption('target-ns'), '\\');
        $formsDir = rtrim($root.'/'.(string)$input->getOption('forms-path'), '/');
        $ctrlDir  = rtrim($root.'/'.(string)$input->getOption('controllers-path'), '/');
        $twigDir  = rtrim($root.'/'.(string)$input->getOption('twig-path'), '/');
        $prefix   = (string) $input->getOption('route-prefix');

        $this->fs->mkdir([$formsDir, $ctrlDir, $twigDir]);

        // --- FormType
        $formPath = $formsDir.'/'.$formCls.'.php';
        if (!$this->fs->exists($formPath)) {
            $this->fs->dumpFile($formPath, $this->renderFormType($targetNs, $formCls));
            $io->success('FormType généré: '.str_replace($root.'/', '', $formPath));
        } else {
            $io->warning('FormType déjà présent: '.$formPath);
        }

        // --- Controller
        $ctrlPath = $ctrlDir.'/'.$ctrlCls.'.php';
        if (!$this->fs->exists($ctrlPath)) {
            $this->fs->dumpFile($ctrlPath, $this->renderController($targetNs, $ctrlCls, $formCls, $prefix));
            $io->success('Controller généré: '.str_replace($root.'/', '', $ctrlPath));
        } else {
            $io->warning('Controller déjà présent: '.$ctrlPath);
        }

        // --- Twig
        $twigPath = $twigDir.'/_slot_form.html.twig';
        if (!$this->fs->exists($twigPath)) {
            $this->fs->dumpFile($twigPath, $this->renderTwig());
            $io->success('Twig généré: '.str_replace($root.'/', '', $twigPath));
        } else {
            $io->warning('Twig déjà présent: '.$twigPath);
        }

        // --- Stimulus helper (optionnel)
        $assetsDir = $root.'/assets/controllers';
        if (is_dir($assetsDir)) {
            $stimulusPath = $assetsDir.'/mptp_form_controller.js';
            if (!$this->fs->exists($stimulusPath)) {
                $this->fs->dumpFile($stimulusPath, $this->renderStimulus());
                $io->success('Stimulus généré: '.str_replace($root.'/', '', $stimulusPath));
                $io->note('Pense à enregistrer le controller Stimulus et à builder tes assets.');
            }
        }

        $io->writeln('');
        $io->writeln('<info>Routes</info> (attributs dans le controller) :');
        $io->writeln('  GET  '.$prefix.'/{id?}   -> affiche le form (modal)');
        $io->writeln('  POST '.$prefix.'/{id?}   -> traite le form (create/update)');
        $io->writeln('');
        $io->success('Done.');

        return Command::SUCCESS;
    }

    private function renderFormType(string $ns, string $class): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace {$ns}\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Types de base
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

// TODO: selon ton domaine, remplace TextType par EntityType (User/Project) et configure les data_class
final class {$class} extends AbstractType
{
    public function buildForm(FormBuilderInterface \$builder, array \$options): void
    {
        \$builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => false,
            ])
            ->add('start_at', DateTimeType::class, [
                'label' => 'Début',
                'widget' => 'single_text',
                'with_seconds' => false,
            ])
            ->add('end_at', DateTimeType::class, [
                'label' => 'Fin',
                'widget' => 'single_text',
                'with_seconds' => false,
            ])
            ->add('user_id', TextType::class, [
                'label' => 'Utilisateur (ID)',
                'required' => false,
            ])
            ->add('project_id', TextType::class, [
                'label' => 'Projet (ID)',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'choices' => [
                    'Prévu' => 'planned',
                    'Confirmé' => 'confirmed',
                    'Terminé' => 'done',
                ],
                'placeholder' => '—',
            ])
        ;
    }

    public function configureOptions(OptionsResolver \$resolver): void
    {
        // Si tu as une entité: \$resolver->setDefaults(['data_class' => YourEntity::class]);
        \$resolver->setDefaults(['data_class' => null]);
    }
}
PHP;
    }

    private function renderController(string $ns, string $class, string $formCls, string $prefix): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace {$ns}\Controller;

use {$ns}\Form\\{$formCls};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// TODO: si Doctrine: injecter le repository et charger l'entité via \$id
#[Route('{$prefix}')]
final class {$class} extends AbstractController
{
    #[Route('/{id?}', name: 'mptp_slot_form', methods: ['GET'])]
    public function form(Request \$request, ?string \$id = null): Response
    {
        // TODO: si \$id -> hydrater avec les valeurs existantes (depuis BDD ou ton API)
        \$data = [
            'title' => \$request->query->get('title'),
            'start_at' => \$request->query->get('start_at'),
            'end_at' => \$request->query->get('end_at'),
            'user_id' => \$request->query->get('user_id'),
            'project_id' => \$request->query->get('project_id'),
            'status' => \$request->query->get('status'),
        ];

        \$form = \$this->createForm({$formCls}::class, \$data, [
            'method' => 'POST',
            'attr' => [
                'data-controller' => 'mptp-form',
                'data-action'     => 'submit->mptp-form#submit',
                'data-mptp-form-target' => 'form',
            ],
        ]);

        return \$this->render('mptp/_slot_form.html.twig', [
            'form' => \$form->createView(),
            'id' => \$id,
        ]);
    }

    #[Route('/{id?}', name: 'mptp_slot_form_submit', methods: ['POST'])]
    public function submit(Request \$request, ?string \$id = null): Response
    {
        \$form = \$this->createForm({$formCls}::class);
        \$form->handleRequest(\$request);

        if (!\$form->isSubmitted() || !\$form->isValid()) {
            return \$this->render('mptp/_slot_form.html.twig', [
                'form' => \$form->createView(),
                'id' => \$id,
            ], new Response(status: 422));
        }

        \$data = \$form->getData();
        // TODO: persiste via Doctrine OU appelle ton API (create si !\$id, update si \$id)
        // Exemple API: POST/PUT sur les URLs que tu as dans la config du bundle.

        // Pour la démo on renvoie 204
        return new Response('', 204);
    }
}
PHP;
    }

    private function renderTwig(): string
    {
        return <<<TWIG
{# templates/mptp/_slot_form.html.twig #}
{{ form_start(form) }}
  {{ form_row(form.title) }}
  <div class="row g-2">
    <div class="col">{{ form_row(form.start_at) }}</div>
    <div class="col">{{ form_row(form.end_at) }}</div>
  </div>
  <div class="row g-2">
    <div class="col">{{ form_row(form.user_id) }}</div>
    <div class="col">{{ form_row(form.project_id) }}</div>
  </div>
  {{ form_row(form.status) }}

  <div class="d-flex gap-2 mt-3">
    <button class="btn btn-primary" type="submit">
      {{ id ? 'Enregistrer' : 'Créer' }}
    </button>
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
  </div>
{{ form_end(form) }}
TWIG;
    }

    private function renderStimulus(): string
    {
        return <<<JS
import { Controller } from "https://unpkg.com/@hotwired/stimulus/dist/stimulus.js";

export default class extends Controller {
  static targets = ["form"];

  async submit(e) {
    e.preventDefault();
    const form = this.formTarget;
    const res  = await fetch(form.action, {
      method: form.method || 'POST',
      body:   new FormData(form),
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    if (res.status === 204) {
      // succès: fermer le modal et recharger la grille
      const grid = document.querySelector('.mptp .mptp-grid-body');
      grid?.dispatchEvent(new CustomEvent('planner-grid:reload', { bubbles: true }));
      document.querySelector('#mptpDetailModal [data-bs-dismiss="modal"]')?.click();
      return;
    }

    // sinon, re-render le twig (erreurs 422)
    const html = await res.text();
    this.element.outerHTML = html;
  }
}
JS;
    }
}
