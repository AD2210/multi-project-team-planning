<?php
declare(strict_types=1);

namespace Ad2210\MultiProjectTeamPlanning\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'mptp:install',
    description: 'Installe la config/route/services nécessaires dans l’app hôte'
)]
final class MptpInstallCommand extends Command
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Filesystem $fs = new Filesystem(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Écraser les fichiers existants');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $root  = $this->kernel->getProjectDir();
        $force = (bool) $input->getOption('force');

        // Emplacements sources (dans le bundle)
        $srcBase = \dirname(__DIR__, 2) . '/Resources/skeleton';

        $map = [
            // config
            'config/packages/ad2210_planning.yaml' => $srcBase . '/config/packages/ad2210_planning.yaml',
            // routes
            'config/routes/ad2210_mptp.yaml'       => $srcBase . '/config/routes/ad2210_mptp.yaml',
            // services (facultatif, vide par défaut)
            'config/services/ad2210_mptp.yaml'     => $srcBase . '/config/services/ad2210_mptp.yaml',
        ];

        foreach ($map as $rel => $src) {
            $dst = $root . '/' . $rel;
            if (!$this->fs->exists($src)) {
                $io->warning("Fichier skeleton manquant côté bundle : $src");
                continue;
            }
            if ($this->fs->exists($dst) && !$force) {
                $io->text("• Existe déjà (skip) : $rel");
                continue;
            }
            $this->fs->mkdir(\dirname($dst));
            $this->fs->copy($src, $dst, true);
            $io->success("Installé : $rel");
        }

        $io->note('Ensuite : php bin/console assets:install --symlink');
        return Command::SUCCESS;
    }
}
