<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Console\Command;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\ModuleAudit\Model\Export\JsonExporter;
use BetterMagento\ModuleAudit\Model\Export\HtmlExporter;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem\Driver\File;
use BetterMagento\Core\Console\Command\AbstractBmCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command: bin/magento bm:audit:run
 *
 * Executes a full module/observer/plugin audit and outputs results.
 */
class RunAuditCommand extends AbstractBmCommand
{
    protected static $defaultName = 'bm:audit:run';
    protected static $defaultDescription = 'Run a complete BetterMagento module audit (modules, observers, plugins)';

    public function __construct(
        private readonly AuditRunnerInterface $auditRunner,
        private readonly JsonExporter $jsonExporter,
        private readonly HtmlExporter $htmlExporter,
        private readonly File $fileDriver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setName((string) self::$defaultName);

        parent::configure();

        $this->setName((string) self::$defaultName);

        parent::configure();

        // Explicit name avoids empty-name failures in some interception/bootstrap paths.
        $this->setName((string) self::$defaultName);

        $this->addOption(
            'output',
            'o',
            InputOption::VALUE_OPTIONAL,
            'Output format (cli, json, html)',
            'cli'
        )
        ->addOption(
            'file',
            'f',
            InputOption::VALUE_OPTIONAL,
            'Output file path (for json/html output)'
        );
    }

    /**
     * Execute the audit command.
     */
    protected function handle(): int
    {
        $input = $this->input;
        $output = $this->output;

        try {
            $output->writeln('<info>BetterMagento Module Audit</info>');
            $output->writeln('<info>==========================</info>');
            $output->writeln('');

            // Run the audit
            $output->writeln('<comment>Scanning modules, observers, and plugins...</comment>');
            $report = $this->auditRunner->execute();

            // Get options
            $outputFormat = strtolower($input->getOption('output') ?? 'cli');
            $outputFile = $input->getOption('file');

            // Handle different output formats
            match ($outputFormat) {
                'json' => $this->handleJsonOutput($output, $report, $outputFile),
                'html' => $this->handleHtmlOutput($output, $report, $outputFile),
                default => $this->displayCliOutput($output, $report),
            };

            $output->writeln('');
            $output->writeln('<info>✓ Audit completed successfully</info>');

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Handle JSON export.
     */
    private function handleJsonOutput(OutputInterface $output, mixed $report, ?string $file): void
    {
        $json = $this->jsonExporter->export($report);

        if ($file) {
            $this->writeToFile($file, $json);
            $output->writeln('');
            $output->writeln("<info>JSON report saved to: {$file}</info>");
        } else {
            $output->writeln('');
            $output->writeln($json);
        }
    }

    /**
     * Handle HTML export.
     */
    private function handleHtmlOutput(OutputInterface $output, mixed $report, ?string $file): void
    {
        $html = $this->htmlExporter->export($report);

        if ($file) {
            $this->writeToFile($file, $html);
            $output->writeln('');
            $output->writeln("<info>HTML report saved to: {$file}</info>");
        } else {
            $output->writeln('');
            $output->writeln('<comment>HTML output requires --file option. Use: --output=html --file=report.html</comment>');
        }
    }

    /**
     * Write content to file.
     */
    private function writeToFile(string $path, string $content): void
    {
        $this->fileDriver->filePutContents($path, $content);
    }

    /**
     * Display audit results in CLI format.
     */
    private function displayCliOutput(OutputInterface $output, mixed $report): void
    {
        $stats = $report->getStatistics();
        $score = $report->getScore();
        $grade = $report->getGrade();

        // Summary
        $output->writeln('<comment>AUDIT SUMMARY</comment>');
        $output->writeln('─────────────────────────────────────');
        $output->writeln(sprintf('Score: %d/100 (Grade: %s)', $score, $grade));
        $output->writeln('');

        // Module Statistics
        $output->writeln('<comment>MODULE STATISTICS</comment>');
        $output->writeln('─────────────────────────────────────');
        $output->writeln(sprintf('  Total Modules: %d', $stats['total_modules'] ?? 0));
        $output->writeln(sprintf('  Enabled Modules: %d', $stats['enabled_modules'] ?? 0));
        $output->writeln(sprintf('  Modules with Routes: %d', $stats['modules_with_routes'] ?? 0));
        $output->writeln(sprintf('  Modules with Observers: %d', $stats['modules_with_observers'] ?? 0));
        $output->writeln(sprintf('  Modules with Plugins: %d', $stats['modules_with_plugins'] ?? 0));
        $output->writeln(sprintf('  Modules with Cron: %d', $stats['modules_with_cron'] ?? 0));
        $output->writeln('');

        // Observer Statistics
        $output->writeln('<comment>OBSERVER STATISTICS</comment>');
        $output->writeln('─────────────────────────────────────');
        $output->writeln(sprintf('  Total Observers: %d', $stats['total_observers'] ?? 0));
        $output->writeln(sprintf('  High-Frequency Observers: %d', $stats['high_frequency_observers'] ?? 0));
        $output->writeln(sprintf('  Invalid Observers: %d', $stats['invalid_observers'] ?? 0));
        $output->writeln('');

        // Plugin Statistics
        $output->writeln('<comment>PLUGIN STATISTICS</comment>');
        $output->writeln('─────────────────────────────────────');
        $output->writeln(sprintf('  Total Plugins: %d', $stats['total_plugins'] ?? 0));
        $output->writeln(sprintf('  Around Plugins: %d', $stats['around_plugins'] ?? 0));
        $output->writeln(sprintf('  Deep Plugin Chains (≥4): %d', $stats['deep_chains'] ?? 0));
        $output->writeln('');

        // Top Issues
        $this->displayTopIssues($output, $report);

        $output->writeln('');
        $output->writeln('<info>Timestamp: ' . $report->getExecutedAt() . '</info>');
    }

    /**
     * Display top performance issues found.
     */
    private function displayTopIssues(OutputInterface $output, mixed $report): void
    {
        $output->writeln('<comment>TOP ISSUES</comment>');
        $output->writeln('─────────────────────────────────────');

        $issues = [];

        // High-frequency observers
        foreach ($report->getObservers() as $observer) {
            if ($observer->isHighFrequency() && $observer->getScore() >= 6) {
                $issues[] = sprintf(
                    '[OBSERVER] %s on "%s" (Module: %s, Score: %d)',
                    $observer->getObserverClass(),
                    $observer->getEventName(),
                    $observer->getModuleName(),
                    $observer->getScore()
                );
            }
        }

        // High-impact plugins
        foreach ($report->getPlugins() as $plugin) {
            if ($plugin->getScore() >= 7) {
                $issues[] = sprintf(
                    '[PLUGIN] %s intercepts %s::%s (%s, Score: %d)',
                    $plugin->getPluginClass(),
                    $plugin->getInterceptedClass(),
                    $plugin->getInterceptedMethod(),
                    $plugin->getPluginType(),
                    $plugin->getScore()
                );
            }
        }

        // Unused modules
        foreach ($report->getModules() as $module) {
            if ($module->getScore() >= 7) {
                $issues[] = sprintf(
                    '[MODULE] %s appears unused (Score: %d) - %s',
                    $module->getName(),
                    $module->getScore(),
                    $module->getRecommendation()
                );
            }
        }

        if (empty($issues)) {
            $output->writeln('  <info>No major performance issues detected</info>');
        } else {
            // Show top 10 issues
            $topIssues = array_slice($issues, 0, 10);
            foreach ($topIssues as $issue) {
                $output->writeln('  ' . $issue);
            }

            if (count($issues) > 10) {
                $output->writeln(sprintf('  ... and %d more issues', count($issues) - 10));
            }
        }
    }
}
