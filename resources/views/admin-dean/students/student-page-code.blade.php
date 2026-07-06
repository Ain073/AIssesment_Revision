@if ($errors->any() && old('base_role') === 'student')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('createStudentModal');

                if (modal) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endif