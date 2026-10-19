<?php

namespace Tests\Feature;

use App\Services\Discovery\QualifyDiscoveredPost;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class QualificationTest extends TestCase
{
    #[TestWith(['Looking for someone to build a website.', 'website', 'qualified', 'website'])]
    #[TestWith(['Can anyone recommend a POS system developer?', 'pos', 'qualified', 'pos'])]
    #[TestWith(['Looking for a custom inventory management system.', 'inventory', 'qualified', 'inventory'])]
    #[TestWith(['Need help integrating our business software.', 'software', 'qualified', 'integration'])]
    #[TestWith(['Butuh website untuk toko saya', 'website', 'qualified', 'website'])]
    #[TestWith(['Need a developer for my small business.', 'developer', 'qualified', 'custom_system'])]
    #[TestWith(['Looking for a website framework for learning', 'website', 'uncertain', 'website'])]
    #[TestWith(['My portfolio: website development', 'website', 'rejected', 'website'])]
    #[TestWith(['We are hiring a website developer. Apply now.', 'website', 'rejected', 'website'])]
    #[TestWith(['Website framework news', 'website', 'rejected', 'website'])]
    #[TestWith(['Looking for a website tutorial', 'website', 'uncertain', 'website'])]
    #[TestWith(['A positive result', 'pos', 'rejected', null])]
    public function test_classifies_evidence_and_preserves_uncertainty(string $body, string $keyword, string $status, ?string $service): void
    {
        $result = app(QualifyDiscoveredPost::class)->classify($body, $keyword);

        $this->assertSame($status, $result['status']);
        $this->assertSame($service, $result['service_category']);
    }

    public function test_matches_unicode_phrases_and_rejects_partial_words(): void
    {
        $qualifier = app(QualifyDiscoveredPost::class);

        $this->assertTrue($qualifier->matches('Butuh WEBSITE   bisnis!', 'website bisnis'));
        $this->assertTrue($qualifier->matches('Butuh café website', 'café'));
        $this->assertFalse($qualifier->matches('positif', 'pos'));
        $this->assertFalse($qualifier->matches('websites', 'website'));
    }
}
