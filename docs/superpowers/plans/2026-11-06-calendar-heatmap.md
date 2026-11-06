# Calendar Heatmap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter un graphique `CalendarHeatmap` (heatmap calendrier façon GitHub) produisant du SVG à partir de valeurs journalières, d'une couleur de base et d'un nombre de nuances.

**Architecture:** Graphique autonome dans `src/Heatmap/`, sur le modèle de `src/Pie/PieChart.php` (constructeur à arguments nommés, `render(): string`, primitives `SVG\Rect`/`SVG\Text`/`SVG\Fragment`). Le calcul du dégradé est isolé dans `ColorScale`. Aucune modification de `Chart`.

**Tech Stack:** PHP ≥ 8.2, Pest 2, PHPStan niveau 8, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-11-06-calendar-heatmap-design.md` — à lire avant chaque tâche ; les formules de layout y sont la référence.

## Global Constraints

- Zéro dépendance runtime (`require` vide dans `composer.json`).
- PHP 8.2 minimum (CI : 8.2, 8.3, 8.4).
- Namespace `Maantje\Charts\Heatmap`, fichiers dans `src/Heatmap/`.
- Erreurs de configuration ⇒ `\InvalidArgumentException`.
- Valeurs par défaut exactes : `color '#216e39'`, `shades 4`, `emptyColor '#ebedf0'`, `cellSize 12`, `gap 3`, `radius 2`, `legend true`, `legendLabels ['Less', 'More']`, `background 'white'`, `fontSize 10`, `fontFamily 'arial'`, `fontColor '#57606a'`, `monthLabels ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']`, ordre par défaut commençant au lundi, `dayLabels` par défaut : Mon/Wed/Fri visibles, autres `''`.
- Estimation de largeur de texte : `strlen × fontSize × 0.6`.
- Toutes les commandes : `vendor/bin/pest`, `vendor/bin/phpstan analyse`, `vendor/bin/pint --test` doivent passer en fin de chaque tâche.

## Review Focus

1. **Changement d'heure (DST)** — une plage traversant le passage à l'heure d'été (fuseau `Europe/Paris`, 2026-03-29) ne doit décaler aucune cellule : utiliser `diff()->days` sur des dates à minuit, jamais `timestamp / 86400`. Test dans la Tâche 3.
2. **Plage à cheval sur deux années / année bissextile** — 2027-12-27 → 2028-03-05 : 29 février présent, colonnes continues. Test dans la Tâche 3.
3. **Toutes les valeurs identiques** (y compris une seule valeur) — `min == max` ⇒ toutes en dernière nuance, pas de division par zéro. Test dans les Tâches 2 et 3.
4. **Valeurs négatives** — `-5, 0, 5` ⇒ nuances 1, 3 (N=4 : floor(0.5×4)+1 = 3), 4. Test dans la Tâche 2.
5. **Plage d'un seul jour** — `start == end` ⇒ 1 cellule, `weeks = 1`. Test dans la Tâche 3.

---

## File Structure

| Fichier | Responsabilité |
|---|---|
| `src/Heatmap/DayOfWeek.php` | Enum ISO 1–7. |
| `src/Heatmap/Day.php` | Objet valeur date (minuit) + valeur. |
| `src/Heatmap/ColorScale.php` | Parsing hex, nuances, valeur → couleur. |
| `src/Heatmap/CalendarHeatmap.php` | Normalisation des options, layout, rendu SVG. |
| `tests/Unit/DayTest.php`, `tests/Unit/ColorScaleTest.php`, `tests/Unit/CalendarHeatmapTest.php` | Tests Pest. |
| `examples/calendar-heatmap.php`, `examples/output/calendar-heatmap.svg`, `README.md` | Documentation. |

---

### Task 1: `DayOfWeek` et `Day`

**Files:**
- Create: `src/Heatmap/DayOfWeek.php`, `src/Heatmap/Day.php`
- Test: `tests/Unit/DayTest.php`

**Interfaces:**
- Produces:
  - `enum DayOfWeek: int { Monday = 1; Tuesday = 2; Wednesday = 3; Thursday = 4; Friday = 5; Saturday = 6; Sunday = 7; }`
  - `DayOfWeek::fromDate(DateTimeInterface $date): self` (via `format('N')`)
  - `final class Day { public readonly DateTimeImmutable $date; public function __construct(string|DateTimeInterface $date, public float $value) }`

- [ ] **Step 0: Installer les dépendances de dev** — `composer install` (le dossier `vendor/` est absent).

- [ ] **Step 1: Écrire les tests**

```php
it('normalizes string date to midnight', function () {
    $day = new Day(date: '2026-01-03 15:42', value: 4);
    expect($day->date->format('Y-m-d H:i:s'))->toBe('2026-01-03 00:00:00')
        ->and($day->value)->toBe(4.0);
});

it('accepts DateTimeInterface and keeps it immutable', function () {
    $source = new DateTime('2026-01-04 08:00');
    $day = new Day(date: $source, value: 1);
    $source->modify('+1 day');
    expect($day->date->format('Y-m-d H:i'))->toBe('2026-01-04 00:00');
});

it('throws on invalid date string', function () {
    new Day(date: 'not a date', value: 1);
})->throws(InvalidArgumentException::class);

it('maps dates to ISO day of week', function () {
    expect(DayOfWeek::fromDate(new DateTimeImmutable('2026-01-01')))->toBe(DayOfWeek::Thursday)
        ->and(DayOfWeek::fromDate(new DateTimeImmutable('2026-01-04')))->toBe(DayOfWeek::Sunday);
});
```

- [ ] **Step 2: Vérifier l'échec** — `vendor/bin/pest tests/Unit/DayTest.php` ⇒ FAIL (classes introuvables).
- [ ] **Step 3: Implémenter** — `DateTimeImmutable::createFromInterface()` pour les objets ; pour les chaînes, attraper l'`\Exception` du constructeur `DateTimeImmutable` et relancer en `InvalidArgumentException` ; puis `setTime(0, 0)`.
- [ ] **Step 4: Vérifier** — `vendor/bin/pest tests/Unit/DayTest.php` ⇒ PASS ; `vendor/bin/phpstan analyse` et `vendor/bin/pint --test` OK.
- [ ] **Step 5: Commit** — `git add src/Heatmap tests/Unit/DayTest.php && git commit -m "Add Day and DayOfWeek for calendar heatmap"`

---

### Task 2: `ColorScale`

**Files:**
- Create: `src/Heatmap/ColorScale.php`
- Test: `tests/Unit/ColorScaleTest.php`

**Interfaces:**
- Produces:
  - `final class ColorScale { public function __construct(string $color, int $shades) }`
  - `public function shades(): array` — `list<string>`, N couleurs `#rrggbb` minuscules, claire → foncée
  - `public function colorFor(float $value, float $min, float $max): string`

- [ ] **Step 1: Écrire les tests** (valeurs calculées avec la formule de la spec : canal = `round(255 + (c − 255) × i/N)`)

```php
it('generates shades from white to the base color', function () {
    expect((new ColorScale('#216e39', 4))->shades())
        ->toBe(['#c8dbce', '#90b79c', '#59926b', '#216e39']);
});

it('accepts short hex and uppercase', function () {
    expect((new ColorScale('#F00', 2))->shades())->toBe(['#ff8080', '#ff0000']);
});

it('maps values linearly to shades', function () {
    $scale = new ColorScale('#216e39', 4);
    expect($scale->colorFor(0, 0, 10))->toBe('#c8dbce')
        ->and($scale->colorFor(5, 0, 10))->toBe('#59926b')
        ->and($scale->colorFor(10, 0, 10))->toBe('#216e39')
        ->and($scale->colorFor(2.4, 0, 10))->toBe('#c8dbce')
        ->and($scale->colorFor(2.5, 0, 10))->toBe('#90b79c');
});

it('handles negative values', function () {
    $scale = new ColorScale('#216e39', 4);
    expect($scale->colorFor(-5, -5, 5))->toBe('#c8dbce')
        ->and($scale->colorFor(0, -5, 5))->toBe('#59926b')
        ->and($scale->colorFor(5, -5, 5))->toBe('#216e39');
});

it('uses the darkest shade when min equals max', function () {
    expect((new ColorScale('#216e39', 4))->colorFor(3, 3, 3))->toBe('#216e39');
});

it('clamps values outside bounds', function () {
    $scale = new ColorScale('#216e39', 4);
    expect($scale->colorFor(-100, 0, 10))->toBe('#c8dbce')
        ->and($scale->colorFor(100, 0, 10))->toBe('#216e39');
});

it('rejects invalid colors', function (string $color) {
    new ColorScale($color, 4);
})->with(['green', '#12', '#1234567', 'rgb(0,0,0)', '#ggg'])->throws(InvalidArgumentException::class);

it('rejects less than one shade', function () {
    new ColorScale('#216e39', 0);
})->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Vérifier l'échec** — `vendor/bin/pest tests/Unit/ColorScaleTest.php` ⇒ FAIL.
- [ ] **Step 3: Implémenter** — regex `/^#([0-9a-f]{3}|[0-9a-f]{6})$/i` ; nuances précalculées dans le constructeur ; `colorFor` : `i = min(N, max(1, (int) floor(($v − $min) / ($max − $min) × N) + 1))`, `i = N` si `$max == $min`.
- [ ] **Step 4: Vérifier** — pest, phpstan, pint OK.
- [ ] **Step 5: Commit** — `git commit -m "Add ColorScale for calendar heatmap"`

---

### Task 3: `CalendarHeatmap` — options, grille et dimensions

Cette tâche implémente le constructeur complet (tous les paramètres de la spec) et `render()` avec fond + cellules. Les méthodes `renderDayLabels()`, `renderMonthLabels()`, `renderLegend()` existent mais retournent `''`, et les marges correspondantes valent 0 — les Tâches 4 et 5 les remplissent. Les tests de cette tâche passent donc `dayLabels: null, monthLabels: null, legend: false`.

**Files:**
- Create: `src/Heatmap/CalendarHeatmap.php`
- Test: `tests/Unit/CalendarHeatmapTest.php`

**Interfaces:**
- Consumes: `Day`, `DayOfWeek::fromDate()`, `ColorScale::shades()`, `ColorScale::colorFor()`
- Produces:
  - Constructeur avec exactement les paramètres, types et défauts de la table « Constructeur » de la spec, dans cet ordre : `days, color, shades, start, end, weekStart, dayOrder, min, max, emptyColor, cellSize, gap, radius, dayLabels, monthLabels, legend, legendLabels, background, fontSize, fontFamily, fontColor`. `dayLabels` a pour défaut une sentinelle (`array|null|false $dayLabels = false` ⇒ défaut Mon/Wed/Fri) afin que `null` signifie « masqué ».
  - `public function render(): string`
  - Propriétés protégées utilisées par les Tâches 4–5 : `list<DayOfWeek> $order`, `?DateTimeImmutable $start`, `?DateTimeImmutable $end`, `?DateTimeImmutable $gridStart`, `int $weeks`, `?array $dayLabels` (aligné sur `$order`), `ColorScale $scale` ; et méthodes `protected function step(): int`, `protected function column(DateTimeImmutable $d): int`, `protected function marginLeft(): float`, `protected function marginTop(): float`, `protected function legendHeight(): float`, `protected function legendWidth(): float`, `protected function width(): float`, `protected function height(): float`.

Scénario de référence (lundi en premier, `cellSize 12`, `gap 3` ⇒ `step 15`) : `start 2026-01-01` (jeudi), `end 2026-01-14` ⇒ `gridStart 2025-12-29`, `weeks 3`, `width 42`, `height 102`, 14 cellules.

- [ ] **Step 1: Écrire les tests** — helper local `heatmap(array $args): CalendarHeatmap` qui fusionne `['dayLabels' => null, 'monthLabels' => null, 'legend' => false]` ; helper `rects(string $svg): array` qui parse le SVG avec `DOMDocument` et retourne la liste des `<rect>` sous forme `['x'=>..,'y'=>..,'fill'=>..]` en sautant le premier (fond).

Tests (noms et assertions) :
- `renders grid cells for a range` — scénario de référence avec `days: ['2026-01-01' => 0, '2026-01-02' => 10, '2026-01-03' => 5]` : SVG `width="42" height="102"` ; 14 rects ; 1er rect `x=0 y=45 fill=#c8dbce` ; rect du 02/01 `x=0 y=60 fill=#216e39` ; du 03/01 `x=0 y=75 fill=#59926b` ; du 04/01 `x=0 y=90 fill=#ebedf0` ; dernier (14/01) `x=30 y=30 fill=#ebedf0` ; tous `width=12 height=12 rx=2`.
- `snapshot of base grid` — même scénario, `expect(pretty($svg))->toBe(<<<'SVG' … SVG)` : générer la sortie, la vérifier contre les assertions précédentes, puis la figer.
- `starts weeks on sunday` — `weekStart: DayOfWeek::Sunday` : 01/01 à `x=0 y=60`, 04/01 à `x=15 y=0`, 14/01 à `x=30 y=45`, `width 42`.
- `renders only days listed in dayOrder` — `dayOrder` lundi→vendredi : 10 rects, `height 72`, 05/01 à `x=15 y=0`.
- `accepts a custom non-cyclic dayOrder` — `dayOrder: [Wednesday, Monday]` : 4 rects, 05/01 (lundi) à `y=15`, 07/01 (mercredi) à `y=0`.
- `infers range from data` — sans `start`/`end`, `days: ['2026-01-05' => 1, '2026-01-07' => 2]` ⇒ 3 rects.
- `ignores data outside the range` — valeur hors plage `'2026-02-01' => 1000` n'influence pas `max` (02/01=10 reste `#216e39`, 03/01=5 reste `#59926b`).
- `sums duplicate dates` — `[new Day('2026-01-02', 4), new Day('2026-01-02', 6), '2026-01-03' => 10]` ⇒ 02/01 et 03/01 tous deux `#216e39`.
- `uses min and max overrides` — `min: 0, max: 100`, `'2026-01-02' => 10` ⇒ `#c8dbce`.
- `renders a single-day range` — `start == end == 2026-01-01` ⇒ 1 rect, `width 12`.
- `uses darkest shade when all values equal` — une seule valeur ⇒ `#216e39`.
- `renders an empty range in empty color` — plage sans données ⇒ 14 rects `#ebedf0`.
- `renders background only when nothing is known` — `new CalendarHeatmap()` ⇒ snapshot `<svg … width="12" height="12">` + rect de fond blanc seul.
- `omits background when null` — `background: null` ⇒ aucun rect de fond (14 rects au total).
- `is not shifted by daylight saving time` — `date_default_timezone_set('Europe/Paris')` (restauré en `afterEach`), plage 2026-03-23 → 2026-04-05 : 29/03 (dimanche) à `x=0 y=90`, 30/03 à `x=15 y=0`.
- `spans years and leap days` — 2027-12-27 → 2028-03-05 : 70 rects (5 + 31 + 29 + 5 jours), présence d'un rect pour le 29/02/2028, `weeks 10`.
- Exceptions (`->throws(InvalidArgumentException::class)`) : `weekStart` + `dayOrder` ; `dayOrder: []` ; `dayOrder` avec doublon ; `start > end` ; élément de `days` non numérique et non `Day` ; `dayLabels` de longueur ≠ nombre de lignes (`dayLabels: ['a']` avec ordre par défaut).

- [ ] **Step 2: Vérifier l'échec** — `vendor/bin/pest tests/Unit/CalendarHeatmapTest.php` ⇒ FAIL.
- [ ] **Step 3: Implémenter** dans le constructeur (normalisation, validations, agrégation `array<string 'Y-m-d', float>`, `min`/`max` effectifs, `gridStart`, `weeks`) puis `render()` sur le modèle de `PieChart::render()`.

`gridStart` et colonne :

```php
$offset = (DayOfWeek::fromDate($start)->value - $this->order[0]->value + 7) % 7;
$this->gridStart = $start->modify("-{$offset} days");
// column($d) = intdiv($this->gridStart->diff($d)->days, 7)
```

Itération des jours : `new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'))`. `width()`/`height()` suivent les formules « Marges et dimensions » de la spec (`max(width, legendWidth)` inclus) ; cas plage indéterminée ⇒ `cellSize × cellSize`.

- [ ] **Step 4: Vérifier** — pest, phpstan, pint OK.
- [ ] **Step 5: Commit** — `git commit -m "Add CalendarHeatmap grid rendering"`

---

### Task 4: Libellés des jours et des mois

**Files:**
- Modify: `src/Heatmap/CalendarHeatmap.php` (`marginLeft()`, `marginTop()`, `renderDayLabels()`, `renderMonthLabels()`)
- Test: `tests/Unit/CalendarHeatmapTest.php`

**Interfaces:**
- Consumes: propriétés et méthodes protégées de la Tâche 3.

Formules : spec, sections « Libellés de jours » et « Libellés de mois ». Avec `fontSize 10`, `gap 3`, défaut lundi : `marginLeft = ceil(3 × 10 × 0.6) + 3 = 21`, `marginTop = 13`.

- [ ] **Step 1: Écrire les tests** (`legend: false` ; helper `texts(string $svg): array` ⇒ `[['text'=>..,'x'=>..,'y'=>..,'anchor'=>..]]`)
  - `renders default day labels` — scénario de référence, `monthLabels: null` : textes `Mon (x=18, y=6)`, `Wed (x=18, y=36)`, `Fri (x=18, y=66)`, tous `text-anchor="end"`, `fill="#57606a"`, `font-size="10"` ; 1er rect de cellule à `x=21`.
  - `reorders default day labels with weekStart` — `weekStart: Sunday` : `Mon` à `y=21`, `Wed` à `y=51`, `Fri` à `y=81`.
  - `uses custom day labels aligned on order` — `dayOrder: [Monday, Tuesday]`, `dayLabels: ['Lundi', 'Mardi']` ⇒ `marginLeft = ceil(5×10×0.6)+3 = 33`, deux textes.
  - `renders month labels` — `start 2026-01-01`, `end 2026-03-15`, `dayLabels: null` : textes `Jan (x=0)`, `Feb (x=60)`, `Mar (x=120)`, tous `y=10` ; 1er rect à `y = 13 + 45 = 58`.
  - `skips month labels too close to the previous one` — `start 2026-01-30`, `end 2026-02-20`, `dayLabels: null` ⇒ seul `Jan`.
  - `uses custom month labels` — `monthLabels` français ⇒ `janv.` au lieu de `Jan`.
  - `snapshot with labels` — scénario de référence avec libellés par défaut, figé après vérification.
- [ ] **Step 2: Vérifier l'échec.**
- [ ] **Step 3: Implémenter** — `monthLabels` doit compter 12 éléments sinon `InvalidArgumentException` (ajouter un test d'exception).
- [ ] **Step 4: Vérifier** — pest, phpstan, pint OK ; les snapshots de la Tâche 3 (labels masqués) inchangés.
- [ ] **Step 5: Commit** — `git commit -m "Add day and month labels to CalendarHeatmap"`

---

### Task 5: Légende

**Files:**
- Modify: `src/Heatmap/CalendarHeatmap.php` (`legendHeight()`, `legendWidth()`, `renderLegend()`)
- Test: `tests/Unit/CalendarHeatmapTest.php`

Formules (spec, « Légende ») : `textWidth(s) = strlen(s) × fontSize × 0.6` ; `legendWidth = textWidth(less) + gap + (N+1) × cellSize + N × gap + gap + textWidth(more)` ; `legendX = width − legendWidth` ; `legendY = marginTop + rows × step`. Textes : `less` à `x = legendX`, `textAnchor 'start'` ; `more` à `x = width`, `textAnchor 'end'` ; tous deux `y = legendY + cellSize / 2`, `dominantBaseline 'middle'`.

- [ ] **Step 1: Écrire les tests** (`dayLabels: null, monthLabels: null`, scénario de référence, N=4 ⇒ `legendWidth = 24 + 3 + 60 + 12 + 3 + 24 = 126`)
  - `renders legend` — SVG `width="126" height="117"` ; 5 rects de légende à `y=105`, `x = 27, 42, 57, 72, 87`, fills `#ebedf0, #c8dbce, #90b79c, #59926b, #216e39` ; textes `Less (x=0, y=111, start)` et `More (x=126, y=111, end)`.
  - `uses custom legend labels` — `legendLabels: ['Moins', 'Plus']`.
  - `aligns legend right on wide grids` — plage 2026-01-01 → 2026-03-31 (14 semaines ⇒ `width = 14×15−3 = 207`) : `Less` à `x = 207 − 126 = 81`.
  - `snapshot full chart` — libellés + légende par défaut, figé après vérification.
- [ ] **Step 2: Vérifier l'échec.**
- [ ] **Step 3: Implémenter.**
- [ ] **Step 4: Vérifier** — pest, phpstan, pint OK.
- [ ] **Step 5: Commit** — `git commit -m "Add legend to CalendarHeatmap"`

---

### Task 6: Exemple et README

**Files:**
- Create: `examples/calendar-heatmap.php`, `examples/output/calendar-heatmap.svg`
- Modify: `README.md` (liste « Usage Examples » + section galerie, après « Pie chart » ; ajouter « calendar heatmaps » à la ligne « Supports various chart types »)

- [ ] **Step 1: Écrire l'exemple** — même forme que `examples/pie-chart.php` (`require '../vendor/autoload.php'`, `echo $chart->render()`) : année 2026, `weekStart: DayOfWeek::Sunday`, `shades: 5`, `mt_srand(42)`, environ 1 jour sur 3 sans valeur, valeurs `mt_rand(1, 20)`.
- [ ] **Step 2: Générer** — `composer examples` ; vérifier que `examples/output/calendar-heatmap.svg` existe et que `git diff --stat examples/output` ne touche aucun autre SVG.
- [ ] **Step 3: Contrôle visuel** — ouvrir le SVG dans le navigateur : 53 colonnes, libellés lisibles sans chevauchement, légende à droite.
- [ ] **Step 4: README** — entrée `### Calendar heatmap` avec image + lien source, et un court snippet d'usage dans la section « Usage ».
- [ ] **Step 5: Vérification finale** — `vendor/bin/pest`, `vendor/bin/phpstan analyse`, `vendor/bin/pint --test` tous OK.
- [ ] **Step 6: Commit** — `git commit -m "Add calendar heatmap example and docs"`
