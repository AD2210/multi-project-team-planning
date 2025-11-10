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
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'mptp:install',
    description: 'Installe/merge la config, Stimulus controllers, AssetMapper & Bootstrap dans l’app hôte'
)]
final class MptpInstallCommand extends Command
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Filesystem $fs = new Filesystem(),
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Écraser les fichiers existants')
            ->addOption('no-bootstrap', null, InputOption::VALUE_NONE, 'Ne pas installer Bootstrap via importmap')
            ->addOption('no-css', null, InputOption::VALUE_NONE, 'Ne pas créer/patcher styles/app.css');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $root  = $this->kernel->getProjectDir();
        $force = (bool) $input->getOption('force');

        // 1) Copier les configs
        $srcBase = \dirname(__DIR__, 2);
        $map = [
            'config/packages/mptp_asset_mapper.yaml' => $srcBase.'/config/packages/mptp_asset_mapper.yaml',
            'config/packages/ad2210_mptp.yaml'   => $srcBase.'/config/packages/ad2210_mptp.yaml',
            'config/routes/ad2210_mptp.yaml'     => $srcBase.'/config/routes/ad2210_mptp.yaml',
        ];
        foreach ($map as $rel => $src) {
            $dst = $root.'/'.$rel;
            if ($this->fs->exists($dst) && !$force) {
                $io->text("• Existe déjà (skip): $rel");
                continue;
            }
            $this->fs->mkdir(\dirname($dst));
            $this->fs->copy($src, $dst, true);
            $io->success("Installé: $rel");
        }

        // 2) Stimulus auto-discover: merge assets/controllers.json
        $controllersFile = $root.'/assets/controllers.json';
        $wanted = [
            'planner-grid' => [
                'enabled' => true, 'fetch' => 'eager',
                'path' => '@mptp/controllers/planner_grid_controller.js',
            ],
            'planner-toolbar' => [
                'enabled' => true, 'fetch' => 'eager',
                'path' => '@mptp/controllers/planner_toolbar_controller.js',
            ],
            'planner-detail' => [
                'enabled' => true, 'fetch' => 'eager',
                'path' => '@mptp/controllers/planner_detail_controller.js',
            ],
        ];
        $merged = false;
        if ($this->fs->exists($controllersFile)) {
            $json = json_decode((string) file_get_contents($controllersFile), true) ?: [];
            $json['controllers'] = $json['controllers'] ?? [];
            foreach ($wanted as $name => $cfg) {
                $json['controllers'][$name] = $cfg; // override/ensure
            }
            $this->fs->dumpFile($controllersFile, json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
            $merged = true;
            $io->success('Stimulus controllers.json fusionné');
        } else {
            $this->fs->mkdir($root.'/assets');
            $json = ['controllers' => $wanted];
            $this->fs->dumpFile($controllersFile, json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
            $merged = true;
            $io->success('Stimulus controllers.json créé');
        }

        // 3) S’assurer des fichiers JS de bootstrap Stimulus
        $bootstrapJs = $root.'/assets/bootstrap.js';
        if (!$this->fs->exists($bootstrapJs)) {
            $this->fs->dumpFile($bootstrapJs, <<<JS
import { Application } from "@hotwired/stimulus";
window.Stimulus = window.Stimulus || Application.start();
JS);
            $io->success('assets/bootstrap.js créé');
        }
        $appJs = $root.'/assets/app.js';
        if (!$this->fs->exists($appJs)) {
            $this->fs->dumpFile($appJs, <<<JS
import "./bootstrap.js";
JS);
            $io->success('assets/app.js créé');
        } else {
            // garantir import "./bootstrap.js";
            $content = (string) file_get_contents($appJs);
            if (!str_contains($content, 'import "./bootstrap.js"')) {
                $content = "import \"./bootstrap.js\";\n".$content;
                $this->fs->dumpFile($appJs, $content);
                $io->success('assets/app.js patché (import "./bootstrap.js")');
            }
        }

        // 4) Bootstrap via importmap (JS) + CSS
        if (!$input->getOption('no-bootstrap')) {
            $this->runCmd($io, ['php','bin/console','importmap:require','bootstrap','@popperjs/core'], $root, 'Importmap Bootstrap/Popper installés', 'Importmap indisponible (ok)');
            $this->runCmd($io, ['php','bin/console','importmap:install'], $root, 'Importmap installé', 'Importmap indisponible (ok)');
            // Ajoute l'import JS dans app.js si pas présent
            $content = (string) file_get_contents($appJs);
            if (!str_contains($content, "import 'bootstrap'")) {
                $content .= "\nimport 'bootstrap';\n";
                $this->fs->dumpFile($appJs, $content);
                $io->success('assets/app.js patché (import "bootstrap")');
            }
        }

        // 5) CSS: app.css + planner.css du bundle
        if (!$input->getOption('no-css')) {
            $stylesDir = $root.'/assets/styles';
            $this->fs->mkdir($stylesDir);
            $appCss = $stylesDir.'/app.css';
            if (!$this->fs->exists($appCss)) {
                $this->fs->dumpFile($appCss, <<<CSS
/* Bootstrap CSS via CDN ou via ton thème */
@import url("https://cdn.jsdelivr.net/npm/bootstrap@5/dist/css/bootstrap.min.css");

/* CSS du bundle MPTP (via AssetMapper alias @mptp) */
@import "@mptp/styles/planner.css";
CSS);
                $io->success('assets/styles/app.css créé');
            } else {
                $css = (string) file_get_contents($appCss);
                $changed = false;
                if (!str_contains($css, '@mptp/styles/planner.css')) {
                    $css .= "\n@import \"@mptp/styles/planner.css\";\n";
                    $changed = true;
                }
                if ($changed) {
                    $this->fs->dumpFile($appCss, $css);
                    $io->success('assets/styles/app.css patché (planner.css)');
                }
            }
        }

        $io->note('Terminé. Pense à inclure <link rel="stylesheet" href="{{ asset(\'styles/app.css\') }}"> dans base.html.twig et {{ importmap(\'app\') }}.');
        return Command::SUCCESS;
    }

    /** Helper pour exécuter une sous-commande console si dispo */
    private function runCmd(SymfonyStyle $io, array $cmd, string $cwd, string $okMsg, string $warnMsg): void
    {
        $p = new Process($cmd, $cwd, null, null, 40);
        $p->run();
        if ($p->isSuccessful()) {
            $io->text('• '.$okMsg);
        } else {
            $io->warning($warnMsg.' — '.$p->getErrorOutput());
        }
    }
}
