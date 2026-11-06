# Calendar Heatmap — Design

Date : 2026-11-06
Branche : `heatmap`

## Objectif

Ajouter à la librairie un graphique heatmap temporel façon GitHub : une ligne par jour
de la semaine, une colonne par semaine, un carré par jour dont la saturation dépend de
la valeur. L'utilisateur fournit une couleur de base, un nombre de nuances et les
valeurs journalières ; la librairie calcule le dégradé et produit le SVG.

Contraintes :

- Cohérence d'usage avec les graphiques existants (constructeur à arguments nommés,
  `render(): string`, primitives `SVG\*`, zéro dépendance, PHP ≥ 8.2).
- Ordre des jours / jour de début de semaine configurable.
- Plage de dates libre.

Hors périmètre : infobulles `<title>`, échelles par quantiles ou seuils personnalisés,
heatmap matricielle générique (heures × jours, etc.).

## Approche retenue

Graphique **autonome** `CalendarHeatmap`, sur le modèle de `PieChart` (seul graphique
existant sans axes). Il n'est pas une `Serie` de `Chart` : il n'a ni axe Y numérique ni
grille, et `maxValue()/minValue()` n'y auraient pas de sens.

## API publique

```php
use Maantje\Charts\Heatmap\CalendarHeatmap;
use Maantje\Charts\Heatmap\Day;
use Maantje\Charts\Heatmap\DayOfWeek;

$chart = new CalendarHeatmap(
    days: [
        new Day(date: '2026-01-03', value: 4),
        new Day(date: new DateTimeImmutable('2026-01-04'), value: 12),
        '2026-01-05' => 7,              // raccourci date => valeur
    ],
    color: '#216e39',
    shades: 5,
    start: '2026-01-01',
    end: '2026-12-31',
    weekStart: DayOfWeek::Sunday,
);

echo $chart->render();
```

### Constructeur `CalendarHeatmap`

| Paramètre      | Type                                   | Défaut                                   | Rôle |
|----------------|----------------------------------------|------------------------------------------|------|
| `days`         | `array<Day\|int\|float>`               | `[]`                                     | Valeurs. Éléments `Day`, ou paires `'Y-m-d' => valeur`. |
| `color`        | `string`                               | `'#216e39'`                              | Couleur de base hex (`#rgb` / `#rrggbb`), nuance la plus foncée. |
| `shades`       | `int`                                  | `4`                                      | Nombre de nuances (≥ 1), hors couleur vide. |
| `start`        | `string\|DateTimeInterface\|null`      | `null`                                   | Début de plage inclus ; sinon date min des données. |
| `end`          | `string\|DateTimeInterface\|null`      | `null`                                   | Fin de plage incluse ; sinon date max des données. |
| `weekStart`    | `?DayOfWeek`                           | `null` (⇒ `Monday`)                      | Raccourci : ordre cyclique commençant à ce jour. |
| `dayOrder`     | `?DayOfWeek[]`                         | `null`                                   | Ordre explicite des lignes, 1 à 7 jours distincts. |
| `min`          | `?float`                               | `null`                                   | Borne basse de l'échelle ; sinon min des valeurs affichées. |
| `max`          | `?float`                               | `null`                                   | Borne haute ; sinon max des valeurs affichées. |
| `emptyColor`   | `string`                               | `'#ebedf0'`                              | Couleur des jours sans valeur. |
| `cellSize`     | `int`                                  | `12`                                     | Côté d'une cellule (px). |
| `gap`          | `int`                                  | `3`                                      | Espace entre cellules (px). |
| `radius`       | `float`                                | `2`                                      | `rx`/`ry` des cellules. |
| `dayLabels`    | `?string[]`                            | voir ci-dessous                          | Libellés des lignes ; `null` masque. |
| `monthLabels`  | `?string[]`                            | `['Jan', …, 'Dec']`                      | 12 libellés de mois ; `null` masque. |
| `legend`       | `bool`                                 | `true`                                   | Affiche la légende. |
| `legendLabels` | `array{0: string, 1: string}`          | `['Less', 'More']`                       | Textes de la légende. |
| `background`   | `?string`                              | `'white'`                                | Fond ; `null` = pas de fond. |
| `fontSize`     | `int`                                  | `10`                                     | Taille des libellés. |
| `fontFamily`   | `string`                               | `'arial'`                                | Police. |
| `fontColor`    | `string`                               | `'#57606a'`                              | Couleur des libellés. |

`dayLabels` par défaut : `Mon`, `Wed`, `Fri` affichés, autres jours vides (`''`),
exprimé par jour de la semaine puis réordonné selon l'ordre effectif des lignes. Si
l'utilisateur fournit `dayLabels`, le tableau est aligné sur l'ordre effectif des
lignes et doit avoir la même longueur (sinon `InvalidArgumentException`).

Normalisation de `days` : un élément instance de `Day` est pris tel quel (sa clé est
ignorée) ; tout autre élément numérique est interprété comme `new Day(date: $clé,
value: $valeur)`. Un élément d'un autre type ⇒ `InvalidArgumentException`.

### `Day`

```php
new Day(date: string|DateTimeInterface, value: float)
```

`date` est normalisée en `DateTimeImmutable` à minuit (une chaîne invalide lève une
`InvalidArgumentException`). Propriétés publiques `date` et `value`.

### `DayOfWeek`

Enum `int` ISO : `Monday = 1` … `Sunday = 7` (correspond à `DateTime::format('N')`).

## Couleurs et échelle — `ColorScale`

Classe isolée, testable indépendamment.

```php
new ColorScale(color: '#216e39', shades: 4);
$scale->shades();                       // string[] de N couleurs, claire → foncée
$scale->colorFor($value, $min, $max);   // string
```

- Parsing hex `#rgb` / `#rrggbb` (insensible à la casse). Autre format ⇒
  `InvalidArgumentException`. `shades < 1` ⇒ `InvalidArgumentException`.
- Nuance `i ∈ [1, N]` : mélange blanc → couleur de base avec poids `t = i / N`, par
  canal : `round(255 + (c − 255) × t)`, sortie `#rrggbb` en minuscules.
- Correspondance valeur → nuance (linéaire) :
  - si `max == min` ⇒ nuance N ;
  - sinon `i = floor((v − min) / (max − min) × N) + 1`, borné à `[1, N]`.
  - Conséquences : `min` ⇒ nuance 1, `max` ⇒ nuance N, valeurs hors bornes ramenées.
- Un jour absent est rendu en `emptyColor` (géré par `CalendarHeatmap`, pas par
  `ColorScale`). Une valeur `0` est une valeur comme une autre et reçoit une nuance.

## Layout

### Ordre des lignes

- `weekStart` et `dayOrder` fournis ensemble ⇒ `InvalidArgumentException`.
- `dayOrder` : 1 à 7 jours distincts, sinon `InvalidArgumentException`. Les jours
  absents de `dayOrder` ne sont pas dessinés.
- `weekStart: X` ⇒ ordre `[X, X+1, …]` cyclique sur 7 jours.
- Aucun des deux ⇒ ordre commençant au lundi.
- Le premier jour de l'ordre effectif définit le début de semaine.

### Plage et agrégation

- `start`/`end` normalisés à minuit. Défaut : bornes des dates des données.
- `start > end` ⇒ `InvalidArgumentException`.
- Les données hors `[start, end]` sont ignorées.
- Plusieurs valeurs pour la même date sont additionnées.
- `min`/`max` par défaut calculés sur les valeurs agrégées dans la plage (jours
  présents dans `dayOrder` uniquement).

### Grille

- `gridStart` = dernier jour ≤ `start` dont le jour de la semaine est le premier de
  l'ordre effectif.
- Pour chaque date `d` de `start` à `end` dont le jour est dans l'ordre effectif :
  - `col = intdiv(jours(d − gridStart), 7)`
  - `row = index du jour de d dans l'ordre effectif`
  - `x = marginLeft + col × (cellSize + gap)`
  - `y = marginTop + row × (cellSize + gap)`
  - `Rect(x, y, cellSize, cellSize, fill, rx: radius, ry: radius)`.
- Les jours hors plage ne sont pas dessinés (première/dernière colonne partielles).
- `weeks = col(end) + 1`, `rows = count(ordre effectif)`.

### Marges et dimensions

- `step = cellSize + gap`.
- `marginLeft` = `0` si `dayLabels === null` ou tous vides, sinon
  `ceil(maxLen × fontSize × 0.6) + gap`.
- `marginTop` = `0` si `monthLabels === null`, sinon `fontSize + gap`.
- `legendHeight` = `0` si `legend === false`, sinon `gap + cellSize`.
- `width  = marginLeft + weeks × step − gap`
- `height = marginTop + rows × step − gap + legendHeight`
- La largeur est en plus élargie si nécessaire pour contenir la légende
  (`width = max(width, legendWidth)`).

### Libellés de jours

`Text` aligné à droite (`textAnchor: 'end'`) à `x = marginLeft − gap`,
`y = marginTop + row × step + cellSize / 2`, `dominantBaseline: 'middle'`. Les chaînes
vides ne produisent rien (comportement existant de `SVG\Text`).

### Libellés de mois

- Candidats : la colonne de `start` (mois de `start`), puis la colonne de chaque 1er du
  mois compris dans `]start, end]`.
- Un candidat est ignoré si sa colonne est à moins de 2 colonnes du dernier libellé
  placé.
- `Text` à `x = marginLeft + col × step`, `y = fontSize` (baseline), `textAnchor:
  'start'`.

### Légende

Alignée à droite sous la grille, ligne à `y = marginTop + rows × step`. De gauche à
droite : texte `legendLabels[0]`, cellule `emptyColor`, les N nuances, texte
`legendLabels[1]`. Cellules espacées de `gap`, textes séparés des cellules par `gap`,
largeur des textes estimée à `len × fontSize × 0.6`.

### Cas vide

Aucune donnée et ni `start` ni `end` (plage indéterminée) ⇒ SVG de dimensions
`cellSize × cellSize` contenant uniquement le fond, analogue au cas vide de
`PieChart`. Si la plage est connue mais sans données, la grille est rendue
entièrement en `emptyColor` (et `min`/`max` ne sont pas utilisés).

## Fichiers

- `src/Heatmap/CalendarHeatmap.php` — constructeur, `render()`, méthodes protégées
  `background()`, `renderCells()`, `renderDayLabels()`, `renderMonthLabels()`,
  `renderLegend()`.
- `src/Heatmap/Day.php`
- `src/Heatmap/DayOfWeek.php`
- `src/Heatmap/ColorScale.php`
- `examples/calendar-heatmap.php` + `examples/output/calendar-heatmap.svg` (une année,
  données pseudo-aléatoires avec `mt_srand` à graine fixe).
- `README.md` — entrée « Calendar Heatmap » dans la liste et la galerie d'exemples.

Réutilise `SVG\Rect`, `SVG\Text`, `SVG\Fragment`. Aucune modification de `Chart`.

## Tests (Pest)

- `tests/Unit/ColorScaleTest.php`
  - nuances pour `#rrggbb` et `#rgb` ;
  - `colorFor` : min ⇒ 1re nuance, max ⇒ dernière, milieu, `min == max`, valeurs hors
    bornes ;
  - couleur invalide et `shades < 1` ⇒ exception.
- `tests/Unit/CalendarHeatmapTest.php`
  - snapshots `pretty($chart->render())` : rendu de base (~3 semaines), `weekStart:
    Sunday`, `dayOrder` jours ouvrés, libellés et légende masqués, graphique vide ;
  - jours absents ⇒ `emptyColor` ;
  - doublons additionnés ;
  - données hors plage ignorées ;
  - exceptions : `weekStart` + `dayOrder`, `dayOrder` invalide, `start > end`,
    `dayLabels` de mauvaise longueur.
- PHPStan et Pint passent (CI existante).
