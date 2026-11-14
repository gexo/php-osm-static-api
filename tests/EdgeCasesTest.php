<?php

namespace DantSu\OpenStreetMapStaticAPI\Tests;

use DantSu\OpenStreetMapStaticAPI\Circle;
use DantSu\OpenStreetMapStaticAPI\LatLng;
use DantSu\OpenStreetMapStaticAPI\Line;
use DantSu\OpenStreetMapStaticAPI\MapData;
use DantSu\OpenStreetMapStaticAPI\Markers;
use DantSu\OpenStreetMapStaticAPI\OpenStreetMap;
use DantSu\OpenStreetMapStaticAPI\Polygon;
use DantSu\OpenStreetMapStaticAPI\TileLayer;
use DantSu\PHPImageEditor\Image;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Edge cases that raise PHP errors on PHP 8.x.
 */
final class EdgeCasesTest extends TestCase
{
    private const MARKER = self::RESOURCES_DIR . '/marker.png';

    /**
     * E1: a bounding box without extent made getCenterAndZoomFromBoundingBox() divide by zero (DivisionByZeroError).
     */
    public function testFitToMarkersWithSingleMarkerUsesMaximumZoom(): void
    {
        $marker = new LatLng(44.351933, 2.568113);

        $map = $this->assertNoPhpErrors(
            fn() => $this->map()->addMarkers((new Markers(self::MARKER))->addMarker($marker))->fitToMarkers(10)
        );

        $this->assertSame(20, $map->getMapData()->getZoom());
        $this->assertLatLngEqualsWithDelta($marker, $this->center($map->getMapData()));
    }

    /**
     * E1: same as above, the maximum zoom of the tile layer still applies.
     */
    public function testFitToPointsWithIdenticalPointsRespectsMaxZoomOfLayer(): void
    {
        $point = new LatLng(48.858535, 2.294107);
        $layer = self::localTileLayer()->setMaxZoom(18);

        $map = $this->assertNoPhpErrors(
            fn() => (new OpenStreetMap(new LatLng(0, 0), 3, 600, 400, $layer))->fitToPoints([$point, $point, $point])
        );

        $this->assertSame(18, $map->getMapData()->getZoom());
        $this->assertLatLngEqualsWithDelta($point, $this->center($map->getMapData()));
    }

    /**
     * E1: a bounding box with extent in only one direction.
     */
    public function testFitToPointsOnHorizontalLine(): void
    {
        $map = $this->assertNoPhpErrors(
            fn() => $this->map()->fitToPoints([new LatLng(45.0, 2.0), new LatLng(45.0, 3.0)])
        );

        $this->assertGreaterThan(0, $map->getMapData()->getZoom());
        $this->assertLessThan(20, $map->getMapData()->getZoom());
    }

    /**
     * E1: an empty list of points divided by zero as well. The map must stay unchanged.
     */
    public function testFitToPointsWithoutPointsKeepsMap(): void
    {
        $map = $this->map();
        $before = $map->getMapData();

        $this->assertNoPhpErrors(static fn() => $map->fitToPoints([]));

        $this->assertSame($before, $map->getMapData());
    }

    public function testFitToMarkersAndDrawsWithoutMarkersAndDrawsKeepsMap(): void
    {
        $map = $this->map();
        $before = $map->getMapData();

        $this->assertNoPhpErrors(static fn() => $map->fitToMarkers()->fitToDraws()->fitToMarkersAndDraws());

        $this->assertSame($before, $map->getMapData());
    }

    /**
     * E2: a padding that leaves no space for the bounding box made the zoom NAN, which raises
     * "The float NAN is not representable as an int" on PHP 8.5 and resulted in a meaningless zoom.
     *
     * @return array<string, array{int, int, int}>
     */
    public static function tooLargePaddings(): array
    {
        return [
            'padding fills the height' => [200, 600, 400],
            'padding exceeds the width' => [400, 600, 1000],
        ];
    }

    #[DataProvider('tooLargePaddings')]
    public function testCreateFromBoundingBoxWithTooLargePaddingThrows(int $padding, int $width, int $height): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OpenStreetMap::createFromBoundingBox(new LatLng(44.36, 2.56), new LatLng(44.35, 2.57), $padding, $width, $height, self::localTileLayer());
    }

    #[DataProvider('tooLargePaddings')]
    public function testFitToPointsWithTooLargePaddingThrows(int $padding, int $width, int $height): void
    {
        $map = new OpenStreetMap(new LatLng(0, 0), 0, $width, $height, self::localTileLayer());

        $this->expectException(\InvalidArgumentException::class);

        $map->fitToPoints([new LatLng(44.36, 2.56), new LatLng(44.35, 2.57)], $padding);
    }

    /**
     * E2: swapped corners resulted in a negative extent, a NAN zoom and on PHP 8.5 in a warning.
     */
    public function testCreateFromBoundingBoxWithSwappedCornersEqualsCorrectOrder(): void
    {
        $topLeft = new LatLng(44.352887, 2.565672);
        $bottomRight = new LatLng(44.351172, 2.571092);

        $expected = OpenStreetMap::createFromBoundingBox($topLeft, $bottomRight, 10, 600, 400, self::localTileLayer());
        $swapped = $this->assertNoPhpErrors(
            fn() => OpenStreetMap::createFromBoundingBox($bottomRight, $topLeft, 10, 600, 400, self::localTileLayer())
        );

        $this->assertSame($expected->getMapData()->getZoom(), $swapped->getMapData()->getZoom());
    }

    /**
     * E3: Image::drawPolygon() threw a ValueError for less than 3 points.
     *
     * @return array<string, array{LatLng[]}>
     */
    public static function fewPoints(): array
    {
        return [
            'no point' => [[]],
            'one point' => [[new LatLng(44.351, 2.567)]],
            'two points' => [[new LatLng(44.351, 2.567), new LatLng(44.352, 2.568)]],
        ];
    }

    /**
     * @param LatLng[] $points
     */
    #[DataProvider('fewPoints')]
    public function testPolygonWithLessThanThreePointsDoesNotFail(array $points): void
    {
        $polygon = new Polygon('FF0000', 2, 'FF000088');
        foreach ($points as $point) {
            $polygon->addPoint($point);
        }

        $image = $this->assertNoPhpErrors(fn() => $this->map()->addDraw($polygon)->getImage());

        $this->assertTrue($image->isImageDefined());
    }

    /**
     * @param LatLng[] $points
     */
    #[DataProvider('fewPoints')]
    public function testLineWithFewPointsDoesNotFail(array $points): void
    {
        $line = new Line('FF0000', 2);
        foreach ($points as $point) {
            $line->addPoint($point);
        }

        $image = $this->assertNoPhpErrors(fn() => $this->map()->addDraw($line)->getImage());

        $this->assertTrue($image->isImageDefined());
    }

    public function testCircleWithZeroRadiusDoesNotFail(): void
    {
        $circle = (new Circle(new LatLng(44.351933, 2.568113), 'FF0000', 5, 'FF000088'))->setRadius(0);

        $image = $this->assertNoPhpErrors(fn() => $this->map()->addDraw($circle)->getImage());

        $this->assertTrue($image->isImageDefined());
    }

    /**
     * Odd-sized marker images with ALIGN anchors lead to fractional pixel positions (implicit float to int
     * conversion is deprecated since PHP 8.1), and numeric anchors are compared with strings (changed in PHP 8.0).
     *
     * @return array<string, array{int|string, int|string}>
     */
    public static function markerAnchors(): array
    {
        return [
            'center/middle' => [Markers::ANCHOR_CENTER, Markers::ANCHOR_MIDDLE],
            'right/bottom' => [Markers::ANCHOR_RIGHT, Markers::ANCHOR_BOTTOM],
            'numeric zero' => [0, 0],
            'numeric' => [7, 13],
        ];
    }

    #[DataProvider('markerAnchors')]
    public function testMarkersWithOddSizedImage(int|string $horizontal, int|string $vertical): void
    {
        $markers = (new Markers(self::RESOURCES_DIR . '/marker2.png'))
            ->setAnchor($horizontal, $vertical)
            ->addMarker(new LatLng(44.351933, 2.568113));

        $image = $this->assertNoPhpErrors(fn() => $this->map()->addMarkers($markers)->getImage());

        $this->assertTrue($image->isImageDefined());
    }

    /**
     * Tiles that cannot be loaded are skipped; the map image is still created (with attribution), without warnings.
     * Also covers running on the command line, where the request related $_SERVER keys do not exist.
     */
    public function testUnreachableTilesAreSkippedWithoutWarning(): void
    {
        $layer = new TileLayer(self::fileUrl(self::FIXTURES_DIR . '/tiles/does-not-exist.png'), '© Missing tiles');

        $image = $this->assertNoPhpErrors(
            static fn() => (new OpenStreetMap(new LatLng(44.351933, 2.568113), 15, 300, 200, $layer))->getImage()
        );

        $this->assertSame([300, 200], [$image->getWidth(), $image->getHeight()]);
    }

    public function testTileUrlPlaceholders(): void
    {
        $layer = new TileLayer('https://{s}.example.org/{z}/{x}/{y}{r}.png', '© Test', 'abc');

        $this->assertSame('https://c.example.org/3/1/1.png', $layer->getTileUrl(1, 1, 3));
        $this->assertSame('https://a.example.org/3/1/2.png', $layer->getTileUrl(1, 2, 3));
    }

    private function map(): OpenStreetMap
    {
        return new OpenStreetMap(new LatLng(44.351933, 2.568113), 15, 600, 400, self::localTileLayer());
    }

    /**
     * Center of the map, derived from its corners.
     */
    private function center(MapData $mapData): LatLng
    {
        $topLeft = $mapData->getLatLngTopLeft();
        $bottomRight = $mapData->getLatLngBottomRight();
        return new LatLng(($topLeft->getLat() + $bottomRight->getLat()) / 2, ($topLeft->getLng() + $bottomRight->getLng()) / 2);
    }

    private function assertLatLngEqualsWithDelta(LatLng $expected, LatLng $actual, float $delta = 0.0001): void
    {
        $this->assertEqualsWithDelta($expected->getLat(), $actual->getLat(), $delta, 'Latitude differs.');
        $this->assertEqualsWithDelta($expected->getLng(), $actual->getLng(), $delta, 'Longitude differs.');
    }
}
