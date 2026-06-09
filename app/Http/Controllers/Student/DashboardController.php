<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = $this->currentUser();

        return view('student.dashboard', $this->sharedData($user, 'dashboard') + [
            'stats' => [
                [
                    'label' => 'Enrolled Classes',
                    'value' => 0,
                    'caption' => 'Classes linked to your account',
                    'icon' => 'school',
                ],
                [
                    'label' => 'Assigned Assessments',
                    'value' => 0,
                    'caption' => 'Assessments currently available',
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'Released Results',
                    'value' => 0,
                    'caption' => 'Scores and published outcomes',
                    'icon' => 'monitoring',
                ],
            ],
            'quickActions' => [
                [
                    'label' => 'Open Classes',
                    'description' => 'View your enrolled classes and sections.',
                    'href' => route('student.classes'),
                    'icon' => 'school',
                ],
                [
                    'label' => 'Take Assessments',
                    'description' => 'Open the assessments assigned to you.',
                    'href' => route('student.assessments'),
                    'icon' => 'assignment',
                ],
                [
                    'label' => 'View Results',
                    'description' => 'Check released scores and assessment outcomes.',
                    'href' => route('student.results'),
                    'icon' => 'grading',
                ],
            ],
            'studentNotes' => [
                [
                    'title' => 'Classes and assessments',
                    'description' => 'Your enrolled classes and assigned assessments will appear here once class roster and publishing flow are connected.',
                ],
                [
                    'title' => 'Released results only',
                    'description' => 'Results should only appear when the instructor allows score release based on the manuscript workflow.',
                ],
                [
                    'title' => 'Answer review depends on instructor settings',
                    'description' => 'If allowed after closing, this portal can also show your submitted answers and correct answers for review.',
                ],
            ],
        ]);
    }

    public function classes(): View
    {
        return view('student.classes', $this->placeholderPageData(
            'classes',
            'Classes',
            'Students should be able to access their enrolled classes through this page.',
            [
                'View enrolled classes',
                'Open class details and assigned work',
                'See which classes are currently active',
            ],
        ));
    }

    public function assessments(): View
    {
        return view('student.assessments', $this->placeholderPageData(
            'assessments',
            'Assessments',
            'Assigned assessments for the logged-in student will appear here.',
            [
                'Open available assessments',
                'Track open and closed assessment items',
                'Access assessment instructions and deadlines',
            ],
        ));
    }

    public function results(): View
    {
        return view('student.results', $this->placeholderPageData(
            'results',
            'Results',
            'Released scores and assessment results will be organized here.',
            [
                'View released scores',
                'Review released assessment results',
                'Show answer review only when allowed by the instructor',
            ],
        ));
    }

    private function placeholderPageData(
        string $activeNav,
        string $pageHeading,
        string $pageDescription,
        array $checklist
    ): array {
        $user = $this->currentUser();

        return $this->sharedData($user, $activeNav) + [
            'pageHeading' => $pageHeading,
            'pageDescription' => $pageDescription,
            'pageChecklist' => $checklist,
        ];
    }

    private function sharedData(User $user, string $activeNav): array
    {
        return [
            'user' => $user,
            'portalSubtitle' => 'Student Portal',
            'profileInitials' => strtoupper(Str::substr($user->first_name ?? $user->displayName(), 0, 1)),
            'profileName' => $user->displayName(),
            'profileMeta' => 'Student Account',
            'navItems' => $this->navItems($activeNav),
            'viewSwitches' => [],
            'showTopbarSearch' => true,
            'topbarSearchPlaceholder' => 'Search classes or assessments...',
        ];
    }

    private function navItems(string $activeNav): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('student.dashboard')],
            ['key' => 'classes', 'label' => 'Classes', 'icon' => 'school', 'href' => route('student.classes')],
            ['key' => 'assessments', 'label' => 'Assessments', 'icon' => 'assignment', 'href' => route('student.assessments')],
            ['key' => 'results', 'label' => 'Results', 'icon' => 'grading', 'href' => route('student.results')],
        ];

        return array_map(
            fn (array $item) => $item + ['active' => $item['key'] === $activeNav],
            $items,
        );
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('roles', 'studentProfile.program.college');

        return $user;
    }
}
