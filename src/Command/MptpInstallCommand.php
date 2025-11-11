<?php

namespace Ad2210\MultiProjectTeamPlanning\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(name: 'mptp:install', description: 'Installe les fichiers de base pour le bundle MultiProjectTeamPlanning')]
class MptpInstallCommand extends Command
{
    private string $projectDir;

    #[Required]
    public function setProjectDir(#[Autowire('%kernel.project_dir%')] string $projectDir): void
    {
        $this->projectDir = $projectDir;
    }
    public function __construct(
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filesystem = new Filesystem();

        // Répertoires
        $assetsDir = $this->projectDir . '/assets';
        $stylesDir = $assetsDir . '/styles';
        $controllersDir = $assetsDir . '/controllers';
        $configDir = $this->projectDir . '/config/packages';

        // Créer les répertoires si besoin
        $filesystem->mkdir([$stylesDir, $controllersDir]);

        // Fichiers source depuis le bundle
        $sourceBaseDir = \dirname(__DIR__, 2).'/src/Resources/install';
        $sourceConfigDir = \dirname(__DIR__, 2).'/config';

        $output->writeln('➔️  Fusion des fichiers JS et CSS');
        $this->mergeFile($sourceBaseDir . '/app.js', $assetsDir . '/app.js');
        $this->mergeFile($sourceBaseDir . '/app.css', $stylesDir . '/app.css');
        $this->mergeFile($sourceBaseDir . '/stimulus_bootstrap.js', $assetsDir . '/stimulus_bootstrap.js');

        $output->writeln('➔️  Copie/Patch des fichiers de config YAML');
        $filesystem->copy($sourceConfigDir . '/packages/ad2210_mptp.yaml', $configDir . '/ad2210_mptp.yaml', true);
        $this->mergeFile($sourceConfigDir . '/packages/asset_mapper.yaml', $configDir . '/asset_mapper.yaml');

        $output->writeln('➔️  Patch du fichier importmap.php');
        $importmapPath = $this->projectDir . '/importmap.php';
        if ($filesystem->exists($importmapPath)) {
            $importmap = include $importmapPath;

            $entries = [
                '@mptp/planner_grid_controller' => ['path' => 'mptp/controllers/planner_grid_controller.js'],
                '@mptp/planner_toolbar_controller' => ['path' => 'mptp/controllers/planner_toolbar_controller.js'],
                '@mptp/planner_detail_controller' => ['path' => 'mptp/controllers/planner_detail_controller.js'],
                '@mptp/planner_controller' => ['path' => 'mptp/controllers/planner_controller.js'],
                '@mptp/planner_anchor_controller' => ['path' => 'mptp/controllers/planner_anchor_controller.js'],
                '@mptp/planner' => ['path' => 'mptp/styles/planner.css'],
                'bootstrap' => ['url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.esm.min.js'],
                '@popperjs/core' => ['url' => 'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/esm/index.js'],
                'bootstrap/dist/css/bootstrap.min.css' => ['url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css'],
            ];

            foreach ($entries as $key => $value) {
                if (!array_key_exists($key, $importmap)) {
                    $importmap[$key] = $value;
                }
            }

            // Écriture sécurisée : on filtre les doublons exacts et on conserve l'ordre existant
            $newContent = '<?php

return ' . var_export($importmap, true) . ';';

            if (!str_contains(file_get_contents($importmapPath), $newContent)) {
                file_put_contents($importmapPath, $newContent);
            }
        } else {
            $output->writeln('<error>Fichier importmap.php introuvable</error>');
        }

        $output->writeln('✅ Installation terminée.');

        return Command::SUCCESS;
    }

    private function mergeFile(string $sourcePath, string $targetPath): void
    {
        $filesystem = new Filesystem();

        if (!$filesystem->exists($sourcePath)) {
            return;
        }

        $newContent = file_get_contents($sourcePath);

        if ($filesystem->exists($targetPath)) {
            $existingContent = file_get_contents($targetPath);

            if (strpos($existingContent, $newContent) === false) {
                file_put_contents($targetPath, $existingContent . PHP_EOL . $newContent);
            }
        } else {
            file_put_contents($targetPath, $newContent);
        }
    }
}
