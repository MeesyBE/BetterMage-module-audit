<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Console\Command;

use BetterMagento\ModuleAudit\Model\Audit\ConfigUsageChecker;
use BetterMagento\Core\Console\Command\AbstractBmCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;

/**
 * Console command: bin/magento bm:audit:config
 *
 * Checks all module config paths for usage, detecting orphaned/unused configuration.
 */
class CheckConfigCommand extends AbstractBmCommand
{
    protected static $defaultName = 'bm:audit:config';
    protected static $defaultDescription = 'Check config usage across modules — detect unused config paths';

    public function __construct(
        private readonly ConfigUsageChecker $configChecker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setName((string) self::$defaultName);

        parent::configure();

        $this->setName((string) self::$defaultName);

        $this->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'Filter by module name')
            ->addOption('unused-only', null, InputOption::VALUE_NONE, 'Show only modules with unused config')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format: table, json', 'table');
    }

    protected function handle(): int
    {
        $output = $this->output;
        $input = $this->input;

        $output->writeln('<info>BetterMagento Module Audit — Config Usage Report</info>');
        $output->writeln('=================================================');
        $output->writeln('');

        try {
            $output->writeln('<comment>Analyzing configuration usage...</comment>');
            $result = $this->configChecker->analyze();

            $modules = $result['modules'];
            $stats = $result['stats'];

            // Apply filters
            $moduleFilter = $input->getOption('module');
            $unusedOnly = $input->getOption('unused-only');
            $format = $input->getOption('format');

            if ($moduleFilter) {
                $modules = array_filter(
                    $modules,
                    fn(string $name) => stripos($name, $moduleFilter) !== false,
                    ARRAY_FILTER_USE_KEY
                );
            }

            if ($unusedOnly) {
                $modules = array_filter($modules, fn(array $m) => !empty($m['unused_paths']));
            }

            // JSON output
            if ($format === 'json') {
                $output->writeln((string) json_encode([
                    'stats' => $stats,
                    'modules' => $modules,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return self::SUCCESS;
            }

            // Stats summary
            $output->writeln(sprintf('<comment>Modules with config:</comment> %d', $stats['modules_with_config']));
            $output->writeln(sprintf('<comment>Total config paths:</comment> %d', $stats['total_paths']));
            $output->writeln(sprintf(
                '<comment>Used:</comment> <fg=green>%d</> | <comment>Unused:</comment> <fg=%s>%d</>',
                $stats['used_paths'],
                $stats['unused_paths'] > 0 ? 'yellow' : 'green',
                $stats['unused_paths']
            ));
            $output->writeln(sprintf('<comment>Modules with unused config:</comment> %d', $stats['modules_with_unused']));
            $output->writeln('');

            if (empty($modules)) {
                $output->writeln('<fg=green>No config issues found.</>');
                return self::SUCCESS;
            }

            // Summary table
            $table = new Table($output);
            $table->setHeaders(['Module', 'Defined', 'Used', 'Unused', 'system.xml']);

            foreach ($modules as $moduleName => $data) {
                $unusedCount = count($data['unused_paths']);
                $table->addRow([
                    $moduleName,
                    (string) count($data['defined_paths']),
                    (string) count($data['used_paths']),
                    $unusedCount > 0
                        ? sprintf('<fg=yellow>%d</>', $unusedCount)
                        : '<fg=green>0</>',
                    $data['has_system_xml'] ? '✓' : '–',
                ]);
            }

            $table->render();
            $output->writeln('');

            // Show unused paths detail
            $hasUnused = false;
            foreach ($modules as $moduleName => $data) {
                if (empty($data['unused_paths'])) {
                    continue;
                }
                $hasUnused = true;
                $output->writeln(sprintf('<fg=yellow>⚠ %s — Unused config paths:</>', $moduleName));
                foreach ($data['unused_paths'] as $path) {
                    $output->writeln(sprintf('    <fg=gray>%s</>', $path));
                }
                $output->writeln('');
            }

            if (!$hasUnused) {
                $output->writeln('<fg=green>✓ All config paths are in use.</>');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return self::FAILURE;
        }
    }
}
