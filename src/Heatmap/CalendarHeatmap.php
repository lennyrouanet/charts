<?php

namespace Maantje\Charts\Heatmap;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Maantje\Charts\SVG\Fragment;
use Maantje\Charts\SVG\Path;
use Maantje\Charts\SVG\Rect;
use Maantje\Charts\SVG\Text;

class CalendarHeatmap
{
    /**
     * @var DayOfWeek[]
     */
    protected array $order;

    /**
     * @var array<string, float> aggregated values keyed by Y-m-d
     */
    protected array $values = [];

    /**
     * @var string[]|null labels aligned on
     */
    protected ?array $dayLabels;

    protected ?DateTimeImmutable $start = null;

    protected ?DateTimeImmutable $end = null;

    protected ?DateTimeImmutable $gridStart = null;

    protected int $weeks = 0;

    protected ColorScale $scale;

    /**
     * @param  array<Day|int|float>  $days
     * @param  DayOfWeek[]|null  $dayOrder
     * @param  string[]|null|false  $dayLabels  false uses the default Mon/Wed/Fri labels, null hides them
     * @param  string[]|null  $monthLabels
     * @param  string[]  $legendLabels  [less, more]
     */
    public function __construct(
        array $days = [],
        string $color = '#216e39',
        int $shades = 4,
        string|DateTimeInterface|null $start = null,
        string|DateTimeInterface|null $end = null,
        ?DayOfWeek $weekStart = null,
        ?array $dayOrder = null,
        protected ?float $min = null,
        protected ?float $max = null,
        public string $emptyColor = '#ebedf0',
        public int $cellSize = 12,
        public int $gap = 3,
        public float $radius = 2,
        array|null|false $dayLabels = false,
        public ?array $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        public bool $legend = true,
        public array $legendLabels = ['Less', 'More'],
        public ?string $background = 'white',
        public int $fontSize = 10,
        public string $fontFamily = 'arial',
        public string $fontColor = '#57606a',
        public ?string $monthBorder = null,
        public float $monthBorderWidth = 1,
    ) {
        $this->scale = new ColorScale($color, $shades);
        $this->order = $this->resolveOrder($weekStart, $dayOrder);
        $this->dayLabels = $this->resolveDayLabels($dayLabels);

        if (! is_null($this->monthLabels) && count($this->monthLabels) !== 12) {
            throw new InvalidArgumentException('monthLabels must contain 12 labels.');
        }

        if (count($this->legendLabels) !== 2) {
            throw new InvalidArgumentException('legendLabels must contain 2 labels.');
        }

        $this->monthLabels = is_null($this->monthLabels) ? null : array_values($this->monthLabels);
        $this->legendLabels = array_values($this->legendLabels);

        $days = $this->normalizeDays($days);
        $this->resolveRange($days, $start, $end);

        if (is_null($this->start) || is_null($this->end)) {
            return;
        }

        foreach ($days as $day) {
            if ($day->date < $this->start || $day->date > $this->end || ! $this->isDisplayed($day->date)) {
                continue;
            }

            $key = $day->date->format('Y-m-d');
            $this->values[$key] = ($this->values[$key] ?? 0) + $day->value;
        }

        $offset = (DayOfWeek::fromDate($this->start)->value - $this->order[0]->value + 7) % 7;
        $this->gridStart = $this->start->modify("-$offset days");
        $this->weeks = $this->column($this->end) + 1;
    }

    public function render(): string
    {
        return <<<SVG
            <svg width="{$this->width()}" height="{$this->height()}" xmlns="http://www.w3.org/2000/svg">
                {$this->background()}
                {$this->renderDayLabels()}
                {$this->renderMonthLabels()}
                {$this->renderCells()}
                {$this->renderMonthBorders()}
                {$this->renderLegend()}
            </svg>
            SVG;
    }

    protected function renderCells(): string
    {
        if (is_null($this->start) || is_null($this->end)) {
            return '';
        }

        $min = $this->min ?? (empty($this->values) ? 0 : min($this->values));
        $max = $this->max ?? (empty($this->values) ? 0 : max($this->values));

        $cells = [];

        foreach (new DatePeriod($this->start, new DateInterval('P1D'), $this->end->modify('+1 day')) as $date) {
            if (! $this->isDisplayed($date)) {
                continue;
            }

            $value = $this->values[$date->format('Y-m-d')] ?? null;

            $cells[] = $this->cell(
                x: $this->marginLeft() + $this->column($date) * $this->step(),
                y: $this->marginTop() + $this->row($date) * $this->step(),
                fill: is_null($value) ? $this->emptyColor : $this->scale->colorFor($value, $min, $max),
            );
        }

        return new Fragment($cells);
    }

    protected function cell(float $x, float $y, string $fill): Rect
    {
        return new Rect(
            x: $x,
            y: $y,
            width: $this->cellSize,
            height: $this->cellSize,
            fill: $fill,
            rx: $this->radius,
            ry: $this->radius,
        );
    }

    protected function renderDayLabels(): string
    {
        if (is_null($this->start) || is_null($this->dayLabels)) {
            return '';
        }

        $labels = [];

        foreach ($this->dayLabels as $row => $label) {
            $labels[] = $this->text(
                content: $label,
                x: $this->marginLeft() - $this->gap,
                y: $this->marginTop() + $row * $this->step() + $this->cellSize / 2,
                textAnchor: 'end',
                dominantBaseline: 'middle',
            );
        }

        return new Fragment($labels);
    }

    protected function renderMonthLabels(): string
    {
        if (is_null($this->start) || is_null($this->end) || is_null($this->monthLabels)) {
            return '';
        }

        $candidates = [$this->start];
        $month = $this->start->modify('first day of next month');

        while ($month <= $this->end) {
            $candidates[] = $month;
            $month = $month->modify('first day of next month');
        }

        $labels = [];
        $lastColumn = null;

        foreach ($candidates as $date) {
            $column = $this->column($date);

            if (! is_null($lastColumn) && $column - $lastColumn < 2) {
                continue;
            }

            $label = $this->monthLabels[(int) $date->format('n') - 1];
            $x = $this->marginLeft() + $column * $this->step();

            if ($x + $this->textWidth($label) > $this->marginLeft() + $this->weeks * $this->step() - $this->gap) {
                continue;
            }

            $lastColumn = $column;

            $labels[] = $this->text(
                content: $label,
                x: $x,
                y: $this->fontSize,
            );
        }

        return new Fragment($labels);
    }

    protected function text(string $content, float $x, float $y, string $textAnchor = 'start', string $dominantBaseline = 'alphabetic'): Text
    {
        return new Text(
            content: $content,
            x: $x,
            y: $y,
            fontFamily: $this->fontFamily,
            fontSize: $this->fontSize,
            fill: $this->fontColor,
            textAnchor: $textAnchor,
            dominantBaseline: $dominantBaseline,
        );
    }

    protected function textWidth(string $text): float
    {
        return strlen($text) * $this->fontSize * 0.6;
    }

    /**
     * Draws a line in the gap between neighbouring cells of different months.
     * Only right and bottom neighbours are checked so each segment is drawn once.
     */
    protected function renderMonthBorders(): string
    {
        if (is_null($this->start) || is_null($this->end) || is_null($this->monthBorder)) {
            return '';
        }

        $half = $this->gap / 2;
        $segments = [];

        foreach (new DatePeriod($this->start, new DateInterval('P1D'), $this->end->modify('+1 day')) as $date) {
            if (! $this->isDisplayed($date)) {
                continue;
            }

            $column = $this->column($date);
            $row = $this->row($date);
            $x = $this->marginLeft() + $column * $this->step();
            $y = $this->marginTop() + $row * $this->step();

            if ($this->isOtherMonth($date, $this->dateAt($column + 1, $row))) {
                $segments[] = sprintf('M%s %sV%s', $x + $this->cellSize + $half, $y - $half, $y + $this->cellSize + $half);
            }

            if ($this->isOtherMonth($date, $this->dateAt($column, $row + 1))) {
                $segments[] = sprintf('M%s %sH%s', $x - $half, $y + $this->cellSize + $half, $x + $this->cellSize + $half);
            }
        }

        if (empty($segments)) {
            return '';
        }

        return new Path(
            d: implode(' ', $segments),
            fill: 'none',
            stroke: $this->monthBorder,
            strokeWidth: $this->monthBorderWidth,
        );
    }

    protected function dateAt(int $column, int $row): ?DateTimeImmutable
    {
        if (is_null($this->gridStart) || ! isset($this->order[$row])) {
            return null;
        }

        $offset = $column * 7 + ($this->order[$row]->value - $this->order[0]->value + 7) % 7;
        $date = $this->gridStart->modify("+$offset days");

        return $date < $this->start || $date > $this->end ? null : $date;
    }

    protected function isOtherMonth(DateTimeInterface $date, ?DateTimeInterface $neighbour): bool
    {
        return ! is_null($neighbour) && $neighbour->format('Y-m') !== $date->format('Y-m');
    }

    protected function renderLegend(): string
    {
        if (is_null($this->start) || ! $this->legend) {
            return '';
        }

        [$less, $more] = $this->legendLabels;

        $x = $this->width() - $this->legendWidth();
        $y = $this->marginTop() + count($this->order) * $this->step();
        $textY = $y + $this->cellSize / 2;

        $elements = [$this->text($less, $x, $textY, dominantBaseline: 'middle')];
        $x += $this->textWidth($less) + $this->gap;

        foreach ([$this->emptyColor, ...$this->scale->shades()] as $color) {
            $elements[] = $this->cell($x, $y, $color);
            $x += $this->step();
        }

        $elements[] = $this->text($more, $this->width(), $textY, textAnchor: 'end', dominantBaseline: 'middle');

        return new Fragment($elements);
    }

    protected function background(): string
    {
        if (is_null($this->background)) {
            return '';
        }

        return new Rect(
            width: $this->width(),
            height: $this->height(),
            fill: $this->background,
        );
    }

    protected function step(): int
    {
        return $this->cellSize + $this->gap;
    }

    protected function column(DateTimeInterface $date): int
    {
        if (is_null($this->gridStart)) {
            return 0;
        }

        return intdiv((int) $this->gridStart->diff($date)->days, 7);
    }

    protected function row(DateTimeInterface $date): int
    {
        return (int) array_search(DayOfWeek::fromDate($date), $this->order, true);
    }

    protected function isDisplayed(DateTimeInterface $date): bool
    {
        return in_array(DayOfWeek::fromDate($date), $this->order, true);
    }

    protected function marginLeft(): float
    {
        // Day labels are right-aligned into this margin, so overestimate their width to keep wide glyphs (W, M) inside the SVG.
        $width = max([0, ...array_map(fn (string $label) => strlen($label) * $this->fontSize * 0.8, $this->dayLabels ?? [])]);

        return $width <= 0 ? 0 : ceil($width) + $this->gap;
    }

    protected function marginTop(): float
    {
        return is_null($this->monthLabels) ? 0 : $this->fontSize + $this->gap;
    }

    protected function legendHeight(): float
    {
        return $this->legend ? $this->gap + $this->cellSize : 0;
    }

    protected function legendWidth(): float
    {
        if (! $this->legend) {
            return 0;
        }

        $shades = count($this->scale->shades());

        return $this->textWidth($this->legendLabels[0]) + $this->gap
            + ($shades + 1) * $this->cellSize + $shades * $this->gap
            + $this->gap + $this->textWidth($this->legendLabels[1]);
    }

    protected function width(): float
    {
        if (is_null($this->start)) {
            return $this->cellSize;
        }

        return max($this->marginLeft() + $this->weeks * $this->step() - $this->gap, $this->legendWidth());
    }

    protected function height(): float
    {
        if (is_null($this->start)) {
            return $this->cellSize;
        }

        return $this->marginTop() + count($this->order) * $this->step() - $this->gap + $this->legendHeight();
    }

    /**
     * @param  DayOfWeek[]|null  $dayOrder
     * @return DayOfWeek[]
     */
    protected function resolveOrder(?DayOfWeek $weekStart, ?array $dayOrder): array
    {
        if (! is_null($weekStart) && ! is_null($dayOrder)) {
            throw new InvalidArgumentException('Use either weekStart or dayOrder, not both.');
        }

        if (! is_null($dayOrder)) {
            $dayOrder = array_values($dayOrder);
            $unique = array_unique(array_map(fn (DayOfWeek $day) => $day->value, $dayOrder));

            if (count($dayOrder) === 0 || count($dayOrder) > 7 || count($unique) !== count($dayOrder)) {
                throw new InvalidArgumentException('dayOrder must contain 1 to 7 distinct days.');
            }

            return $dayOrder;
        }

        $first = ($weekStart ?? DayOfWeek::Monday)->value;

        return array_map(fn (int $i) => DayOfWeek::from(($first - 1 + $i) % 7 + 1), range(0, 6));
    }

    /**
     * @param  string[]|null|false  $dayLabels
     * @return string[]|null
     */
    protected function resolveDayLabels(array|null|false $dayLabels): ?array
    {
        if ($dayLabels === false) {
            $defaults = [DayOfWeek::Monday->value => 'Mon', DayOfWeek::Wednesday->value => 'Wed', DayOfWeek::Friday->value => 'Fri'];

            return array_map(fn (DayOfWeek $day) => $defaults[$day->value] ?? '', $this->order);
        }

        if (! is_null($dayLabels) && count($dayLabels) !== count($this->order)) {
            throw new InvalidArgumentException('dayLabels must have one label per displayed day.');
        }

        return is_null($dayLabels) ? null : array_values($dayLabels);
    }

    /**
     * @param  array<mixed>  $days
     * @return Day[]
     */
    protected function normalizeDays(array $days): array
    {
        $normalized = [];

        foreach ($days as $key => $day) {
            if ($day instanceof Day) {
                $normalized[] = $day;
            } elseif (is_numeric($day)) {
                $normalized[] = new Day((string) $key, (float) $day);
            } else {
                throw new InvalidArgumentException('Days must be Day instances or date => numeric value pairs.');
            }
        }

        return $normalized;
    }

    /**
     * @param  Day[]  $days
     */
    protected function resolveRange(array $days, string|DateTimeInterface|null $start, string|DateTimeInterface|null $end): void
    {
        $dates = array_map(fn (Day $day) => $day->date, $days);

        $this->start = ! is_null($start) ? Day::normalize($start) : (empty($dates) ? null : min($dates));
        $this->end = ! is_null($end) ? Day::normalize($end) : (empty($dates) ? null : max($dates));

        $this->start ??= $this->end;
        $this->end ??= $this->start;

        if (! is_null($this->start) && $this->start > $this->end) {
            throw new InvalidArgumentException('start must be before or equal to end.');
        }
    }
}
