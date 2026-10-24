@if ($activeTab === 'students' && $errors->has('student_number'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('addStudentModal');

            if (modalElement) {
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }
        });
    </script>
@endif

@if ($activeTab === 'students' && $errors->has('student_file') && ! $importPreview)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('addStudentModal');

            if (modalElement) {
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }
        });
    </script>
@endif

<script src="{{ asset('js/pages/instructor/classes/class-show-page-code.js') }}?v={{ filemtime(public_path('js/pages/instructor/classes/class-show-page-code.js')) }}"></script>
