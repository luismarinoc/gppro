<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Pdf;

use App\Pdf\MPdfConverter;
use App\Tests\Mocks\FileHelperFactory;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(MPdfConverter::class)]
#[Group('integration')]
class MPdfConverterTest extends KernelTestCase
{
    public function testConvertToPdf(): void
    {
        $kernel = self::bootKernel();
        $cacheDir = $kernel->getContainer()->getParameter('kernel.cache_dir');

        $options = [
            'foo' => 'bar',
            'additional_xmp_rdf' => '<rdf:Description rdf:about="" xmlns:zf="urn:ferd:pdfa:CrossIndustryDocument:invoice:1p0#"></rdf:Description>',
            'margin_top' => 2,
        ];

        $sut = new MPdfConverter((new FileHelperFactory($this))->create(), $cacheDir);
        $result = $sut->convertToPdf('<h1>Test</h1>', $options);
        // Yeah, that's not a real test, I know ;-)
        self::assertNotEmpty($result);
        preg_match('/\/Creator \((.*)\)/', $result, $matches);
        self::assertCount(2, $matches);
    }

    public function testAssociatedFilesPathIsStripped(): void
    {
        $kernel = self::bootKernel();
        $cacheDir = $kernel->getContainer()->getParameter('kernel.cache_dir');

        // Plant a sentinel on disk that an attacker would try to exfiltrate via
        // mPDF's `SetAssociatedFiles`. The legitimate ZUGFeRD path uses
        // `content` (pre-read bytes); we additionally pass `path` to confirm
        // it is stripped before reaching mPDF.
        $sentinelPath = tempnam(sys_get_temp_dir(), 'gppro-pdf-leak-');
        self::assertNotFalse($sentinelPath);
        $sentinelBytes = 'GPPRO_LEAK_SENTINEL_' . bin2hex(random_bytes(8));
        file_put_contents($sentinelPath, $sentinelBytes);

        $legitimateContent = 'GPPRO_LEGITIMATE_CONTENT_' . bin2hex(random_bytes(8));

        try {
            $sut = new MPdfConverter((new FileHelperFactory($this))->create(), $cacheDir);
            $result = $sut->convertToPdf('<h1>Test</h1>', [
                'associated_files' => [
                    [
                        'name' => 'attachment.txt',
                        'mime' => 'text/plain',
                        'description' => 'mixed entry',
                        'AFRelationship' => 'Alternative',
                        'path' => $sentinelPath,
                        'content' => $legitimateContent,
                    ],
                ],
            ]);
        } finally {
            @unlink($sentinelPath);
        }

        self::assertNotEmpty($result);

        // Decompress every FlateDecode stream in the produced PDF. The
        // sentinel must not appear; the explicitly-supplied `content` must.
        $allDecompressed = $this->extractFlateStreams($result);
        self::assertStringNotContainsString($sentinelBytes, $result);
        self::assertStringNotContainsString($sentinelBytes, $allDecompressed);
        self::assertStringContainsString($legitimateContent, $allDecompressed);
    }

    #[DataProvider('provideFlateStreamBoundaries')]
    public function testExtractFlateStreamsPreservesBytes(string $ending, string $suffix, string $lastByte): void
    {
        $content = 'GPPRO_LEGITIMATE_CONTENT_27d719c754aacd49' . $suffix;
        $compressed = gzcompress($content);
        self::assertNotFalse($compressed);
        self::assertSame($lastByte, bin2hex(substr($compressed, -1)));
        $pdf = "1 0 obj\n<</Type /EmbeddedFile\n/Length " . \strlen($compressed)
            . "\n/Filter /FlateDecode\n/Params <</ModDate (D:20260910000000Z) >>\n>>"
            . $ending . 'stream' . $ending . $compressed . $ending . "endstream\nendobj\n";

        self::assertSame($content, $this->extractFlateStreams($pdf), 'Stream bytes must be preserved.');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideFlateStreamBoundaries(): iterable
    {
        yield 'CR payload, LF delimiter' => ["\n", '', '0d'];
        yield 'CR payload, CRLF delimiter' => ["\r\n", '', '0d'];
        yield 'control, LF delimiter' => ["\n", 'A', '4e'];
        yield 'control, CRLF delimiter' => ["\r\n", 'A', '4e'];
    }

    public function testExtractFlateStreamsSkipsOtherFiltersAndIgnoresPayloadMarkers(): void
    {
        $content = "before\nendstream\nendobj\n2 0 obj\n<< /Length 0 >>\nstream\nafter";
        $compressed = gzcompress($content, 0);
        self::assertNotFalse($compressed);
        self::assertStringContainsString($content, $compressed, 'Stored deflate must contain literal PDF markers.');
        $pdf = "1 0 obj\n<< /Type /Catalog >>\nendobj\n";
        // Even zlib-valid bytes must be ignored unless the filter is FlateDecode.
        foreach (['', '/Filter /DCTDecode', '/Filter/FlateDecode'] as $index => $filter) {
            $pdf .= ($index + 2) . " 0 obj\n<< /Length " . \strlen($compressed) . $filter
                . ">>\nstream\n" . $compressed . "\nendstream\nendobj\n";
        }

        self::assertSame($content, $this->extractFlateStreams($pdf));
    }

    #[DataProvider('provideMalformedFlateStreams')]
    public function testExtractFlateStreamsRejectsMalformedStreams(string $pdf, string $message): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($message);
        $this->extractFlateStreams($pdf);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideMalformedFlateStreams(): iterable
    {
        $invalidLength = 'Invalid or out-of-bounds direct stream /Length.';
        foreach (['-1', '+1', '1.5', '2 0 R', 'nope', '999999999999999999999999999999'] as $length) {
            yield 'invalid length ' . $length => [
                "1 0 obj\n<< /Filter /FlateDecode /Length " . $length . ">>\nstream\nx\nendstream\nendobj\n",
                $invalidLength,
            ];
        }
        foreach (['', '/Length 1 /Length 1'] as $length) {
            yield 'missing or duplicate length ' . $length => [
                "1 0 obj\n<< /Filter /FlateDecode " . $length . ">>\nstream\nx\nendstream\nendobj\n",
                'Expected one direct stream /Length.',
            ];
        }
        yield 'corrupt flate' => [
            "1 0 obj\n<< /Filter /FlateDecode /Length 1 >>\nstream\nx\nendstream\nendobj\n",
            'Failed to decompress declared FlateDecode stream.',
        ];
        yield 'unsupported filter array' => [
            "1 0 obj\n<< /Filter [/FlateDecode] /Length 1 >>\nstream\nx\nendstream\nendobj\n",
            'Unsupported FlateDecode filter declaration.',
        ];
        foreach (['0', '2'] as $length) {
            yield 'incorrect byte count ' . $length => [
                "1 0 obj\n<< /Filter /FlateDecode /Length " . $length . ">>\nstream\nx\nendstream\nendobj\n",
                'Invalid stream terminator at declared /Length.',
            ];
        }
        yield 'truncated terminator' => [
            "1 0 obj\n<< /Filter /FlateDecode /Length 1 >>\nstream\nx\nendstrea",
            'Invalid stream terminator at declared /Length.',
        ];
    }

    private function extractFlateStreams(string $pdf): string
    {
        $allDecompressed = '';
        $offset = 0;
        // Generated, unencrypted mPDF objects only: direct lengths and a single
        // FlateDecode filter. Capture through the outer >>, including nested
        // /Params from MetadataWriter, without crossing a non-stream endobj.
        $headerPattern = '/(?:^|\n)\d+ 0 obj\r?\n(<<(?:(?!\bendobj\b).)*?>>)[ \t\r\n]*stream\r?\n/s';
        while (true) {
            $matched = preg_match($headerPattern, $pdf, $stream, PREG_OFFSET_CAPTURE, $offset);
            self::assertTrue($matched !== false, 'Failed to scan generated PDF stream headers.');
            if ($matched === 0) {
                break;
            }
            $header = $stream[1][0];
            $start = $stream[0][1] + \strlen($stream[0][0]);
            $lengthCount = preg_match_all('/\/Length\s+([^\/<>]*)/', $header, $lengths);
            self::assertSame(1, $lengthCount, 'Expected one direct stream /Length.');
            $lengthValue = trim($lengths[1][0]);
            self::assertSame(1, preg_match('/^(0|[1-9][0-9]*)$/D', $lengthValue), 'Invalid or out-of-bounds direct stream /Length.');
            $length = filter_var($lengthValue, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0, 'max_range' => \strlen($pdf) - $start],
            ]);
            self::assertTrue($length !== false, 'Invalid or out-of-bounds direct stream /Length.');
            $end = $start + $length;
            self::assertSame(1, preg_match('/\G\r?\nendstream\r?\nendobj\b/', $pdf, $terminator, 0, $end), 'Invalid stream terminator at declared /Length.');
            $offset = $end + \strlen($terminator[0]);

            if (!str_contains($header, '/FlateDecode')) {
                continue;
            }
            self::assertSame(1, preg_match('/\/Filter\s*\/FlateDecode\s*(?=\/|>>)/', $header), 'Unsupported FlateDecode filter declaration.');
            $decoded = @gzuncompress(substr($pdf, $start, $length));
            self::assertTrue($decoded !== false, 'Failed to decompress declared FlateDecode stream.');
            $allDecompressed .= $decoded;
        }

        return $allDecompressed;
    }
}
