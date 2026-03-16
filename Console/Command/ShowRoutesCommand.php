<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Console\Command;

use BetterMagento\ModuleAudit\Model\Audit\RouteAnalyzer;
use BetterMagento\Core\Console\Command\AbstractBmCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;

/**
 * Console command: bin/magento bm:audit:routes
 *
 * Lists all registered routes with controller validation and duplicate detection.
 */
class ShowRoutesCommand extends AbstractBmCommand
{
    protected static $defaultName = 'bm:audit:routes';
    protected static $defaultDescription = 'Show route audit report with duplicate and orphan detection';

    public function __construct(
        private readonly RouteAnalyzer $routeAnalyzer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setName((string) self::$defaultName);

        parent::configure();

        $this->setName((string) self::$defaultName);

        $this->addOption('scope', 's', InputOption::VALUE_OPTIONAL, 'Filter by scope: frontend, adminhtml')
            ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'Filter by module name')
            ->addOption('duplicates', null, InputOption::VALUE_NONE, 'Show only duplicate frontNames')
            ->addOption('orphaned', null, InputOption::VALUE_NONE, 'Show only orphaned routes (no controllers)');
    }

    protected function handle(): int
    {
        $output = $this->output;
        $input = $this->input;

        $output->writeln('<info>BetterMagento Module Audit — Route Report</info>');
        $output->writeln('==========================================');
        $output->writeln('');

        try {
            $output->writeln('<comment>Analyzing routes...</comment>');
            $result = $this->routeAnalyzer->analyze();

            $routes = $result['routes'];
            $duplicates = $result['duplicates'];
            $orphaned = $result['orphaned'];
            $stats = $result['stats'];

            // Apply filters
            $scopeFilter = $input->getOption('scope');
            $moduleFilter = $input->getOption('module');
            $duplicatesOnly = $input->getOption('duplicates');
            $orphanedOnly = $input->getOption('orphaned');

            if ($orphanedOnly) {
                $routes = $orphaned;
            }

            if ($scopeFilter) {
                $routes = array_filter($routes, fn(array $r) => $r['scope'] === $scopeFilter);
            }

            if ($moduleFilter) {
                $routes = array_filter($routes, fn(array $r) => stripos($r['module'], $moduleFilter) !== false);
            }

            // Stats summary
            $output->writeln(sprintf('<comment>Total routes:</comment> %d', $stats['total']));
            $output->writeln(sprintf('<comment>Frontend:</comment> %d | <comment>Adminhtml:</comment> %d', $stats['frontend'], $stats['adminhtml']));
            $output->writeln(sprintf('<comment>Orphaned (no controllers):</comment> %d', count($result['orphaned'])));
            $output->writeln(sprintf('<comment>Duplicate frontNames:</comment> %d', count($duplicates)));
            $output->writeln('');

            if ($duplicatesOnly && !empty($duplicates)) {
                $output->writeln('<fg=yellow>⚠ Duplicate Front Names:</>');
                foreach ($duplicates as $frontName => $modules) {
                    $output->writeln(sprintf(
                        '  <fg=red>%s</> → %s',
                        $frontName,
                        implode(', ', $modules)
                    ));
                }
                $output->writeln('');
                return self::SUCCESS;
            }

            // Routes table
            if (empty($routes)) {
                $output->writeln('<fg=green>No routes matching filters.</>');
                return self::SUCCESS;
            }

            $table = new Table($output);
            $table->setHeaders(['Module', 'Scope', 'Route ID', 'Front Name', 'Controllers']);

            foreach ($routes as $route) {
                $hasControllers = $route['has_controllers'] ? '<fg=green>✓</>' : '<fg=red>✗ Missing</>';
                $table->addRow([
                    $route['module'],
                    $route['scope'],
                    $route['id'],
                    $route['front_name'],
                    $hasControllers,
                ]);
            }

            $table->render();
            $output->writeln('');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return self::FAILURE;
        }
    }
}
