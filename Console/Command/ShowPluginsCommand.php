<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Console\Command;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\Core\Console\Command\AbstractBmCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;

/**
 * Console command: bin/magento bm:audit:plugins
 *
 * Lists all detected plugins with chain depth analysis and core class interception.
 */
class ShowPluginsCommand extends AbstractBmCommand
{
    protected static $defaultName = 'bm:audit:plugins';
    protected static $defaultDescription = 'Show plugin audit report with chain depth and core class analysis';

    public function __construct(
        private readonly AuditRunnerInterface $auditRunner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setName((string) self::$defaultName);

        parent::configure();

        $this->setName((string) self::$defaultName);

        $this->addOption('sort', 's', InputOption::VALUE_OPTIONAL, 'Sort by: class, module, type, chain, score', 'score')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Filter by type: before, after, around')
            ->addOption('deep-chains', null, InputOption::VALUE_NONE, 'Show only deep chains (≥4 plugins)')
            ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'Filter by module name');
    }

    protected function handle(): int
    {
        $output = $this->output;
        $input = $this->input;

        $output->writeln('<info>BetterMagento Module Audit — Plugin Report</info>');
        $output->writeln('==========================================');
        $output->writeln('');

        try {
            $output->writeln('<comment>Analyzing plugins...</comment>');
            $report = $this->auditRunner->execute();
            $plugins = $report->getPlugins();

            // Apply filters
            $typeFilter = $input->getOption('type');
            $deepOnly = $input->getOption('deep-chains');
            $moduleFilter = $input->getOption('module');

            $plugins = array_filter($plugins, function ($plugin) use ($typeFilter, $deepOnly, $moduleFilter) {
                if ($typeFilter && strtolower($plugin->getPluginType()) !== strtolower($typeFilter)) {
                    return false;
                }
                if ($deepOnly && $plugin->getChainDepth() < 4) {
                    return false;
                }
                if ($moduleFilter && !str_contains(strtolower($plugin->getModuleName()), strtolower($moduleFilter))) {
                    return false;
                }
                return true;
            });

            // Sort
            $sortBy = $input->getOption('sort');
            usort($plugins, function ($a, $b) use ($sortBy) {
                return match ($sortBy) {
                    'class' => strcmp($a->getInterceptedClass(), $b->getInterceptedClass()),
                    'module' => strcmp($a->getModuleName(), $b->getModuleName()),
                    'type' => strcmp($a->getPluginType(), $b->getPluginType()),
                    'chain' => $b->getChainDepth() <=> $a->getChainDepth(),
                    'score' => $b->getScore() <=> $a->getScore(),
                    default => $b->getScore() <=> $a->getScore(),
                };
            });

            // Statistics
            $stats = $report->getStatistics();
            $output->writeln(sprintf('Total Plugins: <info>%d</info>', $stats['total_plugins'] ?? 0));
            $output->writeln(sprintf('Around Plugins: <comment>%d</comment>', $stats['around_plugins'] ?? 0));
            $output->writeln(sprintf('Deep Chains (≥4): <error>%d</error>', $stats['deep_chains'] ?? 0));
            $output->writeln(sprintf('Showing: <info>%d</info> plugins', count($plugins)));
            $output->writeln('');

            // Display table
            $table = new Table($output);
            $table->setHeaders(['Module', 'Intercepted Class', 'Method', 'Plugin Class', 'Type', 'Order', 'Chain', 'Score']);
            $table->setStyle('box');

            foreach ($plugins as $plugin) {
                $typeLabel = match ($plugin->getPluginType()) {
                    'around' => '<error>around</error>',
                    'before' => '<comment>before</comment>',
                    'after' => '<info>after</info>',
                    default => $plugin->getPluginType(),
                };

                $chainDepth = $plugin->getChainDepth();
                $chainLabel = $chainDepth >= 4 ? "<error>{$chainDepth}</error>"
                    : ($chainDepth >= 2 ? "<comment>{$chainDepth}</comment>"
                        : "<info>{$chainDepth}</info>");

                $score = $plugin->getScore();
                $scoreLabel = $score >= 6 ? "<error>{$score}</error>"
                    : ($score >= 3 ? "<comment>{$score}</comment>"
                        : "<info>{$score}</info>");

                $table->addRow([
                    $plugin->getModuleName(),
                    $this->shortenClass($plugin->getInterceptedClass()),
                    $plugin->getInterceptedMethod(),
                    $this->shortenClass($plugin->getPluginClass()),
                    $typeLabel,
                    (string) $plugin->getSortOrder(),
                    $chainLabel,
                    $scoreLabel,
                ]);
            }

            $table->render();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    /**
     * Shorten fully-qualified class names for display.
     */
    private function shortenClass(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        if (count($parts) <= 3) {
            return $fqcn;
        }

        return $parts[0] . '\\' . $parts[1] . '\\..\\' . end($parts);
    }
}
