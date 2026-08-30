<?php

declare(strict_types=1);

namespace Tests\Feature\Profiles;

use App\Profiles\CvTextExtractor;
use App\Profiles\Exceptions\UnreadableCv;
use Tests\TestCase;

class CvTextExtractorTest extends TestCase
{
    private CvTextExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = app(CvTextExtractor::class);
    }

    public function test_it_normalises_whitespace_and_line_endings(): void
    {
        $text = $this->extractor->fromText("Backend  Engineer\r\n\r\n\r\n\r\nFive   years.  ");

        $this->assertSame("Backend Engineer\n\nFive years.", $text);
    }

    public function test_it_strips_the_invisible_characters_pdfs_emit(): void
    {
        // NBSP, soft hyphen, zero-width space. Left in place these reach the
        // content hash and an unchanged CV mints a new version every upload.
        $text = $this->extractor->fromText("Senior\xC2\xA0Engineer\xC2\xAD\xE2\x80\x8B");

        $this->assertSame('Senior Engineer', $text);
    }

    public function test_it_rejects_empty_input(): void
    {
        $this->expectException(UnreadableCv::class);

        $this->extractor->fromText("   \n\n  ");
    }

    public function test_it_reads_a_plain_text_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($path, 'Muhammad Shakeel — Senior Backend Engineer');

        try {
            $this->assertStringContainsString(
                'Senior Backend Engineer',
                $this->extractor->fromFile($path, 'cv.txt'),
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_it_rejects_an_unsupported_extension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($path, 'anything');

        try {
            $this->expectException(UnreadableCv::class);
            $this->extractor->fromFile($path, 'cv.docx');
        } finally {
            @unlink($path);
        }
    }
}
