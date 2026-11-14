# Changelog

All notable changes of this fork are documented in this file.
For releases up to 0.6.4, see the [original repository](https://github.com/DantSu/php-osm-static-api).

## [Unreleased] - 0.7.0

First release of the fork `gexo/php-osm-static-api`.

### Changed

- Package renamed to `gexo/php-osm-static-api`. The `DantSu\OpenStreetMapStaticAPI` namespace is unchanged.
- Depends on `gexo/php-image-editor` ^1.5 instead of `dantsu/php-image-editor`. The fork replaces the original package
  and keeps the `DantSu\PHPImageEditor` namespace. It removes the PHP 8.5 deprecations (`curl_close()`, `imagedestroy()`)
  and the "Undefined array key" warnings when downloading tiles on the command line.
- **Requires PHP 8.1 or newer.** The previous constraint (`>=7.0`) was already wrong, nullable types need PHP 7.1.
- `OpenStreetMap::createFromBoundingBox()`, `fitToPoints()`, `fitToMarkers()`, `fitToDraws()`, `fitToMarkersAndDraws()`
  and `MapData::getCenterAndZoomFromBoundingBox()` throw an `InvalidArgumentException` if the padding leaves no space
  in the image (`2 * padding >= width or height`). Previously this resulted in a meaningless zoom and, on PHP 8.5, a warning.

### Fixed

- Fitting the map to a single marker, to identical points or to points on a horizontal or vertical line threw a
  `DivisionByZeroError`. The map now uses the maximum zoom of the tile layer, or fits the dimension that has an extent.
- `fitToPoints([])` (and `fitToMarkers()` / `fitToDraws()` without markers or draws) threw a `DivisionByZeroError`.
  The map is now left unchanged.
- `createFromBoundingBox()` with swapped corners resulted in a meaningless zoom; it now yields the same map as the correct order.
- `Circle` with a radius smaller than its stroke weight (e.g. `setRadius(0)`) threw a `ValueError`.
- `Polygon` with less than 3 points threw a `ValueError` (fixed in `gexo/php-image-editor`).

### Added

- PHPUnit test suite including golden image tests for all samples (rendered with local tiles), and a GitHub Actions
  workflow for PHP 8.1 - 8.5.
