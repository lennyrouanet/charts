# Boxplot (boîte à moustaches) — design

Date : 2026-10-08
Source du besoin : `.claude/brainstorming.md`

## Objectif

Ajouter à `maantje/charts` un nouveau type de série, le boxplot, utilisable seul
ou sur un `Chart` qui contient déjà des `Bars` et des `Lines`.

Le développeur qui connaît `Bars`/`Bar` doit retrouver la même ergonomie :
mêmes noms de paramètres, mêmes défauts, même façon de composer un graphique.

## Contraintes

- Aucune dépendance runtime ajoutée.
- Aucun fichier existant de `src/` n'est modifié. Les primitives `SVG\Rect`,
  `SVG\Line`, `SVG\Circle`, `SVG\Text` et `SVG\Fragment` suffisent.
- La librairie ne calcule pas de statistiques : l'appelant fournit les cinq
  valeurs déjà calculées.
- PHPStan niveau 8 et Pint (preset Laravel) passent.

## API publique

Deux classes dans `src/Boxplot/`, namespace `Maantje\Charts\Boxplot`.

### `Boxplots extends Serie`

```php
/**
 * @param  Boxplot[]  $boxplots
 */
public function __construct(
    private readonly array $boxplots = [],
    public ?string $yAxis = null,
)
```

- `minValue(): float` — minimum des `minValue()` de ses boxplots.
- `maxValue(): float` — maximum des `maxValue()` de ses boxplots.
- `render(Chart $chart): string` — voir « Rendu ».

### `Boxplot`

```php
/**
 * @param  float[]  $values  min, Q1, médiane, Q3, max, puis les exceptions
 */
public function __construct(
    public string $name,
    public array $values,
    public ?string $yAxis = null,
    public string $color = '#333',
    public float $strokeWidth = 2,
    public string $fillColor = '#3498db',
    public ?float $width = 60,
    public ?string $medianColor = null,
    public ?string $outlierColor = null,
    public float $outlierSize = 4,
    public string $labelColor = '#333',
    public int $labelMarginY = 30,
)
```

| Paramètre | Rôle |
|---|---|
| `name` | Libellé sous l'axe X. `''` : aucun libellé n'est émis. |
| `values` | Tableau positionnel : `[min, Q1, médiane, Q3, max, ...exceptions]`. |
| `yAxis` | Nom de l'axe Y utilisé ; `null` = axe `default`. |
| `color` | Couleur du trait : contour de la boîte, moustaches, extrémités. |
| `strokeWidth` | Épaisseur de tous les traits. |
| `fillColor` | Couleur de fond de la boîte. |
| `width` | Largeur de la boîte, plafonnée à la largeur du créneau. `null` = tout le créneau. |
| `medianColor` | Couleur du trait de médiane ; `null` = `color`. |
| `outlierColor` | Couleur de remplissage des points d'exception ; `null` = `color`. |
| `outlierSize` | Rayon des points d'exception. |
| `labelColor`, `labelMarginY` | Identiques à `Bar`. |

Méthodes :

- `minValue(): float` — `min($this->values)`, exceptions comprises.
- `maxValue(): float` — `max($this->values)`, exceptions comprises.
- `render(Chart $chart, float $x, float $maxWidth): string` — même forme que
  `BarContract::render()`. `Boxplot` n'implémente pas `BarContract` : `value()`
  n'a pas de sens pour un boxplot, et il ne se place pas dans `Bars`.

### Exemple d'usage

```php
$chart = new Chart(
    series: [
        new Boxplots(
            boxplots: [
                new Boxplot(name: 'Jan', values: [12, 30, 45, 60, 88]),
                new Boxplot(name: 'Feb', values: [20, 35, 50, 70, 95, 130, 4]),
            ],
        ),
    ],
);
```

## Rendu

### `Boxplots::render()`

Même découpage que `Bars::render()` :

```
maxWidth = chart.availableWidth() / count(boxplots)
x        = chart.left()
pour chaque boxplot : svg .= boxplot.render(chart, x, maxWidth) ; x += maxWidth
```

Conséquence : un `Bars` et un `Boxplots` de même taille s'alignent créneau par
créneau, ce qui permet la superposition dans un graphique mixte. L'ordre de
dessin est celui du tableau `series` du `Chart`.

Un `Boxplots` vide renvoie `''` (pas de division par zéro).

### `Boxplot::render()`

Géométrie horizontale :

```
width   = min(this.width ?? maxWidth, maxWidth)
boxX    = x + (maxWidth - width) / 2
centerX = boxX + width / 2
capHalf = width / 4            // extrémités = moitié de la largeur de la boîte
```

Géométrie verticale : chaque valeur `v` est convertie par
`$chart->yForAxis($v, $this->yAxis)`.

Le `Fragment` renvoyé contient, dans cet ordre :

1. `Line` verticale (moustache) de `y(min)` à `y(max)` en `centerX`.
2. `Line` horizontale en `y(min)`, de `centerX - capHalf` à `centerX + capHalf`.
3. `Line` horizontale en `y(max)`, mêmes bornes.
4. `Rect` de la boîte : `x = boxX`, `y = y(Q3)`, `width`, `height = y(Q1) - y(Q3)`,
   `fill = fillColor`, `stroke = color`, `strokeWidth`,
   `title = "min / Q1 / médiane / Q3 / max"` (les cinq valeurs séparées par ` / `).
5. `Line` de médiane en `y(médiane)`, de `boxX` à `boxX + width`,
   `stroke = medianColor ?? color`.
6. Un `Circle` par exception : `cx = centerX`, `cy = y(v)`, `r = outlierSize`,
   `fill = outlierColor ?? color`, `title = v`.
7. Si `name !== ''` : `Text` du libellé en `centerX`,
   `y = chart.bottom() + labelMarginY`, `fontFamily`/`fontSize` du chart,
   `fill = labelColor`, `textAnchor: 'middle'` — identique à `Bar`.

La boîte est dessinée après la moustache pour masquer le segment Q1–Q3.
Les lignes 1 à 3 et 5 utilisent `stroke = color` (sauf médiane) et `strokeWidth`.

## Échelle Y

`Chart::minValue()` / `maxValue()` agrègent les séries liées à un axe via
`Serie::minValue()` / `maxValue()`. Comme `Boxplot` inclut ses exceptions dans
son min et son max, les points d'exception restent dans la zone de tracé.

Comportement existant conservé : l'axe Y par défaut du `Chart` est construit
avec `minValue: 0`. Une valeur négative sort du cadre tant que l'appelant ne
fournit pas son propre `YAxis`, comme pour les barres.

## Erreurs

Le constructeur de `Boxplot` lève une `InvalidArgumentException` :

- s'il y a moins de cinq valeurs —
  `Boxplot "<name>" needs at least 5 values (min, Q1, median, Q3, max), <n> given.`
- si les cinq premières ne sont pas en ordre croissant (égalités admises) —
  `Boxplot "<name>" values must be ordered: min <= Q1 <= median <= Q3 <= max.`

Les exceptions (valeurs à partir de la sixième) ne sont pas contraintes.

C'est la première validation d'entrée de la librairie. Elle est justifiée ici
parce qu'un ordre invalide produit un `Rect` de hauteur négative, donc un SVG
invalide et silencieux.

## Tests

`tests/Unit/BoxplotChartTest.php`, dans le style de `BarChartTest.php` :

1. Rendu d'un boxplot simple (cinq valeurs) — comparaison `toBe()` sur le SVG complet.
2. Rendu avec exceptions : un `<circle>` par valeur supplémentaire, et l'échelle Y
   s'étend jusqu'à l'exception la plus haute.
3. Boxplot lié à un axe Y secondaire nommé.
4. Graphique mixte `Bars` + `Boxplots` : centres alignés créneau par créneau.
5. `name: ''` n'émet aucun `<text>` de libellé.
6. Moins de cinq valeurs → `InvalidArgumentException`.
7. Valeurs non ordonnées → `InvalidArgumentException`.

Les SVG attendus sont générés depuis le rendu réel puis relus : les heredocs
des tests existants décrivent un ancien format de sortie et ne servent pas de
référence.

## Exemples et documentation

- `examples/simple-boxplot-chart.php` → `examples/output/simple-boxplot-chart.svg`.
- `examples/mixed-chart.php` : ajout d'une série `Boxplots` de trois éléments,
  alignée sur les trois barres existantes, avec `name: ''`.
- `composer.json` : inchangé, le script `examples` parcourt `examples/*.php`.
- `README.md` : entrée « Simple boxplot chart » dans « Usage Examples », section
  « Simple Boxplot Chart » dans « Usage », et mention dans « Features ».
- `CLAUDE.md` : ajouter `Boxplot\Boxplots` à la liste des types de séries.

## Hors périmètre

- Calcul des quartiles à partir de données brutes.
- Boxplots horizontaux.
- Placement d'un `Boxplot` dans `Bars` (pas d'implémentation de `BarContract`).
- Encoches (notched boxplot), marqueur de moyenne, dispersion des exceptions (jitter).
- Correction des heredocs obsolètes de `BarChartTest` et `LineChartTest`.
