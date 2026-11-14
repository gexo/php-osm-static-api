[![CI](https://github.com/gexo/php-osm-static-api/actions/workflows/ci.yml/badge.svg)](https://github.com/gexo/php-osm-static-api/actions/workflows/ci.yml)
[![Latest tag](https://img.shields.io/github/v/tag/gexo/php-osm-static-api?sort=semver)](https://github.com/gexo/php-osm-static-api/tags)
[![GitHub license](https://img.shields.io/github/license/gexo/php-osm-static-api.svg)](https://github.com/gexo/php-osm-static-api/blob/master/LICENSE)

# PHP OpenStreetMap Static API

PHP library to easily get static image from OpenStreetMap with markers, lines, circles and polygons.

This project uses the [Tile Server](https://wiki.openstreetmap.org/wiki/Tile_servers) of the OpenStreetMap Foundation which runs entirely on donated resources, see [Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/) for more information.

## About this fork

This is a fork of [DantSu/php-osm-static-api](https://github.com/DantSu/php-osm-static-api) by Franck Alary.
It keeps the original API and the `DantSu\OpenStreetMapStaticAPI` namespace, and focuses on compatibility with current PHP versions
(no deprecations, warnings or notices on PHP 8.1 - 8.5).

It depends on [gexo/php-image-editor](https://github.com/gexo/php-image-editor), a fork of `dantsu/php-image-editor`
with the same goal. That package replaces `dantsu/php-image-editor` and keeps the `DantSu\PHPImageEditor` namespace,
so `OpenStreetMap::getImage()` still returns a `DantSu\PHPImageEditor\Image`.

See [CHANGELOG.md](./CHANGELOG.md) for the changes compared to the original package.

## Requirements

- PHP 8.1 or newer
- Extensions: `gd`, `curl`, `mbstring` (required by `gexo/php-image-editor`)

## Installation

This fork and its dependency `gexo/php-image-editor` are not published on Packagist.
Add **both** repositories to the `composer.json` of your project:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/gexo/php-osm-static-api" },
    { "type": "vcs", "url": "https://github.com/gexo/php-image-editor" }
  ],
  "require": {
    "gexo/php-osm-static-api": "^0.7"
  }
}
```

Then run `composer update gexo/php-osm-static-api`.

The `php-image-editor` entry is required although your project uses it only indirectly: Composer only reads
`repositories` from the root `composer.json` of a project, not from dependencies.

If your project required `dantsu/php-osm-static-api` before, replace it with `gexo/php-osm-static-api`.
Your PHP code does not need to change, the namespaces are the same.

For `github.com` repositories, Composer uses the GitHub API, which allows only 60 anonymous requests per hour.
If you hit that limit, either configure a GitHub token (`composer config --global github-oauth.github.com <token>`)
or add `"no-api": true` to both repository entries, so that Composer uses plain Git instead.

## How to use

### Generate OpenStreetMap static image with markers and polygon :

```php
use \DantSu\OpenStreetMapStaticAPI\OpenStreetMap;
use \DantSu\OpenStreetMapStaticAPI\LatLng;
use \DantSu\OpenStreetMapStaticAPI\Polygon;
use \DantSu\OpenStreetMapStaticAPI\Markers;

\header('Content-type: image/png');
(new OpenStreetMap(new LatLng(44.351933, 2.568113), 17, 600, 400))
    ->addMarkers(
        (new Markers(__DIR__ . '/resources/marker.png'))
            ->setAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
            ->addMarker(new LatLng(44.351933, 2.568113))
            ->addMarker(new LatLng(44.351510, 2.570020))
            ->addMarker(new LatLng(44.351873, 2.566250))
    )
    ->addDraw(
        (new Polygon('FF0000', 2, 'FF0000DD'))
            ->addPoint(new LatLng(44.351172, 2.571092))
            ->addPoint(new LatLng(44.352097, 2.570045))
            ->addPoint(new LatLng(44.352665, 2.568107))
            ->addPoint(new LatLng(44.352887, 2.566503))
            ->addPoint(new LatLng(44.352806, 2.565972))
            ->addPoint(new LatLng(44.351517, 2.565672))
    )
    ->getImage()
    ->displayPNG();
```

![Exported OpenStreetMap image](./src/samples/resources/sample1.png)

### Align and zoom the map to drawings and markers :

- `->fitToDraws(int $padding = 0)`
- `->fitToMarkers(int $padding = 0)`
- `->fitToDrawsAndMarkers(int $padding = 0)`
- `->fitToPoints(LatLng[] $points, int $padding = 0)`

`$padding` sets the amount of padding in the borders of the map that shouldn't be accounted for when setting the view to fit bounds. This can be positive or negative according to your needs.

```php
use \DantSu\OpenStreetMapStaticAPI\OpenStreetMap;
use \DantSu\OpenStreetMapStaticAPI\LatLng;
use \DantSu\OpenStreetMapStaticAPI\Polygon;
use \DantSu\OpenStreetMapStaticAPI\Markers;

\header('Content-type: image/png');
(new OpenStreetMap(new LatLng(0, 0), 0, 600, 400))
    ->addMarkers(
        (new Markers(__DIR__ . '/resources/marker.png'))
            ->setAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
            ->addMarker(new LatLng(44.351933, 2.568113))
            ->addMarker(new LatLng(44.351510, 2.570020))
            ->addMarker(new LatLng(44.351873, 2.566250))
    )
    ->addDraw(
        (new Polygon('FF0000', 2, 'FF0000DD'))
            ->addPoint(new LatLng(44.351172, 2.571092))
            ->addPoint(new LatLng(44.352097, 2.570045))
            ->addPoint(new LatLng(44.352665, 2.568107))
            ->addPoint(new LatLng(44.352887, 2.566503))
            ->addPoint(new LatLng(44.352806, 2.565972))
            ->addPoint(new LatLng(44.351517, 2.565672))
    )
    ->fitToDraws(10)
    ->getImage()
    ->displayPNG();
```

## Documentation

| Class | Description |
|---    |---          |
| [Circle](./docs/classes/DantSu/OpenStreetMapStaticAPI/Circle.md) | DantSu\OpenStreetMapStaticAPI\Circle draw circle on the map.|
| [LatLng](./docs/classes/DantSu/OpenStreetMapStaticAPI/LatLng.md) | DantSu\OpenStreetMapStaticAPI\LatLng define latitude and longitude for map, lines, markers.|
| [Line](./docs/classes/DantSu/OpenStreetMapStaticAPI/Line.md) | DantSu\OpenStreetMapStaticAPI\Line draw line on the map.|
| [MapData](./docs/classes/DantSu/OpenStreetMapStaticAPI/MapData.md) | DantSu\OpenStreetMapStaticAPI\MapData convert latitude and longitude to image pixel position.|
| [Markers](./docs/classes/DantSu/OpenStreetMapStaticAPI/Markers.md) | DantSu\OpenStreetMapStaticAPI\Markers display markers on the map.|
| [OpenStreetMap](./docs/classes/DantSu/OpenStreetMapStaticAPI/OpenStreetMap.md) | DantSu\OpenStreetMapStaticAPI\OpenStreetMap is a PHP library created for easily get static image from OpenStreetMap with markers, lines, polygons and circles.|
| [Polygon](./docs/classes/DantSu/OpenStreetMapStaticAPI/Polygon.md) | DantSu\OpenStreetMapStaticAPI\Polygon draw polygon on the map.|
| [TileLayer](./docs/classes/DantSu/OpenStreetMapStaticAPI/TileLayer.md) | DantSu\OpenStreetMapStaticAPI\TileLayer define tile server url and related configuration|
| [XY](./docs/classes/DantSu/OpenStreetMapStaticAPI/XY.md) | DantSu\OpenStreetMapStaticAPI\XY define X and Y pixel position for map, lines, markers.|


## Contributing

Please fork this repository and contribute back using pull requests.

Run the test suite with `composer test`. The tests use local tiles (no network access) and fail on any PHP deprecation, warning or notice.

Any contributions, large or small, major features, bug fixes, are welcomed and appreciated but will be thoroughly reviewed.

