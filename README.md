# BetterMagento Module Audit

**Professional Magento 2 performance auditing tool** that analyzes installed modules, event observers, plugins, routes, and configuration to identify performance bottlenecks and optimization opportunities.

[![PHP Version](https://img.shields.io/badge/php-8.2%2B-blue)](https://php.net)
[![Magento Version](https://img.shields.io/badge/magento-2.4.7%2B-orange)](https://magento.com)
[![License](https://img.shields.io/badge/license-Proprietary-red)]()

---

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Installation](#installation)
- [CLI Commands](#cli-commands)
  - [`bm:audit:run` — Full Audit](#bmauditrun--full-audit)
  - [`bm:audit:modules` — Module Report](#bmauditmodules--module-report)
  - [`bm:audit:observers` — Observer Report](#bmauditobservers--observer-report)
  - [`bm:audit:plugins` — Plugin Report](#bmauditplugins--plugin-report)
  - [`bm:audit:routes` — Route Report](#bmauditroutes--route-report)
  - [`bm:audit:config` — Config Usage Report](#bmauditconfig--config-usage-report)
- [Export Formats](#export-formats)
  - [CLI (Plain Text)](#cli-plain-text)
  - [JSON](#json)
  - [HTML](#html)
- [Scoring Algorithm](#scoring-algorithm)
  - [Overall Score (0–100)](#overall-score-0100)
  - [Letter Grades (A–F)](#letter-grades-af)
  - [Module-Level Scoring (0–10)](#module-level-scoring-010)
  - [Observer-Level Scoring (0–10)](#observer-level-scoring-010)
  - [Plugin-Level Scoring (0–10)](#plugin-level-scoring-010)
  - [Penalty Caps](#penalty-caps)
- [Interpreting Results](#interpreting-results)
- [Architecture](#architecture)
- [Configuration](#configuration)
- [API Usage](#api-usage)
- [Development](#development)
- [Testing](#testing)
- [License](#license)

---

## 🎯 Overview

BetterMagento Module Audit is a comprehensive analysis tool designed to automate the €2,500 Magento performance audit process. In just ~5 minutes, it delivers:

- **Complete module inventory** with feature detection (routes, observers, plugins, cron, config, database)
- **Observer analysis** identifying high-frequency event listeners that impact every request
- **Plugin analysis** detecting deep plugin chains and around-plugin patterns
- **Intelligent scoring** (0-100) with letter grades (A-F) based on performance impact
- **Multiple export formats** (CLI, JSON, HTML) for different audiences

### Use Case

This tool transforms a manual, time-consuming performance audit into an automated, data-driven analysis. Perfect for:

- **E-commerce agencies** offering performance optimization services
- **DevOps teams** monitoring Magento deployments
- **System integrators** auditing client installations
- **Merchants** understanding their platform complexity

---

## ✨ Features

### Module Analysis
- ✅ Detects all installed Magento modules (enabled and disabled)
- ✅ Identifies module features: routes, observers, plugins, cron jobs, configuration, database schemas
- ✅ Calculates per-module performance impact scores
- ✅ Highlights unused/underutilized modules
- ✅ Provides actionable recommendations

### Observer Analysis
- ✅ Parses all registered event observers across global/frontend/adminhtml scopes
- ✅ Identifies high-frequency events (controller_action_predispatch, layout_load_before, etc.)
- ✅ Validates observer class existence
- ✅ Calculates observer-level performance scores
- ✅ Flags broken/invalid observers

### Plugin Analysis
- ✅ Analyzes all registered plugins (interceptors)
- ✅ Detects plugin types (before, after, around)
- ✅ Calculates plugin chain depth per intercepted method
- ✅ Identifies plugins on core Magento classes
- ✅ Flags disabled plugins

### Scoring & Reporting
- ✅ Overall score (0-100) with letter grade (A-F)
- ✅ Detailed statistics (module counts, observer counts, plugin counts)
- ✅ Top issues list prioritized by performance impact
- ✅ Export to CLI (colored output), JSON (API integration), HTML (executive reports)

---

## 📦 Installation

### Requirements

- **PHP**: 8.2 or higher
- **Magento**: 2.4.7 or higher
- **Composer**: For dependency management

### Install via Composer

```bash
composer require bettermagento/module-audit
```

### Enable the Module

```bash
bin/magento module:enable BetterMagento_ModuleAudit
bin/magento setup:upgrade
bin/magento cache:clean
```

### Verify Installation

```bash
bin/magento bm:audit:run --help
```

---

## 🚀 CLI Commands

Module Audit ships six CLI commands, each targeting a specific dimension of your Magento installation. All commands are prefixed with `bm:audit:`.

---

### `bm:audit:run` — Full Audit

Executes a complete audit (modules, observers, plugins), calculates the overall score, and outputs the results.

```bash
bin/magento bm:audit:run [--output=<format>] [--file=<path>]
```

| Option | Short | Description | Default |
|--------|-------|-------------|---------|
| `--output` | `-o` | Output format: `cli`, `json`, `html` | `cli` |
| `--file` | `-f` | Write output to a file (required for `html`) | – |

#### Example — CLI output

```text
$ bin/magento bm:audit:run

BetterMagento Module Audit
==========================

Scanning modules, observers, and plugins...

AUDIT SUMMARY
─────────────────────────────────────
Score: 72/100 (Grade: C)

MODULE STATISTICS
─────────────────────────────────────
  Total Modules: 247
  Enabled Modules: 243
  Modules with Routes: 58
  Modules with Observers: 47
  Modules with Plugins: 63
  Modules with Cron: 21

OBSERVER STATISTICS
─────────────────────────────────────
  Total Observers: 312
  High-Frequency Observers: 14
  Invalid Observers: 3

PLUGIN STATISTICS
─────────────────────────────────────
  Total Plugins: 189
  Around Plugins: 26
  Deep Plugin Chains (≥4): 5

TOP ISSUES
─────────────────────────────────────
  [OBSERVER] Vendor\Seo\Observer\PageLoadObserver on "controller_action_predispatch" (Module: Vendor_Seo, Score: 6)
  [OBSERVER] Vendor\Tracking\Observer\Dispatch on "controller_action_postdispatch" (Module: Vendor_Tracking, Score: 6)
  [PLUGIN] Vendor\Tax\Plugin\AroundTax intercepts Magento\Quote\Model\Quote::collectTotals (around, Score: 8)
  [PLUGIN] Vendor\Shipping\Plugin\RatePlugin intercepts Magento\Quote\Model\Quote::collectTotals (around, Score: 8)
  [MODULE] Vendor_UnusedBlog appears unused (Score: 7) — Consider disabling or removing this module
  ... and 9 more issues

✓ Audit completed successfully
```

#### Example — JSON export

```bash
bin/magento bm:audit:run --output=json --file=var/bm-audit/report.json
```

#### Example — HTML export

```bash
bin/magento bm:audit:run --output=html --file=var/bm-audit/report.html
```

---

### `bm:audit:modules` — Module Report

Shows every installed module with its audit score, detected features, and a recommendation.

```bash
bin/magento bm:audit:modules [--sort=<field>] [--filter=<name>] [--min-score=<n>] [--enabled-only]
```

| Option | Short | Description | Default |
|--------|-------|-------------|---------|
| `--sort` | `-s` | Sort by `name`, `score`, `observers`, `plugins` | `score` |
| `--filter` | | Filter modules whose name contains `<name>` | – |
| `--min-score` | | Show only modules with score ≥ value | `0` |
| `--enabled-only` | | Show only enabled modules | off |

#### Example

```text
$ bin/magento bm:audit:modules --sort=score --min-score=3

BetterMagento Module Audit — Module Report
==========================================

Scanning modules...
Found 12 modules matching criteria

┌──────────────────────────┬─────────┬─────────┬───────┬────────┬───────────┬─────────┬──────┬─────────────────────────────────────────────┐
│ Module                   │ Version │ Enabled │ Score │ Routes │ Observers │ Plugins │ Cron │ Recommendation                              │
├──────────────────────────┼─────────┼─────────┼───────┼────────┼───────────┼─────────┼──────┼─────────────────────────────────────────────┤
│ Vendor_UnusedBlog        │ 1.0.0   │ ✓       │  7    │        │           │         │      │ Consider disabling or removing this module  │
│ Vendor_OldPromo          │ 2.1.0   │ ✓       │  5    │        │           │ ✓       │      │ Review module necessity and configuration   │
│ Vendor_LegacyReports     │ 1.2.3   │ ✓       │  5    │ ✓      │           │         │      │ Review module necessity and configuration   │
│ Magento_VersionsCms      │ -       │ ✓       │  3    │        │ ✓         │         │      │ Module appears to be properly utilized      │
│ Magento_AdvancedSearch   │ -       │ ✓       │  3    │        │           │         │ ✓    │ Module appears to be properly utilized      │
└──────────────────────────┴─────────┴─────────┴───────┴────────┴───────────┴─────────┴──────┴─────────────────────────────────────────────┘

Overall Score: 72/100 (Grade: C)
```

---

### `bm:audit:observers` — Observer Report

Lists every registered event observer with frequency classification, validity check, and impact score.

```bash
bin/magento bm:audit:observers [--sort=<field>] [--high-frequency] [--invalid] [--module=<name>]
```

| Option | Short | Description | Default |
|--------|-------|-------------|---------|
| `--sort` | `-s` | Sort by `event`, `module`, `score`, `class` | `score` |
| `--high-frequency` | | Show only high-frequency observers | off |
| `--invalid` | | Show only invalid/broken observers | off |
| `--module` | `-m` | Filter by module name | – |

#### Example

```text
$ bin/magento bm:audit:observers --high-frequency

BetterMagento Module Audit — Observer Report
============================================

Analyzing observers...
Total Observers: 312
High-Frequency: 14
Invalid: 3
Showing: 14 observers

┌─────────────────┬──────────────────────────────────────┬──────────────────────────────┬─────────┬────────┬──────┬───────┬───────┐
│ Module          │ Event                                │ Observer Class               │ Method  │ Scope  │ Freq │ Valid │ Score │
├─────────────────┼──────────────────────────────────────┼──────────────────────────────┼─────────┼────────┼──────┼───────┼───────┤
│ Vendor_Seo      │ controller_action_predispatch        │ Vendor\Seo\..\PageLoad      │ execute │ global │ HIGH │ ✓     │  6    │
│ Vendor_Tracking │ controller_action_postdispatch       │ Vendor\Tracking\..\Dispatch │ execute │ global │ HIGH │ ✓     │  6    │
│ Vendor_Log      │ controller_action_predispatch        │ Vendor\Log\..\RequestLog    │ execute │ global │ HIGH │ ✓     │  6    │
│ Magento_PageCa… │ layout_generate_blocks_after         │ Magento\PageCa…\Observer    │ execute │ global │ HIGH │ ✓     │  6    │
│ Vendor_Broken   │ controller_front_send_response_before│ Vendor\Broken\..\Missing    │ execute │ global │ HIGH │ ✗     │  8    │
└─────────────────┴──────────────────────────────────────┴──────────────────────────────┴─────────┴────────┴──────┴───────┴───────┘
```

**High-frequency events** (detected automatically):

- `controller_action_predispatch` / `controller_action_postdispatch`
- `layout_load_before`
- `layout_generate_blocks_before` / `layout_generate_blocks_after`
- `controller_front_send_response_before` / `controller_front_send_response_after`

---

### `bm:audit:plugins` — Plugin Report

Lists all interceptor plugins with type, chain depth, and performance impact score.

```bash
bin/magento bm:audit:plugins [--sort=<field>] [--type=<type>] [--deep-chains] [--module=<name>]
```

| Option | Short | Description | Default |
|--------|-------|-------------|---------|
| `--sort` | `-s` | Sort by `class`, `module`, `type`, `chain`, `score` | `score` |
| `--type` | `-t` | Filter by type: `before`, `after`, `around` | – |
| `--deep-chains` | | Show only deep chains (≥4 plugins on same class) | off |
| `--module` | `-m` | Filter by module name | – |

#### Example

```text
$ bin/magento bm:audit:plugins --deep-chains

BetterMagento Module Audit — Plugin Report
==========================================

Analyzing plugins...
Total Plugins: 189
Around Plugins: 26
Deep Chains (≥4): 5
Showing: 5 plugins

┌──────────────────┬──────────────────────────────┬─────────────────┬─────────────────────────────┬────────┬───────┬───────┬───────┐
│ Module           │ Intercepted Class            │ Method          │ Plugin Class                │ Type   │ Order │ Chain │ Score │
├──────────────────┼──────────────────────────────┼─────────────────┼─────────────────────────────┼────────┼───────┼───────┼───────┤
│ Vendor_Tax       │ Magento\Quote\..\Quote       │ collectTotals   │ Vendor\Tax\..\AroundTax     │ around │ 10    │  5    │  8    │
│ Vendor_Shipping  │ Magento\Quote\..\Quote       │ collectTotals   │ Vendor\Ship\..\RatePlugin   │ around │ 20    │  5    │  8    │
│ Vendor_Discount  │ Magento\Quote\..\Quote       │ collectTotals   │ Vendor\Disc\..\PricePlugin  │ before │ 30    │  5    │  6    │
│ Vendor_Logging   │ Magento\Quote\..\Quote       │ collectTotals   │ Vendor\Log\..\QuoteLog      │ after  │ 99    │  5    │  5    │
│ Vendor_Gift      │ Magento\Quote\..\Quote       │ collectTotals   │ Vendor\Gift\..\WrapPlugin   │ after  │ 40    │  5    │  5    │
└──────────────────┴──────────────────────────────┴─────────────────┴─────────────────────────────┴────────┴───────┴───────┴───────┘
```

**Monitored core classes** (interceptions on these increase the score):

`Magento\Catalog\Model\Product`, `Magento\Sales\Model\Order`, `Magento\Customer\Model\Customer`,
`Magento\Quote\Model\Quote`, `Magento\Checkout\Model\Cart`, `Magento\Catalog\Model\ResourceModel\Product`,
`Magento\Catalog\Model\ResourceModel\Product\Collection`, `Magento\Framework\App\Action\Action`,
`Magento\Framework\View\Element\AbstractBlock`, `Magento\Checkout\Model\Session`

---

### `bm:audit:routes` — Route Report

Scans `routes.xml` files across all modules, detects duplicate front names, and identifies orphaned routes (declared but no matching controllers).

```bash
bin/magento bm:audit:routes [--scope=<scope>] [--module=<name>] [--duplicates] [--orphaned]
```

| Option | Short | Description | Default |
|--------|-------|-------------|---------|
| `--scope` | `-s` | Filter by scope: `frontend`, `adminhtml` | – |
| `--module` | `-m` | Filter by module name | – |
| `--duplicates` | | Show only duplicate front names | off |
| `--orphaned` | | Show only orphaned routes (no controllers) | off |

#### Example — All routes

```text
$ bin/magento bm:audit:routes --scope=frontend

BetterMagento Module Audit — Route Report
==========================================

Analyzing routes...
Total routes: 87
Frontend: 52 | Adminhtml: 35
Orphaned (no controllers): 3
Duplicate frontNames: 1

┌──────────────────────┬──────────┬───────────────┬────────────┬─────────────┐
│ Module               │ Scope    │ Route ID      │ Front Name │ Controllers │
├──────────────────────┼──────────┼───────────────┼────────────┼─────────────┤
│ Magento_Catalog      │ frontend │ catalog       │ catalog    │ ✓           │
│ Magento_Customer     │ frontend │ customer      │ customer   │ ✓           │
│ Magento_Checkout     │ frontend │ checkout      │ checkout   │ ✓           │
│ Vendor_Blog          │ frontend │ blog          │ blog       │ ✓           │
│ Vendor_LegacyPage    │ frontend │ legacy        │ legacy     │ ✗ Missing   │
└──────────────────────┴──────────┴───────────────┴────────────┴─────────────┘
```

#### Example — Duplicates only

```text
$ bin/magento bm:audit:routes --duplicates

⚠ Duplicate Front Names:
  catalog → Magento_Catalog, Vendor_CatalogOverride
```

---

### `bm:audit:config` — Config Usage Report

Scans `system.xml` and `config.xml` for declared configuration paths, then checks PHP and XML source files for actual usage. Detects orphaned/unused config.

```bash
bin/magento bm:audit:config [--module=<name>] [--unused-only] [--format=<format>]
```

| Option | Short | Description | Default |
|--------|-------|-------------|---------|
| `--module` | `-m` | Filter by module name | – |
| `--unused-only` | | Show only modules with unused config | off |
| `--format` | `-f` | Output format: `table`, `json` | `table` |

#### Example — Table output

```text
$ bin/magento bm:audit:config --unused-only

BetterMagento Module Audit — Config Usage Report
=================================================

Analyzing configuration usage...
Modules with config: 83
Total config paths: 641
Used: 598 | Unused: 43
Modules with unused config: 12

┌──────────────────────┬─────────┬──────┬────────┬────────────┐
│ Module               │ Defined │ Used │ Unused │ system.xml │
├──────────────────────┼─────────┼──────┼────────┼────────────┤
│ Vendor_OldPayment    │ 15      │ 8    │ 7      │ ✓          │
│ Vendor_LegacyCms     │ 9       │ 4    │ 5      │ ✓          │
│ Magento_Msrp         │ 6       │ 3    │ 3      │ ✓          │
│ Vendor_Seo           │ 22      │ 20   │ 2      │ ✓          │
└──────────────────────┴─────────┴──────┴────────┴────────────┘

⚠ Vendor_OldPayment — Unused config paths:
    payment/old_gateway/sandbox_mode
    payment/old_gateway/legacy_api_url
    payment/old_gateway/deprecated_key
    payment/old_gateway/fallback_method
    payment/old_gateway/debug_level
    payment/old_gateway/log_responses
    payment/old_gateway/timeout_v1

⚠ Vendor_LegacyCms — Unused config paths:
    cms/legacy/widget_cache
    cms/legacy/old_renderer
    cms/legacy/compat_mode
    cms/legacy/v1_templates
    cms/legacy/deprecated_blocks
```

#### Example — JSON output

```bash
bin/magento bm:audit:config --format=json
```

```json
{
  "stats": {
    "modules_with_config": 83,
    "total_paths": 641,
    "used_paths": 598,
    "unused_paths": 43,
    "modules_with_unused": 12
  },
  "modules": {
    "Vendor_OldPayment": {
      "defined_paths": ["payment/old_gateway/active", "..."],
      "used_paths": ["payment/old_gateway/active", "..."],
      "unused_paths": ["payment/old_gateway/sandbox_mode", "..."],
      "has_system_xml": true
    }
  }
}
```

---

## 📤 Export Formats

### CLI (Plain Text)

The default format. Produces a structured text report with sections for summary, module/observer/plugin statistics, and the top 15 issues ranked by score.

```bash
bin/magento bm:audit:run
# or explicitly:
bin/magento bm:audit:run --output=cli
```

- **MIME type**: `text/plain`
- **File extension**: `.txt`
- Includes color-coded output when run in a terminal (ANSI escape codes)
- Top 15 issues are shown; remaining issues are summarized with a count

### JSON

Machine-readable export for CI/CD pipelines, monitoring dashboards, and API integration.

```bash
bin/magento bm:audit:run --output=json --file=var/bm-audit/report.json
```

- **MIME type**: `application/json`
- **File extension**: `.json`

**Full JSON structure:**

```json
{
  "metadata": {
    "version": "1.0",
    "timestamp": "2026-02-28T10:30:00+01:00",
    "generated_by": "BetterMagento Module Audit"
  },
  "summary": {
    "score": 72,
    "grade": "C",
    "timestamp": "2026-02-28T10:30:00+01:00"
  },
  "statistics": {
    "total_modules": 247,
    "enabled_modules": 243,
    "modules_with_routes": 58,
    "modules_with_observers": 47,
    "modules_with_plugins": 63,
    "modules_with_cron": 21,
    "total_observers": 312,
    "high_frequency_observers": 14,
    "invalid_observers": 3,
    "total_plugins": 189,
    "around_plugins": 26,
    "deep_chains": 5,
    "scan_timestamp": "2026-02-28T10:30:00+01:00"
  },
  "modules": [
    {
      "name": "Magento_Catalog",
      "version": "104.0.7",
      "enabled": true,
      "score": 0,
      "score_reason": "",
      "features": {
        "has_routes": true,
        "has_observers": true,
        "has_plugins": true,
        "has_cron": false,
        "has_config": true,
        "has_database": true
      },
      "dependents": ["Magento_CatalogInventory", "Magento_CatalogSearch"],
      "recommendation": "Module appears to be properly utilized"
    }
  ],
  "observers": [
    {
      "module_name": "Vendor_Seo",
      "event_name": "controller_action_predispatch",
      "observer_class": "Vendor\\Seo\\Observer\\PageLoadObserver",
      "observer_method": "execute",
      "valid": true,
      "high_frequency": true,
      "score": 6,
      "scope": "global",
      "async": false
    }
  ],
  "plugins": [
    {
      "module_name": "Vendor_Tax",
      "intercepted_class": "Magento\\Quote\\Model\\Quote",
      "intercepted_method": "collectTotals",
      "plugin_class": "Vendor\\Tax\\Plugin\\AroundTax",
      "plugin_type": "around",
      "sort_order": 10,
      "disabled": false,
      "chain_depth": 5,
      "score": 8,
      "likely_has_business_logic": true
    }
  ],
  "top_issues": [
    {
      "type": "observer",
      "severity": "high",
      "score": 6,
      "module": "Vendor_Seo",
      "description": "High-frequency observer on \"controller_action_predispatch\" event",
      "class": "Vendor\\Seo\\Observer\\PageLoadObserver"
    },
    {
      "type": "plugin",
      "severity": "high",
      "score": 8,
      "module": "Vendor_Tax",
      "description": "Around plugin intercepting Magento\\Quote\\Model\\Quote::collectTotals",
      "class": "Vendor\\Tax\\Plugin\\AroundTax"
    }
  ]
}
```

### HTML

Self-contained HTML report with embedded CSS — no external dependencies. Open in any browser.

```bash
bin/magento bm:audit:run --output=html --file=var/bm-audit/report.html
```

- **MIME type**: `text/html`
- **File extension**: `.html`

**The HTML report includes:**

| Section | Contents |
|---------|----------|
| **Score card** | Large numeric score with color-coded grade badge |
| **Statistics grid** | Three cards: Modules, Observers, Plugins |
| **Top Issues** | Table sorted by severity (high / medium) |
| **Module Details** | Full table with name, version, score, features, recommendation |
| **Observer Details** | Table with module, event, class, score, frequency, validity |
| **Plugin Details** | Table with module, intercepted class, type, chain depth, score |

Also available from the Magento admin panel under **BetterMagento → Module Audit → Dashboard** via the "Export JSON" and "Export HTML Report" buttons.

---

## 📊 Scoring Algorithm

The scoring engine lives in `ScoreCalculator`. It produces two layers of scores: **per-item scores** (0–10) for individual modules, observers, and plugins, and an **overall audit score** (0–100) that rolls up into a letter grade.

### Overall Score (0–100)

Starts at **100** and subtracts penalties from three categories.

#### Module penalties

| Condition | Penalty |
|-----------|---------|
| Individual module score ≥ 3 (unused) | **-2** per module |
| Total modules > 200 | **-10** |
| Total modules 151–200 | **-5** |

#### Observer penalties (capped at 40 points)

| Condition | Penalty |
|-----------|---------|
| High-frequency observer | **-3** each |
| Invalid/broken observer (class missing) | **-5** each |
| Total observers > 500 | **-10** |
| Total observers 301–500 | **-5** |

#### Plugin penalties (capped at 40 points)

| Condition | Penalty |
|-----------|---------|
| Chain depth ≥ 4 | **-5** each |
| Chain depth 2–3 | **-2** each |
| Around plugin on core class with score ≥ 7 | **-3** each |
| Total plugins > 300 | **-10** |
| Total plugins 201–300 | **-5** |

Final score is clamped to the **0–100** range.

### Letter Grades (A–F)

| Grade | Score Range | Meaning |
|-------|-------------|---------|
| **A** | 90–100 | Excellent — minimal performance issues |
| **B** | 80–89 | Good — some optimization opportunities |
| **C** | 70–79 | Fair — moderate performance concerns |
| **D** | 60–69 | Poor — significant issues present |
| **E** | 50–59 | Very Poor — major performance problems |
| **F** | 0–49 | Critical — severe performance bottlenecks |

### Module-Level Scoring (0–10)

| Condition | Points |
|-----------|--------|
| No routes, observers, plugins, or cron (unused) | **+3** |
| Enabled but no database schema and no config | **+2** |

Score is capped at 10. Recommendations:

- **≥ 7**: *Consider disabling or removing this module*
- **4–6**: *Review module necessity and configuration*
- **< 4**: *Module appears to be properly utilized*

### Observer-Level Scoring (0–10)

| Condition | Points |
|-----------|--------|
| Listens to a high-frequency event | **+6** |
| Observer class does not exist (broken) | **+8** |
| Normal frequency, valid | **+2** |

### Plugin-Level Scoring (0–10)

| Condition | Points |
|-----------|--------|
| `around` plugin type | **+5** |
| `before` plugin type | **+2** |
| `after` plugin type | **+1** |
| Intercepting a core Magento class | **+3** |
| Chain depth ≥ 4 | **+4** |
| Chain depth 2–3 | **+2** |
| Plugin is disabled | **0** (no runtime impact) |

### Penalty Caps

- Observer penalties are capped at **40** points maximum.
- Plugin penalties are capped at **40** points maximum.
- Module penalties are uncapped but limited by module count.

This means even the most observer-heavy installation cannot lose more than 40 points from observers alone, ensuring the grade reflects a balanced view.

---

## 🔍 Interpreting Results

### What a good score (A/B) looks like

- Fewer than 150 modules total
- No invalid observers
- Few or no high-frequency observers
- No deep plugin chains (< 4)
- No unused modules with zero functionality

### Common findings and what to do

| Finding | Impact | Action |
|---------|--------|--------|
| **High-frequency observer** (score 6+) | Runs on every request, adding latency | Move logic to an event that fires less often, or make the observer async |
| **Invalid observer** (score 8) | May cause errors or silent failures | Fix or remove the broken observer class |
| **Deep plugin chain** (≥ 4, score 5+) | Multiple plugins on the same method create unpredictable execution order | Consolidate plugins or remove unnecessary interceptors |
| **Around plugin on core class** (score 7+) | Wraps the entire method, preventing Magento's own logic from running predictably | Replace with before/after if possible |
| **Unused module** (score 3+) | Loads code on every request with no benefit | Disable with `bin/magento module:disable` |
| **Orphaned route** | Declared route with no controller — 404 errors | Remove the stale `routes.xml` entry |
| **Duplicate front name** | Two modules claim the same URL prefix — undefined behavior | Rename one module's front name |
| **Unused config paths** | Dead configuration cluttering admin | Clean up `system.xml` / `config.xml` |

### Prioritizing fixes

1. **Invalid observers** (score 8) — fix immediately, they may be causing errors
2. **Deep plugin chains on core classes** (score 8) — highest runtime impact
3. **High-frequency observers** (score 6) — cumulative effect on every request
4. **Unused modules** (score 3–5) — easy wins, disable to reduce bootstrap overhead
5. **Orphaned routes / unused config** — housekeeping for long-term maintainability

### Using results in CI/CD

Export JSON and fail the pipeline when the score drops below a threshold:

```bash
SCORE=$(bin/magento bm:audit:run --output=json | python3 -c "import sys,json; print(json.load(sys.stdin)['summary']['score'])")
if [ "$SCORE" -lt 70 ]; then
  echo "Audit score $SCORE is below threshold (70). Failing build."
  exit 1
fi
```

---

## 🏗️ Architecture

### Component Overview

```
┌──────────────────────────────────────────────────────────────┐
│                      CLI Layer (6 commands)                  │
│  RunAuditCommand · ShowModulesCommand · ShowObserversCommand │
│  ShowPluginsCommand · ShowRoutesCommand · CheckConfigCommand │
└────────────────────────────┬─────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────┐
│                   Runner (Orchestrator)                      │
│              Model/Audit/Runner.php                          │
│       Scan → Analyze → Score → Report                       │
└──┬──────────┬──────────┬──────────┬──────────────────────────┘
   │          │          │          │
   ▼          ▼          ▼          ▼
┌────────┐ ┌────────┐ ┌────────┐ ┌────────────┐
│ Module │ │Observer│ │Plugin  │ │  Score     │
│ Scanner│ │Analyzer│ │Analyzer│ │ Calculator │
└────────┘ └────────┘ └────────┘ └────────────┘
   │          │          │
   ▼          ▼          ▼
┌──────────────────────────────────────────────────────────────┐
│           Data Models (DTOs)                                 │
│  AuditReport · ModuleData · ObserverData · PluginData        │
└────────────────────────────┬─────────────────────────────────┘
                             │
            ┌────────────────┼────────────────┐
            ▼                ▼                ▼
       ┌─────────┐    ┌──────────┐    ┌───────────┐
       │CLI Export│    │JSON Export│    │HTML Export │
       └─────────┘    └──────────┘    └───────────┘
```

Additionally, `RouteAnalyzer` and `ConfigUsageChecker` are standalone analyzers invoked directly by their respective commands without going through Runner.

### Data Flow

1. User executes a CLI command
2. `Runner.execute()` orchestrates the audit:
   - **Phase 1** — `ModuleScanner` scans all modules via `ModuleListInterface`
   - **Phase 2** — `ObserverAnalyzer` parses `EventConfig` for all registered observers
   - **Phase 3** — `PluginAnalyzer` parses `di.xml` files for interceptor definitions
   - **Phase 4** — `ScoreCalculator` computes per-item and overall scores
3. `AuditReport` DTO is assembled with all data and statistics
4. An exporter (CLI/JSON/HTML) formats the report
5. Result is written to stdout or a file

---

## ⚙️ Configuration

Default configuration lives in `etc/config.xml` and can be overridden in **Stores → Configuration → BetterMagento → Module Audit**.

| Path | Default | Description |
|------|---------|-------------|
| `bm_module_audit/general/enabled` | `1` | Enable/disable the module |
| `bm_module_audit/scoring/observer_weight` | `30` | Observer weight in scoring (%) |
| `bm_module_audit/scoring/plugin_weight` | `40` | Plugin weight in scoring (%) |
| `bm_module_audit/scoring/dependency_weight` | `30` | Dependency weight in scoring (%) |
| `bm_module_audit/scoring/fail_threshold` | `40` | Score below which grade is F |
| `bm_module_audit/scoring/warning_threshold` | `70` | Score below which a warning is shown |
| `bm_module_audit/export/default_format` | `cli` | Default export format |
| `bm_module_audit/export/output_directory` | `var/bm-audit` | Directory for exported reports |

---

## 💻 API Usage

### Programmatic Auditing

Inject `AuditRunnerInterface` via DI to run audits from custom code:

```php
use BetterMagento\ModuleAudit\Api\AuditRunnerInterface;

class MyAuditService
{
    public function __construct(
        private readonly AuditRunnerInterface $auditRunner
    ) {}

    public function performAudit(): void
    {
        $report = $this->auditRunner->execute();

        $score      = $report->getScore();       // int 0-100
        $grade      = $report->getGrade();        // string A-F
        $modules    = $report->getModules();      // ModuleDataInterface[]
        $observers  = $report->getObservers();    // ObserverDataInterface[]
        $plugins    = $report->getPlugins();      // PluginDataInterface[]
        $statistics = $report->getStatistics();   // array
    }
}
```

### Exporting Programmatically

```php
use BetterMagento\ModuleAudit\Model\Export\JsonExporter;
use BetterMagento\ModuleAudit\Model\Export\HtmlExporter;
use BetterMagento\ModuleAudit\Model\Export\CliExporter;

// JSON
$json = $this->jsonExporter->export($report);
file_put_contents('var/bm-audit/report.json', $json);

// HTML
$html = $this->htmlExporter->export($report);
file_put_contents('var/bm-audit/report.html', $html);

// Plain text
$text = $this->cliExporter->export($report);
file_put_contents('var/bm-audit/report.txt', $text);
```

---

## 🛠️ Development

### Project Structure

```
packages/module-audit/
├── Api/
│   ├── AuditRunnerInterface.php
│   ├── Data/
│   │   ├── AuditReportInterface.php
│   │   ├── ModuleDataInterface.php
│   │   ├── ObserverDataInterface.php
│   │   └── PluginDataInterface.php
│   └── Export/
│       └── ExporterInterface.php
├── Block/Adminhtml/
│   └── Dashboard.php
├── Console/Command/
│   ├── RunAuditCommand.php
│   ├── ShowModulesCommand.php
│   ├── ShowObserversCommand.php
│   ├── ShowPluginsCommand.php
│   ├── ShowRoutesCommand.php
│   └── CheckConfigCommand.php
├── Controller/Adminhtml/Dashboard/
│   ├── Index.php
│   └── Export.php
├── Model/
│   ├── Audit/
│   │   ├── Runner.php
│   │   ├── ModuleScanner.php
│   │   ├── ObserverAnalyzer.php
│   │   ├── PluginAnalyzer.php
│   │   ├── RouteAnalyzer.php
│   │   ├── ConfigUsageChecker.php
│   │   └── ScoreCalculator.php
│   ├── Data/
│   │   ├── AuditReport.php
│   │   ├── ModuleData.php
│   │   ├── ObserverData.php
│   │   └── PluginData.php
│   ├── Export/
│   │   ├── CliExporter.php
│   │   ├── JsonExporter.php
│   │   └── HtmlExporter.php
│   └── Config/Source/
│       └── ExportFormat.php
├── Test/
│   ├── Integration/
│   │   └── ModuleLoadTest.php
│   └── Unit/Model/
│       ├── Audit/
│       │   ├── ModuleScannerTest.php
│       │   ├── ObserverAnalyzerTest.php
│       │   ├── PluginAnalyzerTest.php
│       │   ├── RunnerTest.php
│       │   └── ScoreCalculatorTest.php
│       └── Export/
│           ├── CliExporterTest.php
│           ├── HtmlExporterTest.php
│           └── JsonExporterTest.php
├── etc/
│   ├── module.xml
│   ├── di.xml
│   ├── config.xml
│   ├── acl.xml
│   └── adminhtml/
│       ├── menu.xml
│       ├── routes.xml
│       └── system.xml
├── view/adminhtml/
│   ├── layout/bm_audit_dashboard_index.xml
│   └── templates/dashboard.phtml
├── composer.json
├── registration.php
├── phpunit.xml.dist
└── phpstan.neon
```

### Code Standards

- **PHP**: 8.2+ with `declare(strict_types=1)` everywhere
- **Style**: PSR-12
- **Architecture**: Constructor-promoted readonly properties, interface-driven DI
- **Static analysis**: PHPStan level 8

---

## 🧪 Testing

### Running Unit Tests

```bash
vendor/bin/phpunit packages/module-audit/Test/Unit/
```

### Running Integration Tests

```bash
vendor/bin/phpunit packages/module-audit/Test/Integration/
```

### Running PHPStan

```bash
vendor/bin/phpstan analyze packages/module-audit --level=8
```

### Test Coverage

- **12 test classes** (8 unit + 1 integration + 3 export)
- All core analyzers, the score calculator, runner, and all three exporters are covered
- Mock-based unit tests for isolation; integration tests verify Magento DI wiring

---

## 📝 License

**Proprietary License** — © 2026 BetterMagento. All rights reserved.

---

**Made with ❤️ by BetterMagento**
