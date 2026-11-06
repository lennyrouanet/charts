<?php

namespace Maantje\Charts\Heatmap;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Maantje\Charts\SVG\Fragment;
use Maantje\Charts\SVG\Rect;

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
     * @param  array{0: string, 1: string}  $legendLabels
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
    ) {
        $this->scale = new ColorScale($color, $shades);
        $this->order = $this->resolveOrder($weekStart, $dayOrder);
        $this->dayLabels = $this->resolveDayLabels($dayLabels);

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
        return '';
    }

    protected function renderMonthLabels(): string
    {
        return '';
    }

    protected function renderLegend(): string
    {
        return '';
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
        return 0;
    }

    protected function marginTop(): float
    {
        return 0;
    }

    protected function legendHeight(): float
    {
        return 0;
    }

    protected function legendWidth(): float
    {
        return 0;
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
            } elseif (is_int($day) || is_float($day)) {
                $normalized[] = new Day((string) $key, $day);
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
