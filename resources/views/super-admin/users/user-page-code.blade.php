@if ($errors->any())
    <script>
        const formMode = "{{ old('form_mode', 'create') }}";
        const userId = "{{ old('user_id') }}";
        const baseRole = "{{ old('base_role', 'instructor') }}";
        const modalId = formMode === 'edit' && userId
            ? `editUserModal${userId}`
            : (baseRole === 'student' ? 'createStudentModal' : 'createInstructorModal');
        const modalElement = document.getElementById(modalId);

        if (modalElement) {
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
    </script>
@elseif (request('action') === 'create-user' || request('action') === 'create-instructor')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('createInstructorModal');
            if (modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });
    </script>
@elseif (request('action') === 'create-student')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('createStudentModal');
            if (modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });
    </script>
@endif
