# BM Module Audit — Werk Document

> **Module:** `BetterMagento_ModuleAudit`
> **Composer:** `bettermagento/module-audit`
> **Status:** 🔴 Not Started
> **Prioriteit:** P1 — Foundation, Fase 1 (Maand 2)
> **Sprint target:** Maand 2, Week 7–8

---

## Overzicht

BM Module Audit is een **CLI-tool** die de Magento installatie doorlicht op ongebruikte modules, overbodige observers, langzame plugins en performance-bottlenecks. Het genereert een gedetailleerd rapport met prioriteiten en concrete acties.

Dit is één van de eerste modules die gebouwd wordt — het is ook direct te gebruiken als deliverable voor de **Magento Performance Audit service (€2.500)**.

**Doel:** In 5 minuten een volledig performance-inzicht van een Magento installatie leveren.

---

## Doelstellingen

- [ ] Detecteer alle geïnstalleerde modules + enabled/disabled status
- [ ] Detecteer ongebruikte modules (niet geconfigureerd, geen routes, geen usage)
- [ ] Analyseer observers: frequentie, uitvoeringstijd, potentiële bottlenecks
- [ ] Analyseer plugins (interceptors): diepte en impact
- [ ] Performance impact score per module (1–10)
- [ ] Exporteerbaar rapport: JSON, HTML, PDF
- [ ] Direct te gebruiken als onderdeel van de €2.500 audit service

---

## Technische Architectuur

```
BetterMagento_ModuleAudit
├── Api/
│   └── AuditRunnerInterface.php         # Contract voor audit execution
├── Model/
│   ├── Audit/
│   │   ├── Runner.php                   # Orchestreert alle audit-checks
│   │   ├── ModuleScanner.php            # Scant alle modules + status
│   │   ├── ObserverAnalyzer.php         # Analyseert alle observers
│   │   ├── PluginAnalyzer.php           # Analyseert alle plugins (interceptors)
│   │   ├── EventHeatmap.php             # Hoe vaak wordt elk event gefired?
│   │   ├── RouteAnalyzer.php            # Hebben modules een actieve route?
│   │   ├── ConfigUsageChecker.php       # Wordt module-config gebruikt?
│   │   └── ScoreCalculator.php          # Berekent performance impact score
│   ├── Report/
│   │   ├── Generator.php                # Genereert rapport data object
│   │   ├── JsonExporter.php             # Export naar JSON
│   │   ├── HtmlExporter.php             # Export naar HTML (styled)
│   │   ├── CliFormatter.php             # Formatteer voor terminal output
│   │   └── PdfExporter.php              # Export naar PDF (via wkhtmltopdf/WeasyPrint)
│   └── Profiler/
│       ├── ObserverProfiler.php          # Meet uitvoeringstijd van observers
│       └── PluginProfiler.php            # Meet uitvoeringstijd van plugins
├── Console/
│   └── Command/
│       ├── RunAuditCommand.php           # bin/magento bm:audit:run
│       ├── ShowModulesCommand.php        # bin/magento bm:audit:modules
│       ├── ShowObserversCommand.php      # bin/magento bm:audit:observers
│       └── ShowPluginsCommand.php        # bin/magento bm:audit:plugins
├── etc/
│   ├── module.xml
│   ├── di.xml
│   └── config.xml
├── Test/
│   ├── Unit/
│   │   ├── Model/Audit/ModuleScannerTest.php
│   │   ├── Model/Audit/ObserverAnalyzerTest.php
│   │   └── Model/Report/HtmlExporterTest.php
│   └── Integration/
├── registration.php
├── composer.json
└── README.md
```

---

## Audit Checks

### 1. Module Check
| Check | Wat het doet |
|---|---|
| `enabled_status` | Is de module enabled in `app/etc/config.php`? |
| `has_routes` | Heeft de module frontend of admin routes? |
| `has_observers` | Registrreert de module observers? |
| `has_plugins` | Registreert de module plugins? |
| `has_cron` | Heeft de module cron jobs? |
| `has_config` | Heeft de module system configuratie? |
| `config_is_used` | Is er een admin configuratie ingesteld die afwijkt van default? |
| `has_db_schema` | Heeft de module database tabellen? |
| `dependency_count` | Hoeveel andere modules zijn er afhankelijk van deze module? |

### 2. Observer Analysis
| Check | Wat het doet |
|---|---|
| `observer_list` | Alle geregistreerde observers met event name |
| `event_frequency` | Hoe vaak wordt het parent-event normaal gefired? (hoog = impact) |
| `observer_class_exists` | Bestaat de observer class? (broken observers detecteren) |
| `is_async` | Is de observer asynchroon (Magento async event)? |
| `execution_time` | Gemiddelde uitvoeringstijd (via profiling mode) |

### 3. Plugin Analysis
| Check | Wat het doet |
|---|---|
| `plugin_depth` | Hoeveel plugins wrappen dezelfde method? |
| `intercepted_method` | Welke methode wordt geïntercepteerd? |
| `plugin_type` | before / around / after |
| `has_business_logic` | Bevat de plugin zware logica (database calls in around plugin)? |
| `is_disabled` | Staat de plugin op disabled in di.xml? |

---

## Performance Impact Score (1–10)

Elk gedetecteerd item krijgt een score:

| Score | Betekenis |
|---|---|
| 1–3 | Laag risico, minimale impact |
| 4–6 | Middelhoog risico, overweeg optimalisatie |
| 7–8 | Hoog risico, actie aanbevolen |
| 9–10 | Kritiek, direct aanpakken |

### Score-factoren:
- Module disabled maar code wordt nog geladen: +4
- Observer op `controller_action_predispatch` (elke pageview): +6
- `around` plugin op core methode (bijv. `Magento\Catalog\Model\Product::load`): +5
- Module met 0 routes, 0 observers, 0 plugins: +3 (kandidaat voor verwijdering)
- Database call in observer op high-frequency event: +8

---

## Implementatie Taken

### Fase 1 — Module Scanner

- [ ] **Module registry ophalen**
  - Gebruik `Magento\Framework\Module\ModuleListInterface` voor actieve modules
  - Gebruik `Magento\Framework\Module\FullModuleList` voor alle (ook disabled) modules
  - Per module: naam, versie, pad, enabled/disabled status

- [ ] **Usage detection per module**
  - Routes: parse alle `etc/frontend/routes.xml` en `etc/adminhtml/routes.xml`
  - Observers: parse alle `etc/events.xml` (frontend + adminhtml + crontab scope)
  - Plugins: parse alle `etc/di.xml` voor `<plugin>` declaraties
  - Cron: parse alle `etc/crontab.xml`
  - Config: parse alle `etc/adminhtml/system.xml` + vergelijk met `core_config_data`

- [ ] **Dependency analysis**
  - Lees `module.xml` `<sequence>` voor elke module
  - Bouw reverse dependency map: module X → wie hangt er van af?

### Fase 2 — Observer & Plugin Analyzer

- [ ] **Observer analyzer**
  - Lees `Magento\Framework\Event\Config` voor alle geregistreerde observers
  - Categoriseer events op frequentie-niveau:
    - **Elke request:** `controller_action_predispatch`, `layout_generate_blocks_after`
    - **Per checkout:** `sales_order_place_after`
    - **Per product view:** `catalog_product_load_after`
  - Check of observer class en method bestaan (geen broken references)

- [ ] **Plugin analyzer**
  - Parse `generated/code/*/Interceptor.php` bestanden voor plugin depth
  - Identificeer deep plugin chains (≥ 4 plugins op zelfde methode = kritiek)
  - Around plugins op veelgebruikte methoden = hoogste impact

### Fase 3 — Rapport generatie

- [ ] **CLI output (altijd)**
  - Gekleurde terminal output met sorteerbare tabellen
  - Top-10 slechtste items per categorie (modules, observers, plugins)
  - Totale score + grade (A–F)

- [ ] **JSON export**
  - Machine-readable, te gebruiken in CI/CD
  - Schema: `{ "score": 72, "grade": "C", "modules": [...], "observers": [...], ... }`

- [ ] **HTML rapport**
  - Professioneel opgemaakt HTML rapport
  - Te gebruiken als deliverable voor klanten van de audit service
  - Responsive design, BetterMagento branding
  - Prioriteitenmatrix: impact × effort matrix voor alle aanbevelingen

- [ ] **PDF export** (optioneel, nice-to-have)
  - Converteer HTML naar PDF via WeasyPrint of wkhtmltopdf

---

## CLI Commando's

```bash
# Voer volledige audit uit (alle checks) + genereer HTML rapport
bin/magento bm:audit:run --output=html --file=audit-rapport.html

# Toon module overzicht
bin/magento bm:audit:modules --sort=score --limit=20

# Toon observer analyse
bin/magento bm:audit:observers --event=controller_action_predispatch

# Toon plugin analyse
bin/magento bm:audit:plugins --sort=depth --limit=10

# JSON export voor CI/CD integratie
bin/magento bm:audit:run --output=json --file=audit.json
```

### Voorbeeld terminal output `bm:audit:run`

```
BM Module Audit — Performance Scan
=====================================
Magento 2.4.7 | 247 modules gedetecteerd | Store: mystore.com

SCORE: 68/100 (Grade: D) ⚠

TOP ISSUES:
==========
🔴 KRITIEK (9/10): Magento_GoogleAdwords — Observer op dispatcher, niet geconfigureerd
🔴 KRITIEK (8/10): Hyva_Reports_Magento_Backend — Plugin chain depth: 7 op Product::load
🟠 HOOG   (7/10): Magento_Newsletter — 28 observers, module niet gebruikt
🟠 HOOG   (7/10): Amasty_Checkout — Around plugin op ShippingInformationManagement
🟡 MIDDEL (5/10): Magento_Wishlist — 34 observers, uitgeschakeld in config maar module actief

AANBEVELINGEN:
===============
1. Verwijder Magento_GoogleAdwords (geen impact op checkout)
2. Disable Magento_Newsletter als nieuwsbrief niet wordt gebruikt
3. Optimaliseer Amasty_Checkout plugin chain
...

HTML rapport opgeslagen: var/bettermagento/audit-2026-03-01.html
JSON rapport opgeslagen: var/bettermagento/audit-2026-03-01.json
```

---

## Configuratie

Geen admin configuratie nodig (pure CLI tool).

```xml
<!-- etc/config.xml defaults -->
<default>
    <bettermagento>
        <module_audit>
            <high_frequency_events>controller_action_predispatch,layout_load_before,layout_generate_blocks_before</high_frequency_events>
            <critical_plugin_depth>4</critical_plugin_depth>
            <report_output_dir>var/bettermagento/reports</report_output_dir>
        </module_audit>
    </bettermagento>
</default>
```

---

## Performance Targets (voor de tool zelf)

| Metric | Target |
|---|---|
| Scan-tijd op 247 modules | ≤ 30 seconden |
| Memory gebruik tijdens scan | ≤ 256MB |
| HTML rapport generatie | ≤ 5 seconden |

---

## Dependencies

| Dependency | Reden |
|---|---|
| `bettermagento/module-core` | Base CLI command, logging |
| `magento/framework` | Module list, DI config |
| `symfony/console` ≥ 6.0 | CLI commands |
| `twig/twig` ≥ 3.0 | HTML rapport templates |

---

## Gebruik in Performance Audit Service

Dit tool genereert direct de deliverable voor klanten:

1. **Module overzicht** → secties 3 en 4 van het audit rapport
2. **Observer/plugin analyse** → performance bottleneck identificatie
3. **Prioriteitenmatrix** → impact × effort voor alle aanbevelingen
4. **Quick Wins lijst** → direct te implementeren verbeteringen

**Tijdsinvestering voor audit:**
- `bm:audit:run` draaien: 5 minuten
- Rapport interpreteren en aanpassen voor klant: 2–3 uur
- Totaal: ~3 uur van de 8–10 uur audit-tijdsinvestering

---

## Acceptatiecriteria

- [ ] Scan van 247-module Magento 2.4.7 installatie voltooid in < 30 seconden
- [ ] Alle geïnstalleerde modules verschijnen in uitvoer (enabled + disabled)
- [ ] Observer op high-frequency event krijgt score ≥ 7
- [ ] Plugin depth ≥ 4 gedetecteerd en gerapporteerd als kritiek
- [ ] HTML rapport is professioneel, leesbaar en bruikbaar als klant-deliverable
- [ ] JSON export valideert correct als JSON

---

## Notities

_Gebruik deze sectie voor notities tijdens development._
