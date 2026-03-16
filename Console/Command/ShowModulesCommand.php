<?php

declare(strict_types=1);

namespace BetterMagento\ModuleAudit\Console\Command;

use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;
use BetterMagento\Core\Console\Command\AbstractBmCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;

/**
 * Console command: bin/magento bm:audit:modules
 *
 * Lists all modules with their audit score, features, and recommendations.
 */
class ShowModulesCommand extends AbstractBmCommand
{
    protected static $defaultName = 'bm:audit:modules';
    protected static $defaultDescription = 'Show module audit report with scores and recommendations';

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

        $this->addOption('sort', 's', InputOption::VALUE_OPTIONAL, 'Sort by: name, score, observers, plugins', 'score')
            ->addOption('filter', null, InputOption::VALUE_OPTIONAL, 'Filter modules by name pattern')
            ->addOption('min-score', null, InputOption::VALUE_OPTIONAL, 'Show only modules with score >= value', '0')
            ->addOption('enabled-only', null, InputOption::VALUE_NONE, 'Show only enabled modules');
    }

    protected function handle(): int
    {
        $output = $this->output;
        $input = $this->input;

        $output->writeln('<info>BetterMagento Module Audit — Module Report</info>');
        $output->writeln('==========================================');
        $output->writeln('');

        try {
            $output->writeln('<comment>Scanning modules...</comment>');
            $report = $this->auditRunner->execute();
            $modules = $report->getModules();

            // Apply filters
            $filter = $input->getOption('filter');
            $minScore = (int) $input->getOption('min-score');
            $enabledOnly = $input->getOption('enabled-only');

            $modules = array_filter($modules, function ($module) use ($filter, $minScore, $enabledOnly) {
                if ($enabledOnly && !$module->isEnabled()) {
                    return false;
                }
                if ($minScore > 0 && $module->getScore() < $minScore) {
                    return false;
                }
                if ($filter && !str_contains(strtolower($module->getName()), strtolower($filter))) {
                    return false;
                }
                return true;
            });

            // Sort
            $sortBy = $input->getOption('sort');
            usort($modules, function ($a, $b) use ($sortBy) {
                return match ($sortBy) {
                    'name' => strcmp($a->getName(), $b->getName()),
                    'score' => $b->getScore() <=> $a->getScore(),
                    default => $b->getScore() <=> $a->getScore(),
                };
            });

            // Display
            $output->writeln(sprintf('Found <info>%d</info> modules matching criteria', count($modules)));
            $output->writeln('');

            $table = new Table($output);
            $table->setHeaders(['Module', 'Version', 'Enabled', 'Score', 'Routes', 'Observers', 'Plugins', 'Cron', 'Recommendation']);
            $table->setStyle('box');

            foreach ($modules as $module) {
                $score = $module->getScore();
                $scoreStr = $score >= 7 ? "<error> {$score} </error>"
                    : ($score >= 4 ? "<comment>{$score}</comment>"
                        : "<info>{$score}</info>");

                $table->addRow([
                    $module->getName(),
                    $module->getVersion() ?: '-',
                    $module->isEnabled() ? '✓' : '✗',
                    $scoreStr,
                    $module->hasRoutes() ? '✓' : '',
                    $module->hasObservers() ? '✓' : '',
                    $module->hasPlugins() ? '✓' : '',
                    $module->hasCron() ? '✓' : '',
                    $module->getRecommendation() ?: '-',
                ]);
            }

            $table->render();

            // Summary
            $output->writeln('');
            $output->writeln(sprintf('Overall Score: <info>%d/100</info> (Grade: <info>%s</info>)',
                $report->getScore(), $report->getGrade()));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
