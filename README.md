# Charts - SVG Chart Rendering

**Charts** is a zero-dependency PHP library for generating SVG charts. It enables easy creation of SVG-based charts directly from PHP, with no additional dependencies required.

## Features

- Simple, intuitive API for chart creation
- Lightweight, with no external dependencies
- Supports various chart types: line charts, bar charts, stacked charts, boxplots, and mixed charts
- Fully customizable and extendable
- Outputs pure SVG, allowing for:
  - Embedding in PDFs (ideal for reports)

## Installation

To get started, install the package via composer:

```bash
composer require maantje/charts
```

## Usage Examples

Below are some examples of the types of charts you can create using this library. Click on the links to view the source code for each example.

### Simple line chart
![alt text](./examples/output/simple-line-chart.svg)
[View source](./examples/simple-line-chart.php)

### Simple bar chart
![alt text](./examples/output/simple-bar-chart.svg)
[View source](./examples/simple-bar-chart.php)

### Simple stacked chart
![alt text](./examples/output/simple-stacked-bar-chart.svg)
[View source](./examples/simple-stacked-bar-chart.php)

### Simple boxplot chart
![alt text](./examples/output/simple-boxplot-chart.svg)
[View source](./examples/simple-boxplot-chart.php)

### Advanced line charts
![alt text](./examples/output/advanced-line-chart.svg)
[View source](./examples/advanced-line-chart.php)

### Advanced bar chart
![alt text](./examples/output/advanced-bar-chart.svg)
[View source](./examples/advanced-bar-chart.php)

### Mixed chart
![alt text](./examples/output/mixed-chart.svg)
[View source](./examples/mixed-chart.php)


## Usage

### Creating a Chart

You can create different types of charts using the provided classes. Below are examples of how to create a simple bar chart and a line chart.

#### Simple Bar Chart

```php
use Maantje\Charts\Bar\Bar;
use Maantje\Charts\Bar\Bars;
use Maantje\Charts\Chart;

$chart = new Chart(
    series: [
        new Bars(
            bars: [
                new Bar(name: 'Jan', value: 222301),
                new Bar(name: 'Feb', value: 189242),
                new Bar(name: 'Mar', value: 144922),
            ],
        ),
    ],
);

echo $chart->render();
```

#### Simple Line Chart

```php
use Maantje\Charts\Chart;
use Maantje\Charts\Line\Line;
use Maantje\Charts\Line\Lines;
use Maantje\Charts\Line\Point;

$chart = new Chart(
    series: [
        new Lines(
            lines: [
                new Line(
                    points: [
                        new Point(y: 0, x: 0),
                        new Point(y: 4, x: 100),
                        new Point(y: 12, x: 200),
                        new Point(y: 8, x: 300),
                    ],
                ),
            ],
        ),
    ],
);

echo $chart->render();
```

#### Simple Boxplot Chart

```php
use Maantje\Charts\Boxplot\Boxplot;
use Maantje\Charts\Boxplot\Boxplots;
use Maantje\Charts\Chart;

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

echo $chart->render();
```

The `values` of a boxplot are given in order: min, Q1, median, Q3, max. Any value after the fifth is an outlier and is drawn as a point.
The library does not compute quartiles: pass the five statistics already calculated.

#### Annotations

You can add annotations to your charts for better visualization.

```php
use Maantje\Charts\Annotations\PointAnnotation;
use Maantje\Charts\YAxis;

$chart = new Chart(
    yAxis: new YAxis(
        annotations: [
            new PointAnnotation(x: 200, y: 120, label: 'Important Point'),
        ],
    ),
    // ...
);
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
