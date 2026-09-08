<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\ClassDetail;
use App\Models\PublishAssessment;
use Tests\TestCase;

class PublishAssessmentRelationshipsTest extends TestCase
{
    public function test_publish_assessment_relationships_do_not_recurse(): void
    {
        $publishAssessment = new PublishAssessment();
        $publishAssessment->setRawAttributes([
            'publish_assessment_id' => 123,
            'class_details_id' => 456,
        ], true);

        $this->assertSame(123, $publishAssessment->publish_assessment_id);
        $this->assertStringContainsString('submissions', $publishAssessment->submissions()->toSql());
        $this->assertStringContainsString('reports', $publishAssessment->report()->toSql());
        $this->assertStringContainsString('reports', $publishAssessment->reports()->toSql());
    }

    public function test_publish_assessment_class_accessor_uses_class_detail(): void
    {
        $class = new AcademicClass();
        $class->setRawAttributes([
            'class_id' => 10,
            'year_level' => 4,
            'section_name' => 'A',
        ], true);

        $classDetail = new ClassDetail();
        $classDetail->setRawAttributes([
            'class_details_id' => 456,
            'class_id' => 10,
        ], true);
        $classDetail->setRelation('class', $class);

        $publishAssessment = new PublishAssessment();
        $publishAssessment->setRawAttributes([
            'publish_assessment_id' => 123,
            'class_details_id' => 456,
        ], true);
        $publishAssessment->setRelation('classDetail', $classDetail);

        $this->assertTrue($class->is($publishAssessment->class));
        $this->assertSame(10, $publishAssessment->class_id);
    }
}
