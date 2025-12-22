<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\IssueCode;

class IssueCodeTest extends TestCase
{
    public function test_all_issue_codes_have_labels(): void
    {
        foreach (IssueCode::cases() as $code) {
            $label = $code->label();
            $this->assertNotEmpty($label, "Issue code {$code->value} should have a label");
        }
    }

    public function test_all_issue_codes_have_severity(): void
    {
        foreach (IssueCode::cases() as $code) {
            $severity = $code->severity();
            $this->assertGreaterThanOrEqual(1, $severity);
            $this->assertLessThanOrEqual(5, $severity);
        }
    }

    public function test_all_issue_codes_have_category(): void
    {
        $validCategories = ['completeness', 'consistency', 'content', 'integrity'];

        foreach (IssueCode::cases() as $code) {
            $category = $code->category();
            $this->assertContains($category, $validCategories, "Issue code {$code->value} has invalid category: {$category}");
        }
    }

    public function test_is_critical_returns_true_for_severity_4_plus(): void
    {
        $criticalCodes = [
            IssueCode::ORPHAN_RELATIONSHIP,
            IssueCode::INVALID_REFERENCE,
            IssueCode::CIRCULAR_RELATIONSHIP,
            IssueCode::INVALID_DATE,
        ];

        foreach ($criticalCodes as $code) {
            $this->assertTrue($code->isCritical(), "Issue code {$code->value} should be critical");
        }
    }

    public function test_is_critical_returns_false_for_severity_below_4(): void
    {
        $nonCriticalCodes = [
            IssueCode::MISSING_DESCRIPTION,
            IssueCode::SHORT_DESCRIPTION,
            IssueCode::MISSING_FRAGMENTS,
        ];

        foreach ($nonCriticalCodes as $code) {
            $this->assertFalse($code->isCritical(), "Issue code {$code->value} should not be critical");
        }
    }

    public function test_all_returns_all_cases(): void
    {
        $all = IssueCode::all();

        $this->assertSame(IssueCode::cases(), $all);
        $this->assertGreaterThan(10, count($all));
    }

    public function test_by_category_returns_filtered_codes(): void
    {
        $completeness = IssueCode::byCategory('completeness');

        $this->assertNotEmpty($completeness);
        foreach ($completeness as $code) {
            $this->assertSame('completeness', $code->category());
        }
    }

    public function test_specific_issue_codes(): void
    {
        // Test a few specific codes
        $this->assertSame('missing_description', IssueCode::MISSING_DESCRIPTION->value);
        $this->assertSame('Missing description', IssueCode::MISSING_DESCRIPTION->label());
        $this->assertSame('completeness', IssueCode::MISSING_DESCRIPTION->category());
        $this->assertSame(1, IssueCode::MISSING_DESCRIPTION->severity());

        $this->assertSame('circular_relationship', IssueCode::CIRCULAR_RELATIONSHIP->value);
        $this->assertSame('consistency', IssueCode::CIRCULAR_RELATIONSHIP->category());
        $this->assertSame(5, IssueCode::CIRCULAR_RELATIONSHIP->severity());
        $this->assertTrue(IssueCode::CIRCULAR_RELATIONSHIP->isCritical());
    }
}
