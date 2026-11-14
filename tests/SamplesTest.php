<?php

namespace DantSu\OpenStreetMapStaticAPI\Tests;

use DantSu\OpenStreetMapStaticAPI\Circle;
use DantSu\OpenStreetMapStaticAPI\LatLng;
use DantSu\OpenStreetMapStaticAPI\Line;
use DantSu\OpenStreetMapStaticAPI\Markers;
use DantSu\OpenStreetMapStaticAPI\OpenStreetMap;
use DantSu\OpenStreetMapStaticAPI\Polygon;
use DantSu\PHPImageEditor\Image;

/**
 * Regression tests for the samples in src/samples.
 *
 * Each test rebuilds the map of one sample with local tiles instead of OpenStreetMap tiles, and compares it with
 * a golden image generated from release 0.6.4 (plus the PHP 8.4 fix). This makes sure that compatibility fixes
 * do not change tile placement, drawing or attribution. Building the map must not raise any PHP error.
 */
final class SamplesTest extends TestCase
{
    /**
     * @see src/samples/sample1.php Bounding box, polygon and markers
     */
    public function testSample1BoundingBoxPolygonAndMarkers(): void
    {
        $this->assertSample('sample1', static function (): Image {
            return OpenStreetMap::createFromBoundingBox(new LatLng(44.352887, 2.565672), new LatLng(44.351172, 2.571092), 10, 600, 400, self::localTileLayer())
                ->addMarkers(
                    (new Markers(self::RESOURCES_DIR . '/marker.png'))
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
                ->getImage();
        });
    }

    /**
     * @see src/samples/sample2.php Lines and markers with an odd-sized marker image
     */
    public function testSample2LinesAndMarkers(): void
    {
        $this->assertSample('sample2', static function (): Image {
            $origin = new LatLng(44.351933, 2.568113);
            $destinations = [
                new LatLng(48.858535, 2.294107),
                new LatLng(52.516304, 13.378107),
                new LatLng(40.415176, -3.707362),
                new LatLng(51.500845, -0.124591),
                new LatLng(41.890145, 12.492552),
                new LatLng(36.809764, 10.134114),
                new LatLng(31.621758, -7.981512),
                new LatLng(47.500469, 19.054086),
                new LatLng(28.119083, -15.438387),
                new LatLng(57.704334, 11.963886),
            ];

            $markers = (new Markers(self::RESOURCES_DIR . '/marker2.png'))->setAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM);
            $map = (new OpenStreetMap($origin, 5, 1024, 1024, self::localTileLayer()))
                ->addMarkers(
                    (new Markers(self::RESOURCES_DIR . '/marker.png'))
                        ->setAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
                        ->addMarker($origin)
                )
                ->addMarkers($markers);

            foreach ($destinations as $destination) {
                $markers->addMarker($destination);
                $map->addDraw((new Line('FF0000', 1))->addPoint($origin)->addPoint($destination));
            }

            return $map->getImage();
        });
    }

    /**
     * @see src/samples/sample3.php Circles and fitToDraws()
     */
    public function testSample3CirclesFitToDraws(): void
    {
        $this->assertSample('sample3', static function (): Image {
            return (new OpenStreetMap(new LatLng(0, 0), 0, 600, 400, self::localTileLayer()))
                ->addMarkers(
                    (new Markers(self::RESOURCES_DIR . '/marker.png'))
                        ->setAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
                        ->addMarker(new LatLng(44.351933, 2.568113))
                        ->addMarker(new LatLng(44.351510, 2.570020))
                        ->addMarker(new LatLng(44.351073, 2.566480))
                )
                ->addDraw(
                    (new Circle(new LatLng(44.351933, 2.568113), 'FF0000', 5, 'FF0000CC'))
                        ->setEdgePoint(new LatLng(44.351510, 2.570020))
                )
                ->addDraw(
                    (new Circle(new LatLng(44.351933, 2.568113), 'FF0000', 5, 'FF0000CC'))
                        ->setRadius(40)
                )
                ->fitToDraws(10)
                ->getImage();
        });
    }

    /**
     * @see src/samples/sample4.php Two tile layers, the second one with opacity
     */
    public function testSample4MultipleLayersWithOpacity(): void
    {
        $this->assertSample('sample4', static function (): Image {
            return (new OpenStreetMap(new LatLng(44.351933, 2.568113), 17, 600, 400, self::localTileLayer('base.png', '© Base tiles')))
                ->addLayer(self::localTileLayer('overlay.png', '© Overlay tiles')->setOpacity(0.8))
                ->addMarkers(
                    (new Markers(self::RESOURCES_DIR . '/marker.png'))
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
                ->getImage();
        });
    }

    /**
     * Build the image, compare it with the golden image first (so a rendering change is reported even when
     * the code also raises PHP errors), then assert that no PHP error was raised.
     */
    private function assertSample(string $name, callable $build): void
    {
        $errors = $this->collectPhpErrors($build, $image);
        $this->assertInstanceOf(Image::class, $image);
        $this->assertImageMatchesGolden($name, $image);
        $this->assertSame([], $errors, 'Expected no PHP deprecations, warnings or notices.');
    }
}
