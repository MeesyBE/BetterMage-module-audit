# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Changed
- Migrated CLI command to `AbstractBmCommand` base class
- Added phpunit.xml.dist and phpstan.neon infrastructure files

### Added
- CLI audit scanner for unused modules, observers, and plugins
- Performance impact scoring system
- Audit report generation (HTML via Twig)
- Unit test scaffold

### Known Limitations
- Full implementation pending — scaffold only

---

## [0.1.0] — 2026-02-27

### Added
- Package scaffolding and registration
- `AuditRunnerInterface` API contract
- `AuditCommand` CLI command scaffold
- Audit and report model structure
