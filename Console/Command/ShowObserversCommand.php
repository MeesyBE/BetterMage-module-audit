<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Console\Command;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\Core\Console\Command\AbstractBmCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;

/**
 * Console command: bin/magento bm:audit:observers
 *
 * Lists all detected observers with frequency analysis and validation status.
 */
class ShowObserversCommand extends AbstractBmCommand
{
    protected static $defaultName = 'bm:audit:observers';
    protected static $defaultDescription = 'Show observer audit report with frequency and validation analysis';

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

        $this->addOption('sort', 's', InputOption::VALUE_OPTIONAL, 'Sort by: event, module, score, class', 'score')
            ->addOption('high-frequency', null, InputOption::VALUE_NONE, 'Show only high-frequency observers')
            ->addOption('invalid', null, InputOption::VALUE_NONE, 'Show only invalid/broken observers')
            ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'Filter by module name');
    }

    protected function handle(): int
    {
        $output = $this->output;
        $input = $this->input;

        $output->writeln('<info>BetterMagento Module Audit — Observer Report</info>');
        $output->writeln('============================================');
        $output->writeln('');

        try {
            $output->writeln('<comment>Analyzing observers...</comment>');
            $report = $this->auditRunner->execute();
            $observers = $report->getObservers();

            // Apply filters
            $highFreqOnly = $input->getOption('high-frequency');
            $invalidOnly = $input->getOption('invalid');
            $moduleFilter = $input->getOption('module');

            $observers = array_filter($observers, function ($observer) use ($highFreqOnly, $invalidOnly, $moduleFilter) {
                if ($highFreqOnly && !$observer->isHighFrequency()) {
                    return false;
                }
                if ($invalidOnly && $observer->isValid()) {
                    return false;
                }
                if ($moduleFilter && !str_contains(strtolower($observer->getModuleName()), strtolower($moduleFilter))) {
                    return false;
                }
                return true;
            });

            // Sort
            $sortBy = $input->getOption('sort');
            usort($observers, function ($a, $b) use ($sortBy) {
                return match ($sortBy) {
                    'event' => strcmp($a->getEventName(), $b->getEventName()),
                    'module' => strcmp($a->getModuleName(), $b->getModuleName()),
                    'class' => strcmp($a->getObserverClass(), $b->getObserverClass()),
                    'score' => $b->getScore() <=> $a->getScore(),
                    default => $b->getScore() <=> $a->getScore(),
                };
            });

            // Statistics
            $stats = $report->getStatistics();
            $output->writeln(sprintf('Total Observers: <info>%d</info>', $stats['total_observers'] ?? 0));
            $output->writeln(sprintf('High-Frequency: <comment>%d</comment>', $stats['high_frequency_observers'] ?? 0));
            $output->writeln(sprintf('Invalid: <error>%d</error>', $stats['invalid_observers'] ?? 0));
            $output->writeln(sprintf('Showing: <info>%d</info> observers', count($observers)));
            $output->writeln('');

            // Display table
            $table = new Table($output);
            $table->setHeaders(['Module', 'Event', 'Observer Class', 'Method', 'Scope', 'Freq', 'Valid', 'Score']);
            $table->setStyle('box');

            foreach ($observers as $observer) {
                $freqLabel = $observer->isHighFrequency() ? '<error>HIGH</error>' : '<info>normal</info>';
                $validLabel = $observer->isValid() ? '<info>✓</info>' : '<error>✗</error>';
                $score = $observer->getScore();
                $scoreLabel = $score >= 6 ? "<error>{$score}</error>"
                    : ($score >= 3 ? "<comment>{$score}</comment>"
                        : "<info>{$score}</info>");

                $table->addRow([
                    $observer->getModuleName(),
                    $observer->getEventName(),
                    $this->shortenClass($observer->getObserverClass()),
                    $observer->getObserverMethod(),
                    $observer->getScope(),
                    $freqLabel,
                    $validLabel,
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
