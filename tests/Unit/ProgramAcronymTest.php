<?php

namespace Tests\Unit;

use App\Models\Program;
use PHPUnit\Framework\TestCase;

class ProgramAcronymTest extends TestCase
{
    public function test_secondary_education_programs_produce_correct_acronyms(): void
    {
        $this->assertSame('BSED SS', Program::formatAcronym('Bachelor of Secondary Education major in Social Studies'));
        $this->assertSame('BSED FIL', Program::formatAcronym('Bachelor of Secondary Education major in Filipino'));
        $this->assertSame('BSED ENG', Program::formatAcronym('Bachelor of Secondary Education major in English'));
        $this->assertSame('BSED MATH', Program::formatAcronym('Bachelor of Secondary Education major in Mathematics'));
        $this->assertSame('BSED SCI', Program::formatAcronym('Bachelor of Secondary Education major in Science'));
        $this->assertSame('BSED VE', Program::formatAcronym('Bachelor of Secondary Education major in Values Education'));
        $this->assertSame('BSED', Program::formatAcronym('Bachelor of Secondary Education'));
        $this->assertSame('BSED SS', Program::formatAcronym('BSED SS'));
        $this->assertSame('BSED FIL', Program::formatAcronym('BSED-FIL'));
    }

    public function test_business_administration_programs_produce_correct_acronyms(): void
    {
        $this->assertSame('BSBA MM', Program::formatAcronym('Bachelor of Science in Business Administration major in Marketing Management'));
        $this->assertSame('BSBA FM', Program::formatAcronym('Bachelor of Science in Business Administration major in Financial Management'));
        $this->assertSame('BSBA HRDM', Program::formatAcronym('Bachelor of Science in Business Administration major in Human Resource Development Management'));
        $this->assertSame('BSBA HRM', Program::formatAcronym('Bachelor of Science in Business Administration major in Human Resource Management'));
        $this->assertSame('BSBA OM', Program::formatAcronym('Bachelor of Science in Business Administration major in Operations Management'));
        $this->assertSame('BSBA', Program::formatAcronym('Bachelor of Science in Business Administration'));
        $this->assertSame('BSBA MM', Program::formatAcronym('BSBA MM'));
    }

    public function test_technology_and_livelihood_education_programs_produce_correct_acronyms(): void
    {
        $this->assertSame('BTLED HE', Program::formatAcronym('Bachelor of Technology and Livelihood Education major in Home Economics'));
        $this->assertSame('BTLED IA', Program::formatAcronym('Bachelor of Technology and Livelihood Education major in Industrial Arts'));
        $this->assertSame('BTLED ICT', Program::formatAcronym('Bachelor of Technology and Livelihood Education major in Information and Communication Technology'));
        $this->assertSame('BTLED', Program::formatAcronym('Bachelor of Technology and Livelihood Education'));
        $this->assertSame('BTLED HE', Program::formatAcronym('BTLED HE'));
    }

    public function test_other_campus_programs_produce_correct_acronyms(): void
    {
        $this->assertSame('BEED', Program::formatAcronym('Bachelor of Elementary Education'));
        $this->assertSame('BSIT', Program::formatAcronym('Bachelor of Science in Information Technology'));
        $this->assertSame('BSCS', Program::formatAcronym('Bachelor of Science in Computer Science'));
        $this->assertSame('BSHM', Program::formatAcronym('Bachelor of Science in Hospitality Management'));
        $this->assertSame('BSTM', Program::formatAcronym('Bachelor of Science in Tourism Management'));
        $this->assertSame('BSOA', Program::formatAcronym('Bachelor of Science in Office Administration'));
        $this->assertSame('BPA', Program::formatAcronym('Bachelor of Public Administration'));
        $this->assertSame('BSA', Program::formatAcronym('Bachelor of Science in Agriculture'));
        $this->assertSame('BSCrim', Program::formatAcronym('Bachelor of Science in Criminology'));
        $this->assertSame('BSN', Program::formatAcronym('Bachelor of Science in Nursing'));
    }
}
