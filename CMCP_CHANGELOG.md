# CMCP Change Journal

## 2026-09-14 — RC hardening baseline

### Reconnaissance read

- Target: `README.md`, `composer.json`, `composer.lock`, `config/bundles.php`, Doctrine/service config, `phpunit.xml.dist`, changed entities, repositories, services, API controllers, migrations, consumer field-pack declaration, and affected tests.
- Repository state: branch `release/notifying-20260817`, HEAD `45314d833c2b2fec3ca2379a234c44402c525855`, upstream `origin/release/notifying-20260817`, ahead/behind `0/0`, dirty before this run with 16 modified tracked files plus untracked `.gating/` and `migrations/Version20260912210000.php`.
- Dependency contour: Objecting, Cruding, Viewing, Interfacing, Collectioning, and Tabling README/Composer contracts were inspected where present. Missing sibling `AGENTS.md`/`MANIFEST.json` files were treated as absent rather than inferred.
- Canon sources: `Canon018ComposerIdentityMappingRule`, `Canon019NoAlternativeLayerTaxonomyRule`, `Canon021CrudingOwnsGenericCrudRule`, `Canon022StandaloneApplicationDependencyBaselineRule`, `Canon023DevelopmentComposerSymlinkRule`, `Canon024ProductionComposerBundleRule`, `Canon025ComponentDualRuntimeModeRule`, `Canon029MandatoryPhpQualityToolingRule`, `Canon030DoctrineSchemaParityRule`, `Canon031PhpDocCoverageRule`, `Canon039PhpTestToolingRule`, `Canon040PhpTestCoverageRule`, `Canon043DevelopmentComposerDependencyVersionRule`, `Canon044ObjectingSystemFieldNamingRule`, and `Canon045DevelopmentComposerRepositoryClosureRule` from `Canonization`.
- Gating contour: Gating repository instructions/manifest plus the materialized target-side Canon018, Canon043, and Canon045 executable rule implementations were inspected.
- Graph/roadmap discovery: no target-owned architecture/roadmap/memory graph was found in repository text; `.gating/` contains tooling references only.

### Canon mapping

- Canon018: target source correctly uses `App\\Notifying\\`, but package identity `smartresponsor/notifying` does not encode `Notifying` as the first Composer token and therefore conflicts with the textual/executable identity rule.
- Canon019: no competing `src/Domain`, `src/Application`, `src/Infrastructure`, `src/Port`, `src/Adapter`, or `src/Adaptor` root is part of the inspected source inventory.
- Canon021: target API controllers are notification-specific business operations; no component-local generic CRUD engine was found. EasyAdmin remains the permitted admin CRUD surface.
- Canon022: standalone boot surfaces exist and the current development manifest directly declares Objecting, Cruding, Collectioning, Tabling, Viewing, Interfacing, and EasyAdmin.
- Canon023: declared sibling path repositories use `options.symlink=true`.
- Canon024: `composer.prod.json` is absent and must be supplied for canonical production package resolution.
- Canon025: `bin/console`, `config/bundles.php`, `Kernel`, and `NotifyingBundle` provide the dual standalone/bundle shape.
- Canon029: target currently lacks repository-owned PHP-CS-Fixer/PHPStan dependencies, configs, and scripts.
- Canon030: Doctrine is owned here, but no executable schema-parity Composer/CI contract is declared.
- Canon031: meaningful PHPDoc coverage remains a measurable warning gate after hard tooling is present.
- Canon039/040: PHPUnit exists and the suite passes, but configuration does not declare the `src/` coverage population or branch coverage and Composer has no persistent coverage-summary script/evidence.
- Canon043: local sibling repositories are not pinned with `options.versions[package]=dev-master`; `tabling/table` also uses a multi-branch constraint instead of exact `dev-master`.
- Canon044: active entity mapping/tests use entity-native Objecting column names; the in-progress forward convergence migration removes legacy `object_*` persisted fields.
- Canon045: direct root path entries currently expose the known first-party dependency closure (including Collectioning and Tabling); this is rechecked after Composer normalization.

### Baseline verification

- `composer validate --strict`: PASS.
- PHP lint for changed/untracked PHP: PASS.
- `composer test`: PASS — 15 tests, 159 assertions.

### Selected RC-critical work

1. Normalize Composer development/production contracts to Canon018/024/029/039/043/045 without changing Notifying responsibility.
2. Add reproducible static-analysis/format/test-coverage/schema-parity gates and run the available executable checks.
3. Harden notification API failure handling where malformed user input can currently escape as an internal error, with focused regression coverage.
4. Preserve and verify the existing Objecting schema-convergence and dispatch-lease changes already present in the dirty worktree.

### Material risks

- The worktree contained pre-existing uncommitted product changes; this run must not revert or silently rewrite them.
- Composer package identity normalization can affect host consumers and lock metadata; all local references must be searched before changing it.

### RC implementation outcome

- Added canonical local dependency version pins, PHPStan tooling/scripts, production Composer manifest, coverage population, and reproducible schema-parity scripts.
- Repaired the standalone console bootstrap to use Symfony Runtime with Dotenv disabled for this env-file-free component; `--env=test` now selects the isolated test kernel before boot.
- Completed Objecting schema convergence: legacy columns converge to native names, notification duplicate title storage is removed, unique uuid/slug index names are regenerated through DBAL, and the migration is intentionally irreversible rather than restoring ambiguous legacy storage.
- Clean schema rebuild verified from an empty test SQLite database: 5 migrations, 113 SQL queries, mapping/schema sync PASS, migrations up-to-date PASS.
- Updated EasyAdmin 5 dashboard integration to controller-based links and added entity-specific EasyAdmin CRUD controllers (the Canon021-permitted admin exception, not a component-local generic CRUD engine).
- Repaired Doctrine DQL embedded audit paths from the obsolete `objectAudit.objectCreatedAt` path to `objectAudit.createdAt`.
- Hardened `/api/notification/snooze`: malformed timestamps now return HTTP 400 with regression coverage instead of bubbling a DateTime parsing exception.
- Hardened DB-backed test isolation by recreating the dedicated SQLite test file before each kernel integration case; normal and Xdebug path-coverage runs are deterministic.

### Final verification

- `composer validate --strict --check-lock`: PASS.
- Changed/untracked PHP lint: PASS.
- `composer cs:check`: PASS (0/47 fixable files).
- `composer phpstan`: PASS (45/45, no errors).
- `composer test`: PASS — 16 tests, 164 assertions.
- `composer test:coverage`: PASS under Xdebug 3.5.1 using PHPUnit 12 `--path-coverage`.
- Coverage evidence: Lines 52.22% (835/1599), Methods 43.11% (97/225), Branches 49.43% (436/882), Paths 1.05% (143/13625). This is executable evidence but remains below Canon040 target thresholds and is retained as quality debt rather than hidden.
- `composer lint:container`: PASS.
- `composer lint:yaml`: PASS (15 YAML files).
- `composer schema:parity`: PASS from an empty isolated SQLite test database.
- Aggregate `composer quality` exceeded the Console MCP call timeout once; every constituent gate in that aggregate was then executed individually and passed.

### Residuals / growth separation

- Canon018 Composer identity remains a cross-repository integration issue: renaming `smartresponsor/notifying` inside this bounded Notifying run would break the current App host references and therefore is not performed locally.
- Canon040 coverage thresholds remain an explicit quality-growth item; the RC suite now produces persistent, reproducible coverage evidence instead of silently lacking the gate.
- `.gating/` remains pre-existing materialized local tooling and is not promoted into the product change set.
- Broader preference/workflow granularity, digest/rate-limit expansion, additional delivery providers, and richer realtime inbox UX remain growth work, not RC blockers for the bounded Notifying responsibility.
