<?php

namespace DantSu\OpenStreetMapStaticAPI\Tests;

use DantSu\OpenStreetMapStaticAPI\TileLayer;
use DantSu\PHPImageEditor\Image;

/**
 * Base class for all tests.
 *
 * Provides helpers to
 * - use local tiles (file:// URLs) instead of downloading tiles from OpenStreetMap,
 * - collect PHP deprecations, warnings and notices raised by a piece of code and assert there are none,
 * - compare an image against a stored reference image ("golden image") with a small tolerance.
 *
 * Golden images live in tests/fixtures/golden. To (re)generate them, run the suite with UPDATE_GOLDEN=1.
 *
 * GD variants: PHP can be built with its bundled GD library (official Docker images, Windows) or with the system
 * libgd (most Linux distributions, also GitHub Actions via setup-php). Both render TrueType text differently
 * (different glyph metrics, so text can be several pixels wider). Therefore:
 * - <name>.png is the golden image for the bundled GD,
 * - <name>.libgd-<version>.png is an optional golden image for a system libgd; it only exists if the output
 *   differs from <name>.png, and it is used instead of <name>.png on that libgd version.
 * Running with UPDATE_GOLDEN=1 on a system libgd creates or removes these variant files as needed.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected const RESOURCES_DIR = __DIR__ . '/../src/samples/resources';
    protected const FIXTURES_DIR = __DIR__ . '/fixtures';
    protected const GOLDEN_DIR = __DIR__ . '/fixtures/golden';

    /**
     * Maximum difference per channel (0-255, alpha scaled to the same range) for two pixels to count as equal.
     * Absorbs small rendering differences between GD and FreeType builds.
     */
    protected const PIXEL_CHANNEL_TOLERANCE = 8;

    /**
     * Maximum share of pixels that may differ beyond PIXEL_CHANNEL_TOLERANCE.
     */
    protected const MAX_DIFFERENT_PIXEL_RATIO = 0.01;

    private const INSUPPRESSIBLE_LEVELS = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    /**
     * Tile layer that serves the same local tile for every x, y and zoom.
     *
     * @param string $tile File name inside tests/fixtures/tiles
     */
    protected static function localTileLayer(string $tile = 'base.png', string $attribution = '© Test tiles'): TileLayer
    {
        return new TileLayer(static::fileUrl(static::FIXTURES_DIR . '/tiles/' . $tile), $attribution);
    }

    protected static function fileUrl(string $path): string
    {
        $directory = \realpath(\dirname($path));
        if ($directory === false) {
            throw new \RuntimeException('Directory not found: ' . \dirname($path));
        }
        return 'file://' . $directory . '/' . \basename($path);
    }

    /**
     * Run $callback and collect every PHP error (deprecation, warning, notice, ...) it raises.
     *
     * @param callable $callback Code under test
     * @param mixed $result Receives the return value of $callback
     * @return string[] Human-readable error messages
     */
    protected function collectPhpErrors(callable $callback, &$result = null): array
    {
        $errors = [];
        \set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$errors): bool {
            // Respect the @ operator. Do not compare against error_reporting() directly: PHPUnit lowers it while a
            // test runs and handles all other levels itself. Inside @, only the insuppressible levels remain set.
            if ((\error_reporting() & ~self::INSUPPRESSIBLE_LEVELS) === 0) {
                return false;
            }
            $errors[] = \sprintf('[%s] %s in %s:%d', static::severityName($severity), $message, \basename($file), $line);
            return true;
        });

        try {
            $result = $callback();
        } finally {
            \restore_error_handler();
        }

        return $errors;
    }

    /**
     * Run $callback, assert that it raises no PHP error at all and return its result.
     *
     * @param callable $callback Code under test
     * @return mixed Return value of $callback
     */
    protected function assertNoPhpErrors(callable $callback)
    {
        $errors = $this->collectPhpErrors($callback, $result);
        $this->assertSame([], $errors, 'Expected no PHP deprecations, warnings or notices.');
        return $result;
    }

    /**
     * Assert that $image looks like the stored golden image $name.
     *
     * @param string $name File name (without extension) inside tests/fixtures/golden
     * @param Image $image Image under test
     */
    protected function assertImageMatchesGolden(string $name, Image $image): void
    {
        $this->assertTrue($image->isImageDefined(), 'Image under test is not defined.');

        $defaultPath = static::GOLDEN_DIR . '/' . $name . '.png';
        $variant = static::gdVariant();
        $variantPath = $variant === null ? null : static::GOLDEN_DIR . '/' . $name . '.' . $variant . '.png';

        if (\getenv('UPDATE_GOLDEN') === '1') {
            static::updateGolden($image, $defaultPath, $variantPath);
        }

        $goldenPath = $variantPath !== null && \is_file($variantPath) ? $variantPath : $defaultPath;
        $this->assertFileExists($goldenPath, 'Golden image missing. Run the tests with UPDATE_GOLDEN=1 to create it.');

        $expected = \imagecreatefrompng($goldenPath);
        $actual = $image->getImage();

        $this->assertSame(
            [\imagesx($expected), \imagesy($expected)],
            [\imagesx($actual), \imagesy($actual)],
            'Image size differs from golden image "' . \basename($goldenPath) . '".'
        );

        $ratio = static::differentPixelRatio($expected, $actual);
        $this->assertLessThanOrEqual(
            static::MAX_DIFFERENT_PIXEL_RATIO,
            $ratio,
            \sprintf('%.2f %% of the pixels differ from golden image "%s".', $ratio * 100, \basename($goldenPath))
        );
    }

    /**
     * Name of the golden image variant for the GD library in use, or null for PHP's bundled GD.
     */
    protected static function gdVariant(): ?string
    {
        if (\defined('GD_BUNDLED') && GD_BUNDLED) {
            return null;
        }
        return 'libgd-' . GD_VERSION;
    }

    /**
     * Write the golden image for the bundled GD, or the variant for a system libgd if its output differs.
     */
    private static function updateGolden(Image $image, string $defaultPath, ?string $variantPath): void
    {
        if (!\is_dir(static::GOLDEN_DIR)) {
            \mkdir(static::GOLDEN_DIR, 0777, true);
        }

        if ($variantPath === null) {
            \file_put_contents($defaultPath, $image->getDataPNG());
            return;
        }

        $default = \is_file($defaultPath) ? \imagecreatefrompng($defaultPath) : false;
        $matchesDefault = $default !== false
            && \imagesx($default) === $image->getWidth()
            && \imagesy($default) === $image->getHeight()
            && static::differentPixelRatio($default, $image->getImage()) <= static::MAX_DIFFERENT_PIXEL_RATIO;

        if ($matchesDefault) {
            if (\is_file($variantPath)) {
                \unlink($variantPath);
            }
        } else {
            \file_put_contents($variantPath, $image->getDataPNG());
        }
    }

    /**
     * Share of pixels whose color or alpha differs by more than PIXEL_CHANNEL_TOLERANCE.
     *
     * @param \GdImage $expected
     * @param \GdImage $actual
     * @return float Value between 0 and 1
     */
    private static function differentPixelRatio(\GdImage $expected, \GdImage $actual): float
    {
        $width = \imagesx($expected);
        $height = \imagesy($expected);
        $different = 0;

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $a = \imagecolorat($expected, $x, $y);
                $b = \imagecolorat($actual, $x, $y);
                if ($a === $b) {
                    continue;
                }
                $diff = \max(
                    \abs((($a >> 16) & 0xFF) - (($b >> 16) & 0xFF)),
                    \abs((($a >> 8) & 0xFF) - (($b >> 8) & 0xFF)),
                    \abs(($a & 0xFF) - ($b & 0xFF)),
                    \abs((($a >> 24) & 0x7F) - (($b >> 24) & 0x7F)) * 2
                );
                if ($diff > static::PIXEL_CHANNEL_TOLERANCE) {
                    ++$different;
                }
            }
        }

        return $different / ($width * $height);
    }

    private static function severityName(int $severity): string
    {
        switch ($severity) {
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'DEPRECATED';
            case E_WARNING:
            case E_USER_WARNING:
                return 'WARNING';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'NOTICE';
            default:
                return 'E' . $severity;
        }
    }
}
