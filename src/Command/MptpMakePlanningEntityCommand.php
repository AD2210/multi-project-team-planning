<?php

declare(strict_types=1);

namespace Ad2210\MultiProjectTeamPlanning\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'mptp:make:entity', description: 'Génère une entité concrète de Slot qui étend AbstractSlot, avec traits & mapping Doctrine.')]
class MptpMakePlanningEntityCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('class', InputArgument::REQUIRED, 'Nom FQCN de l’entité à générer (ex: App\\Entity\\Planning\\PlanningSlot)')
            ->addOption('owner-class', null, InputOption::VALUE_REQUIRED, 'FQCN de la classe User (ex: App\\Entity\\User)')
            ->addOption('project-class', null, InputOption::VALUE_REQUIRED, 'FQCN de la classe Projet/Chantier (ex: App\\Entity\\WorkSite)')
            ->addOption('uuid-trait', null, InputOption::VALUE_OPTIONAL, 'FQCN du trait UUID si tu en as un (ex: App\\Entity\\Trait\\UuidTrait)')
            ->addOption('ts-trait', null, InputOption::VALUE_OPTIONAL, 'FQCN du trait Timestamp si tu en as un (ex: App\\Entity\\Trait\\TimeStampTrait)')
            ->addOption('id-type', null, InputOption::VALUE_OPTIONAL, 'Type d’ID Doctrine: "uuid" ou "int"', 'uuid')
            ->addOption('table', null, InputOption::VALUE_OPTIONAL, 'Nom de table Doctrine', 'planning_slot')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $class   = (string) $input->getArgument('class');
        $owner   = $input->getOption('owner-class');
        $project = $input->getOption('project-class');
        $uuid    = $input->getOption('uuid-trait');
        $ts      = $input->getOption('ts-trait');
        $idType  = $input->getOption('id-type');
        $table   = $input->getOption('table');

        if (!class_exists('Doctrine\\ORM\\Mapping\\Entity')) {
            $io->error('Doctrine ORM n’est pas disponible. Installe doctrine/orm pour générer la classe mappée.');
            return Command::FAILURE;
        }

        if (!$owner || !$project) {
            $io->error('Options --owner-class et --project-class requises.');
            return Command::FAILURE;
        }

        // Calcul du chemin de fichier
        if (!str_starts_with($class, '\\')) {
            $class = '\\' . $class;
        }
        $parts = explode('\\', ltrim($class, '\\'));
        $short = array_pop($parts);
        $ns    = implode('\\', $parts);
        $path  = getcwd() . '/src/' . implode('/', array_slice($parts, 1)) . '/' . $short . '.php'; // suppose App\... => src/...

        if (file_exists($path)) {
            $io->error('Le fichier existe déjà: ' . $path);
            return Command::FAILURE;
        }

        $useUuid = $uuid ? "use {$uuid};" : '';
        $useTs   = $ts   ? "use {$ts};"   : '';

        $idField = match ($idType) {
            'int'  => <<<PHP
                #[ORM\Id]
                #[ORM\GeneratedValue]
                #[ORM\Column(type: 'integer')]
                private ?int \$id = null;
            PHP,
            default => <<<PHP
                #[ORM\Id]
                #[ORM\Column(type: 'uuid', unique: true)]
                private ?\Ramsey\Uuid\UuidInterface \$id = null;
            PHP
        };

        $idGetter = match ($idType) {
            'int'  => "public function getId(): ?int { return \$this->id; }",
            default => "public function getId(): ?\\Ramsey\\Uuid\\UuidInterface { return \$this->id; }",
        };

        $content = <<<PHP
            <?php
            
            namespace {$ns};
            
            use Ad2210\\MultiProjectTeamPlanning\\Entity\\AbstractSlot;
            use Ad2210\\MultiProjectTeamPlanning\\Contract\\PlanningUserInterface;
            use Ad2210\\MultiProjectTeamPlanning\\Contract\\PlanningProjectInterface;
            use Doctrine\\ORM\\Mapping as ORM;
            
            {$useUuid}
            {$useTs}
            
            #[ORM\\Entity]
            #[ORM\\Table(name: '{$table}')]
            class {$short} extends AbstractSlot
            {
            {$idField}
            
                #[ORM\\ManyToOne(targetEntity: {$owner}::class)]
                #[ORM\\JoinColumn(nullable: false, onDelete: 'CASCADE')]
                protected ?PlanningUserInterface \$owner = null;
            
                #[ORM\\ManyToOne(targetEntity: {$project}::class)]
                #[ORM\\JoinColumn(nullable: false, onDelete: 'CASCADE')]
                protected ?PlanningProjectInterface \$project = null;
            
                {$idGetter}
            }
        PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, $content);
        $io->success("Entité générée: {$path}");
        $io->writeln("✔ Pense à vérifier les namespaces de tes interfaces implémentées par {$owner} et {$project}.");
        $io->writeln("✔ Si tu utilises UUID: ajoute ramsey/uuid-doctrine ou adapte le mapping.");
        return Command::SUCCESS;
    }
}