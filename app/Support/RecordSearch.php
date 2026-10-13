<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class RecordSearch
{
    public const USER_FIELDS = ['name', 'first_name', 'middle_name', 'last_name', 'email', 'instructorProfile.employee_number', 'studentProfile.student_number'];
    public const PROGRAM_FIELDS = ['program_name', 'department.dept_name'];
    public const SUBJECT_FIELDS = ['subject_code', 'subject_name'];
    public const CLASS_FIELDS = ['section_name', 'join_code', 'school_year', 'subject.subject_code', 'subject.subject_name', 'program.program_name'];
    public const PUBLISHED_FIELDS = ['assessment.title', 'assessment.subject.subject_code', 'assessment.subject.subject_name', 'classDetail.class.section_name'];

    public static function placeholder(?string $route): ?string
    {
        return match ($route) {
            'super-admin.users' => 'Search users...',
            'admin-dean.teachers', 'department-chair.teachers' => 'Search teachers...',
            'department-chair.students' => 'Search students...',
            'super-admin.colleges' => 'Search colleges or departments...',
            'admin-dean.departments' => 'Search departments...',
            'super-admin.programs', 'department-chair.programs' => 'Search programs...',
            'super-admin.roles', 'admin-dean.designations' => 'Search designations...',
            'department-chair.subjects' => 'Search subjects...',
            'instructor.classes', 'student.classes' => 'Search classes...',
            'instructor.assessments', 'student.assessments' => 'Search assessments...',
            'instructor.reports', 'department-chair.reports' => 'Search reports...',
            'student.results' => 'Search results...',
            default => null,
        };
    }

    public static function term(): string
    {
        $validated = validator(['search' => request()->query('search')], [
            'search' => ['nullable', 'string', 'max:100'],
        ])->validate();

        return trim($validated['search'] ?? '');
    }

    /** Add one grouped condition to the existing authorized query. */
    public static function apply(Builder $query, array $fields, ?string $term = null): Builder
    {
        $term ??= self::term();
        if ($term === '') {
            return $query;
        }

        // Treat SQL wildcard characters as literal characters in account numbers.
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
        return $query->where(function (Builder $group) use ($fields, $pattern): void {
            foreach ($fields as $field) {
                $separator = strrpos($field, '.');
                if ($separator !== false) {
                    $relation = substr($field, 0, $separator);
                    $column = substr($field, $separator + 1);
                    $group->orWhereHas($relation, fn (Builder $related) => self::matchColumn($related, $column, $pattern));
                } else {
                    $group->orWhere(fn (Builder $columnQuery) => self::matchColumn($columnQuery, $field, $pattern));
                }
            }
        });
    }

    private static function matchColumn(Builder $query, string $column, string $pattern): void
    {
        $column = $query->getQuery()->getGrammar()->wrap($query->getModel()->qualifyColumn($column));
        $query->whereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$pattern]);
    }

    /** Search an already scoped model list while retaining its computed data/order. */
    public static function filter(Collection $records, array $fields): Collection
    {
        $term = self::term();
        if ($term === '' || $records->isEmpty()) return $records;

        $model = $records->first();
        $allowedIds = $records->map(fn (Model $record) => $record->getKey())->all();
        $matchingIds = self::apply($model->newQuery()->whereKey($allowedIds), $fields, $term)->pluck($model->getKeyName());

        return $records->filter(fn (Model $record) => $matchingIds->contains($record->getKey()))->values();
    }
}
