<?php

namespace Tests\Feature;

use App\Http\Controllers\DepartmentChair\DashboardController as ChairDashboard;
use App\Http\Controllers\Instructor\DashboardController as InstructorDashboard;
use App\Http\Controllers\ProfileController;
use App\Models\Role;
use App\Models\User;
use App\Support\PortalNavigation;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class PortalNavigationTest extends TestCase
{
    public function test_chair_profile_and_feature_pages_share_the_same_complete_menu(): void
    {
        $user = $this->userWithRoles(['instructor', 'department_chair']);
        $expected = PortalNavigation::forUser($user);

        foreach ([new InstructorDashboard, new ChairDashboard, new ProfileController] as $controller) {
            $method = new ReflectionMethod($controller, $controller instanceof ProfileController ? 'layoutData' : 'sharedData');
            $data = $controller instanceof ProfileController
                ? $method->invoke($controller, $user)
                : $method->invoke($controller, $user, 'dashboard');
            $this->assertSame($expected, $data['navItems']);
        }

        $this->assertContains('Programs', array_column($expected, 'label'));
        $this->assertSame(route('department-chair.dashboard'), $expected[0]['href']);
    }

    public static function roleMenus(): array
    {
        return [
            'student' => [['student'], ['Dashboard', 'Classes', 'Assessments', 'Results'], 'student.dashboard'],
            'instructor' => [['instructor'], ['Dashboard', 'Classes', 'Assessments', 'Reports'], 'instructor.dashboard'],
            'dean with teaching' => [['instructor', 'admin_dean'], ['Dashboard', 'Classes', 'Assessments', 'Reports', 'Departments', 'Users', 'Chair Designation'], 'admin-dean.dashboard'],
            'super admin' => [['super_admin'], ['Dashboard', 'Colleges & Departments', 'Programs', 'Dean Designation', 'Passing Rates', 'Users', 'Audit Trail'], 'super-admin.dashboard'],
        ];
    }

    #[DataProvider('roleMenus')]
    public function test_navigation_preserves_role_scopes_and_dashboard_destinations(array $roles, array $labels, string $dashboard): void
    {
        $items = PortalNavigation::forUser($this->userWithRoles($roles));

        $this->assertSame($labels, array_column($items, 'label'));
        $this->assertSame(route($dashboard), $items[0]['href']);
    }

    private function userWithRoles(array $roles): User
    {
        $user = new User(['name' => 'Test Faculty', 'first_name' => 'Test']);
        $user->setRelation('roles', new Collection(array_map(fn (string $role) => new Role(['role_name' => $role]), $roles)));

        return $user;
    }
}
