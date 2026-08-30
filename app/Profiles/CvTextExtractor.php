<?php

declare(strict_types=1);

namespace App\Profiles;

use App\Profiles\Exceptions\UnreadableCv;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Turns an uploaded CV into plain text.
 *
 * Extraction only — no interpretation. Understanding the content is the
 * scoring engine's job, and keeping the two apart means a parser change never
 * invalidates a cached score.
 */
final class CvTextExtractor
{
    private const SUPPORTED = ['pdf', 'txt', 'md', 'markdown'];

    public function __construct(private readonly PdfParser $pdfParser = new PdfParser) {}

    /**
     * @return list<string>
     */
    public static function supportedExtensions(): array
    {
        return self::SUPPORTED;
    }

    public function fromFile(string $path, string $originalName): string
    {
        if (! is_readable($path)) {
            throw new UnreadableCv("Could not read the uploaded file [{$originalName}].");
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $text = match ($extension) {
            'pdf' => $this->fromPdf($path, $originalName),
            'txt', 'md', 'markdown' => (string) file_get_contents($path),
            default => throw new UnreadableCv(
                "Unsupported file type [.{$extension}]. Supported: ".implode(', ', self::SUPPORTED).'.'
            ),
        };

        return $this->clean($text, $originalName);
    }

    public function fromText(string $text): string
    {
        return $this->clean($text, 'pasted text');
    }

    private function fromPdf(string $path, string $originalName): string
    {
        try {
            return $this->pdfParser->parseFile($path)->getText();
        } catch (Throwable $e) {
            throw new UnreadableCv(
                "Could not extract text from [{$originalName}]. If it is a scanned image, "
                .'paste the text instead.',
                previous: $e
            );
        }
    }

    private function clean(string $text, string $label): string
    {
        // PDF extraction routinely emits NBSPs, soft hyphens and zero-width
        // characters. Left alone they end up in the content hash, so an
        // unchanged CV would mint a new version on every upload.
        $text = str_replace(["\xC2\xA0", "\xC2\xAD", "\xE2\x80\x8B"], [' ', '', ''], $text);
        $text = preg_replace('/\R/u', "\n", $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            throw new UnreadableCv("No text could be extracted from [{$label}].");
        }

        return $text;
    }
}
